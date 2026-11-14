<?php
/**
 * Plugin Name: OpenPorte Playground demo
 * Description: Navigation and presentation for the temporary demo only.
 * Author: OpenPorte contributors
 * License: GPL-2.0-or-later
 * Text Domain: openporte-demo
 *
 * @package OpenPorteDemo
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

require_once __DIR__ . '/openporte-demo/content.php';

/**
 * Load the demo's catalogue independently of OpenPorte's translations.
 *
 * @since 1.31.0
 * @return void
 */
function openporte_demo_load_textdomain() {
  load_muplugin_textdomain( 'openporte-demo', 'openporte-demo/languages' );
}
add_action( 'init', 'openporte_demo_load_textdomain' );

/**
 * Link to the actual settings and generated form, including after URL changes.
 *
 * @since 1.31.0
 * @return string Escaped navigation markup.
 */
function openporte_demo_links() {
  $copy     = openporte_demo_content();
  $settings = admin_url( 'options-general.php?page=openporte_admin' );
  $form     = add_query_arg(
    array( 'page' => 'wpcf7', 'post' => (int) get_option( 'openporte_demo_form_id' ), 'action' => 'edit' ),
    admin_url( 'admin.php' )
  );
  return '<ul><li><a href="' . esc_url( $settings ) . '">' . esc_html( $copy['settings'] ) . '</a></li><li><a href="' . esc_url( $form ) . '">' . esc_html( $copy['edit_form'] ) . '</a></li></ul>';
}
add_shortcode( 'openporte_demo_links', 'openporte_demo_links' );

/**
 * Offer a return route from the two configuration screens.
 *
 * @since 1.31.0
 * @return void
 */
function openporte_demo_admin_notice() {
  $screen = get_current_screen();
  // CF7's editor may be a top-level page or a submenu of its dashboard.
  if ( ! $screen || ( 'settings_page_openporte_admin' !== $screen->id && ! preg_match( '/_page_wpcf7$/', $screen->id ) ) ) {
    return;
  }
  $url = get_permalink( (int) get_option( 'openporte_demo_page_id' ) );
  if ( $url && current_user_can( 'manage_options' ) ) {
    $copy = openporte_demo_content();
    echo '<div class="notice notice-info"><p><a href="' . esc_url( $url ) . '">' . esc_html( $copy['return'] ) . '</a></p></div>';
  }
}
add_action( 'admin_notices', 'openporte_demo_admin_notice' );

/**
 * Apply the small demo stylesheet only to the generated landing page.
 *
 * @since 1.31.0
 * @return void
 */
function openporte_demo_styles() {
  if ( is_page( (int) get_option( 'openporte_demo_page_id' ) ) ) {
    wp_enqueue_style( 'openporte-demo', WPMU_PLUGIN_URL . '/openporte-demo/demo.css', array(), '1.31.0' );
  }
}
add_action( 'wp_enqueue_scripts', 'openporte_demo_styles' );
