#!/usr/bin/env bash
#
# wp-init.sh — wp-env "afterStart" hook: provision test fixtures for OpenPorte.
#
# Sets up a side-by-side bench so the rebrand/migration can be exercised by hand:
#   * A suite of 3rd-party form plugins (PLUGINS_SUITE below) — installed;
#     only Contact Form 7 is activated (its fixture page needs it). The E2E
#     drivers (tests/e2e) activate the others themselves.
#   * ALTCHA Spam Protection v1.26.3 (the upstream plugin OpenPorte forks) and
#     OpenPorte (this repo, mapped) — both installed; their activation state is
#     never touched (a fresh bench starts with both deactivated), so a tester
#     can activate them one at a time and walk the ALTCHA -> OpenPorte upgrade.
#   * Three pages:
#       - "Contact Us": a Contact Form 7 form (its id is discovered, not guessed).
#       - "WPForms Test": a minimal WPForms fixture form (created as a wpforms
#         CPT post below) rendered via its shortcode.
#       - "Test Page": both the [altcha] and [openporte] shortcodes.
#
# Idempotent: safe to re-run; existing fixture pages and plugin activation
# states are left untouched.
#
# IMPORTANT: activate ALTCHA and OpenPorte one at a time, never together — both
# register the [altcha] shortcode and the altcha/v1 REST route and would clash.
# The script does not enforce this; keeping them apart is up to the tester.

set -euo pipefail

# Run a wp-cli command inside the wp-env "cli" container. wp-env is the binary
# used by wp-env.sh (there is no npm "env" script in this repo).
wpcli() { wp-env run cli -- wp "$@"; }

ALTCHA_SLUG="altcha-spam-protection"
# The plugin dir is mapped to wp-content/plugins/openporte, so the zip in local/
# is reachable from the container at this path (relative to the WordPress root).
# You can change the version between 1.26.3 and 3.0.0
ALTCHA_VERSION="1.26.3"
ALTCHA_ZIP_HOST="local/evidence/${ALTCHA_SLUG}.${ALTCHA_VERSION}.zip"
ALTCHA_ZIP_CONTAINER="wp-content/plugins/openporte/local/${ALTCHA_SLUG}.${ALTCHA_VERSION}.zip"
ALTCHA_URL="https://downloads.wordpress.org/plugin/${ALTCHA_SLUG}.${ALTCHA_VERSION}.zip"

echo "wp-init: installing legacy ALTCHA v${ALTCHA_VERSION} (source plugin for the migration test)…"
if [ -f "$ALTCHA_ZIP_HOST" ] && wpcli plugin install "$ALTCHA_ZIP_CONTAINER" --force; then
  echo "wp-init: installed ALTCHA from the local zip."
else
  # The zip is intentionally not committed to git; wordpress.org still serves the
  # byte-identical 1.26.3 build, so fall back to downloading it.
  echo "wp-init: local zip unavailable, downloading ALTCHA from wordpress.org…"
  wpcli plugin install "$ALTCHA_URL" --force
fi

echo "wp-init: installing 3rd party plugins…"
# Full test suite of plugins installation - tweak the list to your needs.
# Some plugins are not testable (requires paid license) or not found.
# 
# For our oldest supported WordPress version (5.6) w/ PHP 8.0, the
# following plugins can be tested:
#   | Plugin         | Version          | Notes             |
#   | -------------- | ---------------- | ----------------- |
#   | contact-form-7 | 5.4.2            | Verified, all tests passed |
#   | formidable     | 6.25.1           | Not tested but load alright with OpenPorte activated |
#   | forminator     | 1.33.0           | Not tested but load alright with OpenPorte activated |
#   | html-forms     | latest (1.6.4)   | Not tested but load alright with OpenPorte activated |
#   | woocommerce    | 6.2.3            | Verified, all tests passed |
#   | wpdiscuz       | none             | Tried several candidate, none work. Not testable with our minimum requirements #80 |
#   | wpforms-lite   | latest (2.0.0.2) | Verified, all tests passed |
# 
# List of plugins not found: gravityforms
# List of plugins not testable (requires paid license): elementor
# List of themes not found: enfold
# Excluded for now: coblocks wpdiscuz
#
# Columns: <slug> <version>. "latest" installs only when the plugin is absent
# and never upgrades — do a cleanup + start for a fresh bench. An exact version
# (e.g. "contact-form-7 5.4.2" for a WP 5.6 bench) reinstalls whenever the
# installed version differs, in either direction.
PLUGINS_SUITE=(
  "contact-form-7 latest"
  "formidable latest"
  "forminator latest"
  "html-forms latest"
  "woocommerce latest"
  "wpforms-lite latest"
)
for entry in "${PLUGINS_SUITE[@]}"; do
  read -r plugin version <<<"$entry"
  if [ "$version" = "latest" ]; then
    if ! wpcli plugin is-installed "$plugin"; then
      wpcli plugin install "$plugin"
    fi
  else
    installed="$(wpcli plugin get "$plugin" --field=version 2>/dev/null | tr -d '[:space:]' || true)"
    if [ "$installed" != "$version" ]; then
      wpcli plugin install "$plugin" --version="$version" --force
    fi
  fi
done

echo "wp-init: installing Plugin Check (a WordPress plugin test suite)…"
wpcli plugin install plugin-check --force || echo "Could not install Plugin Check; skipping it." >&2

echo "wp-init: creating fixture pages…"
existing_slugs="$(wpcli post list --post_type=page --post_status=publish --field=post_name 2>/dev/null || true)"

