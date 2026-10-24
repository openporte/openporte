<?php
/**
 * Where and how long the counter lives: transient row lifetime, the object
 * cache backend, and the fail-open behaviour of a broken store.
 *
 * @package OpenPorte\Tests
 */
class ReplayStorageTest extends OpenPorteTestCase
{
  public function test_the_counter_lifetime_tracks_the_token_expiry()
  {
    $expires_at = time() + 20000;
    $token = $this->token($expires_at);

    $this->assertTrue($this->verify_in_new_request($token));

    $timeout = $this->counter_timeout($token);
    $this->assertNotNull($timeout);
    $this->assertLessThanOrEqual($expires_at + 2, $timeout);
    $this->assertGreaterThanOrEqual($expires_at - 2, $timeout);
    // No ceiling: a long-lived token must not be clamped to the 4-hour fallback.
    $this->assertGreaterThan(14400 + time(), $timeout);
  }

  public function test_a_near_expired_token_gets_a_one_minute_floor()
  {
    $token = $this->token(time() + 5);

    $this->assertTrue($this->verify_in_new_request($token));

    $timeout = $this->counter_timeout($token);
    $this->assertNotNull($timeout);
    $this->assertLessThanOrEqual(time() + 62, $timeout);
    $this->assertGreaterThanOrEqual(time() + 58, $timeout);
  }

  public function test_a_token_with_no_expiry_falls_back_to_four_hours()
  {
    $token = $this->token(null);

    $this->assertTrue($this->verify_in_new_request($token));

    $timeout = $this->counter_timeout($token);
    $this->assertNotNull($timeout);
    $this->assertLessThanOrEqual(time() + 14402, $timeout);
    $this->assertGreaterThanOrEqual(time() + 14398, $timeout);
  }

  public function test_the_counter_rows_are_created_as_a_pair()
  {
    $token = $this->token(time() + 600);

    $this->assertTrue($this->verify_in_new_request($token));

    $key = $this->counter_key($token);
    $this->assertArrayHasKey('_transient_' . $key, OpenPorte_Test_Env::$options);
    $this->assertArrayHasKey('_transient_timeout_' . $key, OpenPorte_Test_Env::$options);
  }

  public function test_a_token_with_no_expiry_gets_a_fresh_budget_after_its_window()
  {
    OpenPorte_Test_Env::$options['openporte_replaylimit'] = 2;
    $token = $this->token(null);

    $this->assertTrue($this->verify_in_new_request($token));
    $this->assertTrue($this->verify_in_new_request($token));
    $this->assertFalse($this->verify_in_new_request($token));

    // Expire the counter's window: core's lazy sweep drops the pair, so the
    // token starts from a fresh budget.
    OpenPorte_Test_Env::$options['_transient_timeout_' . $this->counter_key($token)] = time() - 1;

    $this->assertTrue($this->verify_in_new_request($token));
    $this->assertSame('1', $this->counter_value($token));
  }

  public function test_the_object_cache_path_enforces_the_limit()
  {
    OpenPorte_Test_Env::$external_object_cache = true;
    OpenPorte_Test_Env::$options['openporte_replaylimit'] = 2;
    $token = $this->token(time() + 600);

    $this->assertTrue($this->verify_in_new_request($token));
    $this->assertTrue($this->verify_in_new_request($token));
    $this->assertFalse($this->verify_in_new_request($token));
  }

  public function test_the_object_cache_counter_is_seeded_as_a_string()
  {
    // String '0', not integer 0: some drop-ins serialize non-strings, which
    // turns INCR into a permanent silent failure — an invisible fail-open.
    OpenPorte_Test_Env::$external_object_cache = true;
    $token = $this->token(time() + 600);

    $this->assertTrue($this->verify_in_new_request($token));

    $this->assertCount(1, OpenPorte_Test_Env::$cache_adds);
    $add = OpenPorte_Test_Env::$cache_adds[0];
    $this->assertSame('0', $add[1]);
    $this->assertSame('openporte_replay', $add[2]);
  }

  public function test_the_object_cache_ttl_is_the_token_lifetime()
  {
    OpenPorte_Test_Env::$external_object_cache = true;
    $token = $this->token(time() + 600);

    $this->assertTrue($this->verify_in_new_request($token));

    $this->assertCount(1, OpenPorte_Test_Env::$cache_adds);
    $this->assertGreaterThanOrEqual(598, OpenPorte_Test_Env::$cache_adds[0][3]);
    $this->assertLessThanOrEqual(602, OpenPorte_Test_Env::$cache_adds[0][3]);
  }

