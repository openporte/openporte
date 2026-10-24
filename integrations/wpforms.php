<?php

if ( ! defined( 'ABSPATH' ) ) exit;

if (openporte_plugin_active('wpforms')) {
  add_filter(
    'wpforms_display_submit_before',
    function () {
      $plugin = OpenPortePlugin::$instance;
      $active = $plugin->get_integration_wpforms();
      if ($active) {
        echo wp_kses($plugin->render_widget($active, true), OpenPortePlugin::$html_espace_allowed_tags);
      }
    },
    10,
    1
  );

  add_action(
    'wpforms_process',
    function ($fields, $entry, $form_data) {
      $plugin = OpenPortePlugin::$instance;
      $active = $plugin->get_integration_wpforms();
      if ($active) {
        $altcha = isset($_POST['altcha']) ? trim(sanitize_text_field(wp_unslash($_POST['altcha']))) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ($plugin->verify($altcha, null, 'wpforms') === false) {
          wpforms()->process->errors[$form_data['id']]['header'] = esc_html__('Could not verify you are not a robot.', 'openporte');
        }
      }
    },
    10,
    3
  );
}