<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RAF_Vehicle {

    private static $table = 'vehicles';

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
            'status'      => 'active',
            'category_id' => 0,
            'search'      => '',
            'orderby'     => 'sort_order',
            'order'       => 'ASC',
            'limit'       => 0,
            'offset'      => 0,
        );
        $args = wp_parse_args( $args, $defaults );

        $where = array( '1=1' );
        $values = array();

        if ( $args['status'] ) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }
        if ( $args['category_id'] ) {
            $where[] = 'category_id = %d';
            $values[] = $args['category_id'];
        }
        if ( $args['search'] ) {
            $where[] = '(name LIKE %s OR make LIKE %s OR model LIKE %s OR license_plate LIKE %s)';
            $search = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $values = array_merge( $values, array( $search, $search, $search, $search ) );
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
        $table = RAF_Helpers::table( self::$table );

        $where = array( '1=1' );
        $values = array();

        if ( ! empty( $args['status'] ) ) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }

        $sql = "SELECT COUNT(*) FROM $table WHERE " . implode( ' AND ', $where );
        if ( $values ) {
            return (int) $wpdb->get_var( $wpdb->prepare( $sql, $values ) );
        }
        return (int) $wpdb->get_var( $sql );
    }

    public static function create( $data ) {
        global $wpdb;
        $data['created_at'] = current_time( 'mysql' );
        $data['updated_at'] = current_time( 'mysql' );

        if ( empty( $data['slug'] ) && ! empty( $data['name'] ) ) {
            $data['slug'] = sanitize_title( $data['name'] );
        }

        // Serialize arrays
        if ( isset( $data['gallery'] ) && is_array( $data['gallery'] ) ) {
            $data['gallery'] = wp_json_encode( $data['gallery'] );
        }
        if ( isset( $data['features'] ) && is_array( $data['features'] ) ) {
            $data['features'] = wp_json_encode( $data['features'] );
        }

        $wpdb->insert( RAF_Helpers::table( self::$table ), $data );
        return $wpdb->insert_id;
    }

    public static function update( $id, $data ) {
        global $wpdb;
        $data['updated_at'] = current_time( 'mysql' );

        if ( isset( $data['gallery'] ) && is_array( $data['gallery'] ) ) {
            $data['gallery'] = wp_json_encode( $data['gallery'] );
        }
        if ( isset( $data['features'] ) && is_array( $data['features'] ) ) {
            $data['features'] = wp_json_encode( $data['features'] );
        }

        return $wpdb->update( RAF_Helpers::table( self::$table ), $data, array( 'id' => $id ) );
    }

    public static function delete( $id ) {
        global $wpdb;
        return $wpdb->delete( RAF_Helpers::table( self::$table ), array( 'id' => $id ) );
    }

    public static function get_by_location( $location_id ) {
        global $wpdb;
        $vehicles_table  = RAF_Helpers::table( 'vehicles' );
        $locations_table = RAF_Helpers::table( 'vehicle_locations' );

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT v.*, vl.units_at_location FROM $vehicles_table v
             INNER JOIN $locations_table vl ON v.id = vl.vehicle_id
             WHERE vl.location_id = %d AND v.status = 'active'
             ORDER BY v.sort_order ASC",
            $location_id
        ) );
    }

    public static function get_gallery( $vehicle ) {
        if ( ! $vehicle || empty( $vehicle->gallery ) ) return array();
        $ids = json_decode( $vehicle->gallery, true );
        if ( ! is_array( $ids ) ) return array();
        return $ids;
    }

    public static function get_features( $vehicle ) {
        if ( ! $vehicle || empty( $vehicle->features ) ) return array();
        $features = json_decode( $vehicle->features, true );
        if ( ! is_array( $features ) ) return array();
        return $features;
    }

    public static function get_locations( $vehicle_id ) {
        global $wpdb;
        $vl_table = RAF_Helpers::table( 'vehicle_locations' );
        $l_table  = RAF_Helpers::table( 'locations' );

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT l.*, vl.units_at_location FROM $l_table l
             INNER JOIN $vl_table vl ON l.id = vl.location_id
             WHERE vl.vehicle_id = %d AND l.status = 'active'",
            $vehicle_id
        ) );
    }

    public static function set_locations( $vehicle_id, $locations ) {
        global $wpdb;
        $table = RAF_Helpers::table( 'vehicle_locations' );

        // Remove existing
        $wpdb->delete( $table, array( 'vehicle_id' => $vehicle_id ) );

        // Insert new
        foreach ( $locations as $loc ) {
            $wpdb->insert( $table, array(
                'vehicle_id'        => $vehicle_id,
                'location_id'       => $loc['location_id'],
                'units_at_location' => isset( $loc['units'] ) ? $loc['units'] : 1,
            ) );
        }
    }

    public static function get_average_rating( $vehicle_id ) {
        global $wpdb;
        return (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT AVG(rating) FROM " . RAF_Helpers::table( 'reviews' ) .
            " WHERE vehicle_id = %d AND is_approved = 1",
            $vehicle_id
        ) );
    }
}
