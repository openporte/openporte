<?php
/**
 * Self-hosted challenge transport (issue #100): the normalized getter, the
 * URL selection in get_challengeurl(), the always-registered admin-ajax
 * actions, the admin-ajax endpoint itself, and both endpoints' page-cache
 * opt-out (issue #133).
 *
 * What these tests cannot prove — real HTTP dispatch, the Cache-Control
 * header on the wire, cookies and a security plugin blocking a transport —
 * still needs the wp-env bench acceptance tests planned in
 * local/plans/plan-issue100.md, section 4. Those tests are not implemented yet.
 *
 * @package OpenPorte\Tests
 * @since 1.30.0
 */

/**
 * Verify transport selection, filter compatibility and Ajax response shape.
 *
 * @since 1.30.0
 */
class ChallengeTransportTest extends OpenPorteTestCase
{
  /* ------------------------------------------------------------------ getter */

  /**
   * Both allowlisted values are returned unchanged.
   *
   * The baseline for the fallback tests below: if this fails, a fallback
   * test passing on 'rest' would prove nothing.
   *
   * @since 1.30.0
   */
  public function test_valid_stored_values_pass_through()
  {
    update_option(OpenPortePlugin::$option_selfhosted_transport, 'rest');
    $this->assertSame('rest', $this->plugin->get_selfhosted_transport());
    update_option(OpenPortePlugin::$option_selfhosted_transport, 'ajax');
    $this->assertSame('ajax', $this->plugin->get_selfhosted_transport());
  }

  /**
   * A missing option row reads as REST.
   *
   * This is the in-place update path: a plugin update never runs the
   * activation hook, so the option is never seeded and the site must keep
   * serving challenges on its historical REST URL.
   *
   * @since 1.30.0
   */
  public function test_absent_option_falls_back_to_rest()
  {
    $this->assertSame('rest', $this->plugin->get_selfhosted_transport());
  }

  /**
   * Any stored value off the allowlist reads as REST.
   *
   * Covers a corrupted or hand-edited option row. Nothing may be cast on the
   * way: (string) on an array would fabricate "Array", and loose comparison
   * would let true or 0 match an allowlist entry.
   *
   * @since 1.30.0
   */
  public function test_invalid_stored_values_fall_back_to_rest()
  {
    $invalid = array(
      '',             // empty string
      'REST',         // case variants are not allowlist values
      ' ajax ',       // whitespace variants are not allowlist values
      'graphql',      // unknown string
      true,           // booleans
      false,
      0,              // integers
      1,
      null,
      array('rest'),  // arrays refuse; (string) would fabricate "Array"
      array(),
      new stdClass(),
    );
    foreach ($invalid as $value) {
      update_option(OpenPortePlugin::$option_selfhosted_transport, $value);
      $this->assertSame(
        'rest',
        $this->plugin->get_selfhosted_transport(),
        'The value ' . var_export($value, true) . ' must fall back to REST.'
      );
    }
  }

  /**
   * The shared normalizer returns null, not a fallback, for invalid input.
   *
   * The getter and the settings sanitizer both build on it and choose their
   * own fallback, so the normalizer itself must only say "not usable" — it
   * must never pick a transport on their behalf.
   *
   * @since 1.30.0
   */
  public function test_normalizer_refuses_everything_off_allowlist()
  {
    $this->assertSame('rest', OpenPortePlugin::normalize_selfhosted_transport('rest'));
    $this->assertSame('ajax', OpenPortePlugin::normalize_selfhosted_transport('ajax'));
    $invalid = array(
      '',
      'REST',
      ' ajax ',
      'graphql',
      true,
      false,
      0,
      null,
      array('rest'),
      new stdClass(),
    );
    foreach ($invalid as $value) {
      $this->assertNull(
        OpenPortePlugin::normalize_selfhosted_transport($value),
        var_export($value, true) . ' is not an allowlist value.'
      );
    }
  }

  /* -------------------------------------------------------- URL selection */

  /**
   * In Self-hosted mode the widget URL follows the selected transport.
   *
   * REST keeps the exact historical URL; Ajax points at admin-ajax.php with
   * the action the endpoint is registered under.
   *
   * @since 1.30.0
   */
  public function test_selfhosted_url_follows_the_selected_transport()
  {
    update_option(OpenPortePlugin::$option_api, 'selfhosted');
    update_option(OpenPortePlugin::$option_selfhosted_transport, 'rest');
    $this->assertSame(
      'https://example.test/wp-json/openporte/v1/challenge',
      $this->plugin->get_challengeurl()
    );
    update_option(OpenPortePlugin::$option_selfhosted_transport, 'ajax');
    $this->assertSame(
      'https://example.test/wp-admin/admin-ajax.php?action=openporte_challenge',
      $this->plugin->get_challengeurl()
    );
  }

