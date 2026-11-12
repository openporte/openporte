<?php
/**
 * Contact Form 7 integration: the [openporte]/[altcha] form-tag handler.
 *
 * Contact Form 7 builds a WPCF7_FormTag only from the admin's form template,
 * never from a submission, so the handler maps the tag's options and values
 * to the shared renderer. The language comes from the CF7-native
 * `language:de` option, or from the shortcode-style `language="de"` written
 * before 1.30.3, which CF7 scans as the option `language=` plus the value
 * `de`.
 *
 * What these tests cannot prove — CF7's real tag scanner, and that visitor
 * input no longer runs shortcodes — is covered on the wp-env bench
 * (tests/e2e/shortcode-injection.spec.js).
 *
 * @package OpenPorte\Tests
 * @since 1.30.3
 */

if (!class_exists('WPCF7_FormTag')) {
  /**
   * Minimal stand-in for Contact Form 7's scanned form-tag: only the two
   * public properties the handler reads.
   *
   * @since 1.30.3
   */
  class WPCF7_FormTag
  {
    /** @var array<int,string> Bare tokens, e.g. "language:de". */
    public $options = array();

    /** @var array<int,string> Quoted strings, quotes removed. */
    public $values = array();

    /**
     * Build a form-tag as CF7's scanner would leave it.
     *
     * @since 1.30.3
     *
     * @param array<int,string> $options Bare tokens.
     * @param array<int,string> $values  Quoted strings.
     */
    public function __construct($options = array(), $values = array())
    {
      $this->options = $options;
      $this->values = $values;
    }
  }
}

/**
 * Verify how a Contact Form 7 form-tag's attributes reach the widget.
 *
 * @since 1.30.3
 */
class Cf7FormTagTest extends OpenPorteTestCase
{
  /**
   * Load the integration with Contact Form 7 reported active: the file only
   * defines its functions when openporte_plugin_active('contact-form-7') is
   * true.
   *
   * @since 1.30.3
   */
  public static function setUpBeforeClass(): void
  {
    parent::setUpBeforeClass();
    require_once dirname(__DIR__, 2) . '/includes/helpers.php';
    OpenPorte_Test_Env::$active_plugins = array('contact-form-7/wp-contact-form-7.php');
    require_once dirname(__DIR__, 2) . '/integrations/contact-form-7.php';
  }

  /**
   * Replace the widget HTML with a marker (see stub_widget_html()).
   *
   * @since 1.30.3
   */
  protected function setUp(): void
  {
    parent::setUp();
    $this->stub_widget_html();
    update_option(OpenPortePlugin::$option_integration_custom, 'captcha');
  }

  /**
   * Render a tag scanned with these options and values.
   *
   * @since 1.30.3
   *
   * @param array<int,string> $options Bare tokens.
   * @param array<int,string> $values  Quoted strings.
   * @return string
   */
  private function render($options = array(), $values = array())
  {
    return openporte_cf7_render_form_tag(new WPCF7_FormTag($options, $values));
  }

  /**
   * [openporte]: site language, stored mode.
   *
   * @since 1.30.3
   */
  public function test_bare_tag_uses_the_defaults()
  {
    $this->assertSame("WIDGET(mode='captcha',language=NULL)", $this->render());
  }

  /**
   * [openporte language:de_DE]: the CF7-native option sets the language.
   *
   * @since 1.30.3
   */
  public function test_language_option_sets_the_language()
  {
    $this->assertSame("WIDGET(mode='captcha',language='de_DE')", $this->render(array('language:de_DE')));
  }

  /**
   * [openporte language="de"]: the pre-1.30.3 shortcode syntax still works.
   *
   * @since 1.30.3
   */
  public function test_legacy_language_attribute_is_recovered()
  {
    $this->assertSame("WIDGET(mode='captcha',language='de')", $this->render(array('language='), array('de')));
  }

  /**
   * [openporte "de"]: a quoted value without the language= marker is not a
   * language.
   *
   * @since 1.30.3
   */
  public function test_value_without_the_language_marker_is_ignored()
  {
    $this->assertSame("WIDGET(mode='captcha',language=NULL)", $this->render(array(), array('de')));
  }

  /**
   * [openporte language:]: an empty option falls back to the site language.
   *
   * @since 1.30.3
   */
  public function test_empty_language_option_is_ignored()
  {
    $this->assertSame("WIDGET(mode='captcha',language=NULL)", $this->render(array('language:')));
  }

  /**
   * [openporte mode:x] and [openporte mode="x"]: the tag has no mode; the
   * stored one is used.
   *
   * @since 1.30.3
   */
  public function test_mode_is_ignored()
  {
    $this->assertSame("WIDGET(mode='captcha',language=NULL)", $this->render(array('mode:shortcode')));
    $this->assertSame("WIDGET(mode='captcha',language=NULL)", $this->render(array('mode='), array('shortcode')));
  }
}
