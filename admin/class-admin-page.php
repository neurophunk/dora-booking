<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class Dora_Admin_Page {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_post_dora_save_tier',          [ $this, 'handle_save_tier' ] );
        add_action( 'admin_post_dora_delete_tier',        [ $this, 'handle_delete_tier' ] );
        add_action( 'admin_post_dora_save_service_config',[ $this, 'handle_save_service_config' ] );
        add_action( 'admin_post_dora_save_email_template',[ $this, 'handle_save_email_template' ] );
        add_action( 'admin_post_dora_save_settings',      [ $this, 'handle_save_settings' ] );
        add_action( 'admin_post_dora_resend_email',       [ $this, 'handle_resend_email' ] );
        add_action( 'admin_post_dora_cancel_booking',     [ $this, 'handle_cancel_booking' ] );
        add_action( 'admin_post_dora_export_csv',         [ $this, 'handle_export_csv' ] );
    }

    public function register_menu(): void {
        add_menu_page(
            'DoraBooking', 'DoraBooking',
            'manage_options', 'dora-booking',
            [ $this, 'render_bookings' ],
            'dashicons-calendar-alt', 30
        );
        add_submenu_page( 'dora-booking', 'Foglalások',      'Foglalások',      'manage_options', 'dora-booking',         [ $this, 'render_bookings' ] );
        add_submenu_page( 'dora-booking', 'Árazás',          'Árazás',          'manage_options', 'dora-pricing',         [ $this, 'render_pricing' ] );
        add_submenu_page( 'dora-booking', 'Email sablonok',  'Email sablonok',  'manage_options', 'dora-emails',          [ $this, 'render_emails' ] );
        add_submenu_page( 'dora-booking', 'Beállítások',     'Beállítások',     'manage_options', 'dora-settings',        [ $this, 'render_settings' ] );
    }

    public function render_bookings(): void { include DORA_PATH . 'admin/views/bookings.php'; }
    public function render_pricing(): void  { include DORA_PATH . 'admin/views/pricing.php'; }
    public function render_emails(): void   { include DORA_PATH . 'admin/views/emails.php'; }
    public function render_settings(): void { include DORA_PATH . 'admin/views/settings.php'; }

    // ── Action handlers ───────────────────────────────────────

    public function handle_save_tier(): void {
        check_admin_referer('dora_save_tier');
        $service_id = absint($_POST['service_id']);
        $min        = absint($_POST['min_persons']);
        $max        = absint($_POST['max_persons']);
        $price      = (float) str_replace(',', '.', $_POST['price_per_person']);
        $currency   = sanitize_text_field($_POST['currency']);

        // Overlap check
        $engine = new Dora_Pricing_Engine();
        $existing = $engine->get_tiers($service_id);
        $new_tier = ['min' => $min, 'max' => $max];
        $all = array_merge(
            array_map(fn($t) => ['min' => (int)$t->min_persons, 'max' => (int)$t->max_persons], $existing),
            [$new_tier]
        );
        if ( ! $engine->validate_tiers($all) ) {
            wp_redirect( admin_url('admin.php?page=dora-pricing&error=overlap&service=' . $service_id) );
            exit;
        }

        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'dora_pricing_tiers', [
            'service_id' => $service_id, 'min_persons' => $min, 'max_persons' => $max,
            'price_per_person' => $price, 'currency' => $currency,
        ], ['%d','%d','%d','%f','%s'] );

        wp_redirect( admin_url('admin.php?page=dora-pricing&saved=1&service=' . $service_id) );
        exit;
    }

    public function handle_delete_tier(): void {
        check_admin_referer('dora_delete_tier');
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'dora_pricing_tiers', ['id' => absint($_POST['tier_id'])], ['%d'] );
        wp_redirect( admin_url('admin.php?page=dora-pricing&deleted=1') );
        exit;
    }

    public function handle_save_service_config(): void {
        check_admin_referer('dora_save_service_config');
        $service_id    = absint($_POST['service_id']);
        $meeting_point = sanitize_textarea_field($_POST['meeting_point'] ?? '');
        $max_persons   = absint($_POST['max_persons'] ?? 99);

        global $wpdb;
        $wpdb->query( $wpdb->prepare(
            "INSERT INTO {$wpdb->prefix}dora_service_config (service_id, meeting_point, max_persons)
             VALUES (%d, %s, %d)
             ON DUPLICATE KEY UPDATE meeting_point = VALUES(meeting_point), max_persons = VALUES(max_persons)",
            $service_id, $meeting_point, $max_persons
        ) );

        wp_redirect( admin_url('admin.php?page=dora-pricing&config_saved=1&service=' . $service_id) );
        exit;
    }

    public function handle_save_email_template(): void {
        check_admin_referer('dora_save_email_template');
        global $wpdb;
        $wpdb->query( $wpdb->prepare(
            "INSERT INTO {$wpdb->prefix}dora_email_templates (type, lang, subject, body)
             VALUES (%s, %s, %s, %s)
             ON DUPLICATE KEY UPDATE subject = VALUES(subject), body = VALUES(body)",
            sanitize_text_field($_POST['type']),
            sanitize_text_field($_POST['lang']),
            sanitize_text_field($_POST['subject']),
            wp_kses_post($_POST['body'])
        ) );
        wp_redirect( admin_url('admin.php?page=dora-emails&saved=1') );
        exit;
    }

    public function handle_save_settings(): void {
        check_admin_referer('dora_save_settings');
        $fields = [
            'dora_default_currency'           => sanitize_text_field($_POST['default_currency'] ?? 'EUR'),
            'dora_max_persons_global'          => absint($_POST['max_persons_global'] ?? 10),
            'dora_cancellation_deadline_hours' => absint($_POST['cancellation_deadline_hours'] ?? 24),
            'dora_admin_notification_email'    => sanitize_email($_POST['admin_notification_email'] ?? ''),
        ];
        foreach ($fields as $key => $val) update_option($key, $val);
        wp_redirect( admin_url('admin.php?page=dora-settings&saved=1') );
        exit;
    }

    public function handle_resend_email(): void {
        check_admin_referer('dora_resend_email');
        if ( ! current_user_can('manage_options') ) wp_die('Unauthorized');
        $booking_id = absint($_POST['booking_id']);
        $service = new Dora_Email_Service();
        $service->send_confirmation($booking_id);
        wp_redirect( admin_url('admin.php?page=dora-booking&resent=1') );
        exit;
    }

    public function handle_cancel_booking(): void {
        check_admin_referer('dora_cancel_booking');
        if ( ! current_user_can('manage_options') ) wp_die('Unauthorized');
        global $wpdb;
        $booking_id = absint($_POST['booking_id']);
        $booking    = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dora_bookings WHERE id = %d", $booking_id
        ) );
        if ( ! $booking ) wp_die('Not found');

        $wpdb->update(
            $wpdb->prefix . 'dora_bookings',
            ['status' => 'cancelled', 'cancel_token_used_at' => gmdate('Y-m-d H:i:s')],
            ['id' => $booking_id], ['%s','%s'], ['%d']
        );
        if ($booking->customer_appointment_id) {
            $wpdb->update( $wpdb->prefix . 'bookly_customer_appointments',
                ['status' => 'cancelled'], ['id' => (int)$booking->customer_appointment_id], ['%s'], ['%d'] );
        }
        $service = new Dora_Email_Service();
        $service->send_cancellation($booking_id);
        wp_redirect( admin_url('admin.php?page=dora-booking&cancelled=1') );
        exit;
    }

    public function handle_export_csv(): void {
        check_admin_referer('dora_export_csv');
        if ( ! current_user_can('manage_options') ) wp_die('Unauthorized');

        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT b.id, s.title as service, b.start_datetime, b.persons, b.total_price, b.currency,
                    b.payment_type, b.status, b.customer_name, b.customer_email, b.customer_phone, b.created_at
             FROM {$wpdb->prefix}dora_bookings b
             LEFT JOIN {$wpdb->prefix}bookly_services s ON s.id = b.service_id
             ORDER BY b.start_datetime DESC"
        );

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="bookings-' . gmdate('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID','Service','Start','Persons','Total','Currency','Payment','Status','Name','Email','Phone','Created']);
        foreach ($rows as $r) {
            fputcsv($out, (array) $r);
        }
        fclose($out);
        exit;
    }
}
