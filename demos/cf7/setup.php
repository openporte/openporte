<?php
/**
 * Seed the temporary Playground site after both plugins have been activated.
 *
 * @package OpenPorteDemo
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * Create or update one seeded post and remember its ID.
 *
 * @since 1.31.0
 * @param string $option  Demo option holding the post ID.
 * @param array  $postarr Post fields passed to WordPress.
 * @return int Saved post ID.
 * @throws RuntimeException When WordPress cannot save the post.
 */
function openporte_demo_upsert_post( $option, $postarr ) {
  $post_id       = (int) get_option( $option );
  $postarr['ID'] = $post_id && get_post( $post_id ) ? $post_id : 0;
  $post_id       = wp_insert_post( $postarr, true );
  if ( is_wp_error( $post_id ) ) {
    throw new RuntimeException( esc_html( $post_id->get_error_message() ) );
  }
  update_option( $option, $post_id );
  return $post_id;
}

/**
 * Create or update the demo's Contact Form 7 form and remember its ID.
 *
 * @since 1.31.0
 * @param array $copy Demo copy from openporte_demo_content().
 * @return int Saved form ID.
 * @throws RuntimeException When CF7 cannot save the form.
 */
function openporte_demo_save_form( $copy ) {
  $form = WPCF7_ContactForm::get_instance( (int) get_option( OPENPORTE_DEMO_FORM_ID ) );
  if ( ! $form ) {
    $form = WPCF7_ContactForm::get_template( array( 'locale' => get_locale() ) );
  }
  $form->set_title( $copy['title'] );
  $messages                     = $form->prop( 'messages' );
  $messages['mail_sent_ok']     = $copy['success'];
  $messages['mail_sent_ng']     = $copy['failed'];
  $messages['validation_error'] = $copy['invalid'];
  $messages['invalid_required'] = $copy['required'];
  $messages['spam']             = $copy['spam'];
  $form->set_properties(
    array(
      // OpenPorte inserts the widget through its normal CF7 integration.
      'form'                => '<label for="demo-message">' . esc_html( $copy['message'] ) . '</label>' . "\n"
        . '[textarea* demo-message id:demo-message 40x5 placeholder "' . esc_attr( $copy['placeholder'] ) . '"]' . "\n"
        . '[submit "' . esc_attr( $copy['submit'] ) . '"]',
      'messages'            => $messages,
      // Suppress delivery only; the real spam/verification pipeline still runs.
      'additional_settings' => "skip_mail: on\ndo_not_store: true",
    )
  );
  $form->save();
  $form_id = $form->id();
  if ( ! $form_id ) {
    throw new RuntimeException( 'Could not create the demo form.' );
  }
  update_option( OPENPORTE_DEMO_FORM_ID, $form_id );
  return $form_id;
}

/**
 * Create or update the focused front-page template and check that it is used.
 *
 * @since 1.31.0
 * @param string $title Template title shown in the site editor.
 * @return void
 * @throws RuntimeException When the template cannot be saved or is not resolved.
 */
function openporte_demo_save_front_page_template( $title ) {
  // A focused front-page template avoids the theme's sample navigation/footer.
  // Only block themes render it: a classic theme would still resolve the saved
  // post below, then display its own PHP template, so the check would pass for
  // nothing. The blueprint activates a block theme; fail if that ever changes.
  if ( ! wp_is_block_theme() ) {
    throw new RuntimeException( 'The demo front-page template requires an active block theme.' );
  }
  $template_id = openporte_demo_upsert_post(
    OPENPORTE_DEMO_TEMPLATE_ID,
    array(
      'post_type'    => 'wp_template',
      'post_name'    => 'front-page',
      'post_title'   => $title,
      'post_status'  => 'publish',
      'post_content' => '<!-- wp:group {"tagName":"main","style":{"spacing":{"padding":{"top":"2rem","bottom":"2rem","left":"1rem","right":"1rem"}}},"layout":{"type":"constrained","contentSize":"42rem"}} -->'
        . '<main class="wp-block-group" style="padding-top:2rem;padding-right:1rem;padding-bottom:2rem;padding-left:1rem">'
        . '<!-- wp:post-title {"level":1} /--><!-- wp:post-content /--></main><!-- /wp:group -->',
    )
  );
  $theme = get_stylesheet();
  $terms = wp_set_object_terms( $template_id, $theme, 'wp_theme' );
  if ( is_wp_error( $terms ) ) {
    throw new RuntimeException( 'Could not assign the demo front-page template: ' . esc_html( $terms->get_error_message() ) );
  }

  // A saved post alone does not guarantee that the active theme will use it.
  $resolved = get_block_template( $theme . '//front-page', 'wp_template' );
  if ( ! $resolved || (int) $resolved->wp_id !== $template_id ) {
    throw new RuntimeException( 'The active theme did not resolve the demo front-page template.' );
  }
}

/**
 * Create or refresh the demo without depending on titles or fixed database IDs.
 *
 * @since 1.31.0
 * @return void
 * @throws RuntimeException When dependencies or content creation fail.
 */
function openporte_demo_setup() {
  if ( ! class_exists( 'WPCF7_ContactForm' ) || ! class_exists( 'OpenPortePlugin' ) ) {
    throw new RuntimeException( 'The demo requires active Contact Form 7 and OpenPorte plugins.' );
  }

  $copy = openporte_demo_content();
  update_option( OpenPortePlugin::$option_api, 'selfhosted' );
  update_option( OpenPortePlugin::$option_selfhosted_transport, 'rest' );
  update_option( OpenPortePlugin::$option_integration_contact_form_7, 1 );
  update_option( OpenPortePlugin::$option_auto, '' );
  update_option( OpenPortePlugin::$option_floating, '' );

  $form_id = openporte_demo_save_form( $copy );

  $content = '<div class="openporte-demo">'
    . '<p>' . esc_html( $copy['intro'] ) . '</p>'
    . '<p class="openporte-demo-note">' . esc_html( $copy['notice'] ) . '</p>'
    . '[contact-form-7 id="' . (int) $form_id . '"]'
    . '<details><summary>' . esc_html( $copy['setup'] ) . '</summary>'
    . '<p>' . esc_html( $copy['explanation'] ) . '</p>[openporte_demo_links]</details>'
    . '<h2>' . esc_html( $copy['next'] ) . '</h2><ol>'
    . '<li>' . esc_html( $copy['install'] ) . '</li>'
    . '<li>' . esc_html( $copy['enable'] ) . '</li>'
    . '<li>' . esc_html( $copy['publish'] ) . '</li></ol>'
    . '<p><a href="https://wordpress.org/plugins/openporte/#faq">' . esc_html( $copy['docs'] ) . '</a> · '
    . '<a href="https://wordpress.org/support/plugin/openporte/">' . esc_html( $copy['support'] ) . '</a></p>'
    . '<hr><p>' . esc_html( $copy['reset'] ) . '</p>'
    . '<p><a href="https://wordpress.org/plugins/openporte/">' . esc_html( $copy['listing'] ) . '</a></p></div>';

  $page_id = openporte_demo_upsert_post(
    OPENPORTE_DEMO_PAGE_ID,
    array(
      'post_type'      => 'page',
      'post_name'      => 'openporte-demo',
      'post_title'     => $copy['title'],
      'post_content'   => wp_slash( $content ),
      'post_status'    => 'publish',
      'comment_status' => 'closed',
    )
  );
  update_option( 'show_on_front', 'page' );
  update_option( 'page_on_front', $page_id );
  update_option( 'blogname', $copy['title'] );
  update_option( 'blogdescription', '' );

  openporte_demo_save_front_page_template( $copy['title'] );
}
