<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RAF_Rate {
    private static $table = 'rates';

    public static function get( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . RAF_Helpers::table( self::$table ) . " WHERE id = %d", $id ) );
    }

    public static function get_for_vehicle( $vehicle_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM " . RAF_Helpers::table( self::$table ) . " WHERE vehicle_id = %d AND status = 'active' ORDER BY min_days ASC",
            $vehicle_id
        ) );
    }

    public static function get_for_category( $category_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM " . RAF_Helpers::table( self::$table ) . " WHERE category_id = %d AND status = 'active' ORDER BY min_days ASC",
            $category_id
        ) );
    }

    public static function get_all( $args = array() ) {
        global $wpdb;
        $table = RAF_Helpers::table( self::$table );
        $sql = "SELECT * FROM $table WHERE status = 'active' ORDER BY vehicle_id, min_days ASC";
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

    public static function get_seasonal( $vehicle_id = 0, $category_id = 0, $date = '' ) {
        global $wpdb;
        $table = RAF_Helpers::table( 'seasonal_rates' );
        $where = array( "status = 'active'", "%s BETWEEN date_from AND date_to" );
        $values = array( $date ?: current_time( 'Y-m-d' ) );
        if ( $vehicle_id ) { $where[] = 'vehicle_id = %d'; $values[] = $vehicle_id; }
        if ( $category_id ) { $where[] = 'category_id = %d'; $values[] = $category_id; }
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table WHERE " . implode( ' AND ', $where ) . " ORDER BY priority DESC",
            $values
        ) );
    }
}
