<?php

if ( ! defined( 'ABSPATH' ) ) exit;

add_action(
  'register_form',
  function () {
    $plugin = OpenPortePlugin::$instance;
    $active = $plugin->get_integration_wordpress_register();
    if ($active) {
      openporte_wordpress_comments_render_widget($active, 'openporte_register');
    }
  },
  10,
  0
);

add_action(
  'register_post',
  function ($user_login, $user_email, $errors) {
    $plugin = OpenPortePlugin::$instance;
    $active = $plugin->get_integration_wordpress_register();
    if ($active) {
      $altcha = isset($_POST['openporte_register']) ? trim(sanitize_text_field(wp_unslash($_POST['openporte_register']))) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
      if ($plugin->verify($altcha) === false) {
        return $errors->add(
          'openporte_error_message',
          '<strong>' . esc_html__('Error', 'openporte') . '</strong> : ' . esc_html__('Could not verify you are not a robot.', 'openporte')
        );
      }
    }
    return $errors;
  },
  10,
  3
);

add_action(
  'login_form',
  function () {
    $plugin = OpenPortePlugin::$instance;
    $active = $plugin->get_integration_wordpress_login();
    if ($active) {
      openporte_wordpress_comments_render_widget($active);
    }
  },
  10,
  0
);

/**
 * The WordPress login challenge check, shared by the pre-password gate and the
 * `authenticate` backstop below.
 *
 * Applies to wp-login.php and any form that signs in through wp_signon(),
 * except XML-RPC, REST and WooCommerce submissions.
 *
 * @since 1.30.0
 *
 * @param WP_User|null $user User the submitted username or email resolved to;
 *                           null only on the backstop, when no earlier
 *                           `authenticate` callback produced a result.
 * @return WP_User|WP_Error|null $user, or a WP_Error when the challenge fails.
 */
