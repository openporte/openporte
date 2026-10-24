<?php

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! function_exists("openporte_insert_before_key") ) {

  function openporte_insert_before_key( $array, $key, $newKey, $newValue )
  {
    $newArray = [];

    foreach ( $array as $k => $v ) {
      // Insert the new key-value pair before the specific key
      if ( $k === $key ) {
        $newArray[$newKey] = $newValue;
      }
      // Add the original key-value pair
      $newArray[$k] = $v;
    }

    return $newArray;
  }
}

if ( ! function_exists('openporte_enfold_theme_add_captcha_field') ) {
  function openporte_enfold_theme_add_captcha_field($elements)
  {
    $plugin = OpenPortePlugin::$instance;
    $active = $plugin->get_integration_enfold_theme();
    // Early-return when the integration is NOT active (the widget is only
    // injected when enabled; avf_form_send verifies under the same condition).
    if (!$active) {
      return $elements;
    }

    $captcha = [
      "id"        => "captcha",
      "type"      => "html",
      "content"   =>  wp_kses($plugin->render_widget($active, true), OpenPortePlugin::$html_espace_allowed_tags)
    ];

    $new = openporte_insert_before_key($elements, 'av-button', 'captcha', $captcha);
    return $new;
  }
}

add_filter( 'ava_mailchimp_contact_form_elements', 'openporte_enfold_theme_add_captcha_field' );

add_filter( 'avia_contact_form_elements', 'openporte_enfold_theme_add_captcha_field' );

add_filter( 'avf_form_send', function ($proceed, $new_post, $form_params, $that)
{
  /** @var avia_form $that */
  $plugin = OpenPortePlugin::$instance;
  $active = $plugin->get_integration_enfold_theme();
  if ($active) {
    $altcha = isset($_POST['altcha']) ? trim(urldecode(sanitize_text_field(wp_unslash($_POST['altcha'])))) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
    if ($plugin->verify($altcha, null, 'enfold_theme') === false) {
      $that->submit_error = __('Verification failed. Try again later.', 'openporte');
      return null;
    }
  }
  return $proceed;
}, 10, 4);

add_filter( 'avf_mailchimp_subscriber_data', function ($data, $that)
{
  /** @var avia_sc_mailchimp $that */
  $plugin = OpenPortePlugin::$instance;
  $active = $plugin->get_integration_enfold_theme();
  if ( $active ) {
    $altcha = isset($_POST['altcha']) ? trim(urldecode(sanitize_text_field(wp_unslash($_POST['altcha'])))) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
    if ($plugin->verify($altcha, null, 'enfold_theme') === false) {
      /* Only changing the email_address promts the user to enter a valid email address, which would confuse them. */
      $data['email_address'] = 'captcha failed';
      $data['status'] = 'THIS STATUS DOES NOT EXIST';
    }
  }
  return $data;
}, 10, 2);
