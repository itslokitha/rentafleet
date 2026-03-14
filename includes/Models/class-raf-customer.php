<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RAF_Customer {

    private static $table = 'customers';

    public static function get( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . RAF_Helpers::table( self::$table ) . " WHERE id = %d", $id
        ) );
    }

    public static function get_by_email( $email ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . RAF_Helpers::table( self::$table ) . " WHERE email = %s", $email
        ) );
    }

public static function get_all( $args = array() ) {
        global $wpdb;
        $table = RAF_Helpers::table( self::$table );
        $defaults = array(
            'status'  => '',
            'search'  => '',
            'orderby' => 'created_at',
            'order'   => 'DESC',
            'limit'   => 20,
            'offset'  => 0,
        );
        $args = wp_parse_args( $args, $defaults );

        $where = array( '1=1' );
        $values = array();

        if ( $args['status'] ) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }
        if ( $args['search'] ) {
            $where[] = '(first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR phone LIKE %s)';
            $s = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $values = array_merge( $values, array( $s, $s, $s, $s ) );
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
        $sql = "SELECT COUNT(*) FROM " . RAF_Helpers::table( self::$table );
        if ( ! empty( $args['status'] ) ) {
            return (int) $wpdb->get_var( $wpdb->prepare( $sql . " WHERE status = %s", $args['status'] ) );
        }
        return (int) $wpdb->get_var( $sql );
    }

    public static function create( $data ) {
        global $wpdb;
        $data['created_at'] = current_time( 'mysql' );
        $data['updated_at'] = current_time( 'mysql' );
        $wpdb->insert( RAF_Helpers::table( self::$table ), $data );
        return $wpdb->insert_id;
    }

    public static function update( $id, $data ) {
        global $wpdb;
        $data['updated_at'] = current_time( 'mysql' );
        return $wpdb->update( RAF_Helpers::table( self::$table ), $data, array( 'id' => $id ) );
    }

    public static function delete( $id ) {
        global $wpdb;
        return $wpdb->delete( RAF_Helpers::table( self::$table ), array( 'id' => $id ) );
    }

    public static function get_or_create( $data ) {
        $customer = self::get_by_email( $data['email'] );
        if ( $customer ) {
            self::update( $customer->id, $data );
            return $customer->id;
        }
        return self::create( $data );
    }

    public static function update_stats( $customer_id ) {
        global $wpdb;
        $table = RAF_Helpers::table( 'bookings' );
        $stats = $wpdb->get_row( $wpdb->prepare(
            "SELECT COUNT(*) as total_bookings, COALESCE(SUM(total_price), 0) as total_spent
             FROM $table WHERE customer_id = %d AND status NOT IN ('cancelled', 'refunded')",
            $customer_id
        ) );
        if ( $stats ) {
            self::update( $customer_id, array(
                'total_bookings' => $stats->total_bookings,
                'total_spent'    => $stats->total_spent,
            ) );
        }
    }

    public static function get_full_name( $customer ) {
        return trim( $customer->first_name . ' ' . $customer->last_name );
    }
}
