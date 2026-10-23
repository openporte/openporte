#!/usr/bin/env bash
# wp-env.sh - Remote WordPress environment helper for OpenPorte plugin testing
#
# USAGE:
#   ./wp-env.sh [OPTIONS] [wp-env COMMAND]
#
# DESCRIPTION:
#   Synchronizes the local repository to a remote host and runs wp-env commands
#   there. Useful for testing the plugin with different PHP and WordPress versions.
#   Read how to use it in tests/README.md.
#
# OPTIONS:
#   --php-version, -p  Override PHP version on the remote (via WP_ENV_PHP_VERSION)
#   --wp-version, -w   Override WordPress Core version on the remote (via WP_ENV_CORE)
#                      Accepts "6.5" (auto-expands to "WordPress/WordPress#6.5")
#                       or full format "WordPress/WordPress#6.5"
#   --verbose, -v      Display detailed environment information after start
#
#   These options can appear anywhere in the command line and are removed before
#   passing arguments to wp-env. Environment variables are exported inline in the
#   remote shell command.
#
# EXAMPLES:
#   # Start with default versions
#   ./wp-env.sh start
#
#   # Start with verbose output
#   ./wp-env.sh -v start
#
#   # Override PHP version
#   ./wp-env.sh -p 7.3 start
#
#   # Override WordPress version (short or full format)
#   ./wp-env.sh -w 6.5 start
#   ./wp-env.sh -w WordPress/WordPress#6.5 start
#
#   # Override both
#   ./wp-env.sh -p 7.3 -w 7.0 start --no-cache
#
#   # Mixed with wp-env arguments
#   ./wp-env.sh start --php-version 8.1 --no-cache
#
#   # Upgrade @wordpress/env itself on the remote (as REMOTE_USER)
#   ./wp-env.sh update
#
#   # Run plugin check on the remote
#   ./wp-env.sh plugin-check openporte
#
# CONFIGURATION:
#   Create a .wp-env.conf file with:
#     REMOTE_USER=your-ssh-username
#     REMOTE_HOST=your-ssh-host
#     REMOTE_PATH=path/to/remote/directory
#
#   The remote host must have ~/.wpenvrc sourced (handled automatically by this script).
#
# ACCESSING REMOTE SERVER:
#   If the remote web server cannot be accessed directly at http://REMOTE_HOST:8888/,
#   create an SSH tunnel: ssh -f -N -L 8888:localhost:8888 ${REMOTE_USER}@${REMOTE_HOST}
#
# IMPLEMENTATION:
#   See docs/maintenance-testing.md for detailed technical documentation.
#
set -euo pipefail

# Verify the configuration file exists and source it to get REMOTE_USER, REMOTE_HOST, and REMOTE_PATH
if [ -f '.wp-env.conf' ]; then
  # shellcheck source=.wp-env.conf
  source .wp-env.conf
else
  echo 'Please create a .wp-env.conf file with the following content:' >&2
  echo 'REMOTE_USER=your-ssh-username' >&2
  echo 'REMOTE_HOST=your-ssh-host' >&2
  echo 'REMOTE_PATH=path/to/remote/directory' >&2
  exit 1
fi

# Verify that the necessary environment variables are set
if [ -z "${REMOTE_USER:-}" ] || [ -z "${REMOTE_HOST:-}" ] || [ -z "${REMOTE_PATH:-}" ]; then
  echo 'REMOTE_USER, REMOTE_HOST, and REMOTE_PATH must be set in .wp-env.conf' >&2
  exit 1
fi

# Verify that rsync is installed
if ! command -v rsync >/dev/null 2>&1; then
  echo 'rsync is required but not found. Please install rsync and try again.' >&2
  exit 1
fi

if ! ssh -q -o BatchMode=yes -o ConnectTimeout=5 -i "${REMOTE_RPC_KEY}" ${REMOTE_USER}@${REMOTE_HOST} 'echo 2>&1'; then
  echo "Unable to connect to ${REMOTE_USER}@${REMOTE_HOST}. Please check your SSH configuration and try again." >&2
  exit 1
fi