  /**
   * An existing site that never stored a transport renders the REST URL.
   *
   * The end-to-end form of test_absent_option_falls_back_to_rest(): what an
   * updated site actually emits in the widget's challengeurl attribute.
   *
   * @since 1.30.0
   */
  public function test_absent_transport_renders_the_rest_url()
  {
    update_option(OpenPortePlugin::$option_api, 'selfhosted');
    $this->assertSame(
      'https://example.test/wp-json/openporte/v1/challenge',
      $this->plugin->get_challengeurl()
    );
  }

  /**
   * Custom mode uses the stored backend URL, whatever the transport.
   *
   * The transport chooses how this site serves challenges; it must never
   * rewrite an operator's own endpoint, even when a latent Ajax value is
   * stored from an earlier Self-hosted configuration.
   *
   * @since 1.30.0
   */
  public function test_custom_mode_ignores_the_transport()
  {
    update_option(OpenPortePlugin::$option_api, 'custom');
    update_option(OpenPortePlugin::$option_api_custom_url, 'https://altcha.example/v1/challenge');
    update_option(OpenPortePlugin::$option_selfhosted_transport, 'ajax');
    $this->assertSame(
      'https://altcha.example/v1/challenge',
      $this->plugin->get_challengeurl()
    );
  }

  /**
   * An unknown API mode is treated as Self-hosted and follows the transport.
   *
   * Legacy ALTCHA stored other mode values; get_challengeurl() has always
   * sent everything except 'custom' to the self-hosted branch, and the
   * transport must apply there too.
   *
   * @since 1.30.0
   */
  public function test_unknown_api_mode_still_takes_the_selfhosted_branch()
  {
    update_option(OpenPortePlugin::$option_api, 'legacy-value');
    update_option(OpenPortePlugin::$option_selfhosted_transport, 'ajax');
    $this->assertSame(
      'https://example.test/wp-admin/admin-ajax.php?action=openporte_challenge',
      $this->plugin->get_challengeurl()
    );
  }

  /**
   * The URL shim rejects URLs it cannot modify faithfully.
   *
   * Reject fragments and existing action keys instead of silently building an
   * unusable URL or leaving duplicate action parameters in a request.
   *
   * @since 1.30.0
   *
   * @dataProvider unsupported_query_urls
   *
   * @param string $url Unsupported URL passed to the shim.
   */
  public function test_url_shim_rejects_unsupported_urls( $url ) {
    $this->expectException( RuntimeException::class );
    add_query_arg( 'action', 'openporte_challenge', $url );
  }

  /**
   * URLs that need fragment handling or query-key replacement.
   *
   * @since 1.30.0
   *
   * @return array<string, array<string>> Named URL cases.
   */
  public static function unsupported_query_urls() {
    return array(
      'fragment'       => array( 'https://example.test/admin-ajax.php#section' ),
      'existing key'   => array( 'https://example.test/admin-ajax.php?action=old' ),
      'encoded key'    => array( 'https://example.test/admin-ajax.php?%61ction=old' ),
    );
  }

  /**
   * The openporte_challenge_url filter receives the transport URL and wins.
   *
   * Sites that already applied the issue #100 workaround through this filter
   * must keep their override after updating.
   *
   * @since 1.30.0
   */
  public function test_challenge_url_filter_still_overrides_the_transport_url()
  {
    update_option(OpenPortePlugin::$option_selfhosted_transport, 'ajax');
    $seen = null;
    OpenPorte_Test_Env::$filters['openporte_challenge_url'] = function ($url) use (&$seen) {
      $seen = $url;
      return 'https://filtered.example/challenge';
    };
    $this->assertSame('https://filtered.example/challenge', $this->plugin->get_challengeurl());
    $this->assertSame(
      'https://example.test/wp-admin/admin-ajax.php?action=openporte_challenge',
      $seen
    );
  }

