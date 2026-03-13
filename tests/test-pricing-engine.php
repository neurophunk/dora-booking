<?php

if ( ! defined( 'DORA_VERSION' ) ) {
    define( 'ABSPATH', '/tmp/' );
    require_once __DIR__ . '/../dora-booking.php';
}

use PHPUnit\Framework\TestCase;

class PricingEngineTest extends TestCase {
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

    public function test_get_price_returns_correct_total(): void {
        global $wpdb;
        $wpdb->shouldReceive('prepare')->andReturn('SQL');
        $wpdb->shouldReceive('get_row')->with('SQL')->andReturn(
            (object)['price_per_person' => '60.00', 'currency' => 'EUR']
        );

        $engine = new Dora_Pricing_Engine();
        $result = $engine->get_price(1, 2);

        $this->assertSame(120.0, $result['total']);
        $this->assertSame(60.0,  $result['price_per_person']);
        $this->assertSame('EUR', $result['currency']);
    }

    public function test_get_price_returns_null_when_no_tier(): void {
        global $wpdb;
        $wpdb->shouldReceive('prepare')->andReturn('SQL');
        $wpdb->shouldReceive('get_row')->with('SQL')->andReturn(null);

        $engine = new Dora_Pricing_Engine();
        $result = $engine->get_price(1, 10);

        $this->assertNull($result);
    }

    public function test_validate_tiers_detects_overlap(): void {
        $engine = new Dora_Pricing_Engine();
        $tiers = [
            ['min' => 1, 'max' => 5],
            ['min' => 4, 'max' => 8], // overlaps with above
        ];
        $this->assertFalse( $engine->validate_tiers($tiers) );
    }

    public function test_validate_tiers_passes_valid_tiers(): void {
        $engine = new Dora_Pricing_Engine();
        $tiers = [
            ['min' => 1, 'max' => 3],
            ['min' => 4, 'max' => 6],
            ['min' => 7, 'max' => 99],
        ];
        $this->assertTrue( $engine->validate_tiers($tiers) );
    }

    public function test_validate_tiers_handles_single_tier(): void {
        $engine = new Dora_Pricing_Engine();
        $this->assertTrue( $engine->validate_tiers([['min' => 1, 'max' => 99]]) );
    }

    public function test_get_price_total_is_rounded(): void {
        global $wpdb;
        $wpdb->shouldReceive('prepare')->andReturn('SQL');
        $wpdb->shouldReceive('get_row')->with('SQL')->andReturn(
            (object)['price_per_person' => '33.33', 'currency' => 'EUR']
        );

        $engine = new Dora_Pricing_Engine();
        $result = $engine->get_price(1, 3);
        // 33.33 * 3 = 99.99
        $this->assertSame(99.99, $result['total']);
    }
}
