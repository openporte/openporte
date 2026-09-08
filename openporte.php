<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/*
 * Plugin Name: OpenPorte Spam Protection
 * Description: Stop spam without CAPTCHAs. Proof-of-Work protection for your site. Open-source, self-hosted, privacy-first.
 * Author: OpenPorte Contributors
 * Author URI: https://github.com/openporte/openporte/graphs/contributors
 * Version: 1.29.0
 * Stable tag: 1.29.0
 * Requires at least: 5.6
 * Requires PHP: 8.0
 * Tested up to: 7.1
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: openporte
 * Domain Path: /languages
 */

/*
 * OpenPorte started as a fork of ALTCHA Spam Protection. For backward compatibility it
 * still defines the ALTCHA_* constant aliases and the AltchaPlugin class alias,
 * and registers the [altcha] shortcode and altcha/v1 REST route. If the original
 * ALTCHA plugin is also active these collide (PHP "already defined" warnings, a
 * duplicate widget, …). ALTCHA loads first (alphabetically), so detect it here
 * and bail out with a clear message instead of running both at once.
 */
if ( defined( 'ALTCHA_VERSION' ) || function_exists( 'altcha_plugin_active' ) || defined( 'ALTCHA_PLUGIN_VERSION' ) || class_exists( 'AltchaPlugin' ) ) {

	/**
	 * Builds the ALTCHA-conflict explanation shown to administrators.
	 *
	 * Shared by the activation blocker and the admin notice below so the two
	 * cannot drift apart. Returns HTML (the message embeds a link); callers are
	 * responsible for escaping via wp_kses_post().
	 *
	 * @since 1.27.0
	 *
	 * @return string Translated message with an embedded link, unescaped.
	 */
	function openporte_conflict_message() {
		return sprintf(
			/* translators: %s: link to the OpenPorte plugin page. */
			__( 'OpenPorte is a fork of ALTCHA Spam Protection and cannot run while the original ALTCHA plugin is active — the two share internal code. Please deactivate "ALTCHA Spam Protection" first, then activate OpenPorte. See the %s for details.', 'openporte' ),
			'<a href="https://wordpress.org/plugins/openporte/" target="_blank" rel="noopener noreferrer">OpenPorte plugin page</a>'
		);
	}

	/**
	 * Blocks activation with a readable message instead of a fatal error.
	 *
	 * Registered only in the conflict branch: the file returns right after
	 * this block, so the real activation hook (openporte_activate, further
	 * down) is never registered. Activating while ALTCHA is active therefore
	 * lands here and dies with the explanation, leaving the plugin inactive.
	 *
	 * @since 1.27.0
	 * @since 1.28.0 The die screen links to the Plugins page instead of a
	 *               browser history back link, which broke when activating
	 *               straight from the plugin uploader (see #65).
	 */
	register_activation_hook( __FILE__, function () {
		wp_die(
			wp_kses_post( openporte_conflict_message() ),
			esc_html__( 'OpenPorte cannot be activated', 'openporte' ),
			array(
				// A fixed destination, not `back_link`: that renders
				// javascript:history.back(), which breaks when activating from
				// the plugin uploader — the previous history entry is the
				// upload POST's result, and re-navigating to it makes WordPress
				// reject the re-submitted nonce as expired. The Plugins page is
				// also where the requested action (deactivating ALTCHA) happens.
				// self_admin_url() so a network-admin activation attempt lands
				// on network/plugins.php on multisite.
				'link_url'  => esc_url( self_admin_url( 'plugins.php' ) ),
				'link_text' => esc_html__( 'Go to the Plugins page', 'openporte' ),
			)
		);
	} );

	// Belt and braces: if OpenPorte ends up active alongside ALTCHA, show a notice.
	add_action( 'admin_notices', function () {
		echo '<div class="notice notice-error"><p>' . wp_kses_post( openporte_conflict_message() ) . '</p></div>';
	} );

	return;
}

define('OPENPORTE_VERSION', '1.29.0');
// Authoritative version of the vendored widget. The string embedded in
// public/altcha.min.js can lag (2.3.0 ships a bundle reporting 2.2.4 — upstream
// did not rebuild dist/); see docs/agents/altcha-upstream.md.
define('OPENPORTE_WIDGET_VERSION', '2.3.0');

// Upstream ALTCHA widget acknowledgement: the visible "Protected by ALTCHA" footer
// link. Intentionally points at altcha.org and is out of scope for the rebrand.
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Upstream-compat constant, intentionally kept.
define('ALTCHA_WEBSITE', 'https://altcha.org/');

