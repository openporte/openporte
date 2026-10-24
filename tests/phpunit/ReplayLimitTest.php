<?php
/**
 * The reuse counter, end to end through verify(): the limit setting, its
 * filter, the per-request counting, and the result hook on refusals.
 *
 * @package OpenPorte\Tests
 */
class ReplayLimitTest extends OpenPorteTestCase
{
  public function test_limit_of_three_accepts_three_requests_then_refuses()
  {
    OpenPorte_Test_Env::$options['openporte_replaylimit'] = 3;
    $token = $this->token(time() + 600);

    $results = array();
    for ($i = 0; $i < 4; $i++) {
      $results[] = $this->verify_in_new_request($token);
    }

    $this->assertSame(array(true, true, true, false), $results);
    $this->assertSame('3', $this->counter_value($token));
  }

  public function test_limit_of_one_refuses_the_second_request()
  {
    OpenPorte_Test_Env::$options['openporte_replaylimit'] = 1;
    $token = $this->token(time() + 600);

    $this->assertTrue($this->verify_in_new_request($token));
    $this->assertFalse($this->verify_in_new_request($token));
  }

  public function test_limit_of_zero_accepts_every_replay()
  {
    OpenPorte_Test_Env::$options['openporte_replaylimit'] = 0;
    $token = $this->token(time() + 600);

    for ($i = 0; $i < 5; $i++) {
      $this->assertTrue($this->verify_in_new_request($token), 'request ' . ($i + 1));
    }
  }

  public function test_limit_of_zero_writes_no_state()
  {
    OpenPorte_Test_Env::$options['openporte_replaylimit'] = 0;
    $token = $this->token(time() + 600);

    $this->assertTrue($this->verify_in_new_request($token));
    $this->assertSame(array(), OpenPorte_Test_Env::replay_rows());
  }

  public function test_missing_option_uses_the_default_of_five()
  {
    $token = $this->token(time() + 600);

    for ($i = 0; $i < 5; $i++) {
      $this->assertTrue($this->verify_in_new_request($token), 'request ' . ($i + 1));
    }
    $this->assertFalse($this->verify_in_new_request($token));
  }

  public function test_non_numeric_option_uses_the_default_of_five()
  {
    OpenPorte_Test_Env::$options['openporte_replaylimit'] = 'abc';

    $this->assertSame(5, $this->plugin->get_replaylimit());
  }

  public function test_a_negative_stored_value_uses_the_default()
  {
    OpenPorte_Test_Env::$options['openporte_replaylimit'] = -5;

    $this->assertSame(5, $this->plugin->get_replaylimit());
  }

  public function test_a_fractional_stored_value_uses_the_default()
  {
    OpenPorte_Test_Env::$options['openporte_replaylimit'] = 0.5;

    $this->assertSame(5, $this->plugin->get_replaylimit());
  }

  public function test_a_scientific_notation_stored_value_uses_the_default()
  {
    OpenPorte_Test_Env::$options['openporte_replaylimit'] = '1e6';

    $this->assertSame(5, $this->plugin->get_replaylimit());
  }

  public function test_an_oversized_stored_value_is_clamped_to_one_hundred()
  {
    OpenPorte_Test_Env::$options['openporte_replaylimit'] = 250;

    $this->assertSame(100, $this->plugin->get_replaylimit());
  }

  public function test_a_filter_can_lower_the_limit()
  {
    OpenPorte_Test_Env::$filters['openporte_replay_limit'] = function ($limit) {
      return 2;
    };
    $token = $this->token(time() + 600);

    $this->assertTrue($this->verify_in_new_request($token));
    $this->assertTrue($this->verify_in_new_request($token));
    $this->assertFalse($this->verify_in_new_request($token));
  }

  public function test_a_filter_receives_the_current_hook_as_context()
  {
    OpenPorte_Test_Env::$current_filter = 'authenticate';
    $captured = null;
    OpenPorte_Test_Env::$filters['openporte_replay_limit'] = function ($limit, $context) use (&$captured) {
      $captured = $context;
      return $limit;
    };

    $this->plugin->get_replaylimit();

    $this->assertSame('authenticate', $captured);
  }

