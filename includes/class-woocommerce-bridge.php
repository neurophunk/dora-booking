<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class Dora_WooCommerce_Bridge {

    private Dora_Booking_Manager $manager;

    public function __construct( Dora_Booking_Manager $manager ) {
        $this->manager = $manager;
    }

    /**
     * Create a WooCommerce order for a pending booking.
     * Returns ['order_id' => int, 'checkout_url' => string] or null on failure.
     */
    public function create_order( int $booking_id ): ?array {
        $booking = $this->manager->get( $booking_id );
        if ( ! $booking ) return null;

        $service_name = $this->get_service_name( (int) $booking->service_id );

        $order = wc_create_order();
        if ( is_wp_error( $order ) ) return null;

        $item = new WC_Order_Item_Product();
        $item->set_name( $service_name . ' × ' . $booking->persons . ' fő' );
        $item->set_quantity( 1 );
        $item->set_total( (float) $booking->total_price );
        $item->add_meta_data( '_dora_booking_id', $booking_id );
        $item->add_meta_data( '_dora_service_id', $booking->service_id );
        $item->add_meta_data( '_dora_staff_id',   $booking->staff_id );
        $item->add_meta_data( '_dora_datetime',   $booking->start_datetime );
        $item->add_meta_data( '_dora_persons',    $booking->persons );
        $order->add_item( $item );

        $order->set_billing_first_name( $booking->customer_name );
        $order->set_billing_email( $booking->customer_email );
        $order->set_billing_phone( $booking->customer_phone ?? '' );
        $order->set_currency( $booking->currency );
        $order->calculate_totals();
        $order->save();

        $order_id = $order->get_id();

        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'dora_bookings',
            [ 'wc_order_id' => $order_id ],
            [ 'id'          => $booking_id ],
            [ '%d' ],
            [ '%d' ]
        );

        return [
            'order_id'     => $order_id,
            'checkout_url' => $order->get_checkout_payment_url(),
        ];
    }

    /**
     * Hook: woocommerce_payment_complete
     * Fires after Stripe / PayPal confirms payment.
     */
    public static function on_payment_complete( int $order_id ): void {
        global $wpdb;

        $booking = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, total_price FROM {$wpdb->prefix}dora_bookings
             WHERE wc_order_id = %d LIMIT 1",
            $order_id
        ) );
        if ( ! $booking ) return;

        $order = wc_get_order( $order_id );
        if ( $order && (float) $order->get_total() < (float) $booking->total_price ) {
            $order->add_order_note( 'DoraBooking: Order total mismatch. Booking NOT confirmed. Manual review required.' );
            return;
        }

        $manager = new Dora_Booking_Manager(
            new Dora_Availability_Engine(),
            new Dora_Pricing_Engine()
        );
        $manager->confirm( (int) $booking->id, $order_id );
    }

    /**
     * Register WooCommerce hooks (called on woocommerce_loaded).
     */
    public static function register_hooks(): void {
        add_action( 'woocommerce_payment_complete', [ static::class, 'on_payment_complete' ] );
    }

    private function get_service_name( int $service_id ): string {
        global $wpdb;
        $title = $wpdb->get_var( $wpdb->prepare(
            "SELECT title FROM {$wpdb->prefix}bookly_services WHERE id = %d",
            $service_id
        ) );
        return $title ?: 'Booking';
    }
}
