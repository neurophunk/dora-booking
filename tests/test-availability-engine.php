<?php
use PHPUnit\Framework\TestCase;

// Load the plugin so Dora_Availability_Engine class is defined
if ( ! defined( 'DORA_VERSION' ) ) {
    define( 'ABSPATH', '/tmp/' );
    require_once __DIR__ . '/../dora-booking.php';
}

class AvailabilityEngineTest extends TestCase {
    private $wpdb;

    protected function setUp(): void {
        parent::setUp();
        \Brain\Monkey\setUp();
        global $wpdb;
        $wpdb = $this->wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
    }

    protected function tearDown(): void {
        \Brain\Monkey\tearDown();
        Mockery::close();
        parent::tearDown();
    }

    public function test_slot_is_free_when_count_zero(): void {
        $this->wpdb->shouldReceive('prepare')->andReturn('PREPARED_SQL');
        $this->wpdb->shouldReceive('get_var')->with('PREPARED_SQL')->andReturn('0');

        $engine = new Dora_Availability_Engine();
        $this->assertTrue( $engine->is_slot_free(1, '2026-04-01 09:00:00', '2026-04-01 10:00:00') );
    }

    public function test_slot_is_taken_when_count_nonzero(): void {
        $this->wpdb->shouldReceive('prepare')->andReturn('PREPARED_SQL');
        $this->wpdb->shouldReceive('get_var')->with('PREPARED_SQL')->andReturn('1');

        $engine = new Dora_Availability_Engine();
        $this->assertFalse( $engine->is_slot_free(1, '2026-04-01 09:00:00', '2026-04-01 10:00:00') );
    }

    public function test_get_available_days_returns_array_of_dates(): void {
        // Stub is_slot_free to always return true
        $engine = $this->getMockBuilder(Dora_Availability_Engine::class)
            ->onlyMethods(['is_slot_free'])
            ->getMock();
        $engine->method('is_slot_free')->willReturn(true);

        $days = $engine->get_available_days(1, 1, '2026-04-01', '2026-04-30', ['09:00', '14:00'], 60);
        $this->assertIsArray($days);
        $this->assertNotEmpty($days);
    }

    public function test_get_available_days_excludes_past_dates(): void {
        $engine = new Dora_Availability_Engine();
        // Past month: all slots should be past, so no available days
        $this->wpdb->shouldReceive('prepare')->andReturn('PREPARED_SQL');
        $this->wpdb->shouldReceive('get_var')->andReturn('0'); // slot technically free
        // But all slots are in the past, so should return empty
        $days = $engine->get_available_days(1, 1, '2020-01-01', '2020-01-31', ['09:00'], 60);
        $this->assertEmpty($days);
    }

    public function test_get_available_slots_for_day_returns_free_slots(): void {
        $this->wpdb->shouldReceive('prepare')->andReturn('PREPARED_SQL');
        $this->wpdb->shouldReceive('get_var')->andReturn('0'); // slot free

        $engine = new Dora_Availability_Engine();
        // Use a future date
        $slots = $engine->get_available_slots_for_day(1, '2030-06-15', ['09:00', '14:00'], 60);
        $this->assertCount(2, $slots);
        $this->assertArrayHasKey('start', $slots[0]);
        $this->assertArrayHasKey('end', $slots[0]);
        $this->assertArrayHasKey('start_datetime', $slots[0]);
        $this->assertArrayHasKey('end_datetime', $slots[0]);
    }
}
