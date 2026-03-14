<?php
use PHPUnit\Framework\TestCase;

// Load the plugin so Dora_Availability_Engine class is defined
if ( ! defined( 'DORA_VERSION' ) ) {
    define( 'ABSPATH', '/tmp/' );
    require_once __DIR__ . '/../dora-booking.php';
}

class AvailabilityEngineTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        \Brain\Monkey\setUp();
        global $wpdb;
        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        // wp_timezone() is already stubbed in bootstrap.php as a plain PHP function
        // returning DateTimeZone('Europe/Budapest') — no need to re-stub here.
    }

    protected function tearDown(): void {
        \Brain\Monkey\tearDown();
        Mockery::close();
        parent::tearDown();
    }

    // ── get_available_days ────────────────────────────────────

    public function test_get_available_days_returns_empty_for_unknown_service(): void {
        global $wpdb;
        $wpdb->shouldReceive( 'get_row' )->andReturn( null );
        $wpdb->shouldReceive( 'prepare' )->andReturn( '' );

        $engine = new Dora_Availability_Engine();
        $result = $engine->get_available_days( 99, '2026-06' );
        $this->assertSame( [], $result );
    }

    // ── is_slot_free ──────────────────────────────────────────

    public function test_is_slot_free_returns_true_when_no_conflicts(): void {
        global $wpdb;
        $wpdb->shouldReceive( 'prepare' )->andReturn( 'SQL' );
        $wpdb->shouldReceive( 'get_var' )->andReturn( '0' );

        $engine = new Dora_Availability_Engine();
        $this->assertTrue( $engine->is_slot_free( 1, '2026-06-01 07:00:00', 60 ) );
    }

    public function test_is_slot_free_returns_false_when_conflict_exists(): void {
        global $wpdb;
        $wpdb->shouldReceive( 'prepare' )->andReturn( 'SQL' );
        $wpdb->shouldReceive( 'get_var' )->andReturn( '1' );

        $engine = new Dora_Availability_Engine();
        $this->assertFalse( $engine->is_slot_free( 1, '2026-06-01 07:00:00', 60 ) );
    }

    // ── get_available_slots ───────────────────────────────────

    public function test_get_available_slots_returns_empty_for_unknown_service(): void {
        global $wpdb;
        $wpdb->shouldReceive( 'prepare' )->andReturn( '' );
        $wpdb->shouldReceive( 'get_row' )->andReturn( null );

        $engine = new Dora_Availability_Engine();
        $this->assertSame( [], $engine->get_available_slots( 99, '2026-06-02' ) );
    }
}
