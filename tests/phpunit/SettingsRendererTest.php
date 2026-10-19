<?php
/**
 * Settings renderers in admin/options.php: the accessible password actions
 * and Custom-mode markup that public/admin.js reacts to, including the preset
 * plus Custom-number shape shared by Expiration and Replay limit.
 *
 * @package OpenPorte\Tests
 */
class SettingsRendererTest extends OpenPorteTestCase
{
  /** Password controls must remain labelled, non-submitting, and linked to their hint. */
  public function test_password_actions_have_accessible_names_and_status()
  {
    OpenPorte_Test_Env::$options['openporte_secret'] = 'renderer-test-secret';
    $html = $this->render(function () {
      openporte_settings_password_callback(array(
        'name' => 'openporte_secret',
        'hint' => 'Changes take effect when saved.',
        'display_toggle' => true,
        'display_copy' => true,
        'display_regenerate' => true,
      ));
    });

    $this->assertSame(3, substr_count($html, 'type="button"'));
    $this->assertStringContainsString('aria-label="Show shared secret"', $html);
    $this->assertStringContainsString('aria-label="Copy shared secret"', $html);
    $this->assertStringContainsString('aria-label="Generate new shared secret"', $html);
    $this->assertStringContainsString('aria-describedby="openporte_secret-hint"', $html);
    $this->assertStringContainsString('id="openporte_secret-hint"', $html);
    $this->assertStringContainsString('role="status" aria-live="polite" aria-atomic="true"', $html);
    $this->assertSame(5, substr_count($html, 'focusable="false"'));
    $this->assertStringContainsString('value="renderer-test-secret"', $html);
  }

  /** Optional controls must not appear or stay active for disabled inputs. */
  public function test_password_actions_respect_optional_and_disabled_states()
  {
    $plain = $this->render(function () {
      openporte_settings_password_callback(array('name' => 'openporte_secret'));
    });
    $this->assertStringNotContainsString('<button', $plain);
    $this->assertStringNotContainsString('role="status"', $plain);
    $this->assertStringNotContainsString('aria-describedby', $plain);

    $disabled = $this->render(function () {
      openporte_settings_password_callback(array(
        'name' => 'openporte_secret',
        'disabled' => true,
        'display_toggle' => true,
        'display_copy' => true,
        'display_regenerate' => true,
      ));
    });
    $this->assertSame(4, substr_count($disabled, "disabled='disabled'"));
  }

  /** Run a renderer and capture the HTML it echoes. */
  private function render(callable $renderer)
  {
    ob_start();
    $renderer();
    return (string) ob_get_clean();
  }

  public function test_the_select_renders_a_visible_note_when_disabled()
  {
    $html = $this->render(function () {
      openporte_settings_select_callback(array(
        'name' => 'openporte_complexity',
        'disabled' => true,
        'disabled_note' => 'Disabled in Custom mode: the backend sets the complexity.',
        'options' => array('low' => 'Low', 'medium' => 'Medium', 'high' => 'High'),
      ));
    });

    $this->assertStringContainsString('data-selfhosted-note', $html);
    $this->assertStringContainsString('Disabled in Custom mode', $html);
    // Disabled => the note is visible, so no inline display:none.
    $this->assertStringNotContainsString('data-selfhosted-note style="display:none"', $html);
  }

  public function test_the_select_hides_the_note_while_enabled()
  {
    $html = $this->render(function () {
      openporte_settings_select_callback(array(
        'name' => 'openporte_complexity',
        'disabled' => false,
        'disabled_note' => 'Disabled in Custom mode: the backend sets the complexity.',
        'options' => array('low' => 'Low', 'medium' => 'Medium', 'high' => 'High'),
      ));
    });

    $this->assertStringContainsString('data-selfhosted-note style="display:none"', $html);
  }

  public function test_the_select_omits_the_note_when_none_is_given()
  {
    $html = $this->render(function () {
      openporte_settings_select_callback(array(
        'name' => 'openporte_algorithm',
        'options' => array('SHA-256' => 'SHA-256', 'SHA-512' => 'SHA-512'),
      ));
    });

    $this->assertStringNotContainsString('data-selfhosted-note', $html);
  }

