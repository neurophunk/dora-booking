<?php

if ( ! defined( 'DORA_VERSION' ) ) {
    define( 'ABSPATH', '/tmp/' );
    require_once __DIR__ . '/../dora-booking.php';
}

use PHPUnit\Framework\TestCase;

// Stub for not-yet-implemented Email Service
if ( ! class_exists('Dora_Email_Service') ) {
    class Dora_Email_Service {
        public function send_confirmation( int $booking_id ): void {}
        public function send_cancellation( int $booking_id ): void {}
    }
}

class BookingManagerTest extends TestCase {
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

    private function make_manager(): Dora_Booking_Manager {
        return new Dora_Booking_Manager(
            new Dora_Availability_Engine(),
            new Dora_Pricing_Engine()
        );
    }

    private function base_data(): array {
        return [
            'service_id'     => 1,
            'staff_id'       => 1,
            'start_datetime' => '2026-04-01 09:00:00',
            'end_datetime'   => '2026-04-01 10:00:00',
            'persons'        => 2,
            'total_price'    => 120.00,
            'currency'       => 'EUR',
            'payment_type'   => 'onsite',
            'lang'           => 'hu',
            'customer_name'  => 'Test User',
            'customer_email' => 'test@example.com',
        ];
    }

    public function test_create_pending_returns_booking_id_when_slot_free(): void {
        global $wpdb;
        $wpdb->shouldReceive('prepare')->andReturn('SQL');
        $wpdb->shouldReceive('get_var')->andReturn('0'); // slot free
        $wpdb->shouldReceive('query')->andReturn(true);  // START TRANSACTION + COMMIT
        $wpdb->shouldReceive('insert')->andReturn(1);
        $wpdb->insert_id = 42;

        $manager = $this->make_manager();
        $result = $manager->create_pending($this->base_data());

        $this->assertSame(42, $result);
    }

    public function test_create_pending_returns_null_when_slot_taken(): void {
        global $wpdb;
        $wpdb->shouldReceive('prepare')->andReturn('SQL');
        $wpdb->shouldReceive('get_var')->andReturn('1'); // slot taken
        $wpdb->shouldReceive('query')->andReturn(true);  // START TRANSACTION + ROLLBACK

        $manager = $this->make_manager();
        $result = $manager->create_pending($this->base_data());

        $this->assertNull($result);
    }

    public function test_cancel_by_token_returns_not_found_for_unknown_token(): void {
        global $wpdb;
        $wpdb->shouldReceive('prepare')->andReturn('SQL');
        $wpdb->shouldReceive('get_row')->andReturn(null);

        $manager = $this->make_manager();
        $this->assertSame('not_found', $manager->cancel_by_token('badtoken'));
    }

    public function test_cancel_by_token_returns_already_used_when_token_consumed(): void {
        global $wpdb;
        $booking = (object)[
            'id' => 1, 'status' => 'confirmed',
            'cancel_token_used_at' => '2026-04-01 08:00:00',
            'start_datetime' => '2026-04-05 09:00:00',
            'customer_appointment_id' => null, 'wc_order_id' => null, 'payment_type' => 'onsite',
        ];
        $wpdb->shouldReceive('prepare')->andReturn('SQL');
        $wpdb->shouldReceive('get_row')->andReturn($booking);

        $manager = $this->make_manager();
        $this->assertSame('already_used', $manager->cancel_by_token('sometoken'));
    }

    public function test_cancel_by_token_returns_not_confirmed_for_pending(): void {
        global $wpdb;
        $booking = (object)[
            'id' => 1, 'status' => 'pending',
            'cancel_token_used_at' => null,
            'start_datetime' => '2030-04-05 09:00:00',
            'customer_appointment_id' => null, 'wc_order_id' => null, 'payment_type' => 'onsite',
        ];
        $wpdb->shouldReceive('prepare')->andReturn('SQL');
        $wpdb->shouldReceive('get_row')->andReturn($booking);

        $manager = $this->make_manager();
        $this->assertSame('not_confirmed', $manager->cancel_by_token('sometoken'));
    }

    public function test_cancel_by_token_returns_past_deadline(): void {
        global $wpdb;
        // Start is 1 hour from now — inside 24h deadline
        $start = (new DateTime('now', new DateTimeZone('UTC')))->modify('+1 hour')->format('Y-m-d H:i:s');
        $booking = (object)[
            'id' => 1, 'status' => 'confirmed',
            'cancel_token_used_at' => null,
            'start_datetime' => $start,
            'customer_appointment_id' => null, 'wc_order_id' => null, 'payment_type' => 'onsite',
        ];
        $wpdb->shouldReceive('prepare')->andReturn('SQL');
        $wpdb->shouldReceive('get_row')->andReturn($booking);
        \Brain\Monkey\Functions\expect('get_option')
            ->with('dora_cancellation_deadline_hours', 24)->andReturn(24);

        $manager = $this->make_manager();
        $this->assertSame('past_deadline', $manager->cancel_by_token('sometoken'));
    }

    public function test_cancel_by_token_ok_when_before_deadline(): void {
        global $wpdb;
        // Start is 48h from now — well before 24h deadline
        $start = (new DateTime('now', new DateTimeZone('UTC')))->modify('+48 hours')->format('Y-m-d H:i:s');
        $booking = (object)[
            'id' => 5, 'status' => 'confirmed',
            'cancel_token_used_at' => null,
            'start_datetime' => $start,
            'customer_appointment_id' => null,
            'wc_order_id' => null,
            'payment_type' => 'onsite',
            'customer_email' => 'test@example.com',
            'lang' => 'hu',
            'service_id' => 1,
            'staff_id' => 1,
            'persons' => 2,
            'total_price' => '120.00',
            'currency' => 'EUR',
            'cancel_token' => 'sometoken',
        ];
        $wpdb->shouldReceive('prepare')->andReturn('SQL');
        $wpdb->shouldReceive('get_row')->andReturn($booking);
        $wpdb->shouldReceive('update')->andReturn(1);
        $wpdb->shouldReceive('insert')->andReturn(1);
        $wpdb->shouldReceive('get_var')->andReturn('Budapest City Tour');
        $wpdb->shouldReceive('get_results')->andReturn([]);
        \Brain\Monkey\Functions\expect('get_option')
            ->with('dora_cancellation_deadline_hours', 24)->andReturn(24);

        $manager = $this->make_manager();
        $result = $manager->cancel_by_token('sometoken');
        $this->assertSame('ok', $result);
    }
}
