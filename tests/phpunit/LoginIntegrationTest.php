<?php
/**
 * WordPress and WooCommerce login / password-reset integrations: which of the
 * two owns a submission, and that exactly one of them checks the challenge.
 *
 * The callbacks are called in the order WordPress runs them: woocommerce.php
 * is loaded, and so registers, before wordpress.php, and both sit at the same
 * priority. wp-login.php is simulated by firing `login_init`.
 *
 * What these tests cannot prove — real wp-login.php and My Account requests,
 * and core's credential checks around these callbacks — needs the wp-env
 * bench (tests/e2e/).
 *
 * @package OpenPorte\Tests
 * @since 1.30.0
 */

/**
 * Verify the routing between the WordPress and WooCommerce integrations.
 *
 * @since 1.30.0
 */
class LoginIntegrationTest extends OpenPorteTestCase
{
  /**
   * Load both integrations once. Their callbacks are named functions, so the
   * tests call them directly.
   *
   * @since 1.30.0
   */
  public static function setUpBeforeClass(): void
  {
    parent::setUpBeforeClass();
    require_once dirname(__DIR__, 2) . '/includes/helpers.php';
    require_once dirname(__DIR__, 2) . '/integrations/woocommerce.php';
    require_once dirname(__DIR__, 2) . '/integrations/wordpress.php';
  }

  protected function tearDown(): void
  {
    $_POST = array();
    parent::tearDown();
  }

  /**
   * Configure the request: which integrations are on, whether WooCommerce is
   * active, whether this is wp-login.php, and what was posted.
   *
   * @since 1.30.0
   *
   * @param array $args {
   *     @type bool  $wordpress   WordPress login and reset protection on.
   *     @type bool  $woocommerce WooCommerce login and reset protection on.
   *     @type bool  $woo_active  WooCommerce plugin active.
   *     @type bool  $wp_login    The request is wp-login.php (`login_init` fired).
   *     @type array $post        The $_POST payload.
   * }
   */
  private function request(array $args)
  {
    $args += array(
      'wordpress' => false,
      'woocommerce' => false,
      'woo_active' => true,
      'wp_login' => false,
      'post' => array(),
    );
    update_option(OpenPortePlugin::$option_integration_wordpress_login, $args['wordpress'] ? 1 : 0);
    update_option(OpenPortePlugin::$option_integration_wordpress_reset_password, $args['wordpress'] ? 1 : 0);
    update_option(OpenPortePlugin::$option_integration_woocommerce_login, $args['woocommerce'] ? 1 : 0);
    update_option(OpenPortePlugin::$option_integration_woocommerce_reset_password, $args['woocommerce'] ? 1 : 0);
    OpenPorte_Test_Env::$active_plugins = $args['woo_active'] ? array('woocommerce/woocommerce.php') : array();
    if ($args['wp_login']) {
      do_action('login_init');
    }
    $_POST = $args['post'];
  }

  /**
   * Run the two `authenticate` callbacks in WordPress's order, on a user that
   * core's credential checks already accepted.
   *
   * @since 1.30.0
   *
   * @return WP_User|WP_Error
   */
  private function authenticate()
  {
    $user = openporte_woocommerce_authenticate(new WP_User());

    return openporte_wordpress_authenticate($user, 'someone', 'secret');
  }

  /**
   * Run the two `wp_authenticate_user` callbacks in WordPress's order: the
   * point where core has found the user but not yet compared the password.
   *
   * @since 1.30.0
   *
   * @param WP_User|WP_Error $user Value core passes in.
   * @return WP_User|WP_Error
   */
  private function authenticate_user($user = null)
  {
    $user = $user === null ? new WP_User() : $user;
    $user = openporte_woocommerce_authenticate_user($user, 'any password');

    return openporte_wordpress_authenticate_user($user, 'any password');
  }

  /**
   * Run the two `lostpassword_post` callbacks in WordPress's order.
   *
   * @since 1.30.0
   *
   * @return bool Whether the reset request was refused.
   */
  private function lostpassword_refused()
  {
    $errors = openporte_wordpress_lostpassword_post(openporte_woocommerce_lostpassword_post(new WP_Error()));

    return in_array('openporte_error_message', $errors->get_error_codes(), true);
  }

  /* ---------------------------------------------------------- wp-login.php */

  /**
   * wp-login.php without a token is refused when WordPress login is on.
   *
   * @since 1.30.0
   */
  public function test_wp_login_without_a_token_is_refused()
  {
    $this->request(array('wordpress' => true, 'wp_login' => true));

    $this->assertInstanceOf(WP_Error::class, $this->authenticate());
  }

