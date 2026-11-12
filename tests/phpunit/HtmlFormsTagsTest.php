<?php
/**
 * HTML Forms integration: the scoped [openporte]/[altcha] expander.
 *
 * It runs on the admin's form markup (hf_form_markup) and must expand our two
 * tags only. Everything else, other shortcodes above all, stays literal text,
 * so a visitor's input can never run a shortcode (GHSA-hvr5-q69x-jp62).
 *
 * What these tests cannot prove — that HTML Forms calls the expander before
 * it fills in {{url_params}} — is covered on the wp-env bench
 * (tests/e2e/shortcode-injection.spec.js).
 *
 * @package OpenPorte\Tests
 * @since 1.30.3
 */

/**
 * Verify which tags the HTML Forms expander renders.
 *
 * @since 1.30.3
 */
class HtmlFormsTagsTest extends OpenPorteTestCase
{
  /**
   * Load the integration with HTML Forms reported active: the file only
   * defines its functions when openporte_plugin_active('html-forms') is true.
   *
   * @since 1.30.3
   */
  public static function setUpBeforeClass(): void
  {
    parent::setUpBeforeClass();
    require_once dirname(__DIR__, 2) . '/includes/helpers.php';
    OpenPorte_Test_Env::$active_plugins = array('html-forms/html-forms.php');
    require_once dirname(__DIR__, 2) . '/integrations/html-forms.php';
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
   * [openporte] and the legacy [altcha] become the widget, in place.
   *
   * @since 1.30.3
   */
  public function test_both_tags_are_rendered_in_place()
  {
    $this->assertSame(
      "<p>A</p>WIDGET(mode='captcha',language=NULL)<p>B</p>WIDGET(mode='captcha',language=NULL)",
      openporte_hf_expand_tags('<p>A</p>[openporte]<p>B</p>[altcha]')
    );
  }

  /**
   * Shortcode-style attributes still reach the widget.
   *
   * @since 1.30.3
   */
  public function test_attributes_reach_the_widget()
  {
    $this->assertSame(
      "WIDGET(mode='captcha',language='de')|WIDGET(mode='shortcode',language='de_DE')",
      openporte_hf_expand_tags('[openporte language="de"]|[altcha language=de_DE mode=shortcode]')
    );
  }

  /**
   * Any other shortcode is left as text, whoever wrote it.
   *
   * @since 1.30.3
   */
  public function test_other_shortcodes_stay_literal()
  {
    $markup = '<textarea name="m">[gallery ids="17"]</textarea><p>[audio src=x]</p>[openporte-other]';

    $this->assertSame($markup, openporte_hf_expand_tags($markup));
  }

  /**
   * [[openporte]] is the shortcode escape: it prints the tag itself.
   *
   * @since 1.30.3
   */
  public function test_escaped_tag_prints_the_tag()
  {
    $this->assertSame('Use [openporte] here', openporte_hf_expand_tags('Use [[openporte]] here'));
  }

  /**
   * A lone extra bracket is kept around the widget, as do_shortcode() does.
   *
   * @since 1.30.3
   */
  public function test_unbalanced_bracket_is_kept()
  {
    $this->assertSame("[WIDGET(mode='captcha',language=NULL)", openporte_hf_expand_tags('[[openporte]'));
  }

  /**
   * Template tags are HTML Forms' business: left for hf_template() to fill.
   *
   * @since 1.30.3
   */
  public function test_template_tags_are_left_alone()
  {
    $this->assertSame(
      "<input value=\"{{url_params.ref}}\">WIDGET(mode='captcha',language=NULL)",
      openporte_hf_expand_tags('<input value="{{url_params.ref}}">[openporte]')
    );
  }
}
