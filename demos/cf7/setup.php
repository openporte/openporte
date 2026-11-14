<?php
/**
 * Seed the temporary Playground site after both plugins have been activated.
 *
 * @package OpenPorteDemo
 */

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

  $form = WPCF7_ContactForm::get_instance( (int) get_option( 'openporte_demo_form_id' ) );
  if ( ! $form ) {
    $form = WPCF7_ContactForm::get_template( array( 'locale' => get_locale() ) );
  }
  $form->set_title( $copy['title'] );
  $messages                    = $form->prop( 'messages' );
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
  update_option( 'openporte_demo_form_id', $form_id );

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

  $page_id = (int) get_option( 'openporte_demo_page_id' );
  $page_id = wp_insert_post(
    array(
      'ID'             => get_post( $page_id ) ? $page_id : 0,
      'post_type'      => 'page',
      'post_name'      => 'openporte-demo',
      'post_title'     => $copy['title'],
      'post_content'   => wp_slash( $content ),
      'post_status'    => 'publish',
      'comment_status' => 'closed',
    ),
    true
  );
  if ( is_wp_error( $page_id ) ) {
    throw new RuntimeException( esc_html( $page_id->get_error_message() ) );
  }
  update_option( 'openporte_demo_page_id', $page_id );
  update_option( 'show_on_front', 'page' );
  update_option( 'page_on_front', $page_id );
  update_option( 'blogname', $copy['title'] );
  update_option( 'blogdescription', '' );

  // A focused front-page template avoids the theme's sample navigation/footer.
  $template_id = (int) get_option( 'openporte_demo_template_id' );
  $template_id = wp_insert_post(
    array(
      'ID'           => get_post( $template_id ) ? $template_id : 0,
      'post_type'    => 'wp_template',
      'post_name'    => 'front-page',
      'post_title'   => $copy['title'],
      'post_status'  => 'publish',
      'post_content' => '<!-- wp:group {"tagName":"main","style":{"spacing":{"padding":{"top":"2rem","bottom":"2rem","left":"1rem","right":"1rem"}}},"layout":{"type":"constrained","contentSize":"42rem"}} -->'
        . '<main class="wp-block-group" style="padding-top:2rem;padding-right:1rem;padding-bottom:2rem;padding-left:1rem">'
        . '<!-- wp:post-title {"level":1} /--><!-- wp:post-content /--></main><!-- /wp:group -->',
    ),
    true
  );
  if ( is_wp_error( $template_id ) ) {
    throw new RuntimeException( esc_html( $template_id->get_error_message() ) );
  }
  wp_set_object_terms( $template_id, get_stylesheet(), 'wp_theme' );
  update_option( 'openporte_demo_template_id', $template_id );
}
