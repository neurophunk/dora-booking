<?php
/**
 * Plugin Name: DoraBooking
 * Description: Custom booking system for dorabudapest.com
 * Version: 1.4.1
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'DORA_VERSION', '1.4.1' );
define( 'DORA_DB_VERSION', '1.2' );
define( 'DORA_PATH', plugin_dir_path( __FILE__ ) );

// Autoload includes
foreach ( [
    'class-availability-engine',
    'class-pricing-engine',
    'class-booking-manager',
    'class-woocommerce-bridge',
    'class-email-service',
    'class-booking-form',
] as $class ) {
    require_once DORA_PATH . 'includes/' . $class . '.php';
}

require_once DORA_PATH . 'admin/class-admin-page.php';

register_activation_hook( __FILE__, 'dora_activate' );

function dora_activate(): void {
    dora_run_migrations();
    dora_schedule_crons();
}

function dora_run_migrations(): void {
    global $wpdb;
    $current = get_option( 'dora_booking_db_version', '0' );
    if ( version_compare( $current, DORA_DB_VERSION, '>=' ) ) {
        return;
    }
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset = $wpdb->get_charset_collate();

    dbDelta( "CREATE TABLE {$wpdb->prefix}dora_pricing_tiers (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        service_id       INT NOT NULL,
        min_persons      TINYINT NOT NULL,
        max_persons      TINYINT NOT NULL,
        price_per_person DECIMAL(10,2) NOT NULL,
        currency         VARCHAR(3) NOT NULL DEFAULT 'EUR',
        UNIQUE KEY service_range (service_id, min_persons, max_persons)
    ) {$charset};" );

    dbDelta( "CREATE TABLE {$wpdb->prefix}dora_bookings (
        id                        INT AUTO_INCREMENT PRIMARY KEY,
        appointment_id            INT NULL,
        customer_appointment_id   INT NULL,
        payment_id                INT NULL,
        wc_order_id               INT NULL,
        service_id                INT NOT NULL,
        staff_id                  INT NOT NULL,
        start_datetime            DATETIME NOT NULL,
        end_datetime              DATETIME NOT NULL,
        persons                   TINYINT NOT NULL,
        total_price               DECIMAL(10,2) NOT NULL,
        currency                  VARCHAR(3) NOT NULL DEFAULT 'EUR',
        payment_type              VARCHAR(20) NOT NULL,
        status                    VARCHAR(20) NOT NULL DEFAULT 'pending',
        lang                      VARCHAR(5) NOT NULL DEFAULT 'hu',
        customer_name             VARCHAR(100) NOT NULL,
        customer_email            VARCHAR(150) NOT NULL,
        customer_phone            VARCHAR(30) NULL,
        customer_notes            TEXT NULL,
        cancel_token              VARCHAR(64) NOT NULL,
        cancel_token_used_at      DATETIME NULL,
        created_at                DATETIME NOT NULL,
        UNIQUE KEY cancel_token (cancel_token),
        KEY staff_start (staff_id, start_datetime),
        KEY status_start (status, start_datetime)
    ) {$charset};" );

    dbDelta( "CREATE TABLE {$wpdb->prefix}dora_email_log (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        booking_id  INT NOT NULL,
        type        VARCHAR(30) NOT NULL,
        lang        VARCHAR(5) NOT NULL,
        recipient   VARCHAR(150) NOT NULL,
        sent_at     DATETIME NOT NULL,
        status      VARCHAR(10) NOT NULL
    ) {$charset};" );

    dbDelta( "CREATE TABLE {$wpdb->prefix}dora_email_templates (
        id      INT AUTO_INCREMENT PRIMARY KEY,
        type    VARCHAR(30) NOT NULL,
        lang    VARCHAR(5) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        body    LONGTEXT NOT NULL,
        UNIQUE KEY type_lang (type, lang)
    ) {$charset};" );

    dbDelta( "CREATE TABLE {$wpdb->prefix}dora_service_config (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        service_id       INT NOT NULL,
        meeting_point    TEXT NULL,
        max_persons      TINYINT NOT NULL DEFAULT 99,
        slot_mode        VARCHAR(10) NOT NULL DEFAULT 'recurring',
        UNIQUE KEY service_id (service_id)
    ) {$charset};" );

    dbDelta( "CREATE TABLE {$wpdb->prefix}dora_specific_slots (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        service_id INT NOT NULL,
        slot_date  DATE NOT NULL,
        slot_time  VARCHAR(5) NOT NULL,
        created_at DATETIME NOT NULL,
        UNIQUE KEY service_date_time (service_id, slot_date, slot_time),
        KEY service_date (service_id, slot_date)
    ) {$charset};" );

    // Add slot_mode column to existing installs (dbDelta won't ADD COLUMN).
    $col = $wpdb->get_var( "SHOW COLUMNS FROM {$wpdb->prefix}dora_service_config LIKE 'slot_mode'" );
    if ( ! $col ) {
        $wpdb->query( "ALTER TABLE {$wpdb->prefix}dora_service_config ADD COLUMN slot_mode VARCHAR(10) NOT NULL DEFAULT 'recurring'" );
    }

    dbDelta( "CREATE TABLE {$wpdb->prefix}dora_services (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        name             VARCHAR(255) NOT NULL,
        description      TEXT NULL,
        duration_minutes INT NOT NULL DEFAULT 60,
        available_times  LONGTEXT NOT NULL,
        available_days   LONGTEXT NOT NULL,
        active           TINYINT(1) NOT NULL DEFAULT 1,
        sort_order       INT NOT NULL DEFAULT 0,
        created_at       DATETIME NOT NULL
    ) {$charset};" );

    update_option( 'dora_booking_db_version', DORA_DB_VERSION );
}

function dora_schedule_crons(): void {
    if ( ! wp_next_scheduled( 'dora_reminder_cron' ) ) {
        $tz        = wp_timezone();
        $today8am  = new DateTime( 'today 08:00:00', $tz );
        wp_schedule_event( $today8am->getTimestamp(), 'daily', 'dora_reminder_cron' );
    }
    if ( ! wp_next_scheduled( 'dora_cleanup_pending' ) ) {
        wp_schedule_event( time(), 'daily', 'dora_cleanup_pending' );
    }
}

add_action( 'plugins_loaded', function () {
    dora_run_migrations();
    dora_schedule_crons();
    if ( is_admin() ) {
        new Dora_Admin_Page();
    }
} );

add_action( 'dora_reminder_cron', [ 'Dora_Email_Service', 'send_reminders' ] );
add_action( 'dora_cleanup_pending', [ 'Dora_Booking_Manager', 'cleanup_pending' ] );
