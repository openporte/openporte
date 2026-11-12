<?php

if ( ! defined( 'ABSPATH' ) ) exit;

if (openporte_plugin_active('contact-form-7')) {
  // The widget is placed in a form as a Contact Form 7 form-tag, never by
  // running do_shortcode on the rendered form: that HTML contains visitor
  // input (re-displayed values, default:get), and expanding it let anyone run
  // any registered shortcode (GHSA-hvr5-q69x-jp62). CF7 builds form-tags
  // from the admin's form template only.
  add_action( 'wpcf7_init', 'openporte_cf7_add_form_tags', 10, 0 );

  /**
   * Register [openporte] and the legacy [altcha] as Contact Form 7 form-tags.
   *
   * Display-only, like CF7's own [response] tag: no name attribute, so CF7
   * neither validates nor submits a value for them. The widget posts its own
   * `altcha` field, which the wpcf7_spam filter below verifies.
   *
   * @since 1.30.3
   */
  function openporte_cf7_add_form_tags() {
    wpcf7_add_form_tag(
      array( 'openporte', 'altcha' ),
      'openporte_cf7_render_form_tag',
      array( 'display-block' => true )
    );
  }

  /**
   * Render the widget for an [openporte]/[altcha] form-tag.
   *
   * Reads the language from the CF7-native option (`[openporte language:de]`).
   * The shortcode syntax used in forms before 1.30.3 (`[openporte
   * language="de"]`) reaches CF7's scanner as the option `language=` and the
   * value `de`; it is recovered only when that marker is present. Both come
   * from the admin's form template, never from the request. The tag has no
   * `mode`: the renderer uses the stored one.
   *
   * @since 1.30.3
   *
   * @param WPCF7_FormTag $tag The scanned form-tag.
   * @return string Widget HTML.
   */
  function openporte_cf7_render_form_tag( $tag ) {
    $attrs   = array();
    $options = (array) $tag->options;
    foreach ( $options as $option ) {
      if ( 0 === strpos( $option, 'language:' ) && strlen( $option ) > strlen( 'language:' ) ) {
        $attrs['language'] = substr( $option, strlen( 'language:' ) );
        break;
      }
    }
    if ( ! isset( $attrs['language'] ) && in_array( 'language=', $options, true ) && ! empty( $tag->values[0] ) ) {
      $attrs['language'] = $tag->values[0];
    }
    return openporte_render_shortcode( $attrs );
  }

  add_filter( 'wpcf7_form_elements', 'openporte_cf7_apply_shortcode', 100, 1 );

  /**
   * Add the widget to Contact Form 7 form elements when needed.
   *
   * @since 1.30.3
   *
   * @param string $elements Rendered form elements.
   * @return string Filtered form elements.
   */
  function openporte_cf7_apply_shortcode( $elements ) {
    $plugin = OpenPortePlugin::$instance;
    $active = $plugin->get_integration_contact_form_7();
    // Skip auto-injection when the form already renders a widget of its own.
    // CF7 replaces its form-tags before it applies this filter, so by the
    // time this callback runs an [openporte]/[altcha] form-tag in the form
    // has already been rendered as <altcha-widget>. Injecting a second
    // one puts two widgets — two required checkboxes, two challenge fetches,
    // two inputs named "altcha" of which PHP keeps only the last — in a
    // single form. Verification is unaffected either way: the wpcf7_spam
    // filter below keys on the option, not on who rendered the widget.
    if ( $active && false === strpos( $elements, '<altcha-widget' ) ) {
      $input  = '<input class="wpcf7-form-control wpcf7-submit ';
      $button = '<button class="wpcf7-form-control wpcf7-submit ';
      $widget = wp_kses( $plugin->render_widget( $active, true, OpenPortePlugin::$language ), OpenPortePlugin::$html_espace_allowed_tags );
      if ( false !== strpos( $elements, $input ) ) {
        $elements = str_replace( $input, $widget . $input, $elements );
      } elseif ( false !== strpos( $elements, $button ) ) {
        $elements = str_replace( $button, $widget . $button, $elements );
      } else {
        $elements .= $widget;
      }
    }
    return $elements;
  }

  add_filter( 'wpcf7_spam', 'openporte_cf7_verify_solution', 9, 2 );

  /**
   * Verify the Contact Form 7 submission challenge.
   *
   * @since 1.30.3
   *
   * @param bool  $spam       Whether the submission is already marked as spam.
   * @param mixed $submission Current submission, expected to be a WPCF7_Submission instance.
   * @return bool Whether the submission is spam.
   */
  function openporte_cf7_verify_solution( $spam, $submission = null ) {
    if ( $spam ) {
      return $spam;
    }
    $plugin = OpenPortePlugin::$instance;
    $active = $plugin->get_integration_contact_form_7();
    if ( ! $active ) {
      // Even with the integration switched off, a widget placed manually in
      // the form (the [openporte]/[altcha] form-tag, or a hand-written
      // <altcha-widget> tag) must still be verified, or the captcha shown
      // to visitors would be decorative and bots could submit right past
      // it — the same backstop html-forms.php applies. The check keys off
      // the server-side form template, never $_POST: a bot simply omits
      // the field. CF7 passes the submission as the filter's second
      // argument (verified against CF7 6.1.6); if it is absent, fall back
      // to the previous behaviour.
      if ( ! ( $submission instanceof WPCF7_Submission ) ) {
        return $spam;
      }
      $form_template = (string) $submission->get_contact_form()->prop( 'form' );
      if (
        false === strpos( $form_template, '[openporte' )
        && false === strpos( $form_template, '[altcha' )
        && false === strpos( $form_template, '<altcha-widget' )
      ) {
        return $spam;
      }
    }
    $altcha = isset( $_POST['altcha'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['altcha'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
    return false === $plugin->verify( $altcha, null, 'contact_form_7' );
  }
}
