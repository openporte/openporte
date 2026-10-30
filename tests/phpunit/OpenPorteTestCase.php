<?php
/**
 * Shared base class: a pristine fake WordPress per test, plus the token
 * builders the replay tests need.
 *
 * @package OpenPorte\Tests
 */

use PHPUnit\Framework\TestCase;

abstract class OpenPorteTestCase extends TestCase
{
  /** The signing secret every helper here signs with. */
  const SECRET = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

  /** @var OpenPortePlugin */
  protected $plugin;

  protected function setUp(): void
  {
    parent::setUp();
    OpenPorte_Test_Env::reset(array(
      'openporte_secret' => self::SECRET,
      'openporte_algorithm' => 'SHA-256',
    ));
    // One instance for the whole test: reset_request_state() is what separates
    // one simulated request from the next, exactly as `init` does in WordPress.
    $this->plugin = new OpenPortePlugin();
    $this->plugin->init();
  }

  /** Start a new simulated request: drops the per-request verification memo. */
  protected function next_request()
  {
    $this->plugin->reset_request_state();
  }

  /**
   * Build a valid proof-of-work token, the way the widget submits one.
   *
   * @param int|null $expires_at Absolute expiry to embed in the salt; null for
   *                             a token that carries no expiry at all.
   * @param string   $algorithm  ALTCHA algorithm label.
   * @param string   $secret     Signing secret.
   * @return string base64(JSON) payload.
   */
  protected function token($expires_at = null, $algorithm = 'SHA-256', $secret = self::SECRET)
  {
    $salt = bin2hex(random_bytes(12));
    if ($expires_at !== null) {
      $salt .= '?expires=' . $expires_at;
    }
    if (substr($salt, -1) !== '&') {
      $salt .= '&';
    }
    $number = random_int(1, 100000);
    $ident = strtolower(str_replace('-', '', $algorithm));
    $challenge = hash($ident, $salt . $number);

    return $this->encode(array(
      'algorithm' => $algorithm,
      'challenge' => $challenge,
      'number' => $number,
      'salt' => $salt,
      'signature' => hash_hmac($ident, $challenge, $secret),
    ));
  }

  /**
   * Build a validly signed server-signature (ALTCHA Sentinel) token.
   *
   * @param int|null   $expire_at Absolute expiry, or null to omit the field.
   * @param mixed|null $verified  The `verified` value, or null to omit it.
   * @return string base64(JSON) payload.
   */
  protected function server_token($expire_at = null, $verified = 'true')
  {
    $fields = array();
    if ($verified !== null) {
      $fields['verified'] = $verified;
    }
    if ($expire_at !== null) {
      $fields['expire'] = $expire_at;
    }
    $verification_data = http_build_query($fields);
    $hash = hash('sha256', $verification_data, true);

    return $this->encode(array(
      'algorithm' => 'SHA-256',
      'verificationData' => $verification_data,
      'signature' => hash_hmac('sha256', $hash, self::SECRET),
    ));
  }

  /** base64(JSON) a payload array. */
  protected function encode(array $payload)
  {
    return base64_encode(json_encode($payload));
  }

  /** Decode a payload back to an array. */
  protected function decode($token)
  {
    return json_decode(base64_decode($token), true);
  }

  /**
   * Re-encode a token's JSON envelope so the bytes differ while the solved
   * challenge — and therefore the verified signature — stays identical.
   */
  protected function reencode($token)
  {
    return $this->encode(array_reverse($this->decode($token), true));
  }

  /** The counter key OpenPorte derives from a token's signature. */
  protected function counter_key($token)
  {
    $payload = $this->decode($token);
    return OpenPortePlugin::$replay_key_prefix . substr(hash('sha256', $payload['signature']), 0, 32);
  }

  /** Current value of a token's counter row, or null when there is none. */
  protected function counter_value($token)
  {
    return get_option('_transient_' . $this->counter_key($token), null);
  }

  /** Expiry timestamp of a token's counter row, or null when there is none. */
  protected function counter_timeout($token)
  {
    $timeout = get_option('_transient_timeout_' . $this->counter_key($token), null);
    return $timeout === null ? null : intval($timeout);
  }

  /** Verify $token as its own request; returns verify()'s result. */
  protected function verify_in_new_request($token)
  {
    $this->next_request();
    return $this->plugin->verify($token);
  }
}
