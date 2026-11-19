#!/usr/bin/env bash
# Usage: bin/demo/i18n.sh (requires WP-CLI with the i18n command).
# Extract only demo strings; the plugin's openporte catalogue is unchanged.
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/../.."
wp i18n make-pot demos/cf7 demos/cf7/languages/openporte-demo.pot \
  --domain=openporte-demo --slug=openporte-demo \
  --headers='{"Report-Msgid-Bugs-To":"https://github.com/openporte/openporte/issues"}'
