<?php
/**
 * The settings sanitizers in includes/settings.php: the null (disabled field)
 * guard, the clamp bounds, the companion Custom input, and the advisories.
 *
 * @package OpenPorte\Tests
 */
class SettingsSanitizerTest extends OpenPorteTestCase
{
  public function test_a_null_expires_preserves_the_stored_value()
  {
    // G1 regression guard: the browser omits the disabled Expiration field in
    // Custom mode, then wp-admin/options.php updates the registered setting
    // with null. absint(null) would be 0 — the worst possible replay
    // configuration — so null must keep the stored value.
    OpenPorte_Test_Env::$options['openporte_expires'] = 1800;

    $this->assertSame(1800, openporte_sanitize_expires(null));
  }

  public function test_a_null_expires_returns_an_int_like_every_other_path()
  {
    // The option is stored as a string by WordPress; every other path through
    // the sanitizer returns int, and this one used to hand back the raw value.
    OpenPorte_Test_Env::$options['openporte_expires'] = '1800';

    $this->assertSame(1800, openporte_sanitize_expires(null));
  }

  public function test_the_expires_advisory_thresholds_are_classified_once()
  {
    // One classifier feeds both surfaces — the save-time _doing_it_wrong()
    // advisory and the settings-screen notice — so the thresholds cannot drift
    // apart, and #103 changes them in one place.
    $this->assertSame('error', openporte_expires_advisory_level(0));
    $this->assertSame('warning', openporte_expires_advisory_level(30));
    $this->assertSame('', openporte_expires_advisory_level(60));
    $this->assertSame('', openporte_expires_advisory_level(300));
  }

  public function test_a_null_expires_does_not_warn()
  {
    OpenPorte_Test_Env::$options['openporte_expires'] = 1800;

    openporte_sanitize_expires(null);

    $this->assertSame(array(), OpenPorte_Test_Env::$deprecations);
  }

  public function test_expires_is_clamped_to_the_maximum()
  {
    $this->assertSame(14400, openporte_sanitize_expires(99999));
  }

  public function test_an_expires_of_zero_warns()
  {
    $this->assertSame(0, openporte_sanitize_expires(0));

    $this->assertContains('openporte_expires', OpenPorte_Test_Env::$deprecations);
  }

  public function test_an_expires_below_sixty_warns()
  {
    $this->assertSame(30, openporte_sanitize_expires(30));

    $this->assertContains('openporte_expires', OpenPorte_Test_Env::$deprecations);
  }

  public function test_an_expires_of_sixty_does_not_warn()
  {
    $this->assertSame(60, openporte_sanitize_expires(60));

    $this->assertSame(array(), OpenPorte_Test_Env::$deprecations);
  }

  public function test_expires_reads_the_companion_input_when_custom()
  {
    $_POST = array('openporte_expires_custom' => '90');

    $this->assertSame(90, openporte_sanitize_expires('custom'));
  }

  public function test_a_custom_expires_without_its_companion_preserves_the_stored_value()
  {
    // A hand-crafted options.php POST: absint('custom') would be 0, i.e.
    // "never expires".
    $_POST = array();
    OpenPorte_Test_Env::$options['openporte_expires'] = 1800;

    $this->assertSame(1800, openporte_sanitize_expires('custom'));
  }

  public function test_a_null_replaylimit_preserves_the_stored_value()
  {
    OpenPorte_Test_Env::$options['openporte_replaylimit'] = 7;

    $this->assertSame(7, openporte_sanitize_replaylimit(null));
  }

  public function test_a_negative_replaylimit_becomes_zero_not_its_absolute_value()
  {
    // intval() on purpose, not absint(): a mistyped "-5" must become 0, which
    // the admin sees is wrong, never 5, which silently re-enables protection.
    $this->assertSame(0, openporte_sanitize_replaylimit(-5));
  }

  public function test_replaylimit_is_clamped_to_one_hundred()
  {
    $this->assertSame(100, openporte_sanitize_replaylimit(250));
  }

  public function test_a_replaylimit_of_zero_is_preserved()
  {
    $this->assertSame(0, openporte_sanitize_replaylimit(0));
  }

  public function test_replaylimit_reads_the_companion_input_when_custom()
  {
    $_POST = array('openporte_replaylimit_custom' => '3');

    $this->assertSame(3, openporte_sanitize_replaylimit('custom'));
  }

  public function test_a_custom_replaylimit_without_its_companion_preserves_the_stored_value()
  {
    // intval('custom') would be 0, i.e. replay protection silently off.
    $_POST = array();
    OpenPorte_Test_Env::$options['openporte_replaylimit'] = 7;

    $this->assertSame(7, openporte_sanitize_replaylimit('custom'));
  }

