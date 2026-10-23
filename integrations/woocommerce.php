<?php

if ( ! defined( 'ABSPATH' ) ) exit;

add_action(
  'woocommerce_register_form',
  function () {
    $plugin = OpenPortePlugin::$instance;
    $active = $plugin->get_integration_woocommerce_register();
    if ($active) {
      openporte_woocommerce_comments_render_widget($active, 'openporte_register');
    }
  },
  10,
  0
);

add_action(
  'woocommerce_register_post',
  function ($user_login, $user_email, $errors) {
    $plugin = OpenPortePlugin::$instance;
    $active = $plugin->get_integration_woocommerce_register();
    if ($active) {
      $altcha = isset($_POST['openporte_register']) ? trim(sanitize_text_field(wp_unslash($_POST['openporte_register']))) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
      if ($plugin->verify($altcha) === false) {
        return $errors->add(
          'openporte_error_message',
          esc_html__('Could not verify you are not a robot.', 'openporte')
        );
      }
    }
    return $errors;
  },
  10,
  3
);

add_action(
  'woocommerce_login_form',
  function () {
    $plugin = OpenPortePlugin::$instance;
    $active = $plugin->get_integration_woocommerce_login();
    if ($active) {
      openporte_woocommerce_comments_render_widget($active);
    }
  },
  10,
  0
);

/**
 * The WooCommerce login challenge check, shared by the pre-password gate and
 * the `authenticate` backstop below.
 *
 * Applies only to WooCommerce's My Account login, as decided by
 * openporte_is_woocommerce_form_submission(), and never to XML-RPC or REST.
 *
 * @since 1.30.0
 *
 * @param WP_User|null $user User the submitted username or email resolved to;
 *                           null only on the backstop, when no earlier
 *                           `authenticate` callback produced a result.
 * @return WP_User|WP_Error|null $user, or a WP_Error when the challenge fails.
 */
function openporte_woocommerce_check_login( $user ) {
  if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
    return $user; // Skip XMLRPC
  }
  if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
    return $user; // Skip REST API
  }
  // Only handle WooCommerce form submissions. Same helper as the WordPress
  // integration, so exactly one of the two owns each login request.
  if ( ! openporte_is_woocommerce_form_submission( 'woocommerce-login-nonce' ) ) {
    return $user;
  }

  $plugin = OpenPortePlugin::$instance;
  $active = $plugin->get_integration_woocommerce_login();
  if ( $active ) {
    $altcha = isset( $_POST['altcha'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['altcha'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
    if ( $plugin->verify( $altcha ) === false ) {
      return new WP_Error(
        'altcha-error',
        esc_html__( 'Could not verify you are not a robot.', 'openporte' )
      );
    }
  }
  return $user;
}

/**
 * Check the challenge on a WooCommerce login before the password is checked.
 *
 * WooCommerce signs in through wp_signon(), so core's `wp_authenticate_user`
 * runs here too; see openporte_wordpress_authenticate_user() for why the
 * check belongs before the password comparison.
 *
 * @since 1.30.0
 *
 * @param WP_User|WP_Error $user     User found for the submitted login, or an
 *                                   earlier callback's error.
 * @param string           $password Submitted password; unused.
 * @return WP_User|WP_Error $user, or a WP_Error when the challenge fails.
 */
function openporte_woocommerce_authenticate_user( $user, $password ) {
  if ( ! ( $user instanceof WP_User ) ) {
    return $user;
  }
  return openporte_woocommerce_check_login( $user );
}
add_filter( 'wp_authenticate_user', 'openporte_woocommerce_authenticate_user', 10, 2 );

/**
 * Backstop for login paths that never apply `wp_authenticate_user`.
 *
 * Hooked on `authenticate` at priority 20, after core's own credential checks;
 * see openporte_wordpress_authenticate() for how it avoids a second use.
 *
 * @since 1.26.3
 * @since 1.30.0 Converted from a closure; acts only when
 *               openporte_is_woocommerce_form_submission() says WooCommerce
 *               owns the submission; now the backstop to
 *               openporte_woocommerce_authenticate_user().
 *
 * @param WP_User|WP_Error|null $user Result of the earlier callbacks.
 * @return WP_User|WP_Error|null $user unchanged, or a WP_Error when the
 *                               challenge fails.
 */
function openporte_woocommerce_authenticate( $user ) {
  if ( $user instanceof WP_Error ) {
    return $user;
  }
  return openporte_woocommerce_check_login( $user );
}
add_filter( 'authenticate', 'openporte_woocommerce_authenticate', 20, 1 );

add_action(
  'woocommerce_lostpassword_form',
  function () {
    $plugin = OpenPortePlugin::$instance;
    $active = $plugin->get_integration_woocommerce_reset_password();
    if ($active) {
      openporte_woocommerce_comments_render_widget($active);
    }
  },
  10,
  0
);

/**
 * Check the challenge on a WooCommerce password-reset request (My Account
 * lost password form).
 *
 * @since 1.26.3
 * @since 1.30.0 Converted from a closure; acts only when
 *               openporte_is_woocommerce_form_submission() says WooCommerce
 *               owns the submission.
 *
 * @param WP_Error $errors Errors collected for the reset request.
 * @return WP_Error $errors, with an error added when the challenge fails.
 */
function openporte_woocommerce_lostpassword_post( $errors ) {
  if ( is_user_logged_in() ) {
    return $errors;
  }
  // Only handle WooCommerce form submissions; see
  // openporte_woocommerce_authenticate().
  if ( ! openporte_is_woocommerce_form_submission( 'woocommerce-lost-password-nonce' ) ) {
    return $errors;
  }
  $plugin = OpenPortePlugin::$instance;
  $active = $plugin->get_integration_woocommerce_reset_password();
  if ( $active ) {
    $altcha = isset( $_POST['altcha'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['altcha'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
    if ( $plugin->verify( $altcha ) === false ) {
      $errors->add(
        'openporte_error_message',
        esc_html__( 'Could not verify you are not a robot.', 'openporte' )
      );
    }
  }
  return $errors;
}
add_filter( 'lostpassword_post', 'openporte_woocommerce_lostpassword_post', 10, 1 );

function openporte_woocommerce_comments_render_widget($active, $name = null)
{
  $plugin = OpenPortePlugin::$instance;
  echo wp_kses($plugin->render_widget($active, true, null, $name), OpenPortePlugin::$html_espace_allowed_tags);
}