<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RAF_Availability_Checker {

    /**
     * Check if a vehicle is available for given dates at a location
     */
    public static function is_available( $vehicle_id, $pickup_date, $dropoff_date, $location_id = 0, $exclude_booking_id = 0 ) {
        $vehicle = RAF_Vehicle::get( $vehicle_id );
        if ( ! $vehicle || $vehicle->status !== 'active' ) {
            return false;
        }

        // Get total units
        $total_units = $vehicle->units;
        if ( $location_id ) {
            global $wpdb;
            $units = $wpdb->get_var( $wpdb->prepare(
                "SELECT units_at_location FROM " . RAF_Helpers::table( 'vehicle_locations' ) .
                " WHERE vehicle_id = %d AND location_id = %d",
                $vehicle_id, $location_id
            ) );
            if ( $units !== null ) {
                $total_units = (int) $units;
            }
        }

        // Check blocked dates
        if ( self::has_blocked_dates( $vehicle_id, $pickup_date, $dropoff_date ) ) {
            return false;
        }

        // Count overlapping bookings
        $booked_units = self::get_booked_units( $vehicle_id, $pickup_date, $dropoff_date, $exclude_booking_id );

        return $booked_units < $total_units;
    }

    /**
     * Get available vehicles for date range and location
     */
    public static function get_available_vehicles( $pickup_date, $dropoff_date, $location_id = 0, $category_id = 0 ) {
        $args = array( 'status' => 'active', 'limit' => 0 );
        if ( $category_id ) {
            $args['category_id'] = $category_id;
        }

        // If location specified, get vehicles at that location
        if ( $location_id ) {
            $vehicles = RAF_Vehicle::get_by_location( $location_id );
        } else {
            $vehicles = RAF_Vehicle::get_all( $args );
        }

        $available = array();
        foreach ( $vehicles as $vehicle ) {
            if ( self::is_available( $vehicle->id, $pickup_date, $dropoff_date, $location_id ) ) {
                // Check min/max rental days
                $rental_days = RAF_Helpers::calculate_rental_days( $pickup_date, $dropoff_date );
                if ( $rental_days < $vehicle->min_rental_days || $rental_days > $vehicle->max_rental_days ) {
                    continue;
                }
                $available[] = $vehicle;
            }
        }

        return $available;
    }

    /**
     * Get the number of booked units for a vehicle in a date range
     */
    public static function get_booked_units( $vehicle_id, $pickup_date, $dropoff_date, $exclude_booking_id = 0 ) {
        global $wpdb;
        $table = RAF_Helpers::table( 'bookings' );

        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM $table
             WHERE vehicle_id = %d
             AND status NOT IN ('cancelled', 'refunded', 'no_show')
             AND pickup_date < %s
             AND dropoff_date > %s",
            $vehicle_id, $dropoff_date, $pickup_date
        );

        if ( $exclude_booking_id ) {
            $sql .= $wpdb->prepare( " AND id != %d", $exclude_booking_id );
        }

        return (int) $wpdb->get_var( $sql );
    }

    /**
     * Check if vehicle has blocked dates in range
     */
    public static function has_blocked_dates( $vehicle_id, $from, $to ) {
        global $wpdb;
        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM " . RAF_Helpers::table( 'blocked_dates' ) .
            " WHERE vehicle_id = %d AND date_from < %s AND date_to > %s",
            $vehicle_id, $to, $from
        ) );
    }

    /**
     * Get blocked dates for a vehicle
     */
    public static function get_blocked_dates( $vehicle_id, $from = '', $to = '' ) {
        global $wpdb;
        $table = RAF_Helpers::table( 'blocked_dates' );

        if ( $from && $to ) {
            return $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM $table WHERE vehicle_id = %d AND date_from < %s AND date_to > %s ORDER BY date_from ASC",
                $vehicle_id, $to, $from
            ) );
        }

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table WHERE vehicle_id = %d ORDER BY date_from ASC",
            $vehicle_id
        ) );
    }

    /**
     * Add blocked dates
     */
    public static function block_dates( $vehicle_id, $from, $to, $reason = '', $notes = '' ) {
        global $wpdb;
        return $wpdb->insert( RAF_Helpers::table( 'blocked_dates' ), array(
            'vehicle_id' => $vehicle_id,
            'date_from'  => $from,
            'date_to'    => $to,
            'reason'     => $reason,
            'notes'      => $notes,
            'created_by' => get_current_user_id(),
            'created_at' => current_time( 'mysql' ),
        ) );
    }

    /**
     * Remove blocked dates
     */
    public static function unblock_dates( $id ) {
        global $wpdb;
        return $wpdb->delete( RAF_Helpers::table( 'blocked_dates' ), array( 'id' => $id ) );
    }

    /**
     * Get availability calendar data for a vehicle (for frontend calendar)
     */
    public static function get_availability_calendar( $vehicle_id, $month, $year ) {
        $vehicle = RAF_Vehicle::get( $vehicle_id );
        if ( ! $vehicle ) return array();

        $days_in_month = cal_days_in_month( CAL_GREGORIAN, $month, $year );
        $calendar = array();

        for ( $day = 1; $day <= $days_in_month; $day++ ) {
            $date = sprintf( '%04d-%02d-%02d', $year, $month, $day );
            $date_start = $date . ' 00:00:00';
            $date_end   = $date . ' 23:59:59';

            $booked = self::get_booked_units( $vehicle_id, $date_start, $date_end );
            $blocked = self::has_blocked_dates( $vehicle_id, $date_start, $date_end );

            $calendar[] = array(
                'date'      => $date,
                'available' => ! $blocked && $booked < $vehicle->units,
                'booked'    => $booked,
                'total'     => $vehicle->units,
                'blocked'   => $blocked,
            );
        }

        return $calendar;
    }
}
