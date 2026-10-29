<?php
/**
 * Gravity Forms field: the challenge is verified only where the form is
 * finally submitted, so a field placed before the last page is refused
 * rather than skipped.
 *
 * What these tests cannot prove — the order in which a real Gravity Forms
 * release validates pages, and how it re-renders earlier pages — needs a
 * Gravity Forms install, which the wp-env bench does not have (paid plugin).
 *
 * @package OpenPorte\Tests
 * @since 1.30.1
 */

if (!class_exists('GF_Field')) {
  /**
   * Minimal stand-in for Gravity Forms' field base class: the properties
   * validate() writes, the page number it reads, and the editor switch.
   *
   * @since 1.30.1
   */
  class GF_Field
  {
    /** @var bool Whether get_field_input() renders for the form editor. */
    public static $in_editor = false;

    /** @var int|string|null Page the field sits on, 1-based. */
    public $pageNumber;

    /** @var bool */
    public $failed_validation = false;

    /** @var string */
    public $validation_message = '';

    public function is_form_editor()
    {
      return self::$in_editor;
    }
  }
}

if (!class_exists('GF_Fields')) {
  /**
   * Minimal stand-in for Gravity Forms' field registry.
   *
   * @since 1.30.1
   */
  class GF_Fields
  {
    public static function register($field) { }
  }
}

if (!class_exists('GFAPI')) {
  /**
   * Minimal stand-in for the Gravity Forms API: fields filtered by type.
   *
   * @since 1.30.1
   */
  class GFAPI
  {
    public static function get_fields_by_type($form, $types)
    {
      return array_values(array_filter($form['fields'], function ($field) use ($types) {
        return in_array($field['type'], $types, true);
      }));
    }
  }
}

/**
 * Verify where validate() checks the challenge, and what it refuses.
 *
 * @since 1.30.1
 */
class GravityFormsFieldTest extends OpenPorteTestCase
{
  /**
   * Load the field class once; its file registers it on load.
   *
   * @since 1.30.1
   */
  public static function setUpBeforeClass(): void
  {
    parent::setUpBeforeClass();
    require_once dirname(__DIR__, 2) . '/integrations/gravityforms/field.php';
  }

  protected function setUp(): void
  {
    parent::setUp();
    OpenPortePlugin::$instance = $this->plugin;
    update_option(OpenPortePlugin::$option_integration_gravityforms, 1);
  }

  protected function tearDown(): void
  {
    $_POST = array();
    GF_Field::$in_editor = false;
    parent::tearDown();
  }

  /**
   * A form with `$breaks` page breaks, i.e. `$breaks + 1` pages.
   *
   * @since 1.30.1
   *
   * @param int $breaks Number of page-break fields.
   * @return array
   */
  private function form($breaks)
  {
    $fields = array(array('type' => 'text'));
    for ($i = 0; $i < $breaks; $i++) {
      $fields[] = array('type' => 'page');
    }
    return array('id' => 1, 'fields' => $fields);
  }

  /**
   * Validate the OpenPorte field on page `$page` against `$form`.
   *
   * @since 1.30.1
   *
   * @param array           $form The form.
   * @param int|string|null $page The field's page number.
   * @return OPENPORTE_GFForms_Field The validated field.
   */
  private function validate($form, $page)
  {
    $field = new OPENPORTE_GFForms_Field();
    $field->pageNumber = $page;
    $field->validate('', $form);

    return $field;
  }

  /**
   * Last page of a multi-page form: a valid token passes.
   *
   * @since 1.30.1
   */
  public function test_a_valid_token_on_the_last_page_passes()
  {
    $_POST['altcha'] = $this->token(time() + 600);

    $this->assertFalse($this->validate($this->form(2), 3)->failed_validation);
  }

  /**
   * Last page: a missing token is refused.
   *
   * @since 1.30.1
   */
  public function test_a_missing_token_on_the_last_page_is_refused()
  {
    $field = $this->validate($this->form(2), 3);

    $this->assertTrue($field->failed_validation);
    $this->assertSame('Could not verify you are not a robot.', $field->validation_message);
  }

  /**
   * Earlier page: refused with the placement message, even with a valid
   * token, and without spending one of the token's uses.
   *
   * @since 1.30.1
   */
  public function test_a_field_before_the_last_page_is_refused_without_spending_the_token()
  {
    update_option(OpenPortePlugin::$option_replaylimit, 1);
    $token = $this->token(time() + 600);
    $_POST['altcha'] = $token;

    $field = $this->validate($this->form(2), 1);

    $this->assertTrue($field->failed_validation);
    $this->assertStringContainsString('moved to the last page', $field->validation_message);
    $this->assertSame(array(), OpenPorte_Test_Env::replay_rows());
    $this->next_request();
    $this->assertTrue($this->plugin->verify($token));
  }

  /**
   * Single-page form: verified as before, whatever its stored page number.
   *
   * @since 1.30.1
   */
  public function test_a_single_page_form_verifies_without_a_page_number()
  {
    $_POST['altcha'] = $this->token(time() + 600);

    $this->assertFalse($this->validate($this->form(0), null)->failed_validation);
    $this->next_request();
    $_POST['altcha'] = 'junk';
    $this->assertTrue($this->validate($this->form(0), null)->failed_validation);
  }

  /**
   * Integration off: the field does nothing, wherever it sits.
   *
   * @since 1.30.1
   */
  public function test_the_integration_off_validates_nothing()
  {
    update_option(OpenPortePlugin::$option_integration_gravityforms, 0);

    $this->assertFalse($this->validate($this->form(2), 1)->failed_validation);
    $this->assertFalse($this->validate($this->form(2), 3)->failed_validation);
  }

  /**
   * Form editor: the placeholder warns about a field before the last page,
   * and only then.
   *
   * @since 1.30.1
   */
  public function test_the_editor_placeholder_warns_only_before_the_last_page()
  {
    GF_Field::$in_editor = true;
    $field = new OPENPORTE_GFForms_Field();

    $field->pageNumber = 1;
    $this->assertStringContainsString('moved to the last page', $field->get_field_input($this->form(1)));
    $field->pageNumber = 2;
    $this->assertStringNotContainsString('moved to the last page', $field->get_field_input($this->form(1)));
  }
}
