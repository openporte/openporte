<?php
/**
 * The crypto gate and the deprecations: expired, junk, forged and tampered
 * tokens are refused without writing any state, and the raw primitives warn
 * third-party callers that bypass verify().
 *
 * @package OpenPorte\Tests
 */
class VerifyPrimitivesTest extends OpenPorteTestCase
{
  public function test_an_expired_token_is_refused()
  {
    $this->assertFalse($this->plugin->verify($this->token(time() - 10)));
  }

  public function test_an_expired_token_writes_no_state()
  {
    $this->assertFalse($this->plugin->verify($this->token(time() - 10)));

    $this->assertSame(array(), OpenPorte_Test_Env::replay_rows());
  }

  public function test_a_junk_token_is_refused_and_writes_no_state()
  {
    $this->assertFalse($this->plugin->verify(base64_encode('junk junk junk')));

    $this->assertSame(array(), OpenPorte_Test_Env::replay_rows());
  }

  public function test_an_empty_payload_is_refused()
  {
    $this->assertFalse($this->plugin->verify(''));
  }

  public function test_a_tampered_signature_is_refused_and_writes_no_state()
  {
    $token = $this->token(time() + 600);
    $payload = $this->decode($token);
    $payload['signature'] = str_repeat('0', strlen($payload['signature']));
    $tampered = $this->encode($payload);

    $this->assertFalse($this->plugin->verify($tampered));
    $this->assertSame(array(), OpenPorte_Test_Env::replay_rows());
  }

  public function test_a_token_signed_with_the_wrong_algorithm_is_refused()
  {
    $this->assertFalse($this->plugin->verify($this->token(time() + 600, 'SHA-512')));
  }

  public function test_editing_the_salt_expiry_breaks_the_challenge()
  {
    // Regression guard for CVE-2025-68113: `expires` lives inside the salt,
    // the challenge hashes the salt, and the signature covers the challenge —
    // so a forged future expiry must fail verification, not extend the
    // token's life.
    $token = $this->token(time() - 10);
    $payload = $this->decode($token);
    $payload['salt'] = preg_replace('/expires=\d+/', 'expires=' . (time() + 3600), $payload['salt']);
    $forged = $this->encode($payload);

    $this->assertFalse($this->plugin->verify($forged));
  }

  public function test_generated_challenge_salt_ends_with_a_delimiter()
  {
    $challenge = $this->plugin->generate_challenge(self::SECRET, 'low', 300);

    $this->assertSame('&', substr($challenge['salt'], -1));
  }

  public function test_the_delimiter_blocks_expiry_splicing()
  {
    // Without a delimiter, moving the leading digit from the secret number to
    // the expiry preserves the exact bytes covered by the challenge digest.
    $vulnerable_salt = 'S?expires=1700000000';
    $forged_salt = 'S?expires=17000000004';
    $this->assertSame(
      hash('sha256', $vulnerable_salt . 42),
      hash('sha256', $forged_salt . 2)
    );

    // The generated trailing ampersand makes that rearrangement change the
    // digest, so the existing signature can no longer validate the forgery.
    $this->assertNotSame(
      hash('sha256', $vulnerable_salt . '&' . 42),
      hash('sha256', $forged_salt . 2)
    );
  }

  public function test_appending_a_parameter_to_the_salt_breaks_the_challenge()
  {
    // Once a token is signed, extending its salt with another parameter must
    // change its challenge digest and invalidate it.
    $token = $this->token(time() + 600);
    $payload = $this->decode($token);
    $payload['salt'] .= 'expires=' . (time() + 9999);
    $forged = $this->encode($payload);

    $this->assertFalse($this->plugin->verify($forged));
  }

  public function test_verify_emits_no_deprecation()
  {
    $this->assertTrue($this->plugin->verify($this->token(time() + 600)));

    $this->assertSame(array(), OpenPorte_Test_Env::$deprecations);
  }

  public function test_calling_verify_solution_directly_emits_a_deprecation()
  {
    $this->plugin->verify_solution($this->token(time() + 600));

    $this->assertContains('OpenPortePlugin::verify_solution', OpenPorte_Test_Env::$deprecations);
  }

  public function test_calling_verify_server_signature_directly_emits_a_deprecation()
  {
    $this->plugin->verify_server_signature($this->server_token(time() + 600));

    $this->assertContains('OpenPortePlugin::verify_server_signature', OpenPorte_Test_Env::$deprecations);
  }

