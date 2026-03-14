<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class Dora_Booking_Manager {

    private Dora_Availability_Engine $availability;
    private Dora_Pricing_Engine $pricing;
    private Dora_Email_Service $email_service;

    public function __construct(
        Dora_Availability_Engine $availability,
        Dora_Pricing_Engine $pricing,
        ?Dora_Email_Service $email_service = null
    ) {
        $this->availability  = $availability;
        $this->pricing       = $pricing;
        $this->email_service = $email_service ?? new Dora_Email_Service();
    }

    /**
     * Create a pending booking inside a DB transaction.
     * Re-checks availability with SELECT FOR UPDATE to prevent race conditions.
     * Returns booking ID or null if slot taken.
     */
    public function create_pending( array $data ): ?int {
        global $wpdb;

        $duration       = $this->availability->get_duration( (int) $data['service_id'] );
        $start_datetime = $data['start_datetime'];
        $end_datetime   = ( new DateTime( $start_datetime, new DateTimeZone( 'UTC' ) ) )
            ->modify( "+{$duration} minutes" )
            ->format( 'Y-m-d H:i:s' );

        $wpdb->query('START TRANSACTION');

        if ( ! $this->availability->is_slot_free(
            (int) $data['service_id'],
            $start_datetime,
            $duration
        ) ) {
            $wpdb->query('ROLLBACK');
            return null;
        }

        $token = bin2hex( random_bytes(32) );

        $inserted = $wpdb->insert(
            $wpdb->prefix . 'dora_bookings',
            [
                'appointment_id'          => null,
                'customer_appointment_id' => null,
                'payment_id'              => null,
                'service_id'              => (int) $data['service_id'],
                'staff_id'                => 0,
                'start_datetime'          => $start_datetime,
                'end_datetime'            => $end_datetime,
                'persons'                 => (int) $data['persons'],
                'total_price'             => (float) $data['total_price'],
                'currency'                => $data['currency'],
                'payment_type'            => $data['payment_type'],
                'status'                  => 'pending',
                'lang'                    => $data['lang'] ?? 'hu',
                'customer_name'           => $data['customer_name'],
                'customer_email'          => $data['customer_email'],
                'customer_phone'          => $data['customer_phone'] ?? null,
                'customer_notes'          => $data['customer_notes'] ?? null,
                'cancel_token'            => $token,
                'created_at'              => gmdate('Y-m-d H:i:s'),
            ],
            [ '%d','%d','%d','%d','%d','%s','%s','%d','%f','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s' ]
        );

        if ( ! $inserted ) {
            $wpdb->query('ROLLBACK');
            return null;
        }

        $booking_id = (int) $wpdb->insert_id;
        $wpdb->query('COMMIT');

        return $booking_id;
    }

    /**
     * Confirm a booking: update dora_bookings status only (no Bookly writes).
     */
    public function confirm( int $booking_id, ?int $wc_order_id = null ): bool {
        global $wpdb;

        $booking = $this->get( $booking_id );
        if ( ! $booking || $booking->status !== 'pending' ) return false;

        $wpdb->query('START TRANSACTION');

        $update         = [ 'status' => 'confirmed' ];
        $update_formats = [ '%s' ];

        if ( $wc_order_id ) {
            $update['wc_order_id'] = $wc_order_id;
            $update_formats[]      = '%d';
        }

        $updated = $wpdb->update(
            $wpdb->prefix . 'dora_bookings',
            $update,
            [ 'id' => $booking_id ],
            $update_formats,
            ['%d']
        );

        if ( $updated === false ) {
            $wpdb->query('ROLLBACK');
            return false;
        }

        $wpdb->query('COMMIT');

        // Send confirmation email + admin notification
        $this->email_service->send_confirmation( $booking_id );

        return true;
    }

    /**
     * Cancel by token. Returns: 'ok' | 'not_found' | 'already_used' | 'not_confirmed' | 'past_deadline'
     */
    public function cancel_by_token( string $token ): string {
        global $wpdb;

        $booking = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dora_bookings WHERE cancel_token = %s",
            $token
        ) );

        if ( ! $booking ) return 'not_found';
        if ( $booking->cancel_token_used_at !== null ) return 'already_used';
        if ( $booking->status !== 'confirmed' ) return 'not_confirmed';

        $deadline_hours = (int) get_option('dora_cancellation_deadline_hours', 24);
        $start    = new DateTime( $booking->start_datetime, new DateTimeZone('UTC') );
        $deadline = clone $start;
        $deadline->modify( "-{$deadline_hours} hours" );
        $now = new DateTime('now', new DateTimeZone('UTC'));
        if ( $now >= $deadline ) return 'past_deadline';

        $wpdb->query('START TRANSACTION');

        $result1 = $wpdb->update(
            $wpdb->prefix . 'dora_bookings',
            [ 'status' => 'cancelled', 'cancel_token_used_at' => gmdate('Y-m-d H:i:s') ],
            [ 'id' => (int) $booking->id ],
            ['%s','%s'], ['%d']
        );

        if ( $result1 === false ) {
            $wpdb->query('ROLLBACK');
            return 'db_error';
        }

        $wpdb->query('COMMIT');

        if ( $booking->wc_order_id && in_array($booking->payment_type, ['stripe','paypal'], true) ) {
            if ( function_exists('wc_get_order') ) {
                $order = wc_get_order( (int) $booking->wc_order_id );
                if ( $order ) {
                    $order->add_order_note('DoraBooking: Booking cancelled by customer. Manual refund required.');
                }
            }
        }

        $this->email_service->send_cancellation( (int) $booking->id );

        return 'ok';
    }

    /**
     * Cleanup pending bookings older than 2 hours (daily cron job).
     */
    public static function cleanup_pending(): void {
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->prefix}dora_bookings
             WHERE status = 'pending'
               AND created_at < UTC_TIMESTAMP() - INTERVAL 2 HOUR"
        );
    }

    public function get( int $booking_id ): ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dora_bookings WHERE id = %d",
            $booking_id
        ) );
    }
}