if [ -z "$SSH_AUTH_SOCK" ]; then
  echo "Warning: SSH agent is not running or SSH_AUTH_SOCK is not set." >&2
  echo "         Please ensure your SSH agent or you might not be able to authenticate." >&2
fi

if [ ! -f '.wpenvrc' ]; then
  echo "Warning: no .wpenvrc file exists locally. This file is meant to be used on the remote host to define environment variables." >&2
  echo "        The local .wpenvrc is copied/rsync to the remote host." >&2
  echo "        Please ensure that any necessary environment variables (e.g. PATH) are defined" >&2
  echo "        in ~/.wpenvrc file locally, it will be copied to the remote host automatically." >&2
fi

# Verify that the script is being run from the root of a git repository
if [ ! -d '.git' ]; then
  echo 'This script must be run from the root of the repository.' >&2
  exit 1
fi

# Default values for version overrides and flags
PHP_VERSION_OVERRIDE=""
WP_VERSION_OVERRIDE=""
VERBOSE_MODE=false
REMAINING_ARGS=()

# Parse all arguments for version overrides and flags
# This allows options to appear anywhere in the command line
while [[ $# -gt 0 ]]; do
  case "$1" in
    --php-version|-p)
      PHP_VERSION_OVERRIDE="$2"
      shift 2
      ;;
    --wp-version|-w)
      # Auto-prepend WordPress/WordPress# if not already present
      if [[ "$2" != *"#"* ]]; then
        WP_VERSION_OVERRIDE="WordPress/WordPress#$2"
      else
        WP_VERSION_OVERRIDE="$2"
      fi
      shift 2
      ;;
    --verbose|-v)
      VERBOSE_MODE=true
      shift
      ;;
    *)
      REMAINING_ARGS+=("$1")
      shift
      ;;
  esac
done
set -- "${REMAINING_ARGS[@]}"