  public function test_the_server_signature_path_refuses_an_expired_payload()
  {
    $this->assertFalse($this->plugin->verify($this->server_token(time() - 10)));
  }

  /**
   * A signed payload without an expiry would stay acceptable forever.
   *
   * @since 1.30.1
   */
  public function test_the_server_signature_path_refuses_a_payload_with_no_expiry()
  {
    $this->assertFalse($this->plugin->verify($this->server_token(null)));
    $this->assertSame(array(), OpenPorte_Test_Env::replay_rows());
  }

  /**
   * Only an explicit `verified` of "true" or "1" is accepted.
   *
   * @since 1.30.1
   *
   * @dataProvider server_verified_values
   *
   * @param mixed $verified The `verified` value; null omits the field.
   * @param bool  $accepted Expected outcome.
   */
  public function test_the_server_signature_path_requires_an_explicit_verified($verified, $accepted)
  {
    $this->assertSame($accepted, $this->plugin->verify($this->server_token(time() + 600, $verified)));
  }

  /** @return array<string,array{0:mixed,1:bool}> */
  public function server_verified_values()
  {
    return array(
      'true' => array('true', true),
      '1' => array('1', true),
      'omitted' => array(null, false),
      'empty' => array('', false),
      'false' => array('false', false),
      '0' => array('0', false),
      'no' => array('no', false),
      'yes' => array('yes', false),
      'TRUE (not the reference spelling)' => array('TRUE', false),
      'array' => array(array('true'), false),
    );
  }

  /**
   * A non-numeric expire reads as no expiry, and is refused.
   *
   * @since 1.30.1
   */
  public function test_the_server_signature_path_refuses_a_non_numeric_expiry()
  {
    $this->assertFalse($this->plugin->verify($this->server_token('soon')));
  }

  /**
   * The proof-of-work horizon applies here too: just inside is accepted, just
   * past is refused and writes no replay state.
   *
   * @since 1.30.1
   */
  public function test_the_server_signature_path_refuses_an_expiry_beyond_the_horizon()
  {
    $beyond = $this->server_token(time() + OpenPortePlugin::$expires_horizon + 60);
    $inside = $this->server_token(time() + OpenPortePlugin::$expires_horizon - 60);

    $this->assertFalse($this->plugin->verify($beyond));
    $this->assertSame(array(), OpenPorte_Test_Env::replay_rows());
    $this->next_request();
    $this->assertTrue($this->plugin->verify($inside));
  }

  public function test_the_server_signature_path_refuses_object_verification_data()
  {
    $token = $this->encode(array(
      'algorithm' => 'SHA-256',
      'verificationData' => array('verified' => true),
      'signature' => str_repeat('0', 64),
    ));

    $this->assertFalse($this->plugin->verify($token));
  }

  /**
   * Every payload field the primitives feed to hash(), parse_str() or
   * hash_equals() must be refused when it is not a string.
   *
   * JSON lets a submitter send any of them as an array, and hash_equals()
   * raises a TypeError on one — a fatal error on an unauthenticated form POST,
   * which is exactly what security-audit.md finding #3 exists to prevent.
   * decode_payload() only guarantees an object, so the guard has to be here.
   *
   * @dataProvider non_string_payload_fields
   *
   * @param array $payload Token payload with one field of the wrong type.
   */
  public function test_a_non_string_payload_field_is_refused_not_fatal(array $payload)
  {
    $this->assertFalse($this->plugin->verify($this->encode($payload)));
  }

  /** @return array<string,array<int,array>> */
  public function non_string_payload_fields()
  {
    $pow = array(
      'algorithm' => 'SHA-256',
      'challenge' => str_repeat('0', 64),
      'number' => 1,
      'salt' => 'abc&',
      'signature' => str_repeat('0', 64),
    );
    $server = array(
      'algorithm' => 'SHA-256',
      'verificationData' => 'verified=true',
      'signature' => str_repeat('0', 64),
    );

    return array(
      'proof-of-work: algorithm' => array(array('algorithm' => array('SHA-256')) + $pow),
      'proof-of-work: challenge' => array(array('challenge' => array('x')) + $pow),
      'proof-of-work: salt' => array(array('salt' => array('abc&')) + $pow),
      'proof-of-work: number' => array(array('number' => array(1)) + $pow),
      'proof-of-work: signature' => array(array('signature' => array('x')) + $pow),
      'server signature: algorithm' => array(array('algorithm' => array('SHA-256')) + $server),
      'server signature: verificationData' => array(array('verificationData' => array('v' => 1)) + $server),
      'server signature: signature' => array(array('signature' => array('x')) + $server),
    );
  }

