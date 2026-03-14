<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class Dora_Booking_Form {

    public function __construct() {
        add_shortcode( 'dora_booking', [ $this, 'render_shortcode' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );

        foreach ( [
            'dora_get_services',
            'dora_get_price',
            'dora_get_available_days',
            'dora_get_available_slots',
            'dora_create_pending',
            'dora_confirm_onsite',
            'dora_get_checkout_url',
        ] as $action ) {
            add_action( 'wp_ajax_' . $action,        [ $this, 'handle_' . $action ] );
            add_action( 'wp_ajax_nopriv_' . $action, [ $this, 'handle_' . $action ] );
        }
    }

    public function enqueue_assets(): void {
        if ( ! $this->page_has_shortcode() ) return;
        wp_enqueue_style( 'dora-booking', plugins_url( 'assets/booking.css', DORA_PATH . 'dora-booking.php' ), [], DORA_VERSION );
        wp_enqueue_script( 'dora-booking', plugins_url( 'assets/booking.js', DORA_PATH . 'dora-booking.php' ), [ 'jquery' ], DORA_VERSION, true );
        wp_localize_script( 'dora-booking', 'doraBooking', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'dora_booking_nonce' ),
            'lang'    => get_locale() === 'hu_HU' ? 'hu' : 'en',
        ] );
    }

    public function render_shortcode(): string {
        ob_start();
        include DORA_PATH . 'templates/form/step-1-service.php';
        return ob_get_clean();
    }

    // ── AJAX handlers ──────────────────────────────────────────────

    public function handle_dora_get_services(): void {
        $this->verify_nonce();
        global $wpdb;
        $services = $wpdb->get_results(
            "SELECT s.id, s.title, s.duration, c.max_persons, c.meeting_point,
                    (SELECT ss.staff_id FROM {$wpdb->prefix}bookly_staff_services ss
                     WHERE ss.service_id = s.id LIMIT 1) AS staff_id
             FROM {$wpdb->prefix}bookly_services s
             LEFT JOIN {$wpdb->prefix}dora_service_config c ON c.service_id = s.id
             WHERE s.visibility = 'public'
             ORDER BY s.title ASC"
        );
        wp_send_json_success( $services );
    }

    public function handle_dora_get_price(): void {
        $this->verify_nonce();
        $service_id = absint( $_POST['service_id'] ?? 0 );
        $persons    = absint( $_POST['persons']    ?? 0 );
        if ( ! $service_id || ! $persons ) wp_send_json_error( 'invalid_params' );

        $engine = new Dora_Pricing_Engine();
        $price  = $engine->get_price( $service_id, $persons );
        wp_send_json_success( $price );
    }

    public function handle_dora_get_available_days(): void {
        $this->verify_nonce();
        $service_id  = absint( $_POST['service_id']  ?? 0 );
        $staff_id    = absint( $_POST['staff_id']    ?? 0 );
        $month_start = sanitize_text_field( $_POST['month_start'] ?? '' );
        $month_end   = sanitize_text_field( $_POST['month_end']   ?? '' );
        if ( ! $service_id || ! $staff_id ) wp_send_json_error( 'invalid_params' );

        $slot_times = $this->get_slot_times( $staff_id );
        $duration   = $this->get_service_duration( $service_id );

        $engine = new Dora_Availability_Engine();
        $days   = $engine->get_available_days( $staff_id, $service_id, $month_start, $month_end, $slot_times, $duration );
        wp_send_json_success( $days );
    }

    public function handle_dora_get_available_slots(): void {
        $this->verify_nonce();
        $staff_id   = absint( $_POST['staff_id']   ?? 0 );
        $service_id = absint( $_POST['service_id'] ?? 0 );
        $date       = sanitize_text_field( $_POST['date'] ?? '' );
        if ( ! $staff_id || ! $date ) wp_send_json_error( 'invalid_params' );

        $slot_times = $this->get_slot_times( $staff_id );
        $duration   = $this->get_service_duration( $service_id );

        $engine = new Dora_Availability_Engine();
        $slots  = $engine->get_available_slots_for_day( $staff_id, $date, $slot_times, $duration );
        wp_send_json_success( $slots );
    }

    public function handle_dora_create_pending(): void {
        $this->verify_nonce();
        $data = $this->sanitize_booking_input( $_POST );
        if ( is_wp_error( $data ) ) wp_send_json_error( $data->get_error_message() );

        // Server-side price verification
        $engine = new Dora_Pricing_Engine();
        $price  = $engine->get_price( (int) $data['service_id'], (int) $data['persons'] );
        if ( ! $price ) wp_send_json_error( 'no_price_tier' );
        $data['total_price'] = $price['total'];
        $data['currency']    = $price['currency'];

        $manager    = new Dora_Booking_Manager( new Dora_Availability_Engine(), $engine );
        $booking_id = $manager->create_pending( $data );

        if ( ! $booking_id ) wp_send_json_error( 'slot_taken' );
        wp_send_json_success( [ 'booking_id' => $booking_id ] );
    }

    public function handle_dora_confirm_onsite(): void {
        $this->verify_nonce();
        $booking_id = absint( $_POST['booking_id'] ?? 0 );
        if ( ! $booking_id ) wp_send_json_error( 'invalid_params' );

        $manager = new Dora_Booking_Manager( new Dora_Availability_Engine(), new Dora_Pricing_Engine() );
        $ok      = $manager->confirm( $booking_id );
        if ( ! $ok ) wp_send_json_error( 'confirm_failed' );

        $booking = $manager->get( $booking_id );
        wp_send_json_success( [
            'booking_ref' => '#' . str_pad( (string) $booking_id, 6, '0', STR_PAD_LEFT ),
            'cancel_url'  => site_url( '/foglalas/lemondas/' ) . '?token=' . $booking->cancel_token,
        ] );
    }

    public function handle_dora_get_checkout_url(): void {
        $this->verify_nonce();
        $booking_id = absint( $_POST['booking_id'] ?? 0 );
        if ( ! $booking_id ) wp_send_json_error( 'invalid_params' );

        $manager = new Dora_Booking_Manager( new Dora_Availability_Engine(), new Dora_Pricing_Engine() );
        $bridge  = new Dora_WooCommerce_Bridge( $manager );
        $result  = $bridge->create_order( $booking_id );

        if ( ! $result ) wp_send_json_error( 'wc_order_failed' );
        wp_send_json_success( $result );
    }

    // ── Helpers ────────────────────────────────────────────────────

    private function verify_nonce(): void {
        if ( ! check_ajax_referer( 'dora_booking_nonce', 'nonce', false ) ) {
            wp_send_json_error( 'invalid_nonce' );
        }
    }

    private function sanitize_booking_input( array $post ): array|\WP_Error {
        $required = [ 'service_id', 'staff_id', 'start_datetime', 'end_datetime', 'persons', 'payment_type', 'customer_name', 'customer_email' ];
        foreach ( $required as $key ) {
            if ( empty( $post[ $key ] ) ) return new \WP_Error( 'missing_field', "Missing: $key" );
        }

        $payment_type = sanitize_text_field( $post['payment_type'] );
        if ( ! in_array( $payment_type, [ 'onsite', 'stripe', 'paypal' ], true ) ) {
            return new \WP_Error( 'invalid_payment_type', 'Invalid payment_type' );
        }

        return [
            'service_id'     => absint( $post['service_id'] ),
            'staff_id'       => absint( $post['staff_id'] ),
            'start_datetime' => sanitize_text_field( $post['start_datetime'] ),
            'end_datetime'   => sanitize_text_field( $post['end_datetime'] ),
            'persons'        => absint( $post['persons'] ),
            'payment_type'   => $payment_type,
            'lang'           => in_array( $post['lang'] ?? 'hu', [ 'hu', 'en' ], true ) ? $post['lang'] : 'hu',
            'customer_name'  => sanitize_text_field( $post['customer_name'] ),
            'customer_email' => sanitize_email( $post['customer_email'] ),
            'customer_phone' => sanitize_text_field( $post['customer_phone'] ?? '' ),
            'customer_notes' => sanitize_textarea_field( $post['customer_notes'] ?? '' ),
        ];
    }

    private function get_slot_times( int $staff_id ): array {
        $times = get_option( "dora_staff_{$staff_id}_slot_times", '' );
        if ( $times ) {
            return array_filter( array_map( 'trim', explode( ',', $times ) ) );
        }
        return [ '09:00', '14:00' ];
    }

    private function get_service_duration( int $service_id ): int {
        global $wpdb;
        $d = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT duration FROM {$wpdb->prefix}bookly_services WHERE id = %d",
            $service_id
        ) );
        return $d ?: 60;
    }

    private function page_has_shortcode(): bool {
        global $post;
        return is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'dora_booking' );
    }
}

