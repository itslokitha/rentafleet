<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RAF_Booking_Model {

    private static $table = 'bookings';

    public static function get( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . RAF_Helpers::table( self::$table ) . " WHERE id = %d", $id
        ) );
    }

    public static function get_by_number( $booking_number ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . RAF_Helpers::table( self::$table ) . " WHERE booking_number = %s", $booking_number
        ) );
    }

    public static function get_all( $args = array() ) {
        global $wpdb;
        $table = RAF_Helpers::table( self::$table );
        $defaults = array(
            'status'         => '',
            'payment_status' => '',
            'customer_id'    => 0,
            'vehicle_id'     => 0,
            'location_id'    => 0,
            'date_from'      => '',
            'date_to'        => '',
            'search'         => '',
            'orderby'        => 'created_at',
            'order'          => 'DESC',
            'limit'          => 20,
            'offset'         => 0,
        );
        $args = wp_parse_args( $args, $defaults );

        $where = array( '1=1' );
        $values = array();

        if ( $args['status'] ) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }
        if ( $args['payment_status'] ) {
            $where[] = 'payment_status = %s';
            $values[] = $args['payment_status'];
        }
        if ( $args['customer_id'] ) {
            $where[] = 'customer_id = %d';
            $values[] = $args['customer_id'];
        }
        if ( $args['vehicle_id'] ) {
            $where[] = 'vehicle_id = %d';
            $values[] = $args['vehicle_id'];
        }
        if ( $args['location_id'] ) {
            $where[] = '(pickup_location_id = %d OR dropoff_location_id = %d)';
            $values[] = $args['location_id'];
            $values[] = $args['location_id'];
        }
        if ( $args['date_from'] ) {
            $where[] = 'pickup_date >= %s';
            $values[] = $args['date_from'];
        }
        if ( $args['date_to'] ) {
            $where[] = 'dropoff_date <= %s';
            $values[] = $args['date_to'];
        }
        if ( $args['search'] ) {
            $where[] = '(booking_number LIKE %s OR driver_name LIKE %s)';
            $s = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $values = array_merge( $values, array( $s, $s ) );
        }

        $sql = "SELECT * FROM $table WHERE " . implode( ' AND ', $where );
        $sql .= " ORDER BY {$args['orderby']} {$args['order']}";

        if ( $args['limit'] > 0 ) {
            $sql .= $wpdb->prepare( " LIMIT %d OFFSET %d", $args['limit'], $args['offset'] );
        }

        if ( $values ) {
            return $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
        }
        return $wpdb->get_results( $sql );
    }

    public static function count( $args = array() ) {
        global $wpdb;
        $where = array( '1=1' );
        $values = array();

        if ( ! empty( $args['status'] ) ) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }
        if ( ! empty( $args['date_from'] ) ) {
            $where[] = 'pickup_date >= %s';
            $values[] = $args['date_from'];
        }
        if ( ! empty( $args['date_to'] ) ) {
            $where[] = 'dropoff_date <= %s';
            $values[] = $args['date_to'];
        }

        $sql = "SELECT COUNT(*) FROM " . RAF_Helpers::table( self::$table ) . " WHERE " . implode( ' AND ', $where );
        if ( $values ) {
            return (int) $wpdb->get_var( $wpdb->prepare( $sql, $values ) );
        }
        return (int) $wpdb->get_var( $sql );
    }

    public static function create( $data ) {
        global $wpdb;
        if ( empty( $data['booking_number'] ) ) {
            $data['booking_number'] = RAF_Helpers::generate_booking_number();
        }
        $data['created_at'] = current_time( 'mysql' );
        $data['updated_at'] = current_time( 'mysql' );

        $wpdb->insert( RAF_Helpers::table( self::$table ), $data );
        $id = $wpdb->insert_id;

        if ( $id ) {
            self::log_status_change( $id, '', $data['status'] ?? 'pending', 'Booking created' );
        }

        return $id;
    }

    public static function update( $id, $data ) {
        global $wpdb;
        $data['updated_at'] = current_time( 'mysql' );
        return $wpdb->update( RAF_Helpers::table( self::$table ), $data, array( 'id' => $id ) );
    }

    public static function update_status( $id, $new_status, $note = '' ) {
        global $wpdb;
        $booking = self::get( $id );
        if ( ! $booking ) return false;

        $old_status = $booking->status;
        $result = self::update( $id, array( 'status' => $new_status ) );

        if ( $result !== false ) {
            self::log_status_change( $id, $old_status, $new_status, $note );
            do_action( 'raf_booking_status_changed', $id, $old_status, $new_status );
        }

        return $result;
    }

    public static function delete( $id ) {
        global $wpdb;
        // Delete related records
        $wpdb->delete( RAF_Helpers::table( 'booking_extras' ), array( 'booking_id' => $id ) );
        $wpdb->delete( RAF_Helpers::table( 'booking_insurance' ), array( 'booking_id' => $id ) );
        $wpdb->delete( RAF_Helpers::table( 'booking_log' ), array( 'booking_id' => $id ) );
        return $wpdb->delete( RAF_Helpers::table( self::$table ), array( 'id' => $id ) );
    }

    public static function log_status_change( $booking_id, $old_status, $new_status, $note = '' ) {
        global $wpdb;
        $wpdb->insert( RAF_Helpers::table( 'booking_log' ), array(
            'booking_id' => $booking_id,
            'old_status' => $old_status,
            'new_status' => $new_status,
            'note'       => $note,
            'changed_by' => get_current_user_id(),
            'created_at' => current_time( 'mysql' ),
        ) );
    }

    public static function get_log( $booking_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM " . RAF_Helpers::table( 'booking_log' ) . " WHERE booking_id = %d ORDER BY created_at DESC",
            $booking_id
        ) );
    }

    public static function get_extras( $booking_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM " . RAF_Helpers::table( 'booking_extras' ) . " WHERE booking_id = %d",
            $booking_id
        ) );
    }

    public static function get_insurance( $booking_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM " . RAF_Helpers::table( 'booking_insurance' ) . " WHERE booking_id = %d",
            $booking_id
        ) );
    }

    public static function add_extra( $booking_id, $data ) {
        global $wpdb;
        $data['booking_id'] = $booking_id;
        return $wpdb->insert( RAF_Helpers::table( 'booking_extras' ), $data );
    }

    public static function add_insurance( $booking_id, $data ) {
        global $wpdb;
        $data['booking_id'] = $booking_id;
        return $wpdb->insert( RAF_Helpers::table( 'booking_insurance' ), $data );
    }

    /**
     * Get bookings for calendar view
     */
    public static function get_for_calendar( $start_date, $end_date, $vehicle_id = 0 ) {
        global $wpdb;
        $table = RAF_Helpers::table( self::$table );

        $where = "status NOT IN ('cancelled', 'refunded')
                  AND pickup_date <= %s AND dropoff_date >= %s";
        $values = array( $end_date, $start_date );

        if ( $vehicle_id ) {
            $where .= " AND vehicle_id = %d";
            $values[] = $vehicle_id;
        }

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT b.*, c.first_name, c.last_name, c.email, v.name as vehicle_name
             FROM $table b
             LEFT JOIN " . RAF_Helpers::table( 'customers' ) . " c ON b.customer_id = c.id
             LEFT JOIN " . RAF_Helpers::table( 'vehicles' ) . " v ON b.vehicle_id = v.id
             WHERE $where
             ORDER BY b.pickup_date ASC",
            $values
        ) );
    }

    /**
     * Get today's pickups
     */
    public static function get_todays_pickups() {
        global $wpdb;
        $today = current_time( 'Y-m-d' );
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT b.*, c.first_name, c.last_name, c.phone, v.name as vehicle_name
             FROM " . RAF_Helpers::table( 'bookings' ) . " b
             LEFT JOIN " . RAF_Helpers::table( 'customers' ) . " c ON b.customer_id = c.id
             LEFT JOIN " . RAF_Helpers::table( 'vehicles' ) . " v ON b.vehicle_id = v.id
             WHERE DATE(b.pickup_date) = %s AND b.status IN ('confirmed', 'pending')
             ORDER BY b.pickup_date ASC",
            $today
        ) );
    }

    /**
     * Get today's returns
     */
    public static function get_todays_returns() {
        global $wpdb;
        $today = current_time( 'Y-m-d' );
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT b.*, c.first_name, c.last_name, c.phone, v.name as vehicle_name
             FROM " . RAF_Helpers::table( 'bookings' ) . " b
             LEFT JOIN " . RAF_Helpers::table( 'customers' ) . " c ON b.customer_id = c.id
             LEFT JOIN " . RAF_Helpers::table( 'vehicles' ) . " v ON b.vehicle_id = v.id
             WHERE DATE(b.dropoff_date) = %s AND b.status = 'active'
             ORDER BY b.dropoff_date ASC",
            $today
        ) );
    }

    /**
     * Revenue for date range
     */
    public static function get_revenue( $date_from, $date_to ) {
        global $wpdb;
        return (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(total_price), 0) FROM " . RAF_Helpers::table( self::$table ) .
            " WHERE status NOT IN ('cancelled', 'refunded') AND created_at BETWEEN %s AND %s",
            $date_from, $date_to
        ) );
    }

    /**
     * Revenue by month for charts
     */
    public static function get_monthly_revenue( $year ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT MONTH(created_at) as month, SUM(total_price) as revenue, COUNT(*) as bookings
             FROM " . RAF_Helpers::table( self::$table ) .
            " WHERE YEAR(created_at) = %d AND status NOT IN ('cancelled', 'refunded')
             GROUP BY MONTH(created_at) ORDER BY month",
            $year
        ) );
    }

    /**
     * Get customer's bookings
     */
    public static function get_by_customer( $customer_id, $limit = 20 ) {
        return self::get_all( array(
            'customer_id' => $customer_id,
            'limit'       => $limit,
        ) );
    }
}
