<?php
/**
 * Plugin Name:       React UI
 * Description:       React components for WordPress frontend
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Você
 * Text Domain:       react-ui
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * Enfileira o bundle React no front-end
 */
add_action('wp_enqueue_scripts', function () {
  wp_enqueue_script(
    'react-ui-frontend',
    plugins_url('build/frontend.js', __FILE__),
    [],
    null,
    true
  );
});

/**
 * Shortcode para montar o React
 * Uso:
 * [react_ui type="text-type" texts="Hello|World"]
 */
add_shortcode('react_ui', function ($atts) {
  $atts = shortcode_atts([
    'type'  => '',
    'texts' => ''
  ], $atts);

  return sprintf(
    '<div class="react-ui" data-component="%s" data-texts="%s"></div>',
    esc_attr($atts['type']),
    esc_attr($atts['texts'])
  );
});