# Handle the `update` pseudo-subcommand: upgrade @wordpress/env itself on the
# remote. This is not a wp-env command, so it must not reach the forwarding
# logic below. It runs as REMOTE_USER (not the account you may SSH in with
# manually), whose nvm-managed global npm is the one that actually executes
# wp-env. Installing @latest is explicit, unlike `npm update -g`, which would
# also touch every other global package.
if [[ ${#REMAINING_ARGS[@]} -ge 1 ]] && [[ "${REMAINING_ARGS[0]}" == "update" ]]; then
  # shellcheck disable=SC2029
  ssh -i "${REMOTE_RPC_KEY}" -o IdentitiesOnly=yes ${REMOTE_USER}@${REMOTE_HOST} \
      "source ~/${REMOTE_PATH}/.wpenvrc && \
       echo \"Before: \$(wp-env --version)\" && \
       npm install -g @wordpress/env@latest && \
       echo \"After:  \$(wp-env --version)\""
  exit 0
fi

# Detect plugin-check subcommand and rewrite args accordingly
PLUGIN_CHECK_SLUG=""
if [[ ${#REMAINING_ARGS[@]} -ge 2 ]] && [[ "${REMAINING_ARGS[0]}" == "plugin-check" ]]; then
  PLUGIN_CHECK_SLUG="${REMAINING_ARGS[1]}"
  REMAINING_ARGS=(
    "run" "cli" "--" "wp" "plugin" "check" "${PLUGIN_CHECK_SLUG}" \
    "--include-experimental" \
    "--exclude-directories=tests" \
    "--exclude-files=.wp-env.conf,.wpenvrc,.wp-env.json,wp-env.sh"
  )
fi

# Build the remote command string from REMAINING_ARGS
REMOTE_CMD=""
for arg in "${REMAINING_ARGS[@]}"; do
  REMOTE_CMD+="${arg} "
done
REMOTE_CMD="${REMOTE_CMD% }"  # trim trailing space

# Function to print environment information after successful start
print_environment_info() {
  echo ""
  echo "✅ WordPress environment started successfully!"
  echo ""
  echo "Environment Details:"

  # Get all info in a single SSH call for efficiency
  local all_info
  # shellcheck disable=SC2029
  all_info=$(ssh -i "${REMOTE_RPC_KEY}" ${REMOTE_USER}@${REMOTE_HOST} "
    cd ~/${REMOTE_PATH} && \
    source ./.wpenvrc && \
    echo '===STATUS===' && \
    wp-env status 2>/dev/null && \
    echo '===WP_VERSION===' && \
    wp-env run cli -- wp core version 2>/dev/null && \
    echo '===PHP_INFO===' && \
    wp-env run cli -- wp --info 2>/dev/null
  " 2>/dev/null) || {
    echo "  ⚠️  Warning: Could not retrieve all environment details."
    echo "      Please run './wp-env.sh status' to check manually."
    return
  }

  # Parse status info
  local url runtime multisite xdebug install_path
  url=$(echo "$all_info" | awk -F': ' '/^    - url:/ {print $2}')
  runtime=$(echo "$all_info" | awk -F': ' '/^    - runtime:/ {print $2}')
  multisite=$(echo "$all_info" | awk -F': ' '/^    - multisite:/ {print $2}')
  xdebug=$(echo "$all_info" | awk -F': ' '/^    - xdebug:/ {print $2}')
  install_path=$(echo "$all_info" | awk -F': ' '/^    - install path:/ {print $2}')

  # Parse versions from dedicated sections
  local wp_version php_version mysql_version
  wp_version=$(echo "$all_info" | awk '/^===WP_VERSION===/{getline; print}')
  php_version=$(echo "$all_info" | awk -F'[ \t]+' '/PHP version:/ {print $3}')
  # Extract version from "MySQL version:  mariadb from 11.4.10-MariaDB, ..."
  mysql_version=$(echo "$all_info" | awk -F'[ ,]' '/MySQL version:/ {print $4}')

  # Calculate admin URL
  local admin_url="${url%/}/wp-admin"

  # Display all information
  echo "  Admin URL:        ${admin_url:-N/A}"
  echo "  WordPress:        ${wp_version:-N/A}"
  echo "  PHP:              ${php_version:-N/A}"
  echo "  Database:         MariaDB ${mysql_version:-N/A}"
  echo "  Runtime:          ${runtime:-N/A}"
  echo "  Multisite:        ${multisite:-N/A}"
  echo "  Xdebug:           ${xdebug:-N/A}"
  echo "  Install Path:     ${install_path:-N/A}"
  echo ""
  echo "Service Status:    ✅ Running"
  echo ""
}

# Sync the working tree to the remote only for the `start` and `plugin-check`
# commands. For other commands (logs, stop, destroy, run, status, …) an
# `rsync --delete` can race with in-progress local edits and interfere with the
# command, so skip it.
IS_START=false
for arg in "${REMAINING_ARGS[@]}"; do
  if [[ "$arg" == "start" ]]; then
    IS_START=true
    break
  fi
done

if [[ "$IS_START" == "true" ]] || [[ -n "$PLUGIN_CHECK_SLUG" ]]; then
  # shellcheck disable=SC2029
  rsync -az --delete --include="wp-env.sh" --include=".wp*" \
      --include="tests/" --include="tests/bin/" --include="tests/bin/wp-init.sh" \
      --include="tests/e2e/package.json" --include="tests/e2e/package-lock.json" \
      --exclude="tests/e2e/node_modules" \
      --exclude="tests/e2e/playwright-report" --exclude="tests/e2e/test-results" \
      --exclude-from=./.distignore \
      -e "ssh -i \"${REMOTE_RSYNC_KEY}\" -o IdentitiesOnly=yes" \
      . ${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_PATH}/
fi

# shellcheck disable=SC2029
ssh -i "${REMOTE_RPC_KEY}" -o IdentitiesOnly=yes ${REMOTE_USER}@${REMOTE_HOST} \
    "cd ~/${REMOTE_PATH} && \
     source ./.wpenvrc && \
     ${PHP_VERSION_OVERRIDE:+WP_ENV_PHP_VERSION=$PHP_VERSION_OVERRIDE} \
     ${WP_VERSION_OVERRIDE:+WP_ENV_CORE=$WP_VERSION_OVERRIDE} \
     wp-env ${REMOTE_CMD}"

# Display environment info in verbose mode after a start.
if [[ "${VERBOSE_MODE}" == "true" ]] && [[ "$IS_START" == "true" ]]; then
  print_environment_info
fi
