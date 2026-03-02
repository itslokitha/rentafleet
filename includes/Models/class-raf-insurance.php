<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RAF_Insurance {
    private static $table = 'insurance';

    public static function get( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . RAF_Helpers::table( self::$table ) . " WHERE id = %d", $id ) );
    }

    public static function get_all( $args = array() ) {
        global $wpdb;
        $table = RAF_Helpers::table( self::$table );
        $defaults = array( 'status' => 'active', 'orderby' => 'sort_order', 'order' => 'ASC' );
        $args = wp_parse_args( $args, $defaults );
        $where = '1=1'; $values = array();
        if ( $args['status'] ) { $where .= ' AND status = %s'; $values[] = $args['status']; }
        $sql = "SELECT * FROM $table WHERE $where ORDER BY {$args['orderby']} {$args['order']}";
        if ( $values ) return $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
        return $wpdb->get_results( $sql );
    }

    public static function create( $data ) {
        global $wpdb;
        $data['created_at'] = current_time( 'mysql' );
        if ( empty( $data['slug'] ) ) $data['slug'] = sanitize_title( $data['name'] );
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

    public static function calculate_total( $insurance, $rental_days ) {
        if ( $insurance->price_per_rental > 0 ) return (float) $insurance->price_per_rental;
        return (float) $insurance->price_per_day * $rental_days;
    }
}
