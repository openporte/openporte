<?php
/**
 * CoBlocks integration: the reCAPTCHA spoof acts only while OpenPorte's
 * CoBlocks integration is on, so a site using CoBlocks' own reCAPTCHA keeps it
 * when the integration is off.
 *
 * What these tests cannot prove — CoBlocks' real call order and its HTTP call
 * to Google — needs an installed CoBlocks release on the wp-env bench.
 *
 * @package OpenPorte\Tests
 * @since 1.30.0
 */

if (!class_exists('CoBlocks_Form')) {
  /**
   * Minimal stand-in for CoBlocks' form class: only the verify-URL constant
   * the integration compares against.
   *
   * @since 1.30.0
   */
  class CoBlocks_Form
  {
    const GCAPTCHA_VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';
  }
}

/**
 * Verify that each step of the spoof respects the integration toggle.
 *
 * @since 1.30.0
 */
class CoblocksIntegrationTest extends OpenPorteTestCase
{
  /**
   * Load the integration once, with CoBlocks reported active: the file only
   * defines its class when openporte_plugin_active('coblocks') is true.
   *
   * @since 1.30.0
   */
  public static function setUpBeforeClass(): void
  {
    parent::setUpBeforeClass();
    require_once dirname(__DIR__, 2) . '/includes/helpers.php';
    OpenPorte_Test_Env::$active_plugins = array('coblocks/class-coblocks.php');
    require_once dirname(__DIR__, 2) . '/integrations/coblocks.php';
  }

  protected function tearDown(): void
  {
    $_POST = array();
    parent::tearDown();
  }

  /**
   * How many times before_form_submit() was queued on CoBlocks' action.
   *
   * @since 1.30.0
   *
   * @return int
   */
  private function queued_submit_hooks()
  {
    return count(array_filter(OpenPorte_Test_Env::$registered_actions, function ($call) {
      return $call[0] === 'coblocks_before_form_submit';
    }));
  }

  /**
   * Call verify() with the request CoBlocks would send for our dummy token.
   *
   * @since 1.30.0
   *
   * @param mixed $response Short-circuit value passed in by pre_http_request.
   * @return mixed
   */
  private function intercept($response = false)
  {
    return OpenPortePlugin_Coblocks::verify(
      $response,
      array('body' => array('response' => 'openporte_dummy_token')),
      CoBlocks_Form::GCAPTCHA_VERIFY_URL
    );
  }

  /**
   * Off: a CoBlocks submission queues nothing.
   *
   * Only the off path goes through render_block_data(): its once-per-request
   * static flag cannot be reset between tests, and only the armed path sets it.
   *
   * @since 1.30.0
   */
  public function test_off_render_block_data_queues_nothing()
  {
    $_POST = array('action' => 'coblocks-form-submit');
    $before = $this->queued_submit_hooks();

    OpenPortePlugin_Coblocks::render_block_data(array('blockName' => 'coblocks/form'), array());

    $this->assertSame($before, $this->queued_submit_hooks());
  }

  /**
   * Off: the site's own reCAPTCHA token is left as submitted.
   *
   * @since 1.30.0
   */
  public function test_off_before_form_submit_leaves_the_token_alone()
  {
    $_POST = array('g-recaptcha-token' => 'real-token');

    OpenPortePlugin_Coblocks::before_form_submit(array(), array());

    $this->assertSame('real-token', $_POST['g-recaptcha-token']);
  }

  /**
   * Off: verify() hands CoBlocks its own request back.
   *
   * @since 1.30.0
   */
  public function test_off_verify_returns_the_response_unchanged()
  {
    $this->assertFalse($this->intercept(false));
  }

  /**
   * On: a submission without a solved challenge fails the reCAPTCHA check.
   *
   * @since 1.30.0
   */
  public function test_on_a_missing_token_fails()
  {
    update_option(OpenPortePlugin::$option_integration_coblocks, 1);

    $this->assertSame('{"success":false}', $this->intercept()['body']);
  }

  /**
   * On: a forged token fails the reCAPTCHA check.
   *
   * @since 1.30.0
   */
  public function test_on_a_forged_token_fails()
  {
    update_option(OpenPortePlugin::$option_integration_coblocks, 1);
    $_POST = array('altcha' => base64_encode('not a token'));

    $this->assertSame('{"success":false}', $this->intercept()['body']);
  }

  /**
   * On: a solved challenge passes the reCAPTCHA check.
   *
   * @since 1.30.0
   */
  public function test_on_a_valid_token_passes()
  {
    update_option(OpenPortePlugin::$option_integration_coblocks, 1);
    $_POST = array('altcha' => $this->token(time() + 600));

    $this->assertSame('{"success":true}', $this->intercept()['body']);
  }

  /**
   * On: the dummy token is planted for CoBlocks to send.
   *
   * @since 1.30.0
   */
  public function test_on_before_form_submit_plants_the_dummy_token()
  {
    update_option(OpenPortePlugin::$option_integration_coblocks, 1);
    $_POST = array('g-recaptcha-token' => 'real-token');

    OpenPortePlugin_Coblocks::before_form_submit(array(), array());

    $this->assertSame('openporte_dummy_token', $_POST['g-recaptcha-token']);
  }
}
