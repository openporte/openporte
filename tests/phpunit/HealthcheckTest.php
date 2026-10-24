<?php
/**
 * The settings-page health checks in admin/healthcheck.php: expiry advisories,
 * the reuse-counter status (including fail-open records), and the custom
 * endpoint evaluation.
 *
 * @package OpenPorte\Tests
 */
class HealthcheckTest extends OpenPorteTestCase
{
  public function test_an_expires_of_zero_reports_an_error()
  {
    $result = openporte_evaluate_expires_setting(0);

    $this->assertSame('error', $result['level']);
  }

  public function test_an_expires_below_sixty_reports_a_warning()
  {
    $result = openporte_evaluate_expires_setting(30);

    $this->assertSame('warning', $result['level']);
  }

  public function test_a_sane_expires_reports_nothing()
  {
    $this->assertNull(openporte_evaluate_expires_setting(300));
  }

  public function test_replay_protection_reports_success_when_active()
  {
    $result = openporte_evaluate_replay_protection();

    $this->assertSame('success', $result['level']);
    $this->assertStringContainsString('5', $result['message']);
  }

  public function test_replay_protection_warns_when_the_limit_is_zero()
  {
    OpenPorte_Test_Env::$options['openporte_replaylimit'] = 0;

    $result = openporte_evaluate_replay_protection();

    $this->assertSame('warning', $result['level']);
    $this->assertStringContainsString('Replay limit is 0', $result['message']);
  }

  public function test_replay_protection_warns_after_a_recent_fail_open()
  {
    OpenPorte_Test_Env::$options['openporte_replay_health'] = array('count' => 4, 'last' => time() - 60);

    $result = openporte_evaluate_replay_protection();

    $this->assertSame('warning', $result['level']);
    $this->assertStringContainsString('accepted without being counted', $result['message']);
  }

  public function test_replay_protection_ignores_a_stale_fail_open_record()
  {
    OpenPorte_Test_Env::$options['openporte_replay_health'] = array('count' => 4, 'last' => time() - (2 * DAY_IN_SECONDS));

    $result = openporte_evaluate_replay_protection();

    $this->assertSame('success', $result['level']);
  }

  public function test_replay_protection_names_the_object_cache_when_present()
  {
    OpenPorte_Test_Env::$external_object_cache = true;

    $result = openporte_evaluate_replay_protection();

    $this->assertSame('success', $result['level']);
    $this->assertStringContainsString('persistent object cache', $result['message']);
  }

  public function test_the_endpoint_check_succeeds_when_the_backend_sets_an_expiry()
  {
    $response = $this->endpoint_response('abcd?expires=' . (time() + 1200) . '&', self::SECRET);

    $result = openporte_evaluate_challenge_response($response, self::SECRET, 'SHA-256');

    $this->assertSame('success', $result['level']);
  }

  public function test_the_endpoint_check_warns_when_the_backend_omits_an_expiry()
  {
    $response = $this->endpoint_response('abcd&', self::SECRET);

    $result = openporte_evaluate_challenge_response($response, self::SECRET, 'SHA-256');

    $this->assertSame('warning', $result['level']);
    $this->assertStringContainsString('no expiry', $result['message']);
  }

  public function test_the_endpoint_check_distinguishes_an_expired_challenge()
  {
    $response = $this->endpoint_response('abcd?expires=' . (time() - 60) . '&', self::SECRET);

    $result = openporte_evaluate_challenge_response($response, self::SECRET, 'SHA-256');

    $this->assertSame('warning', $result['level']);
    $this->assertStringContainsString('already in the past', $result['message']);
    $this->assertStringNotContainsString('no expiry', $result['message']);
  }

  public function test_the_endpoint_check_warns_on_a_very_short_backend_expiry()
  {
    $response = $this->endpoint_response('abcd?expires=' . (time() + 20) . '&', self::SECRET);

    $result = openporte_evaluate_challenge_response($response, self::SECRET, 'SHA-256');

    $this->assertSame('warning', $result['level']);
    $this->assertStringContainsString('short enough to expire', $result['message']);
  }

  /**
   * A salt without the trailing "&" still verifies, so it is a warning that
   * names the risk, not an error.
   *
   * @since 1.30.0
   */
  public function test_the_endpoint_check_warns_on_an_unterminated_salt()
  {
    $response = $this->endpoint_response('abcd?expires=' . (time() + 1200), self::SECRET);

    $result = openporte_evaluate_challenge_response($response, self::SECRET, 'SHA-256');

    $this->assertSame('warning', $result['level']);
    $this->assertStringContainsString('valid SHA-256 challenge', $result['message']);
    $this->assertStringContainsString('CVE-2025-68113', $result['message']);
    $this->assertStringContainsString('Check the backend for an update', $result['message']);
  }

  /**
   * Both warnings are reported when both apply, rather than the first hiding
   * the second.
   *
   * @since 1.30.0
   */
  public function test_the_endpoint_check_reports_a_short_expiry_and_an_unterminated_salt()
  {
    $response = $this->endpoint_response('abcd?expires=' . (time() + 20), self::SECRET);

    $result = openporte_evaluate_challenge_response($response, self::SECRET, 'SHA-256');

    $this->assertSame('warning', $result['level']);
    $this->assertStringContainsString('short enough to expire', $result['message']);
    $this->assertStringContainsString('CVE-2025-68113', $result['message']);
  }

  /**
   * An expiry past the verifier's horizon, typically a millisecond timestamp,
   * would make every submission fail, so it is an error.
   *
   * @since 1.30.0
   */
  public function test_the_endpoint_check_reports_an_expiry_beyond_the_horizon()
  {
    $response = $this->endpoint_response('abcd?expires=' . (time() * 1000) . '&', self::SECRET);

    $result = openporte_evaluate_challenge_response($response, self::SECRET, 'SHA-256');

    $this->assertSame('error', $result['level']);
    $this->assertStringContainsString('milliseconds', $result['message']);
  }

  public function test_the_endpoint_check_still_reports_a_secret_mismatch()
  {
    $response = $this->endpoint_response('abcd?expires=' . (time() + 1200) . '&', str_repeat('b', 64));

    $result = openporte_evaluate_challenge_response($response, self::SECRET, 'SHA-256');

    $this->assertSame('error', $result['level']);
    $this->assertStringContainsString('secret', $result['message']);
  }

  /**
   * Build a canned challenge-endpoint response for the given salt and secret.
   *
   * @param string $salt   Challenge salt as the backend would serve it.
   * @param string $secret HMAC secret the backend signs with.
   * @return array wp_remote_get() shaped response.
   */
  private function endpoint_response($salt, $secret)
  {
    $number = 12345;
    $challenge = hash('sha256', $salt . $number);

    return array(
      'response' => array('code' => 200),
      'body' => json_encode(array(
        'algorithm' => 'SHA-256',
        'challenge' => $challenge,
        'salt' => $salt,
        'signature' => hash_hmac('sha256', $challenge, $secret),
      )),
    );
  }
}
