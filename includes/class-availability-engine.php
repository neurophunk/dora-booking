<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class Dora_Availability_Engine {

    private function get_service( int $service_id ): ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dora_services WHERE id = %d AND active = 1",
            $service_id
        ) );
    }

    /**
     * Returns list of dates (Y-m-d) that have at least one free slot.
     */
    public function get_available_days( int $service_id, string $year_month ): array {
        $service = $this->get_service( $service_id );
        if ( ! $service ) return [];

        $available_days  = json_decode( $service->available_days, true );  // [0..6]
        $available_times = json_decode( $service->available_times, true ); // ["09:00",...]
        if ( ! is_array( $available_days ) || ! is_array( $available_times ) ) return [];

        $tz             = wp_timezone();
        [ $year, $month ] = explode( '-', $year_month );
        $days_in_month  = (int) ( new DateTime( "$year-$month-01", $tz ) )->format( 't' );
        $now_utc        = new DateTime( 'now', new DateTimeZone( 'UTC' ) );

        $result = [];
        for ( $d = 1; $d <= $days_in_month; $d++ ) {
            $date = sprintf( '%s-%02d', $year_month, $d );
            $dow  = (int) ( new DateTime( $date, $tz ) )->format( 'w' ); // 0=Sun

            if ( ! in_array( $dow, $available_days, true ) ) continue;

            foreach ( $available_times as $time ) {
                $start = new DateTime( "$date $time", $tz );
                $start->setTimezone( new DateTimeZone( 'UTC' ) );
                if ( $start <= $now_utc ) continue; // past slot
                if ( $this->is_slot_free( $service_id, $start->format( 'Y-m-d H:i:s' ), (int) $service->duration_minutes ) ) {
                    $result[] = $date;
                    break;
                }
            }
        }
        return $result;
    }

    /**
     * Returns list of local-time strings ("HH:MM") available for a given date.
     */
    public function get_available_slots( int $service_id, string $date ): array {
        $service = $this->get_service( $service_id );
        if ( ! $service ) return [];

        $available_times = json_decode( $service->available_times, true );
        if ( ! is_array( $available_times ) ) return [];

        $tz      = wp_timezone();
        $now_utc = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
        $result  = [];

        foreach ( $available_times as $time ) {
            $start = new DateTime( "$date $time", $tz );
            $start->setTimezone( new DateTimeZone( 'UTC' ) );
            if ( $start <= $now_utc ) continue;
            if ( $this->is_slot_free( $service_id, $start->format( 'Y-m-d H:i:s' ), (int) $service->duration_minutes ) ) {
                $result[] = $time;
            }
        }
        return $result;
    }

    /**
     * Returns true if no confirmed/pending booking overlaps the given UTC slot.
     * Also checks OTA sync blocks (ota-calendar-sync plugin) if present.
     */
    public function is_slot_free( int $service_id, string $start_utc, int $duration_minutes ): bool {
        global $wpdb;
        $end_utc = ( new DateTime( $start_utc, new DateTimeZone( 'UTC' ) ) )
            ->modify( "+{$duration_minutes} minutes" )
            ->format( 'Y-m-d H:i:s' );

        // Check own bookings table.
        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}dora_bookings
             WHERE service_id = %d
               AND status IN ('pending','confirmed')
               AND start_datetime < %s
               AND end_datetime > %s",
            $service_id, $end_utc, $start_utc
        ) );
        if ( (int) $count > 0 ) return false;

        // Check OTA sync blocks if ota-calendar-sync plugin is active.
        if ( $this->ota_sync_active() ) {
            $ota_count = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$wpdb->prefix}ota_sync_blocks osb
                 JOIN {$wpdb->prefix}ota_sync_feeds osf ON osb.feed_id = osf.id
                 WHERE osf.dora_service_id = %d
                   AND osb.start_datetime < %s
                   AND osb.end_datetime > %s",
                $service_id, $end_utc, $start_utc
            ) );
            if ( (int) $ota_count > 0 ) return false;
        }

        return true;
    }

    /**
     * Returns true if ota-calendar-sync plugin tables exist with dora_service_id support.
     */
    private function ota_sync_active(): bool {
        global $wpdb;
        static $checked = null;
        if ( $checked !== null ) return $checked;

        // Check table exists.
        $table = $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}ota_sync_blocks'" );
        if ( ! $table ) {
            $checked = false;
            return false;
        }
        // Check dora_service_id column exists in feeds table.
        $col = $wpdb->get_var( "SHOW COLUMNS FROM {$wpdb->prefix}ota_sync_feeds LIKE 'dora_service_id'" );
        $checked = (bool) $col;
        return $checked;
    }

    /**
     * Returns duration_minutes for a service.
     */
    public function get_duration( int $service_id ): int {
        global $wpdb;
        $d = $wpdb->get_var( $wpdb->prepare(
            "SELECT duration_minutes FROM {$wpdb->prefix}dora_services WHERE id = %d",
            $service_id
        ) );
        return (int) ( $d ?? 60 );
    }
}