  /**
   * Naming a surface preserves the hook context existing callbacks receive.
   *
   * @since 1.30.0
   */
  public function test_a_named_surface_preserves_the_hook_context() {
    OpenPorte_Test_Env::$current_filter = 'preprocess_comment';
    $captured = null;
    OpenPorte_Test_Env::$filters['openporte_replay_limit'] = function ( $limit, $context, $surface, $hook ) use ( &$captured ) {
      $captured = array( $context, $surface, $hook );
      return $limit;
    };

    $this->assertTrue( $this->plugin->verify( $this->token( time() + 600 ), null, 'wordpress_comments' ) );

    $this->assertSame( array( 'preprocess_comment', 'wordpress_comments', 'preprocess_comment' ), $captured );
  }

  /**
   * A 1.29 hook-based filter still refuses the first comment replay.
   *
   * @since 1.30.0
   */
  public function test_a_legacy_comment_filter_still_enforces_its_strict_limit() {
    OpenPorte_Test_Env::$current_filter = 'preprocess_comment';
    OpenPorte_Test_Env::$filters['openporte_replay_limit'] = function ( $limit, $context ) {
      return 'preprocess_comment' === $context ? 1 : $limit;
    };
    $token = $this->token( time() + 600 );

    $this->assertTrue( $this->plugin->verify( $token, null, 'wordpress_comments' ) );
    $this->next_request();
    $this->assertFalse( $this->plugin->verify( $token, null, 'wordpress_comments' ) );
  }

  /**
   * Direct callers retain the explicit context supported since 1.29.
   *
   * @since 1.30.0
   */
  public function test_an_explicit_legacy_context_is_preserved() {
    OpenPorte_Test_Env::$current_filter = 'wp_authenticate_user';
    $captured = null;
    OpenPorte_Test_Env::$filters['openporte_replay_limit'] = function ( $limit, $context, $surface, $hook ) use ( &$captured ) {
      $captured = array( $context, $surface, $hook );
      return $limit;
    };

    $this->plugin->get_replaylimit( 'custom_login', 'wordpress_login' );

    $this->assertSame( array( 'custom_login', 'wordpress_login', 'wp_authenticate_user' ), $captured );
  }

  /**
   * A surface does not manufacture a hook context outside a running hook.
   *
   * @since 1.30.0
   */
  public function test_a_surface_outside_a_hook_keeps_the_legacy_context_empty() {
    $captured = null;
    OpenPorte_Test_Env::$filters['openporte_replay_limit'] = function ( $limit, $context, $surface, $hook ) use ( &$captured ) {
      $captured = array( $context, $surface, $hook );
      return $limit;
    };

    $this->plugin->get_replaylimit( '', 'wordpress_comments' );

    $this->assertSame( array( '', 'wordpress_comments', '' ), $captured );
  }

  /**
   * A caller that supplies no surface receives the legacy hook context.
   *
   * @since 1.30.0
   */
  public function test_an_unnamed_surface_preserves_the_hook_context() {
    OpenPorte_Test_Env::$current_filter = 'preprocess_comment';
    $captured = null;
    OpenPorte_Test_Env::$filters['openporte_replay_limit'] = function ( $limit, $context, $surface, $hook ) use ( &$captured ) {
      $captured = array( $context, $surface, $hook );
      return $limit;
    };

    $this->plugin->verify( $this->token( time() + 600 ) );

    $this->assertSame( array( 'preprocess_comment', '', 'preprocess_comment' ), $captured );
  }

  /**
   * An unusable surface is omitted without changing the legacy hook context.
   *
   * @since 1.30.0
   */
  public function test_a_non_string_surface_is_omitted() {
    OpenPorte_Test_Env::$current_filter = 'preprocess_comment';
    $captured = null;
    OpenPorte_Test_Env::$filters['openporte_replay_limit'] = function ( $limit, $context, $surface, $hook ) use ( &$captured ) {
      $captured = array( $context, $surface, $hook );
      return $limit;
    };

    $this->plugin->verify( $this->token( time() + 600 ), null, array( 'wordpress_comments' ) );

    $this->assertSame( array( 'preprocess_comment', '', 'preprocess_comment' ), $captured );
  }

