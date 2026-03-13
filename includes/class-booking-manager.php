<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class Dora_Booking_Manager {

    private Dora_Availability_Engine $availability;
    private Dora_Pricing_Engine $pricing;
    private Dora_Email_Service $email_service;

    public function __construct(
        Dora_Availability_Engine $availability,
        Dora_Pricing_Engine $pricing,
        Dora_Email_Service $email_service = null
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

        $wpdb->query('START TRANSACTION');

        if ( ! $this->availability->is_slot_free(
            (int) $data['staff_id'],
            $data['start_datetime'],
            $data['end_datetime']
        ) ) {
            $wpdb->query('ROLLBACK');
            return null;
        }

        $token = bin2hex( random_bytes(32) );

        $inserted = $wpdb->insert(
            $wpdb->prefix . 'dora_bookings',
            [
                'service_id'     => (int) $data['service_id'],
                'staff_id'       => (int) $data['staff_id'],
                'start_datetime' => $data['start_datetime'],
                'end_datetime'   => $data['end_datetime'],
                'persons'        => (int) $data['persons'],
                'total_price'    => (float) $data['total_price'],
                'currency'       => $data['currency'],
                'payment_type'   => $data['payment_type'],
                'status'         => 'pending',
                'lang'           => $data['lang'] ?? 'hu',
                'customer_name'  => $data['customer_name'],
                'customer_email' => $data['customer_email'],
                'customer_phone' => $data['customer_phone'] ?? null,
                'customer_notes' => $data['customer_notes'] ?? null,
                'cancel_token'   => $token,
                'created_at'     => gmdate('Y-m-d H:i:s'),
            ],
            [ '%d','%d','%s','%s','%d','%f','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s' ]
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
     * Confirm a booking: write to Bookly tables, update dora_bookings status.
     */
    public function confirm( int $booking_id, ?int $wc_order_id = null ): bool {
        global $wpdb;

        $booking = $this->get( $booking_id );
        if ( ! $booking || $booking->status !== 'pending' ) return false;

        $wpdb->query('START TRANSACTION');

        // 1. Upsert bookly_customers (key: email)
        $wpdb->query( $wpdb->prepare(
            "INSERT INTO {$wpdb->prefix}bookly_customers (full_name, email, phone)
             VALUES (%s, %s, %s)
             ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), phone = VALUES(phone)",
            $booking->customer_name,
            $booking->customer_email,
            $booking->customer_phone ?? ''
        ) );
        $customer_id = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}bookly_customers WHERE email = %s",
            $booking->customer_email
        ) );

        // Fix 4: Guard missing customer_id
        if ( ! $customer_id ) {
            $wpdb->query('ROLLBACK');
            return false;
        }

        // 2. Insert bookly_appointments
        $wpdb->insert( $wpdb->prefix . 'bookly_appointments', [
            'staff_id'   => (int) $booking->staff_id,
            'service_id' => (int) $booking->service_id,
            'start_date' => $booking->start_datetime,
            'end_date'   => $booking->end_datetime,
            'created'    => gmdate('Y-m-d H:i:s'),
            'updated'    => gmdate('Y-m-d H:i:s'),
        ], ['%d','%d','%s','%s','%s','%s'] );
        $appt_id = (int) $wpdb->insert_id;

        // Fix 2: Guard missing appt_id
        if ( ! $appt_id ) {
            $wpdb->query('ROLLBACK');
            return false;
        }

        // 3. Insert bookly_customer_appointments
        $wpdb->insert( $wpdb->prefix . 'bookly_customer_appointments', [
            'appointment_id'    => $appt_id,
            'customer_id'       => $customer_id,
            'status'            => 'approved',
            'number_of_persons' => (int) $booking->persons,
            'created'           => gmdate('Y-m-d H:i:s'),
            'updated'           => gmdate('Y-m-d H:i:s'),
        ], ['%d','%d','%s','%d','%s','%s'] );
        $ca_id = (int) $wpdb->insert_id;

        // Fix 2: Guard missing ca_id
        if ( ! $ca_id ) {
            $wpdb->query('ROLLBACK');
            return false;
        }

        // 4. Insert bookly_payments
        $payment_method = $wc_order_id ? 'woocommerce' : 'local';
        $payment_status = $wc_order_id ? 'completed'   : 'pending';
        $wpdb->insert( $wpdb->prefix . 'bookly_payments', [
            'appointment_id' => $appt_id,
            'type'           => $payment_method,
            'total'          => (float) $booking->total_price,
            'status'         => $payment_status,
            'created'        => gmdate('Y-m-d H:i:s'),
        ], ['%d','%s','%f','%s','%s'] );
        $payment_id = (int) $wpdb->insert_id;

        // Fix 2: Guard missing payment_id
        if ( ! $payment_id ) {
            $wpdb->query('ROLLBACK');
            return false;
        }

        // 5. Update wp_dora_bookings
        // Fix 7: Use proper per-field format specifiers (%d for integers, %s for strings)
        $update = [
            'status'                  => 'confirmed',
            'appointment_id'          => $appt_id,
            'customer_appointment_id' => $ca_id,
            'payment_id'              => $payment_id,
        ];
        $update_formats = [ '%s', '%d', '%d', '%d' ];

        if ( $wc_order_id ) {
            $update['wc_order_id'] = $wc_order_id;
            $update_formats[]      = '%d';
        }

        $wpdb->update(
            $wpdb->prefix . 'dora_bookings',
            $update,
            [ 'id' => $booking_id ],
            $update_formats,
            ['%d']
        );

        $wpdb->query('COMMIT');

        // 6. Send confirmation email + admin notification
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

        // Fix 3: Wrap both updates in a transaction
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

        if ( $booking->customer_appointment_id ) {
            $result2 = $wpdb->update(
                $wpdb->prefix . 'bookly_customer_appointments',
                [ 'status' => 'cancelled' ],
                [ 'id' => (int) $booking->customer_appointment_id ],
                ['%s'], ['%d']
            );

            if ( $result2 === false ) {
                $wpdb->query('ROLLBACK');
                return 'db_error';
            }
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
        // Fix 5: Use UTC_TIMESTAMP() instead of NOW() since created_at is stored as UTC
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
