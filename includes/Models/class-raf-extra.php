<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RAF_Extra {
    private static $table = 'extras';

    public static function get( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . RAF_Helpers::table( self::$table ) . " WHERE id = %d", $id ) );
    }

    public static function get_all( $args = array() ) {
        global $wpdb;
        $table = RAF_Helpers::table( self::$table );
        $defaults = array( 'status' => 'active', 'orderby' => 'sort_order', 'order' => 'ASC', 'limit' => 0, 'offset' => 0 );
        $args = wp_parse_args( $args, $defaults );
        $where = array( '1=1' ); $values = array();
        if ( $args['status'] ) { $where[] = 'status = %s'; $values[] = $args['status']; }
        $sql = "SELECT * FROM $table WHERE " . implode( ' AND ', $where ) . " ORDER BY {$args['orderby']} {$args['order']}";
        if ( $args['limit'] > 0 ) $sql .= $wpdb->prepare( " LIMIT %d OFFSET %d", $args['limit'], $args['offset'] );
        if ( $values ) return $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
        return $wpdb->get_results( $sql );
    }

    public static function create( $data ) {
        global $wpdb;
        $data['created_at'] = current_time( 'mysql' );
        if ( empty( $data['slug'] ) ) $data['slug'] = sanitize_title( $data['name'] );
        foreach ( array( 'vehicle_ids', 'category_ids', 'location_ids' ) as $field ) {
            if ( isset( $data[ $field ] ) && is_array( $data[ $field ] ) ) $data[ $field ] = wp_json_encode( $data[ $field ] );
        }
        $wpdb->insert( RAF_Helpers::table( self::$table ), $data );
        return $wpdb->insert_id;
    }

    public static function update( $id, $data ) {
        global $wpdb;
        foreach ( array( 'vehicle_ids', 'category_ids', 'location_ids' ) as $field ) {
            if ( isset( $data[ $field ] ) && is_array( $data[ $field ] ) ) $data[ $field ] = wp_json_encode( $data[ $field ] );
        }
        return $wpdb->update( RAF_Helpers::table( self::$table ), $data, array( 'id' => $id ) );
    }

    public static function delete( $id ) {
        global $wpdb;
        return $wpdb->delete( RAF_Helpers::table( self::$table ), array( 'id' => $id ) );
    }

    public static function get_for_vehicle( $vehicle_id, $location_id = 0 ) {
        $extras = self::get_all();
        $result = array();
        foreach ( $extras as $extra ) {
            $vehicle_ids = json_decode( $extra->vehicle_ids, true );
            $location_ids = json_decode( $extra->location_ids, true );
            if ( ! empty( $vehicle_ids ) && ! in_array( $vehicle_id, $vehicle_ids ) ) continue;
            if ( $location_id && ! empty( $location_ids ) && ! in_array( $location_id, $location_ids ) ) continue;
            $result[] = $extra;
        }
        return $result;
    }

    public static function calculate_total( $extra, $rental_days ) {
        if ( $extra->price_type === 'per_rental' ) return (float) $extra->price;
        return (float) $extra->price * $rental_days;
    }
}
