<?php
/**
 * OpenPorte uninstall handler.
 *
 * Runs when the user deletes the plugin from WordPress. It removes ONLY
 * OpenPorte's own data: its transients always, and its (openporte_*) options
 * unless the administrator switched on "Keep settings when the plugin is
 * deleted" (since 1.30.0), so a later reinstall picks the configuration up
 * again. The legacy altcha_* options belong to the original ALTCHA Spam
 * Protection plugin and are intentionally left untouched, so a user can keep
 * using — or roll back to — ALTCHA v1 without losing their configuration.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// The "Keep settings" toggle (OpenPortePlugin::$option_uninstall_keep_data).
// The raw key is hardcoded because the class is not loaded during uninstall;
// tests/phpunit/UninstallTest.php pins it to the property.
$openporte_keep_data = 1 === (int) get_option( 'openporte_uninstall_keep_data', 0 );

// The plugin's transients are always removed: the endpoint health-check cache
// and, since 1.29.0, the replay counter's rows (OpenPortePlugin::$replay_key_prefix
// — keep the two in sync; the class is not loaded during uninstall). They are
// short-lived state, not configuration, so keeping them across a reinstall
// would serve no one. They are stored as options too, but under WordPress's
// own _transient_ / _transient_timeout_ prefixes, which the openporte_ pattern
// below does not match.
$openporte_patterns = array(
	$wpdb->esc_like( '_transient_openporte_' ) . '%',
	$wpdb->esc_like( '_transient_timeout_openporte_' ) . '%',
);
// Unless the administrator chose to keep them, also remove every openporte_*
// option in the same pass (no hard-coded key list, so new openporte_* options
// are cleaned up automatically). Kept options include openporte_version, so
// openporte_upgrade() still runs the migrations a reinstalled newer release
// needs. Note: this targets the current site's options table only;
// network-wide cleanup on multisite is not handled here.
if ( ! $openporte_keep_data ) {
	$openporte_patterns[] = $wpdb->esc_like( 'openporte_' ) . '%';
}
foreach ( $openporte_patterns as $openporte_like ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off bulk delete of the plugin's own options on uninstall; there is no core API for prefix deletion and caching is irrelevant during uninstall.
	$wpdb->query(
		$wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $openporte_like )
	);
}

wp_cache_flush();
