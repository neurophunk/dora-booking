<?php

if ( ! defined( 'DORA_VERSION' ) ) {
    define( 'ABSPATH', '/tmp/' );
    require_once __DIR__ . '/../dora-booking.php';
}

use PHPUnit\Framework\TestCase;

// Stub WC_Order_Item_Product — not available outside WP
if ( ! class_exists('WC_Order_Item_Product') ) {
    class WC_Order_Item_Product {
        public function set_name($v) {}
        public function set_quantity($v) {}
        public function set_total($v) {}
        public function add_meta_data($k, $v) {}
    }
}

class WooCommerceBridgeTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        \Brain\Monkey\setUp();
        global $wpdb;
        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
    }

    protected function tearDown(): void {
        \Brain\Monkey\tearDown();
        Mockery::close();
        parent::tearDown();
    }

    private function make_bridge(): Dora_WooCommerce_Bridge {
        return new Dora_WooCommerce_Bridge(
            new Dora_Booking_Manager(
                new Dora_Availability_Engine(),
                new Dora_Pricing_Engine()
            )
        );
    }

    public function test_create_order_returns_null_when_booking_not_found(): void {
        global $wpdb;
        $wpdb->shouldReceive('prepare')->andReturn('SQL');
        $wpdb->shouldReceive('get_row')->andReturn(null);

        $this->assertNull($this->make_bridge()->create_order(999));
    }

    public function test_create_order_returns_order_id_and_checkout_url(): void {
        global $wpdb;

        $booking = (object)[
            'id' => 1, 'service_id' => 2, 'staff_id' => 1,
            'start_datetime' => '2026-04-01 09:00:00',
            'end_datetime'   => '2026-04-01 10:00:00',
            'persons' => 2, 'total_price' => '120.00', 'currency' => 'EUR',
            'payment_type' => 'stripe', 'customer_name' => 'Test', 'customer_email' => 't@t.com',
            'customer_phone' => '', 'status' => 'pending',
        ];
        $wpdb->shouldReceive('prepare')->andReturn('SQL');
        $wpdb->shouldReceive('get_row')->andReturn($booking);
        $wpdb->shouldReceive('get_var')->andReturn('Budapest City Tour');
        $wpdb->shouldReceive('update')->andReturn(1);

        $order_mock = Mockery::mock('WC_Order');
        $order_mock->shouldReceive('get_id')->andReturn(99);
        $order_mock->shouldReceive('get_checkout_payment_url')->andReturn('https://dorabudapest.com/checkout/order-pay/99/');
        $order_mock->shouldReceive('add_item')->andReturn(true);
        $order_mock->shouldReceive('set_billing_first_name')->andReturn();
        $order_mock->shouldReceive('set_billing_email')->andReturn();
        $order_mock->shouldReceive('set_billing_phone')->andReturn();
        $order_mock->shouldReceive('set_currency')->andReturn();
        $order_mock->shouldReceive('calculate_totals')->andReturn();
        $order_mock->shouldReceive('save')->andReturn(99);

        \Brain\Monkey\Functions\expect('wc_create_order')->once()->andReturn($order_mock);
        \Brain\Monkey\Functions\expect('is_wp_error')->once()->andReturn(false);

        $result = $this->make_bridge()->create_order(1);

        $this->assertIsArray($result);
        $this->assertSame(99, $result['order_id']);
        $this->assertStringContainsString('checkout', $result['checkout_url']);
    }

    public function test_on_payment_complete_skips_if_no_booking(): void {
        global $wpdb;
        $wpdb->shouldReceive('prepare')->andReturn('SQL');
        $wpdb->shouldReceive('get_row')->andReturn(null);
        \Brain\Monkey\Functions\expect('wc_get_order')->never();

        Dora_WooCommerce_Bridge::on_payment_complete(42);
        $this->assertTrue(true);
    }

    public function test_on_payment_complete_rejects_if_order_total_too_low(): void {
        global $wpdb;
        $booking = (object)['id' => 1, 'total_price' => '120.00'];
        $wpdb->shouldReceive('prepare')->andReturn('SQL');
        $wpdb->shouldReceive('get_row')->andReturn($booking);
        $wpdb->shouldReceive('insert')->never();

        $order_mock = Mockery::mock('WC_Order');
        $order_mock->shouldReceive('get_total')->andReturn('50.00');
        $order_mock->shouldReceive('add_order_note')->once();
        \Brain\Monkey\Functions\expect('wc_get_order')->once()->andReturn($order_mock);

        Dora_WooCommerce_Bridge::on_payment_complete(99);
        $this->assertTrue(true);
    }
}
