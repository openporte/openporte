<?php

if ( ! defined( 'ABSPATH' ) ) exit;

if (openporte_plugin_active('html-forms')) {
  // Expand our own tags only, and only in the admin's form markup: never run
  // do_shortcode on the rendered form. hf_template() fills {{url_params.*}},
  // {{user.*}} and {{post.*}} into the markup at priority 10, so the rendered
  // form contains visitor input, and expanding it let anyone run any
  // registered shortcode (GHSA-hvr5-q69x-jp62). Priority 5 runs before that.
  add_filter( 'hf_form_markup', 'openporte_hf_expand_tags', 5, 1 );

  /**
   * Render the [openporte] and legacy [altcha] tags in HTML Forms markup.
   *
   * Matches only those two tags, with WordPress's own shortcode grammar
   * (attributes, [[openporte]] escaping); every other shortcode stays text.
   *
   * @since 1.30.3
   *
   * @param string $markup The form's markup, as written by the admin.
   * @return string Markup with the widget in place of each tag.
   */
  function openporte_hf_expand_tags( $markup ) {
    $markup = (string) $markup;
    if ( false === strpos( $markup, '[' ) ) {
      return $markup;
    }
    $pattern = '/' . get_shortcode_regex( array( 'openporte', 'altcha' ) ) . '/';
    return preg_replace_callback( $pattern, 'openporte_hf_render_tag', $markup );
  }

  /**
   * Render one matched [openporte]/[altcha] tag, like core's do_shortcode_tag().
   *
   * @since 1.30.3
   *
   * @param array<int,string> $m get_shortcode_regex() match: 0 the whole tag,
   *                             1 and 6 the escaping brackets, 3 the
   *                             attributes.
   * @return string Widget HTML, or the tag itself when escaped.
   */
  function openporte_hf_render_tag( $m ) {
    // [[openporte]] escapes the tag: print [openporte] as text.
    if ( '[' === $m[1] && ']' === $m[6] ) {
      return substr( $m[0], 1, -1 );
    }
    return $m[1] . openporte_render_shortcode( shortcode_parse_atts( $m[3] ) ) . $m[6];
  }

  add_filter(
    'hf_form_html',
    function ($html) {
      $plugin = OpenPortePlugin::$instance;
      $active = $plugin->get_integration_html_forms();
      if ($active) {
        return str_replace('</form>', wp_kses($plugin->render_widget($active), OpenPortePlugin::$html_espace_allowed_tags) . '</form>', $html);
      }
      return $html;
    }
  );

  add_filter(
    'hf_validate_form',
    function ($error_code, $form, $data) {
      $plugin = OpenPortePlugin::$instance;
      $active = $plugin->get_integration_html_forms();
      // Even with the integration switched off, a widget placed manually in the
      // form markup (e.g. via the [openporte] shortcode — the legacy
      // "shortcode" mode) must still be verified, or the captcha shown to
      // visitors would be decorative and bots could submit right past it.
      if (!$active && strpos($form, "<altcha-widget ") === false) {
        return $error_code;
      }
      $altcha = isset($_POST['altcha']) ? trim(sanitize_text_field(wp_unslash($_POST['altcha']))) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
      if ($plugin->verify($altcha, null, 'html_forms') === false) {
        return "openporte_invalid";
      }
      return $error_code;
    },
    10,
    3
  );

  add_filter(
    'hf_form_message_openporte_invalid',
    function ($message) {
      return __('Could not verify you are not a robot.', 'openporte');
    }
  );
}