  /**
   * Regression: a WooCommerce nonce field added to a wp-login.php request no
   * longer makes both integrations stand down.
   *
   * @since 1.30.0
   */
  public function test_wp_login_with_a_forged_woocommerce_field_is_still_checked()
  {
    $this->request(array(
      'wordpress' => true,
      'wp_login' => true,
      'post' => array('woocommerce-login-nonce' => 'x'),
    ));

    $this->assertInstanceOf(WP_Error::class, $this->authenticate());
  }

  /**
   * wp-login.php with a solved challenge is accepted.
   *
   * @since 1.30.0
   */
  public function test_wp_login_with_a_valid_token_is_accepted()
  {
    $this->request(array(
      'wordpress' => true,
      'wp_login' => true,
      'post' => array('altcha' => $this->token(time() + 600), 'woocommerce-login-nonce' => 'x'),
    ));

    $this->assertInstanceOf(WP_User::class, $this->authenticate());
  }

  /**
   * With only WooCommerce login on, wp-login.php is not protected, forged
   * field or not: the WooCommerce integration never claims wp-login.php.
   *
   * @since 1.30.0
   */
  public function test_wp_login_is_not_claimed_by_the_woocommerce_integration()
  {
    $this->request(array(
      'woocommerce' => true,
      'wp_login' => true,
      'post' => array('woocommerce-login-nonce' => 'x'),
    ));

    $this->assertInstanceOf(WP_User::class, $this->authenticate());
  }

  /* --------------------------------------------------- WooCommerce My Account */

  /**
   * A real WooCommerce login without a token is refused when WooCommerce
   * login is on.
   *
   * @since 1.30.0
   */
  public function test_woocommerce_login_without_a_token_is_refused()
  {
    $this->request(array('woocommerce' => true, 'post' => array('woocommerce-login-nonce' => 'x')));

    $this->assertInstanceOf(WP_Error::class, $this->authenticate());
  }

  /**
   * A real WooCommerce login is left to WooCommerce's own toggle: with it off,
   * the WordPress integration does not demand a token its form never renders.
   *
   * @since 1.30.0
   */
  public function test_woocommerce_login_with_its_toggle_off_is_not_checked()
  {
    $this->request(array('wordpress' => true, 'post' => array('woocommerce-login-nonce' => 'x')));

    $this->assertInstanceOf(WP_User::class, $this->authenticate());
  }

  /**
   * Without WooCommerce active, its nonce field means nothing: the WordPress
   * integration checks the request.
   *
   * @since 1.30.0
   */
  public function test_the_woocommerce_field_is_ignored_without_woocommerce()
  {
    $this->request(array(
      'wordpress' => true,
      'woo_active' => false,
      'post' => array('woocommerce-login-nonce' => 'x'),
    ));

    $this->assertInstanceOf(WP_Error::class, $this->authenticate());
  }

  /* ------------------------------------------------------------ lost password */

  /**
   * Regression: a forged WooCommerce field on wp-login.php's lost password
   * form no longer skips the check.
   *
   * @since 1.30.0
   */
  public function test_wp_lost_password_with_a_forged_woocommerce_field_is_still_checked()
  {
    $this->request(array(
      'wordpress' => true,
      'wp_login' => true,
      'post' => array('woocommerce-lost-password-nonce' => 'x'),
    ));

    $this->assertTrue($this->lostpassword_refused());
  }

  /**
   * wp-login.php's lost password form with a solved challenge is accepted.
   *
   * @since 1.30.0
   */
  public function test_wp_lost_password_with_a_valid_token_is_accepted()
  {
    $this->request(array(
      'wordpress' => true,
      'wp_login' => true,
      'post' => array('altcha' => $this->token(time() + 600)),
    ));

    $this->assertFalse($this->lostpassword_refused());
  }

  /**
   * A real WooCommerce reset without a token is refused when WooCommerce
   * reset protection is on.
   *
   * @since 1.30.0
   */
  public function test_woocommerce_lost_password_without_a_token_is_refused()
  {
    $this->request(array('woocommerce' => true, 'post' => array('woocommerce-lost-password-nonce' => 'x')));

    $this->assertTrue($this->lostpassword_refused());
  }

  /**
   * A real WooCommerce reset with WooCommerce's toggle off is not checked.
   *
   * @since 1.30.0
   */
  public function test_woocommerce_lost_password_with_its_toggle_off_is_not_checked()
  {
    $this->request(array('wordpress' => true, 'post' => array('woocommerce-lost-password-nonce' => 'x')));

    $this->assertFalse($this->lostpassword_refused());
  }

  /* ------------------------------------------------- before the password check */