  /**
   * A stored array (database tampering only) is not cast to "Array" with a
   * PHP warning; the guard returns an empty string instead.
   *
   * @since 1.30.0
   */
  public function test_a_null_complexity_with_a_non_scalar_stored_value_returns_empty()
  {
    OpenPorte_Test_Env::$options['openporte_complexity'] = array('high');

    $this->assertSame('', openporte_sanitize_complexity(null));
  }

  public function test_a_null_complexity_preserves_the_stored_value()
  {
    // G1 regression guard: the browser omits the disabled Complexity field in
    // Custom mode, then wp-admin/options.php updates the registered setting
    // with null. Without the guard sanitize_text_field(null) would wipe it to
    // '', silently downgrading the stored level to 'low' on the next
    // Self-hosted save. null must keep the stored value.
    OpenPorte_Test_Env::$options['openporte_complexity'] = 'high';

    $this->assertSame('high', openporte_sanitize_complexity(null));
  }

  public function test_a_complexity_value_is_trimmed()
  {
    $this->assertSame('high', openporte_sanitize_complexity('  high  '));
  }

  public function test_an_unknown_complexity_falls_back_to_low()
  {
    $this->assertSame('low', openporte_sanitize_complexity('extreme'));
  }

  public function test_a_complexity_level_added_via_the_matrix_filter_is_accepted()
  {
    OpenPorte_Test_Env::$filters['openporte_complexity_matrix'] = function ($matrix) {
      $matrix['extreme'] = array('min' => 1, 'max' => 2);
      return $matrix;
    };

    $this->assertSame('extreme', openporte_sanitize_complexity('extreme'));
  }

  /* ---------------------------------------------------- self-hosted transport */

  /**
   * Both allowlisted transports are saved unchanged.
   *
   * The baseline for the fallback cases below: if a valid value did not pass
   * through, every "falls back to REST" test would pass vacuously.
   */
  public function test_a_valid_transport_passes_through()
  {
    $this->assertSame('rest', openporte_sanitize_selfhosted_transport('rest'));
    $this->assertSame('ajax', openporte_sanitize_selfhosted_transport('ajax'));
  }

  /**
   * Any off-allowlist submission is saved as REST, never cast.
   *
   * Covers a mistyped or hand-edited form value and the type-confusion cases
   * (string) would mangle: an array becomes "Array", a boolean "1". Neither
   * is a transport, so both must land on the compatibility fallback.
   */
  public function test_an_invalid_transport_falls_back_to_rest()
  {
    $invalid = array(
      '',             // empty string
      'REST',         // case variant is not an allowlist value
      ' ajax ',       // whitespace variant is not an allowlist value
      'graphql',      // unknown string
      true,           // booleans must not become "1"
      false,
      0,              // integers are not transports
      array('rest'),  // arrays must not fabricate "Array"
      new stdClass(),
    );
    foreach ($invalid as $value) {
      $this->assertSame(
        'rest',
        openporte_sanitize_selfhosted_transport($value),
        var_export($value, true) . ' must fall back to REST.'
      );
    }
  }

  /**
   * A null (disabled-field) submission keeps a stored Ajax choice.
   *
   * The field is disabled in Custom mode, so the browser omits it and
   * wp-admin/options.php passes null. Without the guard a Custom-mode save
   * would wipe a deliberate Ajax choice back to REST on every save.
   */
  public function test_a_null_transport_preserves_a_stored_ajax_choice()
  {
    OpenPorte_Test_Env::$options['openporte_selfhosted_transport'] = 'ajax';

    $this->assertSame('ajax', openporte_sanitize_selfhosted_transport(null));
  }

  /**
   * A null submission with nothing stored reads as REST.
   *
   * The in-place-update shape: the option was never seeded, so there is no
   * stored choice to preserve and the compatibility fallback applies.
   */
  public function test_a_null_transport_with_nothing_stored_returns_rest()
  {
    $this->assertSame('rest', openporte_sanitize_selfhosted_transport(null));
  }

  /**
   * A null submission with a corrupt stored value reads as REST.
   *
   * The guard must return the normalized stored value, not the raw row: an
   * array or unknown string in the database is not a transport and cannot be
   * cast back into one. This is what distinguishes the guard from a blind
   * (string) get_option() cast.
   */
  public function test_a_null_transport_with_a_corrupt_stored_value_returns_rest()
  {
    foreach (array(array('ajax'), 'bogus') as $corrupt) {
      OpenPorte_Test_Env::$options['openporte_selfhosted_transport'] = $corrupt;

      $this->assertSame(
        'rest',
        openporte_sanitize_selfhosted_transport(null),
        var_export($corrupt, true) . ' is not a usable stored transport.'
      );
    }
  }
}