  /**
   * Only a server-signature token can still carry an expiry this far ahead:
   * since 1.30.0 a proof-of-work one is refused past $expires_horizon.
   */
  public function test_a_long_object_cache_ttl_is_capped_at_thirty_days()
  {
    OpenPorte_Test_Env::$external_object_cache = true;
    $token = $this->server_token(time() + (40 * DAY_IN_SECONDS));

    $this->assertTrue($this->verify_in_new_request($token));

    $this->assertCount(1, OpenPorte_Test_Env::$cache_adds);
    $this->assertSame(30 * DAY_IN_SECONDS, OpenPorte_Test_Env::$cache_adds[0][3]);
  }

  public function test_a_numeric_string_from_cache_incr_enforces_the_limit()
  {
    OpenPorte_Test_Env::$external_object_cache = true;
    OpenPorte_Test_Env::$string_cache_incr = true;
    OpenPorte_Test_Env::$options['openporte_replaylimit'] = 1;
    $token = $this->token(time() + 600);

    $this->assertTrue($this->verify_in_new_request($token));
    $this->assertFalse($this->verify_in_new_request($token));
  }

  public function test_a_broken_object_cache_fails_open()
  {
    OpenPorte_Test_Env::$external_object_cache = true;
    OpenPorte_Test_Env::$broken_cache_incr = true;
    OpenPorte_Test_Env::$options['openporte_replaylimit'] = 1;
    $token = $this->token(time() + 600);

    for ($i = 0; $i < 3; $i++) {
      $this->assertTrue($this->verify_in_new_request($token), 'request ' . ($i + 1));
    }
  }

  public function test_a_broken_object_cache_fires_the_store_unavailable_action()
  {
    OpenPorte_Test_Env::$external_object_cache = true;
    OpenPorte_Test_Env::$broken_cache_incr = true;
    OpenPorte_Test_Env::$options['openporte_replaylimit'] = 1;
    $token = $this->token(time() + 600);

    for ($i = 0; $i < 3; $i++) {
      $this->verify_in_new_request($token);
    }

    $this->assertCount(3, OpenPorte_Test_Env::actions('openporte_replay_store_unavailable'));
  }

  public function test_a_broken_object_cache_is_recorded_for_the_settings_page()
  {
    OpenPorte_Test_Env::$external_object_cache = true;
    OpenPorte_Test_Env::$broken_cache_incr = true;
    OpenPorte_Test_Env::$options['openporte_replaylimit'] = 1;
    $token = $this->token(time() + 600);

    for ($i = 0; $i < 3; $i++) {
      $this->verify_in_new_request($token);
    }

    $health = get_option('openporte_replay_health');
    // One record a minute, not one per submission: the settings page needs to
    // know the store is failing now, and an uncounted submission must not cost
    // an option write at a rate the submitter controls.
    $this->assertSame(1, $health['count']);
    $this->assertLessThanOrEqual(time() + 2, $health['last']);
    $this->assertGreaterThanOrEqual(time() - 2, $health['last']);
  }

  public function test_a_fail_open_outside_the_sample_window_is_recorded()
  {
    // The throttle must not swallow a store that keeps failing: an incident
    // more than a minute after the last recorded one still counts.
    OpenPorte_Test_Env::$external_object_cache = true;
    OpenPorte_Test_Env::$broken_cache_incr = true;
    OpenPorte_Test_Env::$options['openporte_replaylimit'] = 1;
    OpenPorte_Test_Env::$options['openporte_replay_health'] = array(
      'count' => 4,
      'last' => time() - 120,
    );

    $this->verify_in_new_request($this->token(time() + 600));

    $health = get_option('openporte_replay_health');
    $this->assertSame(5, $health['count']);
  }

  public function test_a_broken_database_fails_open()
  {
    OpenPorte_Test_Env::$broken_database = true;
    OpenPorte_Test_Env::$options['openporte_replaylimit'] = 1;
    $token = $this->token(time() + 600);

    $this->assertTrue($this->verify_in_new_request($token));
    $this->assertTrue($this->verify_in_new_request($token));

    $this->assertCount(2, OpenPorte_Test_Env::actions('openporte_replay_store_unavailable'));
  }

  public function test_a_missing_timeout_row_fails_open_without_creating_a_value_row()
  {
    OpenPorte_Test_Env::$broken_timeout_add = true;
    OpenPorte_Test_Env::$options['openporte_replaylimit'] = 1;
    $token = $this->token(time() + 600);

    $this->assertTrue($this->verify_in_new_request($token));

    $this->assertNull($this->counter_timeout($token));
    $this->assertNull($this->counter_value($token));
    $this->assertCount(1, OpenPorte_Test_Env::actions('openporte_replay_store_unavailable'));
  }

  public function test_an_existing_timeout_row_allows_the_value_row_to_be_created()
  {
    $token = $this->token(time() + 600);
    OpenPorte_Test_Env::$options['_transient_timeout_' . $this->counter_key($token)] = time() + 600;

    $this->assertTrue($this->verify_in_new_request($token));

    $this->assertSame('1', $this->counter_value($token));
  }
}
