<?php
/**
 * Plugin Name: React UI
 * Description: React components for WordPress frontend
 * Version: 1.0
 */

if (!defined('ABSPATH')) exit;

add_action('wp_enqueue_scripts', function () {
  wp_enqueue_script(
    'react-ui',
    plugins_url('dist/assets/index.js', __FILE__),
    [],
    null,
    true
  );

  wp_localize_script('react-ui', 'WP_DATA', [
    'api'   => rest_url(),
    'nonce' => wp_create_nonce('wp_rest')
  ]);
});

add_shortcode('react_ui', function () {
  return '<div id="react-ui"></div>';
});