// Cancel page handler
add_action( 'template_redirect', function () {
    if ( strpos( $_SERVER['REQUEST_URI'] ?? '', '/foglalas/lemondas/' ) === false ) return;
    $token = sanitize_text_field( $_GET['token'] ?? '' );
    if ( ! $token ) return;

    $manager = new Dora_Booking_Manager( new Dora_Availability_Engine(), new Dora_Pricing_Engine() );
    $result  = $manager->cancel_by_token( $token );

    $messages = [
        'ok'            => 'Foglalásod sikeresen lemondtad. / Your booking has been cancelled.',
        'not_found'     => 'Érvénytelen lemondási link. / Invalid cancellation link.',
        'already_used'  => 'Ez a lemondási link már fel lett használva. / This cancellation link has already been used.',
        'not_confirmed' => 'A foglalás nincs megerősített állapotban. / Booking is not in confirmed status.',
        'past_deadline' => 'A lemondási határidő lejárt. Kérjük vegye fel velünk a kapcsolatot. / Cancellation deadline has passed. Please contact us.',
        'db_error'      => 'Adatbázis hiba. Kérjük próbálja újra. / Database error. Please try again.',
    ];

    wp_die( esc_html( $messages[ $result ] ?? 'Hiba történt. / An error occurred.' ), 'DoraBooking', [ 'response' => $result === 'ok' ? 200 : 400 ] );
} );

// Bootstrap
add_action( 'init', function () {
    new Dora_Booking_Form();
} );
