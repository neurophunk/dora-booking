<?php

if ( ! defined( 'DORA_VERSION' ) ) {
    define( 'ABSPATH', '/tmp/' );
    require_once __DIR__ . '/../dora-booking.php';
}

use PHPUnit\Framework\TestCase;

class EmailServiceTest extends TestCase {
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

    public function test_render_replaces_all_variables(): void {
        $service  = new Dora_Email_Service();
        $template = 'Hello {name}, your booking {booking_ref} is confirmed. Meet at {meeting_point}.';
        $vars     = [
            '{name}'          => 'Gábor',
            '{booking_ref}'   => '#000042',
            '{meeting_point}' => 'Vörösmarty tér',
        ];
        $result = $service->render_template( $template, $vars );
        $this->assertSame(
            'Hello Gábor, your booking #000042 is confirmed. Meet at Vörösmarty tér.',
            $result
        );
    }

    public function test_build_vars_includes_all_required_keys(): void {
        global $wpdb;

        $booking = (object) [
            'id'              => 42,
            'service_id'      => 1,
            'staff_id'        => 1,
            'start_datetime'  => '2026-04-01 07:00:00',
            'persons'         => 2,
            'total_price'     => '120.00',
            'currency'        => 'EUR',
            'payment_type'    => 'onsite',
            'customer_name'   => 'Gábor',
            'customer_email'  => 'g@g.com',
            'cancel_token'    => 'abc123',
        ];

        $wpdb->shouldReceive('prepare')->andReturn('SQL');
        $wpdb->shouldReceive('get_var')
             ->andReturnValues( [ 'Budapest City Tour', 'Dóra Varga' ] );
        $wpdb->shouldReceive('get_row')
             ->andReturn( (object) [ 'meeting_point' => 'Vörösmarty tér' ] );

        // wp_timezone, esc_html, site_url are already stubbed in bootstrap.php
        // as plain PHP functions — Patchwork cannot intercept them here.
        // The bootstrap stubs return exactly what we need:
        //   wp_timezone() => DateTimeZone('Europe/Budapest')
        //   esc_html($v)  => htmlspecialchars($v)
        //   site_url($p)  => 'https://dorabudapest.com' . $p

        $service  = new Dora_Email_Service();
        $vars     = $service->build_vars( $booking );

        $required = [
            '{name}', '{service}', '{date}', '{time}', '{persons}', '{total}',
            '{currency}', '{payment_type}', '{meeting_point}', '{guide_name}',
            '{cancel_url}', '{booking_ref}',
        ];
        foreach ( $required as $key ) {
            $this->assertArrayHasKey( $key, $vars, "Missing variable: $key" );
        }
        // Verify booking ref format
        $this->assertSame( '#000042', $vars['{booking_ref}'] );
        // Verify cancel URL contains token
        $this->assertStringContainsString( 'abc123', $vars['{cancel_url}'] );
    }

    public function test_send_returns_false_when_booking_not_found(): void {
        global $wpdb;
        $wpdb->shouldReceive('prepare')->andReturn('SQL');
        $wpdb->shouldReceive('get_row')->andReturn( null );

        $service = new Dora_Email_Service();
        $this->assertFalse( $service->send( 999, 'confirmation' ) );
    }
}