// Deprecated ALTCHA_* aliases kept for backward compatibility with third-party
// code; scheduled for removal in a future release.
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Documented deprecated back-compat alias.
define('ALTCHA_VERSION', OPENPORTE_VERSION);
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Documented deprecated back-compat alias.
define('ALTCHA_WIDGET_VERSION', OPENPORTE_WIDGET_VERSION);


// Define the base name of the plugin for use in hooks and filters
if ( ! defined( 'OPENPORTE_PLUGIN_BASE' ) ) {
        define( 'OPENPORTE_PLUGIN_BASE', plugin_basename( __FILE__ ) );
}

// required for is_plugin_active
require_once ABSPATH . 'wp-admin/includes/plugin.php';

require plugin_dir_path(__FILE__) . 'includes/helpers.php';
require plugin_dir_path(__FILE__) . 'includes/core.php';
require plugin_dir_path( __FILE__ ) . './public/widget.php';

require plugin_dir_path( __FILE__ ) . './integrations/coblocks.php';
require plugin_dir_path( __FILE__ ) . './integrations/contact-form-7.php';
require plugin_dir_path( __FILE__ ) . './integrations/custom.php';
require plugin_dir_path( __FILE__ ) . './integrations/elementor.php';
require plugin_dir_path( __FILE__ ) . './integrations/enfold-theme.php';
require plugin_dir_path( __FILE__ ) . './integrations/formidable.php';
require plugin_dir_path( __FILE__ ) . './integrations/forminator.php';
require plugin_dir_path( __FILE__ ) . './integrations/html-forms.php';
require plugin_dir_path( __FILE__ ) . './integrations/gravityforms.php';
require plugin_dir_path( __FILE__ ) . './integrations/wpdiscuz.php';
require plugin_dir_path( __FILE__ ) . './integrations/wpforms.php';
require plugin_dir_path( __FILE__ ) . './integrations/wpmembers.php';
require plugin_dir_path( __FILE__ ) . './integrations/woocommerce.php';
require plugin_dir_path( __FILE__ ) . './integrations/wordpress.php';

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- These are static properties of the prefixed OpenPortePlugin class, not global variables.
OpenPortePlugin::$widget_script_src = plugin_dir_url(__FILE__) . "public/altcha.min.js";
OpenPortePlugin::$widget_style_src = plugin_dir_url(__FILE__) . "public/altcha.css";
OpenPortePlugin::$wp_script_src = plugin_dir_url(__FILE__) . "public/script.js";
OpenPortePlugin::$admin_script_src = plugin_dir_url(__FILE__) . "public/admin.js";
OpenPortePlugin::$admin_css_src = plugin_dir_url(__FILE__) . "public/admin.css";
OpenPortePlugin::$custom_script_src = plugin_dir_url(__FILE__) . "public/custom.js";
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

register_activation_hook(__FILE__, 'openporte_activate');
register_deactivation_hook(__FILE__, 'openporte_deactivate');

add_action('after_plugin_row_' . plugin_basename(__FILE__), 'openporte_plugin_custom_message');

$openporte_shortcode = function ($attrs) {
  $plugin = OpenPortePlugin::$instance;
  $default = array(
    'language' => null,
    // `mode` is a vestige of the pre-1.28 mode-string era: render_widget()
    // never branches on it, it is only passed through to the widget filters.
    // Read the option directly — get_integration_custom() is deprecated (#62)
    // and would log a notice on every shortcode render. The whole `mode`
    // plumbing is slated for cleanup in the next major release.
    'mode' => get_option(OpenPortePlugin::$option_integration_custom),
  );
  $a = shortcode_atts($default, $attrs);
  return wp_kses($plugin->render_widget($a['mode'], true, $a['language']), OpenPortePlugin::$html_espace_allowed_tags);
};
add_shortcode('openporte', $openporte_shortcode);
// Deprecated [altcha] alias kept for back-compat; remove in a future release.
add_shortcode('altcha', $openporte_shortcode);

// Note: we intentionally do NOT call load_plugin_textdomain(). Since WordPress
// 4.6 (and we require 5.6+), translations are loaded automatically via core's
// just-in-time mechanism — both translate.wordpress.org language packs and the
// .mo files bundled in ./languages/ (the latter auto-discovered on modern WP).
// See https://make.wordpress.org/core/2024/10/21/i18n-improvements-6-7/.