  /**
   * Regression: on wp-login.php the challenge is checked before the password,
   * so a missing token is refused whatever password was typed.
   *
   * @since 1.30.0
   */
  public function test_wp_login_gate_refuses_a_missing_token_before_the_password()
  {
    $this->request(array('wordpress' => true, 'wp_login' => true));

    $this->assertInstanceOf(WP_Error::class, $this->authenticate_user());
  }

  /**
   * The same on WooCommerce's My Account login.
   *
   * @since 1.30.0
   */
  public function test_woocommerce_login_gate_refuses_a_missing_token_before_the_password()
  {
    $this->request(array('woocommerce' => true, 'post' => array('woocommerce-login-nonce' => 'x')));

    $this->assertInstanceOf(WP_Error::class, $this->authenticate_user());
  }

  /**
   * The gate follows the same routing: a forged WooCommerce field on
   * wp-login.php is still checked.
   *
   * @since 1.30.0
   */
  public function test_wp_login_gate_ignores_a_forged_woocommerce_field()
  {
    $this->request(array(
      'wordpress' => true,
      'wp_login' => true,
      'post' => array('woocommerce-login-nonce' => 'x'),
    ));

    $this->assertInstanceOf(WP_Error::class, $this->authenticate_user());
  }

  /**
   * An error from an earlier callback passes through the gate untouched.
   *
   * @since 1.30.0
   */
  public function test_login_gate_passes_an_earlier_error_through()
  {
    $this->request(array('wordpress' => true, 'wp_login' => true));
    $error = new WP_Error('earlier', 'earlier');

    $this->assertSame($error, $this->authenticate_user($error));
  }

  /**
   * A valid token passes the gate, then the `authenticate` backstop, on one
   * request at Replay limit 1: the backstop's second check of the same token
   * is answered by the per-request memo, so the login costs exactly one use.
   * The next request with the same token is refused, which shows that use was
   * really consumed.
   *
   * @since 1.30.0
   */
  public function test_gate_and_backstop_consume_one_use_per_login()
  {
    update_option(OpenPortePlugin::$option_replaylimit, 1);
    $token = $this->token(time() + 600);
    $this->request(array('wordpress' => true, 'wp_login' => true, 'post' => array('altcha' => $token)));

    $this->assertInstanceOf(WP_User::class, $this->authenticate_user());
    $this->assertInstanceOf(WP_User::class, $this->authenticate());

    $this->next_request();
    $this->assertInstanceOf(WP_Error::class, $this->authenticate_user());
  }

  /* -------------------------------------------------------- replay context */

  /**
   * Capture the context the replay-limit filter receives during $run.
   *
   * @since 1.30.0
   *
   * @param callable $run Code that verifies a token.
   * @return string|null The context, or null when the filter never ran.
   */
  private function replay_context(callable $run)
  {
    $captured = null;
    OpenPorte_Test_Env::$filters['openporte_replay_limit'] = function ($limit, $context) use (&$captured) {
      $captured = $context;
      return $limit;
    };
    $run();

    return $captured;
  }

  /**
   * A wp-login.php login names its surface 'wordpress_login', although it now
   * runs on the same hook as a WooCommerce one.
   *
   * @since 1.30.0
   */
  public function test_a_wordpress_login_names_its_surface()
  {
    $this->request(array(
      'wordpress' => true,
      'wp_login' => true,
      'post' => array('altcha' => $this->token(time() + 600)),
    ));

    $this->assertSame('wordpress_login', $this->replay_context(function () {
      $this->authenticate_user();
    }));
  }

  /**
   * A WooCommerce My Account login names its surface 'woocommerce_login'.
   *
   * @since 1.30.0
   */
  public function test_a_woocommerce_login_names_its_surface()
  {
    $this->request(array(
      'woocommerce' => true,
      'post' => array('altcha' => $this->token(time() + 600), 'woocommerce-login-nonce' => 'x'),
    ));

    $this->assertSame('woocommerce_login', $this->replay_context(function () {
      $this->authenticate_user();
    }));
  }

  /**
   * Password-reset requests name their surface too.
   *
   * @since 1.30.0
   */
  public function test_lost_password_requests_name_their_surface()
  {
    $this->request(array(
      'wordpress' => true,
      'wp_login' => true,
      'post' => array('altcha' => $this->token(time() + 600)),
    ));
    $this->assertSame('wordpress_reset_password', $this->replay_context(function () {
      $this->lostpassword_refused();
    }));

    $this->next_request();
    OpenPorte_Test_Env::$actions = array();
    $this->request(array(
      'woocommerce' => true,
      'post' => array('altcha' => $this->token(time() + 600), 'woocommerce-lost-password-nonce' => 'x'),
    ));
    $this->assertSame('woocommerce_reset_password', $this->replay_context(function () {
      $this->lostpassword_refused();
    }));
  }
}
