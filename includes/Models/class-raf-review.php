<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RAF_Review {
    private static $table = 'reviews';

    public static function get( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . RAF_Helpers::table( self::$table ) . " WHERE id = %d", $id ) );
    }

    public static function get_for_vehicle( $vehicle_id, $approved_only = true ) {
        global $wpdb;
        $where = "vehicle_id = %d";
        if ( $approved_only ) $where .= " AND is_approved = 1";
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT r.*, c.first_name, c.last_name FROM " . RAF_Helpers::table( self::$table ) . " r
             LEFT JOIN " . RAF_Helpers::table( 'customers' ) . " c ON r.customer_id = c.id
             WHERE $where ORDER BY r.created_at DESC",
            $vehicle_id
        ) );
    }

    public static function get_all( $args = array() ) {
        global $wpdb;
        $table = RAF_Helpers::table( self::$table );
        $defaults = array( 'is_approved' => -1, 'orderby' => 'created_at', 'order' => 'DESC', 'limit' => 20, 'offset' => 0 );
        $args = wp_parse_args( $args, $defaults );
        $where = '1=1'; $values = array();
        if ( $args['is_approved'] >= 0 ) { $where .= ' AND is_approved = %d'; $values[] = $args['is_approved']; }
        $sql = "SELECT r.*, c.first_name, c.last_name, v.name as vehicle_name FROM $table r
                LEFT JOIN " . RAF_Helpers::table( 'customers' ) . " c ON r.customer_id = c.id
                LEFT JOIN " . RAF_Helpers::table( 'vehicles' ) . " v ON r.vehicle_id = v.id
                WHERE $where ORDER BY r.{$args['orderby']} {$args['order']}";
        if ( $args['limit'] > 0 ) $sql .= $wpdb->prepare( " LIMIT %d OFFSET %d", $args['limit'], $args['offset'] );
        if ( $values ) return $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
        return $wpdb->get_results( $sql );
    }

    public static function create( $data ) {
        global $wpdb;
        $data['created_at'] = current_time( 'mysql' );
        $wpdb->insert( RAF_Helpers::table( self::$table ), $data );
        return $wpdb->insert_id;
    }

    public static function update( $id, $data ) {
        global $wpdb;
        return $wpdb->update( RAF_Helpers::table( self::$table ), $data, array( 'id' => $id ) );
    }

    public static function delete( $id ) {
        global $wpdb;
        return $wpdb->delete( RAF_Helpers::table( self::$table ), array( 'id' => $id ) );
    }

    public static function approve( $id ) { return self::update( $id, array( 'is_approved' => 1 ) ); }
    public static function reject( $id ) { return self::update( $id, array( 'is_approved' => 0 ) ); }
}