  public function test_a_selfhosted_only_select_is_marked_in_the_served_markup()
  {
    // The attribute has to be in the HTML the server sends, not added by
    // public/admin.js on load: with JavaScript off, the markup is the only
    // thing that says this control is inert in Custom mode.
    $html = $this->render(function () {
      openporte_settings_select_callback(array(
        'name' => 'openporte_complexity',
        'selfhosted_only' => true,
        'options' => array('low' => 'Low', 'high' => 'High'),
      ));
    });

    $this->assertStringContainsString('data-selfhosted-api', $html);
  }

  public function test_a_select_is_not_marked_selfhosted_only_by_default()
  {
    $html = $this->render(function () {
      openporte_settings_select_callback(array(
        'name' => 'openporte_algorithm',
        'options' => array('SHA-256' => 'SHA-256', 'SHA-512' => 'SHA-512'),
      ));
    });

    $this->assertStringNotContainsString('data-selfhosted-api', $html);
  }

  public function test_disabled_expiration_markup_keeps_both_controls_inert()
  {
    OpenPorte_Test_Env::$options['openporte_expires'] = 300;
    $html = $this->render(function () {
      openporte_settings_expires_callback(array(
        'name' => 'openporte_expires',
        'disabled' => true,
        'disabled_note' => 'Disabled in Custom mode: the backend sets the challenge expiry.',
      ));
    });

    $this->assertSame(2, substr_count($html, 'data-selfhosted-api'));
    $this->assertSame(2, substr_count($html, ' disabled'));
    $this->assertStringContainsString('data-selfhosted-note', $html);
    $this->assertStringNotContainsString('data-selfhosted-note style="display:none"', $html);
    $this->assertStringContainsString('min="0" max="14400" step="1"', $html);
  }

  public function test_a_non_preset_expiration_selects_and_shows_custom_input()
  {
    OpenPorte_Test_Env::$options['openporte_expires'] = 777;
    $html = $this->render(function () {
      openporte_settings_expires_callback(array('name' => 'openporte_expires'));
    });

    $this->assertStringContainsString("value=\"custom\"  selected='selected'", $html);
    $this->assertStringContainsString('value="777" data-selfhosted-api', $html);
    $this->assertStringNotContainsString('value="777" data-selfhosted-api style="display:none"', $html);
  }

  public function test_replay_limit_markup_uses_its_own_bounds_and_stays_active()
  {
    OpenPorte_Test_Env::$options['openporte_replaylimit'] = 5;
    $html = $this->render(function () {
      openporte_settings_replaylimit_callback(array('name' => 'openporte_replaylimit'));
    });

    $this->assertStringContainsString('min="0" max="100" step="1"', $html);
    $this->assertStringNotContainsString('data-selfhosted-api', $html);
  }

  public function test_the_replay_limit_field_shows_the_limit_that_is_enforced()
  {
    // A stored value that is not integer-like can only have arrived out of
    // band (WP-CLI, another plugin, a hand-edited row). get_replaylimit()
    // refuses it and falls back to the default, so the field must show the
    // default too: rendering the raw option would select "Unlimited" while
    // verify() went on enforcing 5, and saving that form would make the lie
    // true.
    OpenPorte_Test_Env::$options['openporte_replaylimit'] = '0.5';

    $html = $this->render(function () {
      openporte_settings_replaylimit_callback(array('name' => 'openporte_replaylimit'));
    });

    $this->assertSame(5, $this->plugin->get_replaylimit());
    $this->assertStringContainsString("<option value=\"5\"  selected='selected'", $html);
    $this->assertStringNotContainsString("<option value=\"0\"  selected='selected'", $html);
  }

  public function test_the_replay_limit_field_clamps_an_oversized_stored_value()
  {
    OpenPorte_Test_Env::$options['openporte_replaylimit'] = 250;

    $html = $this->render(function () {
      openporte_settings_replaylimit_callback(array('name' => 'openporte_replaylimit'));
    });

    $this->assertSame(100, $this->plugin->get_replaylimit());
    $this->assertStringContainsString('value="100"', $html);
    $this->assertStringNotContainsString('value="250"', $html);
  }

  public function test_the_preset_renderer_floors_a_negative_option_instead_of_flipping_it()
  {
    // absint() would render a stored -5 as 5. For Expiration that reads as
    // "5 seconds" when generate_challenge() actually treats it as no expiry.
    OpenPorte_Test_Env::$options['openporte_expires'] = -5;

    $html = $this->render(function () {
      openporte_settings_expires_callback(array('name' => 'openporte_expires'));
    });

    $this->assertStringContainsString('value="0"', $html);
    $this->assertStringNotContainsString('value="5"', $html);
  }
}
