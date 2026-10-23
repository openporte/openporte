<?php

if ( ! defined( 'ABSPATH' ) ) exit;

if (openporte_plugin_active('elementor')){
  /**
   * Register the OpenPorte field with Elementor Pro's form widget.
   *
   * @since 1.26.3
   * @since 1.30.0 Registers nothing when Elementor Pro's field base class is
   *               missing, instead of letting field.php end the request.
   *
   * @param object $form_fields_registrar Elementor Pro's form fields registrar.
   * @return void
   */
  function openporte_register_form_field( $form_fields_registrar ) {
    // field.php extends this class; without it the field cannot exist.
    if ( ! class_exists( '\ElementorPro\Modules\Forms\Fields\Field_Base' ) ) {
      return;
    }
    require_once __DIR__ . '/elementor/field.php';

    $form_fields_registrar->register( new \OpenPorte_Elementor_Form_Field() );
  }
  $openporte_plugin = OpenPortePlugin::$instance;
  $openporte_active = $openporte_plugin->get_integration_elementor();
  // Truthy check: the option is a checkbox (0/1) since the spam-filter removal;
  // legacy string values ('captcha', …) are normalized on upgrade.
  if ($openporte_active) {
    add_action('elementor_pro/forms/fields/register', 'openporte_register_form_field');
  }
}