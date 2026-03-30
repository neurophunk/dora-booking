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
        add_action( 'admin_post_dora_save_service',       [ $this, 'handle_save_service' ] );
        add_action( 'admin_post_dora_delete_service',     [ $this, 'handle_delete_service' ] );
        add_action( 'admin_post_dora_save_slots',         [ $this, 'handle_save_slots' ] );
        add_action( 'admin_post_dora_delete_slot',        [ $this, 'handle_delete_slot' ] );
    }

    public function register_menu(): void {
        add_menu_page(
            'DoraBooking', 'DoraBooking',
            'manage_options', 'dora-booking',
            [ $this, 'render_bookings' ],
            'dashicons-calendar-alt', 30
        );
        add_submenu_page( 'dora-booking', 'Foglalások',      'Foglalások',      'manage_options', 'dora-booking',         [ $this, 'render_bookings' ] );
        add_submenu_page( 'dora-booking', 'Szolgáltatások',  'Szolgáltatások',  'manage_options', 'dora-services',        [ $this, 'render_services' ] );
        add_submenu_page( 'dora-booking', 'Árazás',          'Árazás',          'manage_options', 'dora-pricing',         [ $this, 'render_pricing' ] );
        add_submenu_page( 'dora-booking', 'Email sablonok',  'Email sablonok',  'manage_options', 'dora-emails',          [ $this, 'render_emails' ] );
        add_submenu_page( 'dora-booking', 'Beállítások',     'Beállítások',     'manage_options', 'dora-settings',        [ $this, 'render_settings' ] );
    }

    public function render_bookings(): void  { include DORA_PATH . 'admin/views/bookings.php'; }
    public function render_services(): void  { include DORA_PATH . 'admin/views/services.php'; }
    public function render_pricing(): void   { include DORA_PATH . 'admin/views/pricing.php'; }
    public function render_emails(): void    { include DORA_PATH . 'admin/views/emails.php'; }
    public function render_settings(): void  { include DORA_PATH . 'admin/views/settings.php'; }

    // ── Action handlers ───────────────────────────────────────

    public function handle_save_tier(): void {
        check_admin_referer('dora_save_tier');
        if ( ! current_user_can('manage_options') ) wp_die('Unauthorized');
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
        if ( ! current_user_can('manage_options') ) wp_die('Unauthorized');
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'dora_pricing_tiers', ['id' => absint($_POST['tier_id'])], ['%d'] );
        wp_redirect( admin_url('admin.php?page=dora-pricing&deleted=1') );
        exit;
    }

    public function handle_save_service_config(): void {
        check_admin_referer('dora_save_service_config');
        if ( ! current_user_can('manage_options') ) wp_die('Unauthorized');
        $service_id    = absint($_POST['service_id']);
        $meeting_point = sanitize_textarea_field($_POST['meeting_point'] ?? '');
        $max_persons   = absint($_POST['max_persons'] ?? 99);
        $slot_mode     = in_array($_POST['slot_mode'] ?? 'recurring', ['recurring', 'specific'], true)
                         ? $_POST['slot_mode'] : 'recurring';

        global $wpdb;
        $wpdb->query( $wpdb->prepare(
            "INSERT INTO {$wpdb->prefix}dora_service_config (service_id, meeting_point, max_persons, slot_mode)
             VALUES (%d, %s, %d, %s)
             ON DUPLICATE KEY UPDATE meeting_point = VALUES(meeting_point), max_persons = VALUES(max_persons), slot_mode = VALUES(slot_mode)",
            $service_id, $meeting_point, $max_persons, $slot_mode
        ) );

        wp_redirect( admin_url('admin.php?page=dora-pricing&config_saved=1&service=' . $service_id) );
        exit;
    }

    public function handle_save_email_template(): void {
        check_admin_referer('dora_save_email_template');
        if ( ! current_user_can('manage_options') ) wp_die('Unauthorized');
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
        if ( ! current_user_can('manage_options') ) wp_die('Unauthorized');
        $color_raw = sanitize_hex_color($_POST['primary_color'] ?? '#1a56db') ?: '#1a56db';
        $fields = [
            'dora_default_currency'           => sanitize_text_field($_POST['default_currency'] ?? 'EUR'),
            'dora_max_persons_global'          => absint($_POST['max_persons_global'] ?? 10),
            'dora_cancellation_deadline_hours' => absint($_POST['cancellation_deadline_hours'] ?? 24),
            'dora_admin_notification_email'    => sanitize_email($_POST['admin_notification_email'] ?? ''),
            'dora_primary_color'               => $color_raw,
            'dora_advance_booking_months'      => min(24, max(1, absint($_POST['advance_booking_months'] ?? 2))),
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
        $service = new Dora_Email_Service();
        $service->send_cancellation($booking_id);
        wp_redirect( admin_url('admin.php?page=dora-booking&cancelled=1') );
        exit;
    }

    public function handle_export_csv(): void {
        check_admin_referer('dora_export_csv');
        if ( ! current_user_can('manage_options') ) wp_die('Unauthorized');

        global $wpdb;

        $where  = ['1=1'];
        $params = [];
        $f_date_from = sanitize_text_field($_POST['date_from'] ?? '');
        $f_date_to   = sanitize_text_field($_POST['date_to']   ?? '');
        $f_service   = absint($_POST['service'] ?? 0);
        $f_status    = sanitize_text_field($_POST['status']  ?? '');
        $f_payment   = sanitize_text_field($_POST['payment'] ?? '');

        if ($f_date_from) { $where[] = 'b.start_datetime >= %s'; $params[] = $f_date_from . ' 00:00:00'; }
        if ($f_date_to)   { $where[] = 'b.start_datetime <= %s'; $params[] = $f_date_to   . ' 23:59:59'; }
        if ($f_service)   { $where[] = 'b.service_id = %d';      $params[] = $f_service; }
        if ($f_status)    { $where[] = 'b.status = %s';          $params[] = $f_status; }
        if ($f_payment)   { $where[] = 'b.payment_type = %s';    $params[] = $f_payment; }

        $sql = "SELECT b.id, s.name as service, b.start_datetime, b.persons, b.total_price, b.currency,
                       b.payment_type, b.status, b.customer_name, b.customer_email, b.customer_phone, b.created_at
                FROM {$wpdb->prefix}dora_bookings b
                LEFT JOIN {$wpdb->prefix}dora_services s ON s.id = b.service_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY b.start_datetime DESC";

        $rows = $params
            ? $wpdb->get_results($wpdb->prepare($sql, ...$params))
            : $wpdb->get_results($sql);

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

    public function handle_save_service(): void {
        check_admin_referer('dora_save_service');
        if ( ! current_user_can('manage_options') ) wp_die('Unauthorized');

        global $wpdb;
        $id          = absint( $_POST['id'] ?? 0 );
        $name        = sanitize_text_field( $_POST['name'] );
        $description = sanitize_textarea_field( $_POST['description'] ?? '' );
        $duration    = absint( $_POST['duration_minutes'] );
        $sort_order  = absint( $_POST['sort_order'] ?? 0 );
        $active      = isset( $_POST['active'] ) ? 1 : 0;

        // available_times: comma-separated "HH:MM,HH:MM" → JSON array
        $times_raw = sanitize_text_field( $_POST['available_times'] ?? '' );
        $times     = array_values( array_filter( array_map( 'trim', explode( ',', $times_raw ) ) ) );
        // Validate HH:MM format
        $times = array_filter( $times, fn($t) => preg_match('/^\d{2}:\d{2}$/', $t) );
        $times_json = wp_json_encode( array_values( $times ) );

        // available_days: checkboxes 0-6
        $days = [];
        foreach ( range(0,6) as $d ) {
            if ( isset($_POST['day_' . $d]) ) $days[] = $d;
        }
        $days_json = wp_json_encode( $days );

        $data = [
            'name'             => $name,
            'description'      => $description,
            'duration_minutes' => $duration,
            'available_times'  => $times_json,
            'available_days'   => $days_json,
            'sort_order'       => $sort_order,
            'active'           => $active,
        ];

        if ( $id ) {
            $wpdb->update( $wpdb->prefix . 'dora_services', $data, ['id' => $id],
                ['%s','%s','%d','%s','%s','%d','%d'], ['%d'] );
            // Save slot_mode to service_config
            $slot_mode = in_array($_POST['slot_mode'] ?? 'recurring', ['recurring', 'specific'], true)
                         ? $_POST['slot_mode'] : 'recurring';
            $wpdb->query( $wpdb->prepare(
                "INSERT INTO {$wpdb->prefix}dora_service_config (service_id, slot_mode)
                 VALUES (%d, %s)
                 ON DUPLICATE KEY UPDATE slot_mode = VALUES(slot_mode)",
                $id, $slot_mode
            ) );
            wp_redirect( admin_url('admin.php?page=dora-services&edit=' . $id . '&saved=1') );
        } else {
            $data['created_at'] = gmdate('Y-m-d H:i:s');
            $wpdb->insert( $wpdb->prefix . 'dora_services', $data,
                ['%s','%s','%d','%s','%s','%d','%d','%s'] );
            wp_redirect( admin_url('admin.php?page=dora-services&saved=1') );
        }
        exit;
    }

    public function handle_delete_service(): void {
        check_admin_referer('dora_delete_service');
        if ( ! current_user_can('manage_options') ) wp_die('Unauthorized');
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'dora_services', ['id' => absint($_POST['id'])], ['%d'] );
        wp_redirect( admin_url('admin.php?page=dora-services&deleted=1') );
        exit;
    }

    public function handle_save_slots(): void {
        check_admin_referer('dora_save_slots');
        if ( ! current_user_can('manage_options') ) wp_die('Unauthorized');
        global $wpdb;

        $service_id  = absint($_POST['service_id']);
        $raw         = sanitize_textarea_field($_POST['slots_import'] ?? '');
        $current_year = (int) gmdate('Y');
        $inserted    = 0;

        foreach ( explode("\n", $raw) as $line ) {
            $line = trim($line);
            if ( ! $line ) continue;
            // Format: MM.DD HH:MM;HH:MM or M.D H:MM;HH:MM (flexible)
            if ( ! preg_match('/^(\d{1,2})\.(\d{1,2})\s+(.+)$/', $line, $m) ) continue;
            $month = (int) $m[1];
            $day   = (int) $m[2];
            if ( $month < 1 || $month > 12 || $day < 1 || $day > 31 ) continue;
            $date  = sprintf('%04d-%02d-%02d', $current_year, $month, $day);
            foreach ( array_map('trim', explode(';', $m[3])) as $time ) {
                if ( ! preg_match('/^\d{1,2}:\d{2}$/', $time) ) continue;
                [$h, $i] = explode(':', $time);
                $normalized = sprintf('%02d:%02d', (int)$h, (int)$i);
                $wpdb->query( $wpdb->prepare(
                    "INSERT IGNORE INTO {$wpdb->prefix}dora_specific_slots (service_id, slot_date, slot_time, created_at)
                     VALUES (%d, %s, %s, %s)",
                    $service_id, $date, $normalized, gmdate('Y-m-d H:i:s')
                ) );
                $inserted++;
            }
        }

        wp_redirect( admin_url("admin.php?page=dora-services&edit={$service_id}&slots_saved={$inserted}") );
        exit;
    }

    public function handle_delete_slot(): void {
        check_admin_referer('dora_delete_slot');
        if ( ! current_user_can('manage_options') ) wp_die('Unauthorized');
        global $wpdb;
        $service_id = absint($_POST['service_id']);
        $wpdb->delete( $wpdb->prefix . 'dora_specific_slots', ['id' => absint($_POST['slot_id'])], ['%d'] );
        wp_redirect( admin_url("admin.php?page=dora-services&edit={$service_id}&slot_deleted=1") );
        exit;
    }
}
