<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class Dora_Pricing_Engine {

    /**
     * Get price for a service + passenger count.
     * Returns ['total', 'price_per_person', 'currency'] or null if no tier.
     */
    public function get_price( int $service_id, int $persons ): ?array {
        global $wpdb;
        $sql = $wpdb->prepare(
            "SELECT price_per_person, currency
             FROM {$wpdb->prefix}dora_pricing_tiers
             WHERE service_id  = %d
               AND min_persons <= %d
               AND max_persons >= %d
             LIMIT 1",
            $service_id, $persons, $persons
        );
        $row = $wpdb->get_row( $sql );
        if ( ! $row ) return null;

        $ppp = (float) $row->price_per_person;
        return [
            'price_per_person' => $ppp,
            'total'            => round( $ppp * $persons, 2 ),
            'currency'         => $row->currency,
        ];
    }

    /**
     * Validate that pricing tiers don't overlap.
     * $tiers: array of ['min' => int, 'max' => int]
     */
    public function validate_tiers( array $tiers ): bool {
        if ( count($tiers) <= 1 ) return true;
        usort( $tiers, fn( $a, $b ) => $a['min'] <=> $b['min'] );
        for ( $i = 0; $i < count($tiers) - 1; $i++ ) {
            if ( $tiers[$i]['max'] >= $tiers[$i + 1]['min'] ) {
                return false;
            }
        }
        return true;
    }

    /**
     * Get service config (meeting_point, max_persons).
     */
    public function get_service_config( int $service_id ): ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dora_service_config WHERE service_id = %d",
            $service_id
        ) );
    }

    /**
     * Get all pricing tiers for a service (ordered by min_persons ASC).
     */
    public function get_tiers( int $service_id ): array {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dora_pricing_tiers WHERE service_id = %d ORDER BY min_persons ASC",
            $service_id
        ) ) ?: [];
    }
}