  /**
   * The deprecated altcha_challenge_url filter still runs, after the new one.
   *
   * Order is part of the back-compat contract: the legacy filter receives
   * the openporte_challenge_url result, so it keeps the last word for code
   * written against the original ALTCHA plugin, and its use is reported as
   * deprecated.
   *
   * @since 1.30.0
   */
  public function test_deprecated_challenge_url_filter_runs_last()
  {
    update_option(OpenPortePlugin::$option_selfhosted_transport, 'ajax');
    OpenPorte_Test_Env::$filters['openporte_challenge_url'] = function ($url) {
      return $url . '&first=openporte';
    };
    $seen = null;
    OpenPorte_Test_Env::$filters['altcha_challenge_url'] = function ($url) use (&$seen) {
      $seen = $url;
      return 'https://legacy.example/challenge';
    };

    $this->assertSame('https://legacy.example/challenge', $this->plugin->get_challengeurl());
    $this->assertSame(
      'https://example.test/wp-admin/admin-ajax.php?action=openporte_challenge&first=openporte',
      $seen
    );
    $this->assertContains('altcha_challenge_url', OpenPorte_Test_Env::$deprecations);
  }

  /* ------------------------------------------------------- ajax endpoint */

  /**
   * Both admin-ajax actions are registered at plugin load, to one handler.
   *
   * wp_ajax_nopriv_ serves logged-out visitors — the case issue #100 is
   * about — and wp_ajax_ serves logged-in ones; missing either breaks the
   * widget for that audience only. Registration is unconditional, so it is
   * checked against what the plugin registered while loading, whatever the
   * selected transport.
   *
   * @since 1.30.0
   */
  public function test_both_ajax_actions_register_at_load()
  {
    $expected = 'openporte_generate_challenge_ajax_endpoint';
    $registered = array();
    foreach (OpenPorte_Test_Env::$registered_actions as $registration) {
      $tag = $registration[0];
      if ('wp_ajax_' . OpenPortePlugin::$ajax_action === $tag
        || 'wp_ajax_nopriv_' . OpenPortePlugin::$ajax_action === $tag) {
        $registered[$tag] = $registration[1];
      }
    }
    $this->assertSame(
      array(
        'wp_ajax_openporte_challenge' => $expected,
        'wp_ajax_nopriv_openporte_challenge' => $expected,
      ),
      $registered
    );
  }

  /**
   * The Ajax endpoint sends the flat five-key challenge and ends the request.
   *
   * The widget parses the bare challenge object, so the response must not be
   * wrapped the way wp_send_json_success() would wrap it ({success, data}).
   * The shim's wp_send_json() throws OpenPorte_JsonSent where core would
   * die(), which also proves the handler terminates.
   *
   * @since 1.30.0
   */
  public function test_ajax_endpoint_sends_the_flat_challenge_json_and_terminates()
  {
    $sent = null;
    $unexpected = array();
    // The handler calls header() before wp_send_json(). In this CLI process
    // PHPUnit has already written output, so PHP warns that headers were
    // already sent; that one warning is environmental and is ignored. Any
    // other error is collected and fails the test — a blanket handler would
    // hide a real warning from generate_challenge(). Checking the header
    // on the wire remains pending bench acceptance work (plan section 4).
    set_error_handler(static function (int $errno, string $errstr) use (&$unexpected) {
      if (E_WARNING === $errno && false !== strpos($errstr, 'Cannot modify header information')) {
        return true;
      }
      $unexpected[] = $errstr;
      return true;
    });
    try {
      openporte_generate_challenge_ajax_endpoint();
      $this->fail('The endpoint must terminate the request through wp_send_json().');
    } catch (OpenPorte_JsonSent $e) {
      $sent = OpenPorte_Test_Env::$sent_json;
    } finally {
      restore_error_handler();
    }

    $this->assertSame(array(), $unexpected, 'The endpoint raised an unexpected PHP error.');
    $this->assertIsArray($sent);
    $this->assertSame(
      array('algorithm', 'challenge', 'maxnumber', 'salt', 'signature'),
      array_keys($sent)
    );
    $this->assertContains($sent['algorithm'], OpenPortePlugin::get_allowed_algorithms());
    $this->assertNotSame('', $sent['challenge']);
    $this->assertNotSame('', $sent['salt']);
  }

  /* ------------------------------------------------------- Custom API mode */

  /**
   * Call the Ajax endpoint and return what it sent, ignoring only the
   * environmental "headers already sent" warning (see the test above).
   *
   * @since 1.30.0
   *
   * @return mixed The payload passed to wp_send_json().
   */
  private function call_ajax_endpoint()
  {
    set_error_handler(static function (int $errno, string $errstr) {
      return E_WARNING === $errno && false !== strpos($errstr, 'Cannot modify header information');
    });
    try {
      openporte_generate_challenge_ajax_endpoint();
      $this->fail('The endpoint must terminate the request through wp_send_json().');
    } catch (OpenPorte_JsonSent $e) {
      return OpenPorte_Test_Env::$sent_json;
    } finally {
      restore_error_handler();
    }
  }

