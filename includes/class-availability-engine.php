<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class Dora_Availability_Engine {

    /**
     * Check if a specific time slot is free for a staff member.
     * Uses LEFT JOIN to include OTA Sync Block ghost appointments
     * (which have no bookly_customer_appointments row).
     */
    public function is_slot_free( int $staff_id, string $start, string $end ): bool {
        global $wpdb;
        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}bookly_appointments a
             LEFT JOIN {$wpdb->prefix}bookly_customer_appointments ca
                    ON ca.appointment_id = a.id
             WHERE a.staff_id = %d
               AND a.start_date < %s
               AND a.end_date   > %s
               AND (ca.id IS NULL OR ca.status NOT IN ('cancelled','rejected'))",
            $staff_id, $end, $start
        );
        return (int) $wpdb->get_var( $sql ) === 0;
    }

    /**
     * Return array of available date strings ('Y-m-d') in a date range.
     *
     * @param int    $staff_id         Staff member ID.
     * @param int    $service_id       Service ID (reserved for future filtering).
     * @param string $month_start      Range start date 'Y-m-d'.
     * @param string $month_end        Range end date 'Y-m-d'.
     * @param array  $slot_times       Array of 'HH:MM' start times.
     * @param int    $duration_minutes Service duration in minutes.
     * @return array Unique sorted date strings that have at least one free slot.
     */
    public function get_available_days(
        int $staff_id,
        int $service_id,
        string $month_start,
        string $month_end,
        array $slot_times,
        int $duration_minutes
    ): array {
        $available = [];
        $cursor = new DateTime( $month_start, new DateTimeZone('UTC') );
        $end    = new DateTime( $month_end,   new DateTimeZone('UTC') );
        $now    = new DateTime( 'now',         new DateTimeZone('UTC') );

        while ( $cursor <= $end ) {
            $date = $cursor->format('Y-m-d');
            foreach ( $slot_times as $time ) {
                $slot_start = new DateTime( $date . ' ' . $time, new DateTimeZone('UTC') );
                if ( $slot_start <= $now ) {
                    continue; // skip past slots
                }
                $slot_end = clone $slot_start;
                $slot_end->modify( "+{$duration_minutes} minutes" );

                if ( $this->is_slot_free(
                    $staff_id,
                    $slot_start->format('Y-m-d H:i:s'),
                    $slot_end->format('Y-m-d H:i:s')
                ) ) {
                    $available[] = $date;
                    break; // at least one free slot found — day is available
                }
            }
            $cursor->modify('+1 day');
        }

        return array_values( array_unique( $available ) );
    }

    /**
     * Get free time slots for a specific date.
     *
     * @param int    $staff_id         Staff member ID.
     * @param string $date             Date string 'Y-m-d'.
     * @param array  $slot_times       Array of 'HH:MM' start times.
     * @param int    $duration_minutes Service duration in minutes.
     * @return array Each element: ['start', 'end', 'start_datetime', 'end_datetime'].
     */
    public function get_available_slots_for_day(
        int $staff_id,
        string $date,
        array $slot_times,
        int $duration_minutes
    ): array {
        $free = [];
        $tz   = new DateTimeZone('UTC');
        $now  = new DateTime('now', $tz);

        foreach ( $slot_times as $time ) {
            $start = new DateTime( $date . ' ' . $time, $tz );
            if ( $start <= $now ) {
                continue; // skip past slots
            }
            $end = clone $start;
            $end->modify( "+{$duration_minutes} minutes" );

            if ( $this->is_slot_free(
                $staff_id,
                $start->format('Y-m-d H:i:s'),
                $end->format('Y-m-d H:i:s')
            ) ) {
                $free[] = [
                    'start'          => $start->format('H:i'),
                    'end'            => $end->format('H:i'),
                    'start_datetime' => $start->format('Y-m-d H:i:s'),
                    'end_datetime'   => $end->format('Y-m-d H:i:s'),
                ];
            }
        }

        return $free;
    }
}
