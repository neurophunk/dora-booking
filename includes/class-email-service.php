<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class Dora_Email_Service {

    /**
     * Replace {placeholder} tokens in a template string with their values.
     *
     * @param string $template Raw template string.
     * @param array  $vars     Map of '{token}' => 'value'.
     * @return string
     */
    public function render_template( string $template, array $vars ): string {
        return str_replace( array_keys( $vars ), array_values( $vars ), $template );
    }

    /**
     * Build the full variable map for a booking object.
     *
     * @param object $booking Row from wp_dora_bookings.
     * @return array
     */
    public function build_vars( object $booking ): array {
        global $wpdb;

        $tz = wp_timezone();
        $dt = new DateTime( $booking->start_datetime, new DateTimeZone( 'UTC' ) );
        $dt->setTimezone( $tz );

        $service_title = (string) $wpdb->get_var( $wpdb->prepare(
            "SELECT title FROM {$wpdb->prefix}bookly_services WHERE id = %d",
            $booking->service_id
        ) );

        $guide_name = (string) $wpdb->get_var( $wpdb->prepare(
            "SELECT full_name FROM {$wpdb->prefix}bookly_staff WHERE id = %d",
            $booking->staff_id
        ) );

        $config = $wpdb->get_row( $wpdb->prepare(
            "SELECT meeting_point FROM {$wpdb->prefix}dora_service_config WHERE service_id = %d",
            $booking->service_id
        ) );
        $meeting_point = $config->meeting_point ?? '';

        $payment_labels = [
            'onsite' => 'Helyszíni fizetés / On-site payment',
            'stripe' => 'Online — Stripe',
            'paypal' => 'Online — PayPal',
        ];

        return [
            '{name}'          => esc_html( $booking->customer_name ),
            '{service}'       => esc_html( $service_title ),
            '{date}'          => $dt->format( 'Y-m-d' ),
            '{time}'          => $dt->format( 'H:i' ),
            '{persons}'       => (string) $booking->persons,
            '{total}'         => number_format( (float) $booking->total_price, 2 ),
            '{currency}'      => $booking->currency,
            '{payment_type}'  => $payment_labels[ $booking->payment_type ] ?? $booking->payment_type,
            '{meeting_point}' => esc_html( $meeting_point ),
            '{guide_name}'    => esc_html( $guide_name ),
            '{cancel_url}'    => site_url( '/foglalas/lemondas/' ) . '?token=' . $booking->cancel_token,
            '{booking_ref}'   => '#' . str_pad( (string) $booking->id, 6, '0', STR_PAD_LEFT ),
        ];
    }

    /**
     * Send a single email of the given type for a booking.
     *
     * Looks up a DB template first; falls back to a PHP file template.
     * Logs the result to wp_dora_email_log.
     *
     * @param int    $booking_id
     * @param string $type  'confirmation' | 'reminder' | 'cancellation'
     * @return bool  True if wp_mail succeeded.
     */
    public function send( int $booking_id, string $type ): bool {
        global $wpdb;

        $booking = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dora_bookings WHERE id = %d",
            $booking_id
        ) );
        if ( ! $booking ) return false;

        $lang = $booking->lang ?? 'hu';
        $vars = $this->build_vars( $booking );

        // Prefer DB-stored template over file template.
        $tpl = $wpdb->get_row( $wpdb->prepare(
            "SELECT subject, body FROM {$wpdb->prefix}dora_email_templates
             WHERE type = %s AND lang = %s",
            $type, $lang
        ) );

        if ( $tpl ) {
            $subject = $this->render_template( $tpl->subject, $vars );
            $body    = $this->render_template( $tpl->body,    $vars );
        } else {
            $file = DORA_PATH . "templates/emails/{$type}-{$lang}.php";
            if ( ! file_exists( $file ) ) return false;
            ob_start();
            include $file;
            $body = ob_get_clean();
            $subject = $this->default_subject( $type, $lang, $vars );
        }

        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        $sent    = wp_mail( $booking->customer_email, $subject, $body, $headers );

        $wpdb->insert(
            $wpdb->prefix . 'dora_email_log',
            [
                'booking_id' => $booking_id,
                'type'       => $type,
                'lang'       => $lang,
                'recipient'  => $booking->customer_email,
                'sent_at'    => gmdate( 'Y-m-d H:i:s' ),
                'status'     => $sent ? 'sent' : 'failed',
            ],
            [ '%d', '%s', '%s', '%s', '%s', '%s' ]
        );

        return $sent;
    }

    /**
     * Send a booking confirmation email and trigger admin notification.
     *
     * @param int $booking_id
     * @return bool
     */
    public function send_confirmation( int $booking_id ): bool {
        $result = $this->send( $booking_id, 'confirmation' );
        $this->send_admin_notification( $booking_id );
        return $result;
    }

    /**
     * Send a booking cancellation email.
     *
     * @param int $booking_id
     * @return bool
     */
    public function send_cancellation( int $booking_id ): bool {
        return $this->send( $booking_id, 'cancellation' );
    }

    /**
     * Send an internal admin notification for a new booking.
     *
     * @param int $booking_id
     */
    public function send_admin_notification( int $booking_id ): void {
        global $wpdb;

        $booking = $wpdb->get_row( $wpdb->prepare(
            "SELECT b.*, s.title as service_title
               FROM {$wpdb->prefix}dora_bookings b
               LEFT JOIN {$wpdb->prefix}bookly_services s ON s.id = b.service_id
              WHERE b.id = %d",
            $booking_id
        ) );
        if ( ! $booking ) return;

        $admin_email = get_option( 'dora_admin_notification_email' ) ?: get_option( 'admin_email' );

        $tz = wp_timezone();
        $dt = new DateTime( $booking->start_datetime, new DateTimeZone( 'UTC' ) );
        $dt->setTimezone( $tz );

        $ref     = str_pad( (string) $booking->id, 6, '0', STR_PAD_LEFT );
        $subject = sprintf( '[DoraBooking] New booking #%s — %s', $ref, $booking->service_title );

        $body  = "<p><strong>Booking #{$ref}</strong></p>";
        $body .= "<p>Service: " . esc_html( $booking->service_title ) . "<br>";
        $body .= "Date: " . $dt->format( 'Y-m-d H:i' ) . " (Budapest)<br>";
        $body .= "Persons: {$booking->persons}<br>";
        $body .= "Total: {$booking->total_price} {$booking->currency}<br>";
        $body .= "Payment: {$booking->payment_type}<br>";
        $body .= "Customer: " . esc_html( $booking->customer_name )
               . " &lt;" . esc_html( $booking->customer_email ) . "&gt;</p>";

        wp_mail( $admin_email, $subject, $body, [ 'Content-Type: text/html; charset=UTF-8' ] );
    }

    /**
     * Cron callback: send reminder emails for bookings starting in ~24 hours
     * that have not yet received a reminder.
     */
    public static function send_reminders(): void {
        global $wpdb;

        $bookings = $wpdb->get_results(
            "SELECT b.id FROM {$wpdb->prefix}dora_bookings b
              WHERE b.status = 'confirmed'
                AND b.start_datetime BETWEEN UTC_TIMESTAMP() + INTERVAL 23 HOUR
                                         AND UTC_TIMESTAMP() + INTERVAL 25 HOUR
                AND NOT EXISTS (
                    SELECT 1 FROM {$wpdb->prefix}dora_email_log l
                     WHERE l.booking_id = b.id AND l.type = 'reminder'
                )"
        ) ?: [];

        $service = new self();
        foreach ( $bookings as $row ) {
            $service->send( (int) $row->id, 'reminder' );
        }
    }

    /**
     * Return a default email subject when no DB template is found.
     *
     * @param string $type
     * @param string $lang
     * @param array  $vars
     * @return string
     */
    private function default_subject( string $type, string $lang, array $vars ): string {
        $subjects = [
            'confirmation' => [
                'hu' => 'Foglalás visszaigazolás — ' . $vars['{service}'],
                'en' => 'Booking confirmation — '    . $vars['{service}'],
            ],
            'reminder' => [
                'hu' => 'Emlékeztető: holnap ' . $vars['{time}'] . ' — ' . $vars['{service}'],
                'en' => 'Reminder: tomorrow '   . $vars['{time}'] . ' — ' . $vars['{service}'],
            ],
            'cancellation' => [
                'hu' => 'Foglalás lemondva — ' . $vars['{service}'],
                'en' => 'Booking cancelled — '  . $vars['{service}'],
            ],
        ];
        return $subjects[ $type ][ $lang ] ?? 'DoraBooking notification';
    }
}