  public function test_salt_expires_reads_the_embedded_expiry()
  {
    $this->assertSame(1234567890, OpenPortePlugin::salt_expires('abc?expires=1234567890&'));
  }

  public function test_salt_expires_returns_zero_without_one()
  {
    $this->assertSame(0, OpenPortePlugin::salt_expires('abc&'));
    $this->assertSame(0, OpenPortePlugin::salt_expires(null));
  }

  /**
   * Sign an arbitrary salt/number pair the way the server would, so a test
   * can isolate the `number` type check from the digest and HMAC checks.
   *
   * @since 1.30.0
   *
   * @param string $salt      Challenge salt.
   * @param mixed  $number    Solution number, of any JSON type.
   * @param string $algorithm ALTCHA algorithm label.
   * @return string base64(JSON) payload.
   */
  private function signed($salt, $number, $algorithm = 'SHA-256')
  {
    $ident = OpenPortePlugin::hash_ident($algorithm);
    $challenge = hash($ident, $salt . $number);

    return $this->encode(array(
      'algorithm' => $algorithm,
      'challenge' => $challenge,
      'number' => $number,
      'salt' => $salt,
      'signature' => hash_hmac($ident, $challenge, self::SECRET),
    ));
  }

  /**
   * The widget's JSON integer and a digit-only string are both accepted.
   *
   * @since 1.30.0
   */
  public function test_a_plain_integer_number_is_accepted()
  {
    $salt = bin2hex(random_bytes(12)) . '?expires=' . (time() + 600) . '&';

    $this->assertTrue($this->plugin->verify($this->signed($salt, 12345)));
    $this->next_request();
    $this->assertTrue($this->plugin->verify($this->signed($salt, '54321')));
  }

  /**
   * Anything but a plain non-negative integer is refused, even when it is
   * correctly signed, and writes no replay state.
   *
   * @since 1.30.0
   *
   * @dataProvider non_integer_numbers
   *
   * @param mixed $number Solution number of an unaccepted shape.
   */
  public function test_a_non_integer_number_is_refused_even_when_signed($number)
  {
    $salt = bin2hex(random_bytes(12)) . '?expires=' . (time() + 600) . '&';

    $this->assertFalse($this->plugin->verify($this->signed($salt, $number)));
    $this->assertSame(array(), OpenPorte_Test_Env::replay_rows());
  }

  /** @return array<string,array<int,mixed>> */
  public function non_integer_numbers()
  {
    return array(
      'negative int' => array(-1),
      'float' => array(1.5),
      'boolean' => array(true),
      'empty string' => array(''),
      'negative string' => array('-1'),
      'signed string' => array('+1'),
      'leading space' => array(' 1'),
      'trailing space' => array('1 '),
      'decimal string' => array('1.0'),
      'exponent string' => array('1e3'),
      'hex string' => array('0x1F'),
      'letters' => array('abc'),
      'digits then letters' => array('12a'),
      'query characters' => array('?x=1&2'),
    );
  }

  /**
   * A JSON number with a fraction or an exponent decodes to a float, even
   * when its value is integral, and is refused. json_encode() writes 1000.0
   * as 1000, so these payloads are spelled out as raw JSON.
   *
   * @since 1.30.0
   */
  public function test_an_integral_json_float_is_refused()
  {
    $salt = bin2hex(random_bytes(12)) . '?expires=' . (time() + 600) . '&';
    $challenge = hash('sha256', $salt . '1000');
    $signature = hash_hmac('sha256', $challenge, self::SECRET);

    foreach (array('1000.0', '1e3') as $literal) {
      $this->next_request();
      $json = '{"algorithm":"SHA-256","challenge":"' . $challenge . '","number":' . $literal
        . ',"salt":"' . $salt . '","signature":"' . $signature . '"}';

      $this->assertFalse($this->plugin->verify(base64_encode($json)), $literal);
    }
  }

