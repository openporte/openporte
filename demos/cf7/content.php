<?php
/**
 * English source strings for the Playground demo (separate gettext domain).
 *
 * @package OpenPorteDemo
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * Demo form option key.
 *
 * @since 1.31.0
 */
const OPENPORTE_DEMO_FORM_ID = 'openporte_demo_form_id';

/**
 * Demo page option key.
 *
 * @since 1.31.0
 */
const OPENPORTE_DEMO_PAGE_ID = 'openporte_demo_page_id';

/**
 * Demo template option key.
 *
 * @since 1.31.0
 */
const OPENPORTE_DEMO_TEMPLATE_ID = 'openporte_demo_template_id';

/**
 * Get demo copy after the selected locale and translation catalogue are loaded.
 *
 * @since 1.31.0
 * @return array<string, string> Complete strings, keyed by stable identifiers.
 */
function openporte_demo_content() {
  return array(
    'title'       => __( 'Try OpenPorte', 'openporte-demo' ),
    'intro'       => __( 'Try a Contact Form 7 form protected by OpenPorte. Write a sample message, tick the verification box, and submit. No puzzles to solve.', 'openporte-demo' ),
    'notice'      => __( 'Demo only—no email is sent.', 'openporte-demo' ),
    'message'     => __( 'Message', 'openporte-demo' ),
    'placeholder' => __( 'Hello from the OpenPorte demo!', 'openporte-demo' ),
    'submit'      => __( 'Submit message', 'openporte-demo' ),
    'success'     => __( 'Verification successful. Your demo message was accepted. No email was sent.', 'openporte-demo' ),
    'invalid'     => __( 'Please check the message field and try again.', 'openporte-demo' ),
    'required'    => __( 'Please enter a sample message.', 'openporte-demo' ),
    'spam'        => __( 'Verification could not be completed. Please verify again and retry your message.', 'openporte-demo' ),
    'failed'      => __( 'The demo could not accept your message. Please try again.', 'openporte-demo' ),
    'setup'       => __( 'See how this is set up', 'openporte-demo' ),
    'explanation' => __( 'OpenPorte generates and verifies a small proof-of-work challenge within WordPress. The Contact Form 7 integration adds the widget and checks the result when the form is submitted.', 'openporte-demo' ),
    'settings'    => __( 'Explore OpenPorte settings', 'openporte-demo' ),
    'edit_form'   => __( 'View the Contact Form 7 form', 'openporte-demo' ),
    'return'      => __( 'Back to demo', 'openporte-demo' ),
    'next'        => __( 'Use OpenPorte on your site', 'openporte-demo' ),
    'install'     => __( 'Install and activate OpenPorte and Contact Form 7 from the WordPress plugin directory.', 'openporte-demo' ),
    /* translators: Settings is the WordPress menu; OpenPorte Anti-spam and Integrations must match the plugin's translated menu/tab labels. */
    'enable'      => __( 'Under Settings → OpenPorte Anti-spam → Integrations, enable Contact Form 7. Keep the default self-hosted mode; no external account is needed.', 'openporte-demo' ),
    'publish'     => __( 'Create and publish your contact form, then test a submission as a visitor. Configure email delivery for your own site; it is disabled only in this demo.', 'openporte-demo' ),
    'docs'        => __( 'Documentation', 'openporte-demo' ),
    'support'     => __( 'Community support', 'openporte-demo' ),
    'reset'       => __( 'Want to start over? Open a new Preview from the OpenPorte listing on WordPress.org to create a fresh demo. Reloading this page keeps your current changes.', 'openporte-demo' ),
    'listing'     => __( 'OpenPorte on WordPress.org', 'openporte-demo' ),
  );
}
