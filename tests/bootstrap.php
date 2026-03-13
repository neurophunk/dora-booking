<?php
// Patchwork MUST be loaded before any user-defined functions it needs to intercept.
// The autoloader will trigger Patchwork loading, so require it explicitly first.
require_once __DIR__ . '/../vendor/antecedent/patchwork/Patchwork.php';

require_once __DIR__ . '/../vendor/autoload.php';

// Create a stub for wp-admin/includes/upgrade.php so the plugin's require_once doesn't fail
@mkdir('/tmp/wp-admin/includes', 0755, true);
if ( ! file_exists('/tmp/wp-admin/includes/upgrade.php') ) {
    file_put_contents('/tmp/wp-admin/includes/upgrade.php', '<?php // stub ?>');
}

// Stub WP functions that are called at plugin FILE LOAD TIME (top-level, outside functions).
// Do NOT stub get_option / update_option / dbDelta here — Brain\Monkey's Functions\expect()
// needs to intercept those, and Patchwork can only intercept functions defined in files
// it has stream-wrapped. Instead, leave them undefined so Brain\Monkey can define them.
if ( ! function_exists( 'add_action' ) ) {
    function add_action() {}
    function add_filter() {}
    function register_activation_hook() {}
    function plugin_dir_path( $f ) { return dirname( $f ) . '/'; }
    function wp_next_scheduled() { return false; }
    function wp_schedule_event() {}
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