  /**
   * Regression: the signed bytes of an expired token cannot be re-split
   * between salt and number so that the salt no longer carries the expiry.
   * Covered for every allowed algorithm; nothing is written to replay state.
   *
   * @since 1.30.0
   */
  public function test_moving_the_expiry_out_of_the_salt_is_refused()
  {
    foreach (OpenPortePlugin::get_allowed_algorithms() as $algorithm) {
      update_option(OpenPortePlugin::$option_algorithm, $algorithm);
      $this->next_request();
      $random = bin2hex(random_bytes(32));
      $query = '?expires=' . (time() - 10) . '&';
      $number = 12345;

      $this->assertFalse(
        $this->plugin->verify($this->signed($random . $query, $number, $algorithm)),
        $algorithm . ': the expired original must be refused'
      );
      $this->assertFalse(
        $this->plugin->verify($this->signed($random, $query . $number, $algorithm)),
        $algorithm . ': the re-split token must be refused'
      );
    }

    $this->assertSame(array(), OpenPorte_Test_Env::replay_rows());
  }

  /**
   * Regression: a Custom-mode backend whose salts lack the trailing "&" still
   * has its genuine tokens accepted, but digits moved from the number onto
   * the salt's expiry put it past the horizon, so the re-split token is
   * refused, expired or not. Covered for every allowed algorithm.
   *
   * @since 1.30.0
   */
  public function test_an_unterminated_salt_cannot_be_given_a_later_expiry()
  {
    foreach (OpenPortePlugin::get_allowed_algorithms() as $algorithm) {
      update_option(OpenPortePlugin::$option_algorithm, $algorithm);
      foreach (array(time() - 10, time() + 600) as $expires) {
        $this->next_request();
        $salt = bin2hex(random_bytes(32)) . '?expires=' . $expires;
        $label = $algorithm . ', expires ' . ($expires < time() ? 'past' : 'future');

        $this->assertSame(
          $expires > time(),
          $this->plugin->verify($this->signed($salt, 12345, $algorithm)),
          $label . ': the original must be judged on its own expiry'
        );
        $this->assertFalse(
          $this->plugin->verify($this->signed($salt . '1', '2345', $algorithm)),
          $label . ': the re-split token must be refused'
        );
      }
    }
  }

  /**
   * The horizon itself: an expiry just inside it is accepted, one just past
   * it is refused and writes no replay state.
   *
   * @since 1.30.0
   */
  public function test_an_expiry_beyond_the_horizon_is_refused()
  {
    $random = bin2hex(random_bytes(12));
    $inside = $random . '?expires=' . (time() + OpenPortePlugin::$expires_horizon - 60) . '&';
    $beyond = $random . '?expires=' . (time() + OpenPortePlugin::$expires_horizon + 60) . '&';

    $this->assertFalse($this->plugin->verify($this->signed($beyond, 12345)));
    $this->assertSame(array(), OpenPorte_Test_Env::replay_rows());
    $this->next_request();
    $this->assertTrue($this->plugin->verify($this->signed($inside, 12345)));
  }

  /**
   * A valid proof-of-work token padded with an ignored field to exactly
   * `$length` bytes once encoded.
   *
   * @since 1.30.1
   *
   * @param int $length Encoded length; a multiple of 4, as base64 is.
   * @return string base64(JSON) payload.
   */
  private function token_of_length($length)
  {
    $payload = $this->decode($this->token(time() + 600));
    $payload['pad'] = '';
    // base64 turns every 3 bytes of JSON into 4 characters.
    $payload['pad'] = str_repeat('x', $length / 4 * 3 - strlen(json_encode($payload)));
    $token = $this->encode($payload);
    $this->assertSame($length, strlen($token));

    return $token;
  }

  /**
   * The size limit itself: a valid token of exactly the limit is accepted.
   *
   * @since 1.30.1
   */
  public function test_a_token_at_the_size_limit_is_accepted()
  {
    $this->assertTrue($this->plugin->verify($this->token_of_length(OpenPortePlugin::$payload_max_bytes)));
  }

  /**
   * One step over the limit, a valid token is refused and writes no state.
   * The step is 4 bytes: a base64 token one byte longer could not be valid.
   *
   * @since 1.30.1
   */
  public function test_a_token_over_the_size_limit_is_refused_and_writes_no_state()
  {
    $token = $this->token_of_length(OpenPortePlugin::$payload_max_bytes + 4);

    $this->assertFalse($this->plugin->verify($token));
    $this->assertSame(array(), OpenPorte_Test_Env::replay_rows());
    $this->assertCount(1, OpenPorte_Test_Env::actions('openporte_verify_result'));
  }

  /**
   * The deprecated primitives, called directly, enforce the limit too.
   *
   * @since 1.30.1
   */
  public function test_the_primitives_refuse_a_token_over_the_size_limit()
  {
    $this->assertFalse($this->plugin->verify_solution(
      $this->token_of_length(OpenPortePlugin::$payload_max_bytes + 4)
    ));
  }
}