  /**
   * The use case the context exists for: one site, strict on login and
   * lenient on comments, decided per surface by one filter.
   *
   * @since 1.30.0
   */
  public function test_a_site_can_be_strict_on_login_and_lenient_on_comments() {
    OpenPorte_Test_Env::$filters['openporte_replay_limit'] = function ( $limit, $context, $surface ) {
      return 'wordpress_login' === $surface ? 1 : 3;
    };
    $login = $this->token( time() + 600 );
    $comment = $this->token( time() + 600 );

    $this->assertTrue( $this->plugin->verify( $login, null, 'wordpress_login' ) );
    $this->next_request();
    $this->assertFalse( $this->plugin->verify( $login, null, 'wordpress_login' ) );

    for ( $i = 0; $i < 3; $i++ ) {
      $this->next_request();
      $this->assertTrue( $this->plugin->verify( $comment, null, 'wordpress_comments' ), 'use ' . ( $i + 1 ) );
    }
    $this->next_request();
    $this->assertFalse( $this->plugin->verify( $comment, null, 'wordpress_comments' ) );
  }

  /**
   * The counter is per token, not per surface: a token already used on a
   * lenient surface counts against a strict one. Otherwise a strict login
   * limit could be dodged by spending the token elsewhere first.
   *
   * @since 1.30.0
   */
  public function test_the_counter_is_shared_across_surfaces() {
    OpenPorte_Test_Env::$filters['openporte_replay_limit'] = function ( $limit, $context, $surface ) {
      return 'wordpress_login' === $surface ? 1 : 5;
    };
    $token = $this->token( time() + 600 );

    $this->assertTrue( $this->plugin->verify( $token, null, 'wordpress_comments' ) );
    $this->next_request();
    $this->assertFalse( $this->plugin->verify( $token, null, 'wordpress_login' ) );
  }

  public function test_a_filter_returning_a_non_numeric_value_falls_back_to_the_stored_limit()
  {
    OpenPorte_Test_Env::$options['openporte_replaylimit'] = 7;
    OpenPorte_Test_Env::$filters['openporte_replay_limit'] = function ($limit) {
      return 'abc';
    };

    $this->assertSame(7, $this->plugin->get_replaylimit());
  }

  public function test_a_filter_returning_a_negative_value_falls_back_to_the_stored_limit()
  {
    OpenPorte_Test_Env::$options['openporte_replaylimit'] = 7;
    OpenPorte_Test_Env::$filters['openporte_replay_limit'] = function ($limit) {
      return -3;
    };

    $this->assertSame(7, $this->plugin->get_replaylimit());
  }

  public function test_a_filter_returning_a_fraction_falls_back_to_the_stored_limit()
  {
    OpenPorte_Test_Env::$options['openporte_replaylimit'] = 7;
    OpenPorte_Test_Env::$filters['openporte_replay_limit'] = function ($limit) {
      return 0.5;
    };

    $this->assertSame(7, $this->plugin->get_replaylimit());
  }

  public function test_a_filter_returning_scientific_notation_falls_back_to_the_stored_limit()
  {
    OpenPorte_Test_Env::$options['openporte_replaylimit'] = 7;
    OpenPorte_Test_Env::$filters['openporte_replay_limit'] = function ($limit) {
      return '1e6';
    };

    $this->assertSame(7, $this->plugin->get_replaylimit());
  }

  public function test_an_oversized_filter_result_is_clamped_to_one_hundred()
  {
    OpenPorte_Test_Env::$filters['openporte_replay_limit'] = function ($limit) {
      return 250;
    };

    $this->assertSame(100, $this->plugin->get_replaylimit());
  }

  public function test_a_refused_replay_still_fires_the_result_hook_with_false()
  {
    OpenPorte_Test_Env::$options['openporte_replaylimit'] = 1;
    $token = $this->token(time() + 600);

    $this->assertTrue($this->verify_in_new_request($token));
    $this->assertFalse($this->verify_in_new_request($token));

    $actions = OpenPorte_Test_Env::actions('openporte_verify_result');
    $this->assertSame(2, count($actions));
    $this->assertFalse(end($actions)[1]);
  }

  public function test_a_server_signature_token_is_counted_too()
  {
    OpenPorte_Test_Env::$options['openporte_replaylimit'] = 1;
    $token = $this->server_token(time() + 600);

    $this->assertTrue($this->verify_in_new_request($token));
    $this->assertFalse($this->verify_in_new_request($token));
  }
}