function openporte_activate()
{
  openporte_migrate_legacy_options();
  // Normalize AFTER the migration: it may have just imported legacy ALTCHA
  // select-mode strings that the checkbox settings cannot represent.
  openporte_normalize_integration_options();

  // Is this a genuinely new site, or one that already has a configuration?
  // Captured here — after the migration, before anything below is seeded — and
  // keyed on the signing secret, which is the one option every configured site
  // has: seeded by an earlier activation, or just imported from altcha_secret.
  // A version-gated check would not do: openporte_version postdates the
  // releases this has to recognise, and legacy ALTCHA never stored one.
  $is_fresh_install = get_option(OpenPortePlugin::$option_secret, null) === null;

  // Seed defaults only when the option is absent (add_option is a no-op when it
  // already exists), so a freshly migrated or a pre-existing configuration is
  // preserved across (re)activation. In particular the signing secret must not
  // be regenerated, or previously issued challenges would stop verifying.
  add_option(OpenPortePlugin::$option_api, 'selfhosted');
  add_option(OpenPortePlugin::$option_api_custom_url, '');
  add_option(OpenPortePlugin::$option_expires, '300');
  add_option(OpenPortePlugin::$option_replaylimit, OpenPortePlugin::$replaylimit_default);
  // Only a new install gets SHA-512. Any site that arrives here with a
  // configuration is an upgrade and keeps SHA-256: every release before 1.28
  // hardcoded it, and an external ALTCHA-compatible backend still serves it, so
  // seeding SHA-512 would silently break verification in Custom API mode. This
  // must be decided explicitly rather than left to get_algorithm()'s SHA-256
  // fallback, because add_option() would materialise the row first and the
  // fallback would never be reached. The two upgrade routes therefore agree:
  // this one pins SHA-256, and a plugin update — which never runs the
  // activation hook — leaves the option absent and falls back to it.
  add_option(OpenPortePlugin::$option_algorithm, $is_fresh_install ? 'SHA-512' : 'SHA-256');
  add_option(OpenPortePlugin::$option_secret, OpenPortePlugin::$instance->random_secret());
  // openporte_integration_custom is deliberately no longer seeded to 1 here:
  // the Custom HTML integration is deprecated (#62), so new installs get the
  // register_setting() default of 0.
}

/**
 * Version-gated upgrade routine.
 *
 * Plugin updates do NOT re-run the activation hook, so value/schema migrations
 * must run from plugins_loaded, gated by the stored plugin version. Every step
 * in here must be idempotent: the gate re-opens on each version bump.
 */
function openporte_upgrade()
{
  $stored_version = get_option(OpenPortePlugin::$option_version);
  if ($stored_version === OPENPORTE_VERSION) {
    return;
  }
  openporte_normalize_integration_options();
  // Replay protection (#101) is on by default from 1.29.0. Seeded here as well
  // as at activation because a plugin update never runs the activation hook,
  // and add_option() is a no-op once the row exists, so an admin who has
  // chosen another value keeps it.
  add_option(OpenPortePlugin::$option_replaylimit, OpenPortePlugin::$replaylimit_default);
  // The spam filter is gone (issue #6): drop its orphaned option. The raw key
  // is hardcoded on purpose — its static property was removed with the feature
  // (same convention as the legacy altcha_* keys in the migration map).
  delete_option('openporte_blockspam');
  // Custom HTML is deprecated (#62): switch it off once when crossing 1.28.0.
  // Most enabled values were never a user choice — upstream force-enabled the
  // option on every activation since 1.9.2 — and it costs the widget scripts
  // on every front-end page. Gated on the PRE-update stored version so that a
  // deliberate re-enable through the still-functional settings toggle survives
  // later upgrades. A missing stored version also passes the gate: releases
  // before this option existed must be treated as "upgrading from old", and on
  // a fresh 1.28.0 install writing 0 just materialises the default.
  if (!is_string($stored_version) || version_compare($stored_version, '1.28.0', '<')) {
    update_option(OpenPortePlugin::$option_integration_custom, 0);
  }
  update_option(OpenPortePlugin::$option_version, OPENPORTE_VERSION);
}
add_action('plugins_loaded', 'openporte_upgrade');

/**
 * Map legacy select-mode strings to the checkbox (0/1) integration options.
 *
 * Pre-1.28 the integration options stored a mode string. Every mode except
 * 'spamfilter' (spam filter without captcha — feature removed in #6) showed
 * and/or verified the captcha, so they map to 1. Exception: HTML Forms'
 * 'shortcode' maps to 0 — that mode never auto-injected the widget, and
 * hf_validate_form still verifies a shortcode-placed widget on its own, so 0
 * preserves the exact legacy behavior.
 *
 * Contact Form 7's 'shortcode' maps to 1, not 0. Pre-1.28 that mode skipped
 * auto-injection but still enforced verification: the wpcf7_spam filter listed
 * 'shortcode' alongside 'captcha'. Mapping it to 0 would leave the shortcode
 * still rendering a widget while silently accepting every submission — a form
 * that looks protected and is not. Mapping to 1 keeps verification, and the
 * shortcode guard in integrations/contact-form-7.php suppresses the injection
 * when the form already renders its own widget, so these sites keep exactly
 * their legacy behavior: one widget, still verified.
 *
 * Second exception: Custom HTML always maps to 0. The integration is
 * deprecated (#62), and a legacy truthy value was rarely a user choice anyway:
 * upstream force-enabled it on every activation since 1.9.2.
 */