if wpcli plugin is-installed contact-form-7; then
  if ! wpcli plugin is-active contact-form-7; then
    echo "wp-init: activating Contact Form 7…"
    wpcli plugin activate contact-form-7
  fi

  if ! grep -qxF "contact-us" <<<"$existing_slugs"; then
    # Contact Form 7 5.x identifies forms by a hash (stored in the _hash postmeta);
    # the numeric post id is deprecated. Discover the default form's post id, then
    # read its hash (see the quoted shortcode emitted via a file below).
    cf7_post_id="$(wpcli post list --post_type=wpcf7_contact_form --format=ids 2>/dev/null | grep -oE '[0-9]+' | head -n1 || true)"
    cf7_hash=""
    if [ -n "$cf7_post_id" ]; then
      cf7_hash="$(wpcli post meta get "$cf7_post_id" _hash 2>/dev/null | grep -oE '[a-f0-9]{7,}' | head -n1 || true)"
    fi

    if [ -n "$cf7_hash" ]; then
      # Contact Form 7's editor uses the 7-char short hash. Emit the canonical
      # quoted shortcode, but pass it through a file (read by `wp post create`)
      # rather than a CLI argument — embedded double quotes get mangled crossing
      # the wp-env -> docker shell boundary, whereas file content is preserved.
      cf7_short="${cf7_hash:0:7}"
      # Write into tests/ (tracked, hence synced to the remote and mapped into the
      # container) — local/ is git-ignored and absent on the remote.
      cf7_content_host="tests/.cf7-contact-us.html"
      cf7_content_container="wp-content/plugins/openporte/${cf7_content_host}"
      echo "wp-init: using Contact Form 7 short hash ${cf7_short}."
      printf '%s' "[contact-form-7 id=\"${cf7_short}\" title=\"Contact form 1\"]" > "$cf7_content_host"
      wpcli post create "$cf7_content_container" --post_type=page --post_title='Contact Us' --post_status=publish
      rm -f "$cf7_content_host"
    elif [ -n "$cf7_post_id" ]; then
      echo "wp-init: hash not found; falling back to Contact Form 7 post id ${cf7_post_id}." >&2
      wpcli post create --post_type=page --post_title='Contact Us' --post_status=publish \
        --post_content="[contact-form-7 id=${cf7_post_id}]"
    else
      echo "wp-init: WARNING — no Contact Form 7 form found; the 'Contact Us' page will have no form." >&2
      wpcli post create --post_type=page --post_title='Contact Us' --post_status=publish \
        --post_content='No Contact Form 7 form was found when this page was provisioned.'
    fi
  fi
fi

if wpcli plugin is-installed wpforms-lite; then
  if ! grep -qxF "wpforms-test" <<<"$existing_slugs"; then
    echo "wp-init: creating the WPForms fixture form + page…"
    # The form is a `wpforms` CPT post whose post_content is the form JSON.
    # wp-cli happily inserts posts of a type that is not registered (yet), so
    # wpforms-lite can stay deactivated here — the E2E driver activates it.
    # Two steps because the JSON must embed its own post id.
    wpforms_id="$(wpcli post create --post_type=wpforms --post_title='E2E WPForms' --post_status=publish --porcelain | grep -oE '[0-9]+' | head -n1 || true)"
    if [ -n "$wpforms_id" ]; then
      # Minimal form: one textarea + a message confirmation. Anti-spam token
      # and honeypot are left disabled so this hand-rolled JSON doesn't depend
      # on their schema. Passed through a file — the embedded double quotes
      # would be mangled crossing the wp-env -> docker shell boundary (same
      # trick as the CF7 shortcode above).
      wpforms_content_host="tests/.wpforms-e2e.json"
      wpforms_content_container="wp-content/plugins/openporte/${wpforms_content_host}"
      printf '%s' '{"id":"'"${wpforms_id}"'","field_id":"2","fields":{"1":{"id":"1","type":"textarea","label":"Message","description":"","size":"medium","placeholder":"","css":""}},"settings":{"form_title":"E2E WPForms","form_desc":"","submit_text":"Submit","submit_text_processing":"Sending","antispam":"","honeypot":"","confirmations":{"1":{"type":"message","message":"Thanks for contacting us!","message_scroll":"1"}},"notification_enable":"0"},"meta":{"template":"blank"}}' > "$wpforms_content_host"
      wpcli post update "$wpforms_id" "$wpforms_content_container"
      rm -f "$wpforms_content_host"
      wpcli post create --post_type=page --post_title='WPForms Test' --post_status=publish \
        --post_content="[wpforms id=${wpforms_id}]"
    else
      echo "wp-init: WARNING — could not create the WPForms fixture form; skipping its page." >&2
    fi
  fi
fi

if ! grep -qxF "test-page" <<<"$existing_slugs"; then
  # Real newline between the two shortcodes (a literal \n would not render).
  wpcli post create --post_type=page --post_title='Test Page' --post_status=publish \
    --post_content=$'[altcha]\n[openporte]'
fi

# Drain the first-run cron backlog here rather than letting the first E2E run
# pay for it. A freshly provisioned bench has every scheduled event due at once
# — wp_version_check, wp_update_plugins, wp_update_themes, WooCommerce's
# wc_regenerate_images and Action Scheduler's initial queue. WordPress spawns
# cron as a loopback request from an ordinary front-end hit, so that backlog
# lands on whichever test happens to trigger it and stalls that one request
# (observed: a single woocommerce-register combo taking 108 s against a 120 s
# timeout, while its seven siblings ran in ~11 s). Running it now makes the
# first run's timings look like every subsequent run's.
echo "wp-init: draining the initial WP-Cron backlog…"
wpcli cron event run --due-now >/dev/null 2>&1 || \
  echo "wp-init: WARNING — could not drain the cron backlog; the first E2E run may see a slow combo." >&2

echo "wp-init: done."
