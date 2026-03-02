<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RAF_Damage_Report_Model {
    private static $table = 'damage_reports';

    public static function get( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . RAF_Helpers::table( self::$table ) . " WHERE id = %d", $id ) );
    }

    public static function get_all( $args = array() ) {
        global $wpdb;
        $table = RAF_Helpers::table( self::$table );
        $defaults = array( 'booking_id' => 0, 'vehicle_id' => 0, 'orderby' => 'created_at', 'order' => 'DESC', 'limit' => 20, 'offset' => 0 );
        $args = wp_parse_args( $args, $defaults );
        $where = array( '1=1' ); $values = array();
        if ( $args['booking_id'] ) { $where[] = 'booking_id = %d'; $values[] = $args['booking_id']; }
        if ( $args['vehicle_id'] ) { $where[] = 'vehicle_id = %d'; $values[] = $args['vehicle_id']; }
        $sql = "SELECT * FROM $table WHERE " . implode( ' AND ', $where ) . " ORDER BY {$args['orderby']} {$args['order']}";
        if ( $args['limit'] > 0 ) $sql .= $wpdb->prepare( " LIMIT %d OFFSET %d", $args['limit'], $args['offset'] );
        if ( $values ) return $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
        return $wpdb->get_results( $sql );
    }

    public static function create( $data ) {
        global $wpdb;
        $data['created_at'] = current_time( 'mysql' );
        if ( isset( $data['images'] ) && is_array( $data['images'] ) ) $data['images'] = wp_json_encode( $data['images'] );
        $wpdb->insert( RAF_Helpers::table( self::$table ), $data );
        return $wpdb->insert_id;
    }

    public static function update( $id, $data ) {
        global $wpdb;
        if ( isset( $data['images'] ) && is_array( $data['images'] ) ) $data['images'] = wp_json_encode( $data['images'] );
        return $wpdb->update( RAF_Helpers::table( self::$table ), $data, array( 'id' => $id ) );
    }

    public static function delete( $id ) {
        global $wpdb;
        return $wpdb->delete( RAF_Helpers::table( self::$table ), array( 'id' => $id ) );
    }

    public static function get_by_booking( $booking_id ) {
        return self::get_all( array( 'booking_id' => $booking_id, 'limit' => 0 ) );
    }

    public static function get_by_vehicle( $vehicle_id ) {
        return self::get_all( array( 'vehicle_id' => $vehicle_id, 'limit' => 0 ) );
    }

    public static function get_total_repair_cost( $vehicle_id ) {
        global $wpdb;
        return (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(repair_cost), 0) FROM " . RAF_Helpers::table( self::$table ) . " WHERE vehicle_id = %d",
            $vehicle_id
        ) );
    }
}
