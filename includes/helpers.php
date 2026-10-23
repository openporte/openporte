<?php

if ( ! defined( 'ABSPATH' ) ) exit;

function openporte_plugin_active($name) {
  switch ($name) {
    case 'coblocks':
      return is_plugin_active('coblocks/class-coblocks.php');
    case 'elementor':
      return is_plugin_active('elementor/elementor.php');
    case 'formidable':
      return is_plugin_active('formidable/formidable.php');
    case 'forminator':
      return is_plugin_active('forminator/forminator.php');
    case 'gravityforms':
      return is_plugin_active('gravityforms/gravityforms.php');
    case 'html-forms':
      return is_plugin_active('html-forms/html-forms.php');
    case 'contact-form-7':
      return is_plugin_active('contact-form-7/wp-contact-form-7.php');
    case 'woocommerce':
      return is_plugin_active('woocommerce/woocommerce.php');
    case 'wpdiscuz':
      return is_plugin_active('wpdiscuz/class.WpdiscuzCore.php');
    case 'wpmembers':
      return is_plugin_active('wp-members/wp-members.php');
    case 'wpforms':
      return is_plugin_active('wpforms/wpforms.php') || is_plugin_active('wpforms-lite/wpforms.php');
    case 'enfold-theme':
      // Enfold is a theme, not a plugin: available when the ACTIVE theme (or
      // its parent — get_template() returns the parent's directory) is Enfold.
      // An installed-but-inactive Enfold can't render forms, so it doesn't count.
      return stripos(get_template(), 'enfold') !== false;
    default:
      $active = apply_filters('openporte_plugin_active', false, $name);
      // Deprecated alias kept for back-compat; remove in a future release.
      return apply_filters_deprecated('altcha_plugin_active', array($active, $name), '1.27.0', 'openporte_plugin_active');
  }
}

/**
 * Whether the current request is a WooCommerce form submission, whose CAPTCHA
 * check belongs to the WooCommerce integration rather than the WordPress one.
 *
 * The WooCommerce nonce field alone proves nothing: it is an ordinary POST
 * field, anyone can add it to a wp-login.php request, and wp-login.php never
 * checks it. So it only counts outside wp-login.php: that is the one screen
 * that fires `login_init`, and WooCommerce's My Account login and lost
 * password handlers never load it. A wp-login.php request carrying the field
 * therefore stays with the WordPress integration.
 *
 * Both integrations route through this one helper, so their checks stay
 * mutually exclusive and cannot both stand down on the same request.
 *
 * @since 1.30.0
 *
 * @param string $nonce_field WooCommerce's nonce field for the form:
 *                            'woocommerce-login-nonce' or
 *                            'woocommerce-lost-password-nonce'.
 * @return bool True when WooCommerce is active, the field is present and the
 *              request is not wp-login.php.
 */
function openporte_is_woocommerce_form_submission( $nonce_field ) {
  if ( did_action( 'login_init' ) || ! openporte_plugin_active( 'woocommerce' ) ) {
    return false;
  }
  // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Presence check only, to route the submission; WooCommerce verifies its own nonce.
  return isset( $_POST[ $nonce_field ] );
}

function openporte_enqueue_styles() {
  wp_enqueue_style(
    'altcha-widget-styles',
    OpenPortePlugin::$widget_style_src,
    array(),
    OPENPORTE_VERSION,
    'all'
  );
}

function openporte_enqueue_scripts() {
  wp_enqueue_script(
    'altcha-widget',
    OpenPortePlugin::$widget_script_src,
    array(),
    OPENPORTE_VERSION,
    true
  );
  wp_enqueue_script(
    'altcha-widget-wp',
    OpenPortePlugin::$wp_script_src,
    array('altcha-widget'),
    OPENPORTE_VERSION,
    true
  );
}
