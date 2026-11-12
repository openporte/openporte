<?php
/**
 * The shared [openporte]/[altcha] renderer: what the WordPress shortcode, the
 * Contact Form 7 form-tag and the HTML Forms expander all call.
 *
 * The widget markup itself is render_widget()'s business. These tests watch
 * what reaches it through the public openporte_widget_html filter.
 *
 * @package OpenPorte\Tests
 * @since 1.30.3
 */

/**
 * Verify that shortcode attributes reach the widget renderer.
 *
 * @since 1.30.3
 */
class RenderShortcodeTest extends OpenPorteTestCase
{
  /**
   * Load the helpers that define the renderer.
   *
   * @since 1.30.3
   */
  public static function setUpBeforeClass(): void
  {
    parent::setUpBeforeClass();
    require_once dirname(__DIR__, 2) . '/includes/helpers.php';
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
  }

  /**
   * A shortcode with no attributes (WordPress passes '') uses the site
   * language and the stored Custom HTML mode.
   *
   * @since 1.30.3
   */
  public function test_no_attributes_uses_the_defaults()
  {
    update_option(OpenPortePlugin::$option_integration_custom, 'captcha');

    $this->assertSame("WIDGET(mode='captcha',language=NULL)", openporte_render_shortcode(''));
  }

  /**
   * The language attribute is handed to the widget.
   *
   * @since 1.30.3
   */
  public function test_language_attribute_reaches_the_widget()
  {
    $this->assertSame(
      "WIDGET(mode=false,language='de_DE')",
      openporte_render_shortcode(array('language' => 'de_DE'))
    );
  }

  /**
   * The mode attribute is still passed through to the widget filters.
   *
   * @since 1.30.3
   */
  public function test_mode_attribute_reaches_the_widget()
  {
    $this->assertSame(
      "WIDGET(mode='shortcode',language=NULL)",
      openporte_render_shortcode(array('mode' => 'shortcode'))
    );
  }
}
