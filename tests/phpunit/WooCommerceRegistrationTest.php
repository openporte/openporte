<?php
/**
 * WooCommerce My Account registration: the challenge check and the replay
 * policy context it presents.
 *
 * What these tests cannot prove — that WooCommerce applies the filter only on
 * that form, so checkout and programmatic account creation are left alone —
 * needs the wp-env bench (tests/e2e/woocommerce-checkout.spec.js).
 *
 * @package OpenPorte\Tests
 * @since 1.30.2
 */

/**
 * Verify the WooCommerce registration callback.
 *
 * @since 1.30.2
 */
class WooCommerceRegistrationTest extends OpenPorteTestCase
{
  /**
   * Load the integration once; its callback is a named function.
   *
   * @since 1.30.2
   */
  public static function setUpBeforeClass(): void
  {
    parent::setUpBeforeClass();
    require_once dirname(__DIR__, 2) . '/includes/helpers.php';
    require_once dirname(__DIR__, 2) . '/integrations/woocommerce.php';
  }

  protected function tearDown(): void
  {
    $_POST = array();
    parent::tearDown();
  }

  /**
   * Run the callback for a request and say whether it refused.
   *
   * @since 1.30.2
   *
   * @param bool  $active Whether WooCommerce registration protection is on.
   * @param array $post   The $_POST payload.
   * @return bool Whether the registration was refused.
   */
  private function refused($active, array $post = array())
  {
    update_option(OpenPortePlugin::$option_integration_woocommerce_register, $active ? 1 : 0);
    $_POST = $post;
    $errors = openporte_woocommerce_process_registration_errors(new WP_Error());

    return in_array('openporte_error_message', $errors->get_error_codes(), true);
  }

  /**
   * A registration without a token is refused.
   *
   * @since 1.30.2
   */
  public function test_missing_token_is_refused()
  {
    $this->assertTrue($this->refused(true));
  }

  /**
   * A malformed token is refused.
   *
   * @since 1.30.2
   */
  public function test_invalid_token_is_refused()
  {
    $this->assertTrue($this->refused(true, array('openporte_register' => 'not-a-token')));
  }

  /**
   * A solved challenge is accepted.
   *
   * @since 1.30.2
   */
  public function test_solved_challenge_is_accepted()
  {
    $this->assertFalse($this->refused(true, array('openporte_register' => $this->token(time() + 600))));
  }

  /**
   * Nothing is required when the protection is off.
   *
   * @since 1.30.2
   */
  public function test_protection_off_requires_nothing()
  {
    $this->assertFalse($this->refused(false));
  }

  /**
   * Replay policies written for the previous hook keep matching.
   *
   * @since 1.30.2
   */
  public function test_replay_policy_keeps_the_legacy_context()
  {
    $seen = null;
    OpenPorte_Test_Env::$filters['openporte_replay_limit'] = function ($limit, $context, $surface, $hook) use (&$seen) {
      $seen = array($context, $surface, $hook);

      return $limit;
    };
    OpenPorte_Test_Env::$current_filter = 'woocommerce_process_registration_errors';

    $this->plugin->get_replaylimit('', 'woocommerce_register');

    $this->assertSame(
      array('woocommerce_register_post', 'woocommerce_register', 'woocommerce_process_registration_errors'),
      $seen
    );
  }
}