function openporte_wordpress_check_login( $user ) {
  if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
    return $user; // Skip XMLRPC
  }
  if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
    return $user; // Skip REST API
  }
  // WooCommerce form submissions are handled by integrations/woocommerce.php.
  // The helper ignores the nonce field on wp-login.php, so adding it to a
  // wp-login.php request cannot make both integrations stand down.
  if ( openporte_is_woocommerce_form_submission( 'woocommerce-login-nonce' ) ) {
    return $user;
  }
  $plugin = OpenPortePlugin::$instance;
  $active = $plugin->get_integration_wordpress_login();
  if ( $active ) {
    $altcha = isset( $_POST['altcha'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['altcha'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
    if ( $plugin->verify( $altcha ) === false ) {
      return new WP_Error( 'altcha-error', '<strong>' . esc_html__( 'Error', 'openporte' ) . '</strong> : ' . esc_html__( 'Could not verify you are not a robot.', 'openporte' ) );
    }
  }
  return $user;
}

/**
 * Check the challenge on a WordPress login before the password is checked.
 *
 * Core applies `wp_authenticate_user` after looking the user up and before
 * comparing the password, in both its username and its email path. Checking
 * here means a wrong password costs a solved challenge too, and a correct one
 * cannot be told apart from a wrong one by the error message.
 *
 * @since 1.30.0
 *
 * @param WP_User|WP_Error $user     User found for the submitted login, or an
 *                                   earlier callback's error.
 * @param string           $password Submitted password; unused.
 * @return WP_User|WP_Error $user, or a WP_Error when the challenge fails.
 */
function openporte_wordpress_authenticate_user( $user, $password ) {
  if ( ! ( $user instanceof WP_User ) ) {
    return $user;
  }
  return openporte_wordpress_check_login( $user );
}
add_filter( 'wp_authenticate_user', 'openporte_wordpress_authenticate_user', 10, 2 );

/**
 * Backstop for login paths that never apply `wp_authenticate_user`, such as a
 * third-party authentication provider at the `authenticate` filter.
 *
 * Hooked at priority 20, after core's own credential checks. On a core login
 * the gate above has already verified the same token in this request, so the
 * per-request verify memo answers here without consuming a second use.
 *
 * @since 1.26.3
 * @since 1.30.0 Converted from a closure; routes WooCommerce submissions
 *               through openporte_is_woocommerce_form_submission(); now the
 *               backstop to openporte_wordpress_authenticate_user().
 *
 * @param WP_User|WP_Error|null $user     Result of the earlier callbacks.
 * @param string                $username Submitted username or email; unused.
 * @param string                $password Submitted password; unused.
 * @return WP_User|WP_Error|null $user unchanged, or a WP_Error when the
 *                               challenge fails.
 */
function openporte_wordpress_authenticate( $user, $username, $password ) {
  if ( $user instanceof WP_Error ) {
    return $user;
  }
  return openporte_wordpress_check_login( $user );
}
add_filter( 'authenticate', 'openporte_wordpress_authenticate', 20, 3 );

add_action(
  'lostpassword_form',
  function () {
    $plugin = OpenPortePlugin::$instance;
    $active = $plugin->get_integration_wordpress_reset_password();
    if ($active) {
      openporte_wordpress_comments_render_widget($active);
    }
  },
  10,
  0
);

/**
 * Check the challenge on a WordPress password-reset request, unless
 * WooCommerce owns the submission.
 *
 * @since 1.26.3
 * @since 1.30.0 Converted from a closure; routes WooCommerce submissions
 *               through openporte_is_woocommerce_form_submission().
 *
 * @param WP_Error $errors Errors collected for the reset request.
 * @return WP_Error $errors, with an error added when the challenge fails.
 */
function openporte_wordpress_lostpassword_post( $errors ) {
  if ( is_user_logged_in() ) {
    return $errors;
  }
  // WooCommerce form submissions are handled by integrations/woocommerce.php;
  // see openporte_wordpress_authenticate() for why the helper decides.
  if ( openporte_is_woocommerce_form_submission( 'woocommerce-lost-password-nonce' ) ) {
    return $errors;
  }
  $plugin = OpenPortePlugin::$instance;
  $active = $plugin->get_integration_wordpress_reset_password();
  if ( $active ) {
    $altcha = isset( $_POST['altcha'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['altcha'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
    if ( $plugin->verify( $altcha ) === false ) {
      $errors->add(
        'openporte_error_message',
        '<strong>' . esc_html__( 'Error', 'openporte' ) . '</strong> : ' . esc_html__( 'Could not verify you are not a robot.', 'openporte' )
      );
    }
  }
  return $errors;
}
add_filter( 'lostpassword_post', 'openporte_wordpress_lostpassword_post', 10, 1 );

add_action(
  'comment_form_after_fields',
  function () {
    $plugin = OpenPortePlugin::$instance;
    $active = $plugin->get_integration_wordpress_comments();
    if ($active) {
      openporte_wordpress_comments_render_widget($active);
    }
  },
  10,
  0
);

add_action(
  'comment_form_logged_in_after',
  function () {
    $plugin = OpenPortePlugin::$instance;
    $active = $plugin->get_integration_wordpress_comments();
    if ($active) {
      openporte_wordpress_comments_render_widget($active);
    }
  },
  10,
  0
);

add_filter(
  'preprocess_comment',
  function ($comment) {
    // trackback or pingback
    if ($comment['comment_type'] != '' && $comment['comment_type'] != 'comment') {
      return $comment;
    }
    // admin replies
    if (is_user_logged_in() && current_user_can('manage_options')) {
      return $comment;
    }
    $plugin = OpenPortePlugin::$instance;
    $active = (openporte_plugin_active('wpdiscuz') && $plugin->get_integration_wpdiscuz()) || $plugin->get_integration_wordpress_comments();
    if ($active) {
      $altcha = isset($_POST['altcha']) ? trim(sanitize_text_field(wp_unslash($_POST['altcha']))) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
      if ($plugin->verify($altcha) === false) {
        wp_die('<strong>' . esc_html__('Error', 'openporte') . '</strong> : ' . esc_html__('Could not verify you are not a robot.', 'openporte'));
      }
    }
    return $comment;
  },
  10,
  1
);

function openporte_wordpress_comments_render_widget($active, $name = null)
{
  $plugin = OpenPortePlugin::$instance;
  echo wp_kses($plugin->render_widget($active, true, null, $name), OpenPortePlugin::$html_espace_allowed_tags);
}