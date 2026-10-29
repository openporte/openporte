<?php

if ( ! defined( 'ABSPATH' ) ) exit;

if (openporte_plugin_active('gravityforms')) {
  add_action(
    'gform_loaded',
    function () {
      $plugin = OpenPortePlugin::$instance;
      $active = $plugin->get_integration_gravityforms();
      if ($active) {
        require_once('gravityforms/addon.php');
        GFAddOn::register('OPENPORTE_GFFormsAddOn');
      }
    },
    5
  );
}