function openporte_normalize_integration_options()
{
  $integration_keys = array(
    OpenPortePlugin::$option_integration_coblocks,
    OpenPortePlugin::$option_integration_contact_form_7,
    OpenPortePlugin::$option_integration_custom,
    OpenPortePlugin::$option_integration_elementor,
    OpenPortePlugin::$option_integration_formidable,
    OpenPortePlugin::$option_integration_forminator,
    OpenPortePlugin::$option_integration_gravityforms,
    OpenPortePlugin::$option_integration_woocommerce_login,
    OpenPortePlugin::$option_integration_woocommerce_register,
    OpenPortePlugin::$option_integration_woocommerce_reset_password,
    OpenPortePlugin::$option_integration_html_forms,
    OpenPortePlugin::$option_integration_wordpress_login,
    OpenPortePlugin::$option_integration_wordpress_register,
    OpenPortePlugin::$option_integration_wordpress_reset_password,
    OpenPortePlugin::$option_integration_wordpress_comments,
    OpenPortePlugin::$option_integration_wpdiscuz,
    OpenPortePlugin::$option_integration_wpforms,
    OpenPortePlugin::$option_integration_enfold_theme,
  );

  foreach ($integration_keys as $key) {
    $value = get_option($key, null);
    // Skip absent options (don't create rows), values that are already
    // numeric (0/1 checkbox era), and '' which is falsy in both eras.
    if (!is_string($value) || $value === '' || is_numeric($value)) {
      continue;
    }
    $disable = ($value === 'spamfilter')
      || ($key === OpenPortePlugin::$option_integration_html_forms && $value === 'shortcode')
      || ($key === OpenPortePlugin::$option_integration_custom);
    update_option($key, $disable ? 0 : 1);
  }
}

/**
 * Copy any legacy ALTCHA (altcha_*) option values into the OpenPorte
 * (openporte_*) namespace on activation.
 *
 * Copy-only and guarded: an existing openporte_* value is never overwritten,
 * and the original altcha_* options are left in place so a user can roll back
 * to the original ALTCHA v1 plugin without losing their configuration.
 */
function openporte_migrate_legacy_options()
{
  $option_keys = array(
    OpenPortePlugin::$option_api,
    OpenPortePlugin::$option_api_custom_url,
    OpenPortePlugin::$option_secret,
    OpenPortePlugin::$option_complexity,
    OpenPortePlugin::$option_expires,
    OpenPortePlugin::$option_auto,
    OpenPortePlugin::$option_floating,
    OpenPortePlugin::$option_delay,
    OpenPortePlugin::$option_hidefooter,
    OpenPortePlugin::$option_hidelogo,
    OpenPortePlugin::$option_integration_coblocks,
    OpenPortePlugin::$option_integration_contact_form_7,
    OpenPortePlugin::$option_integration_custom,
    OpenPortePlugin::$option_integration_elementor,
    OpenPortePlugin::$option_integration_formidable,
    OpenPortePlugin::$option_integration_forminator,
    OpenPortePlugin::$option_integration_gravityforms,
    OpenPortePlugin::$option_integration_woocommerce_login,
    OpenPortePlugin::$option_integration_woocommerce_register,
    OpenPortePlugin::$option_integration_woocommerce_reset_password,
    OpenPortePlugin::$option_integration_html_forms,
    OpenPortePlugin::$option_integration_wordpress_login,
    OpenPortePlugin::$option_integration_wordpress_register,
    OpenPortePlugin::$option_integration_wordpress_reset_password,
    OpenPortePlugin::$option_integration_wordpress_comments,
    OpenPortePlugin::$option_integration_wpdiscuz,
    OpenPortePlugin::$option_integration_wpforms,
    OpenPortePlugin::$option_integration_enfold_theme,
  );

  foreach ($option_keys as $new_key) {
    $legacy_key = 'altcha_' . substr($new_key, strlen('openporte_'));
    $legacy_value = get_option($legacy_key, null);
    if ($legacy_value !== null && get_option($new_key, null) === null) {
      update_option($new_key, $legacy_value);
    }
  }
}

function openporte_deactivate()
{
}

function openporte_plugin_custom_message()
{

}
