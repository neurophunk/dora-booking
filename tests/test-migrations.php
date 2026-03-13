<?php
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

// Load the plugin (defines constants and functions)
if ( ! defined( 'DORA_VERSION' ) ) {
    define( 'ABSPATH', '/tmp/' );
    require_once __DIR__ . '/../dora-booking.php';
}

class MigrationsTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        \Brain\Monkey\setUp();

        global $wpdb;
        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->shouldReceive('get_charset_collate')->andReturn('DEFAULT CHARSET=utf8mb4');
    }

    protected function tearDown(): void {
        \Brain\Monkey\tearDown();
        Mockery::close();
        parent::tearDown();
    }

    public function test_migration_skipped_if_version_matches(): void {
        Functions\expect('get_option')
            ->once()
            ->with('dora_booking_db_version', '0')
            ->andReturn(DORA_DB_VERSION);

        Functions\expect('update_option')->never();
        Functions\expect('dbDelta')->never();

        dora_run_migrations();
    }

    public function test_migration_runs_update_option_if_version_old(): void {
        Functions\expect('get_option')
            ->once()
            ->with('dora_booking_db_version', '0')
            ->andReturn('0');

        Functions\stubs(['dbDelta' => null]);
        Functions\expect('update_option')
            ->once()
            ->with('dora_booking_db_version', DORA_DB_VERSION);

        dora_run_migrations();
    }
}
