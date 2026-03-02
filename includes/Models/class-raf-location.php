<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RAF_Location {

    private static $table = 'locations';

    public static function get( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . RAF_Helpers::table( self::$table ) . " WHERE id = %d", $id
        ) );
    }

    public static function get_all( $args = array() ) {
        global $wpdb;
        $table = RAF_Helpers::table( self::$table );
        $defaults = array(
            'status'  => 'active',
            'type'    => '',
            'search'  => '',
            'orderby' => 'sort_order',
            'order'   => 'ASC',
            'limit'   => 0,
            'offset'  => 0,
        );
        $args = wp_parse_args( $args, $defaults );

        $where = array( '1=1' );
        $values = array();

        if ( $args['status'] ) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }
        if ( $args['type'] === 'pickup' ) {
            $where[] = 'is_pickup = 1';
        } elseif ( $args['type'] === 'dropoff' ) {
            $where[] = 'is_dropoff = 1';
        }
        if ( $args['search'] ) {
            $where[] = '(name LIKE %s OR city LIKE %s OR address LIKE %s)';
            $s = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $values = array_merge( $values, array( $s, $s, $s ) );
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
        $where = '1=1';
        $values = array();
        if ( ! empty( $args['status'] ) ) {
            $where .= ' AND status = %s';
            $values[] = $args['status'];
        }
        $sql = "SELECT COUNT(*) FROM " . RAF_Helpers::table( self::$table ) . " WHERE $where";
        if ( $values ) {
            return (int) $wpdb->get_var( $wpdb->prepare( $sql, $values ) );
        }
        return (int) $wpdb->get_var( $sql );
    }

    public static function create( $data ) {
        global $wpdb;
        $data['created_at'] = current_time( 'mysql' );
        if ( empty( $data['slug'] ) && ! empty( $data['name'] ) ) {
            $data['slug'] = sanitize_title( $data['name'] );
        }
        if ( isset( $data['opening_hours'] ) && is_array( $data['opening_hours'] ) ) {
            $data['opening_hours'] = wp_json_encode( $data['opening_hours'] );
        }
        $wpdb->insert( RAF_Helpers::table( self::$table ), $data );
        return $wpdb->insert_id;
    }

    public static function update( $id, $data ) {
        global $wpdb;
        $data['updated_at'] = current_time( 'mysql' );
        if ( isset( $data['opening_hours'] ) && is_array( $data['opening_hours'] ) ) {
            $data['opening_hours'] = wp_json_encode( $data['opening_hours'] );
        }
        return $wpdb->update( RAF_Helpers::table( self::$table ), $data, array( 'id' => $id ) );
    }

    public static function delete( $id ) {
        global $wpdb;
        return $wpdb->delete( RAF_Helpers::table( self::$table ), array( 'id' => $id ) );
    }

    public static function get_opening_hours( $location ) {
        if ( empty( $location->opening_hours ) ) return array();
        $hours = json_decode( $location->opening_hours, true );
        return is_array( $hours ) ? $hours : array();
    }

    public static function get_pickup_locations() {
        return self::get_all( array( 'type' => 'pickup' ) );
    }

    public static function get_dropoff_locations() {
        return self::get_all( array( 'type' => 'dropoff' ) );
    }
}
