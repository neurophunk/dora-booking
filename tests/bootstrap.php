<?php
require_once __DIR__ . '/../vendor/autoload.php';

\Brain\Monkey\setUp();

// Stub WP functions used in plugin
if ( ! function_exists( 'add_action' ) ) {
    function add_action() {}
    function add_filter() {}
    function register_activation_hook() {}
    function plugin_dir_path( $f ) { return dirname( $f ) . '/'; }
    function get_option( $k, $d = false ) { return $d; }
    function update_option() {}
    function wp_schedule_event() {}
    function wp_next_scheduled() { return false; }
    function dbDelta() {}
    function absint( $v ) { return abs( (int) $v ); }
    function sanitize_text_field( $v ) { return trim( $v ); }
    function sanitize_email( $v ) { return trim( $v ); }
    function wp_unslash( $v ) { return $v; }
    function esc_html( $v ) { return htmlspecialchars( $v, ENT_QUOTES ); }
    function esc_attr( $v ) { return htmlspecialchars( $v, ENT_QUOTES ); }
    function site_url( $p = '' ) { return 'https://dorabudapest.com' . $p; }
    function wp_timezone() { return new DateTimeZone('Europe/Budapest'); }
}

global $wpdb;
$wpdb = Mockery::mock('wpdb');
$wpdb->prefix = 'wp_';
