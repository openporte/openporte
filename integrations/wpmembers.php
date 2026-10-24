<?php

if ( ! defined( 'ABSPATH' ) ) exit;

add_action(
  'wpmem_pre_register_data',
  function () {
    $openporte_plugin = OpenPortePlugin::$instance;
    // WP-members uses native WordPress integration and does not have an activation select
    // If this hook is being called and WordPress register is enabled, validate altcha
    $openporte_mode = $openporte_plugin->get_integration_wordpress_register();
    if (!empty($openporte_mode)) {
      $altcha = isset($_POST['openporte_register']) ? trim(sanitize_text_field(wp_unslash($_POST['openporte_register']))) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
      if ($openporte_plugin->verify($altcha, null, 'wpmembers') === false) {
        global $wpmem_themsg;
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- $wpmem_themsg is WP-Members' own global, used to surface the error message.
        $wpmem_themsg = esc_html__('Registration failed. Please try again later.', 'openporte');
      }
    }
  },
  10,
  0
);