  /**
   * In Custom API mode the REST endpoint refuses with a 403 error instead of
   * a signed challenge: the backend issues them, with the same secret.
   *
   * @since 1.30.0
   */
  public function test_rest_endpoint_refuses_in_custom_mode()
  {
    update_option(OpenPortePlugin::$option_api, 'custom');

    $response = openporte_generate_challenge_endpoint();

    $this->assertInstanceOf(WP_Error::class, $response);
    $this->assertSame('openporte_challenge_unavailable', $response->get_error_code());
    $this->assertSame(array('status' => 403), $response->get_error_data());
  }

  /**
   * In Custom API mode the Ajax endpoint refuses with the same error shape
   * and a 403, and sends no challenge fields.
   *
   * @since 1.30.0
   */
  public function test_ajax_endpoint_refuses_in_custom_mode()
  {
    update_option(OpenPortePlugin::$option_api, 'custom');

    $sent = $this->call_ajax_endpoint();

    $this->assertSame(403, OpenPorte_Test_Env::$sent_json_status);
    $this->assertSame('openporte_challenge_unavailable', $sent['code']);
    $this->assertSame(array('status' => 403), $sent['data']);
    $this->assertArrayNotHasKey('signature', $sent);
  }

  /**
   * Any other stored mode, including a legacy value, still issues
   * challenges: only Custom refuses.
   *
   * @since 1.30.0
   */
  public function test_ajax_endpoint_still_issues_outside_custom_mode()
  {
    update_option(OpenPortePlugin::$option_api, 'eu');

    $sent = $this->call_ajax_endpoint();

    $this->assertNull(OpenPorte_Test_Env::$sent_json_status);
    $this->assertArrayHasKey('signature', $sent);
  }

  /* --------------------------------------------------------- page caches */

  /**
   * Assert that the current request told page-caching plugins not to store
   * its response, through both signals (issue #133).
   *
   * DONOTCACHEPAGE cannot be undefined between tests, so once one test has
   * defined it the constant check alone proves nothing for later tests; the
   * LiteSpeed action is logged per test and carries the per-call proof.
   *
   * @since 1.30.1
   *
   * @param string $context What was called, for the failure message.
   */
  private function assertPageCachingRefused($context)
  {
    $this->assertCount(
      1,
      OpenPorte_Test_Env::actions('litespeed_control_set_nocache'),
      $context . ' must tell LiteSpeed Cache not to cache it, exactly once.'
    );
    $this->assertTrue(
      defined('DONOTCACHEPAGE') && DONOTCACHEPAGE,
      $context . ' must define DONOTCACHEPAGE.'
    );
  }

  /**
   * The REST endpoint opts its challenge out of page caches and keeps its
   * Cache-Control header.
   *
   * LiteSpeed Cache caches REST responses by default and ignores
   * Cache-Control, so it served one challenge to every visitor until long
   * after it expired, and no login went through (#133).
   *
   * @since 1.30.1
   */
  public function test_rest_endpoint_opts_out_of_page_caches()
  {
    $response = openporte_generate_challenge_endpoint();

    $this->assertInstanceOf(WP_REST_Response::class, $response);
    $this->assertArrayHasKey('signature', $response->get_data());
    $this->assertSame(
      array('Cache-Control' => 'no-cache, no-store, max-age=0'),
      $response->get_headers()
    );
    $this->assertPageCachingRefused('The REST challenge endpoint');
  }

  /**
   * The Ajax endpoint opts its challenge out of page caches too.
   *
   * LiteSpeed Cache can be set to cache chosen admin-ajax actions; both
   * transports must follow one policy whatever a site configures.
   *
   * @since 1.30.1
   */
  public function test_ajax_endpoint_opts_out_of_page_caches()
  {
    $sent = $this->call_ajax_endpoint();

    $this->assertArrayHasKey('signature', $sent);
    $this->assertPageCachingRefused('The Ajax challenge endpoint');
  }

  /**
   * Both Custom-mode refusals opt out of page caches as well.
   *
   * A cached 403 would keep refusing after the site switches back to
   * Self-hosted mode.
   *
   * @since 1.30.1
   */
  public function test_custom_mode_refusals_opt_out_of_page_caches()
  {
    update_option(OpenPortePlugin::$option_api, 'custom');

    $this->assertInstanceOf(WP_Error::class, openporte_generate_challenge_endpoint());
    $this->assertPageCachingRefused('The REST refusal');

    OpenPorte_Test_Env::$actions = array();
    $this->call_ajax_endpoint();
    $this->assertSame(403, OpenPorte_Test_Env::$sent_json_status);
    $this->assertPageCachingRefused('The Ajax refusal');
  }
}
