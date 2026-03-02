<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RAF_Coupon_Model {

    private static $table = 'coupons';

    public static function get( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . RAF_Helpers::table( self::$table ) . " WHERE id = %d", $id ) );
    }

    public static function get_by_code( $code ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . RAF_Helpers::table( self::$table ) . " WHERE code = %s", strtoupper( $code ) ) );
    }

    public static function get_all( $args = array() ) {
        global $wpdb;
        $table = RAF_Helpers::table( self::$table );
        $defaults = array( 'status' => '', 'orderby' => 'created_at', 'order' => 'DESC', 'limit' => 20, 'offset' => 0 );
        $args = wp_parse_args( $args, $defaults );
        $where = array( '1=1' ); $values = array();
        if ( $args['status'] ) { $where[] = 'status = %s'; $values[] = $args['status']; }
        $sql = "SELECT * FROM $table WHERE " . implode( ' AND ', $where ) . " ORDER BY {$args['orderby']} {$args['order']}";
        if ( $args['limit'] > 0 ) { $sql .= $wpdb->prepare( " LIMIT %d OFFSET %d", $args['limit'], $args['offset'] ); }
        if ( $values ) { return $wpdb->get_results( $wpdb->prepare( $sql, $values ) ); }
        return $wpdb->get_results( $sql );
    }

    public static function create( $data ) {
        global $wpdb;
        $data['code'] = strtoupper( $data['code'] );
        $data['created_at'] = current_time( 'mysql' );
        if ( isset( $data['vehicle_ids'] ) && is_array( $data['vehicle_ids'] ) ) $data['vehicle_ids'] = wp_json_encode( $data['vehicle_ids'] );
        if ( isset( $data['category_ids'] ) && is_array( $data['category_ids'] ) ) $data['category_ids'] = wp_json_encode( $data['category_ids'] );
        if ( isset( $data['location_ids'] ) && is_array( $data['location_ids'] ) ) $data['location_ids'] = wp_json_encode( $data['location_ids'] );
        $wpdb->insert( RAF_Helpers::table( self::$table ), $data );
        return $wpdb->insert_id;
    }

    public static function update( $id, $data ) {
        global $wpdb;
        if ( isset( $data['code'] ) ) $data['code'] = strtoupper( $data['code'] );
        if ( isset( $data['vehicle_ids'] ) && is_array( $data['vehicle_ids'] ) ) $data['vehicle_ids'] = wp_json_encode( $data['vehicle_ids'] );
        if ( isset( $data['category_ids'] ) && is_array( $data['category_ids'] ) ) $data['category_ids'] = wp_json_encode( $data['category_ids'] );
        if ( isset( $data['location_ids'] ) && is_array( $data['location_ids'] ) ) $data['location_ids'] = wp_json_encode( $data['location_ids'] );
        return $wpdb->update( RAF_Helpers::table( self::$table ), $data, array( 'id' => $id ) );
    }

    public static function delete( $id ) {
        global $wpdb;
        return $wpdb->delete( RAF_Helpers::table( self::$table ), array( 'id' => $id ) );
    }

    public static function increment_usage( $id ) {
        global $wpdb;
        $wpdb->query( $wpdb->prepare( "UPDATE " . RAF_Helpers::table( self::$table ) . " SET times_used = times_used + 1 WHERE id = %d", $id ) );
    }

    public static function validate( $code, $booking_data = array() ) {
        $coupon = self::get_by_code( $code );
        if ( ! $coupon ) return new WP_Error( 'invalid', __( 'Invalid coupon code.', 'rentafleet' ) );
        if ( $coupon->status !== 'active' ) return new WP_Error( 'inactive', __( 'This coupon is no longer active.', 'rentafleet' ) );
        if ( $coupon->valid_from && current_time( 'mysql' ) < $coupon->valid_from ) return new WP_Error( 'not_started', __( 'This coupon is not yet valid.', 'rentafleet' ) );
        if ( $coupon->valid_to && current_time( 'mysql' ) > $coupon->valid_to ) return new WP_Error( 'expired', __( 'This coupon has expired.', 'rentafleet' ) );
        if ( $coupon->usage_limit > 0 && $coupon->times_used >= $coupon->usage_limit ) return new WP_Error( 'limit', __( 'This coupon has reached its usage limit.', 'rentafleet' ) );
        if ( ! empty( $booking_data['rental_days'] ) && $coupon->min_rental_days > 0 && $booking_data['rental_days'] < $coupon->min_rental_days ) return new WP_Error( 'min_days', sprintf( __( 'Minimum %d rental days required.', 'rentafleet' ), $coupon->min_rental_days ) );
        if ( ! empty( $booking_data['total'] ) && $coupon->min_order_amount > 0 && $booking_data['total'] < $coupon->min_order_amount ) return new WP_Error( 'min_amount', sprintf( __( 'Minimum order of %s required.', 'rentafleet' ), RAF_Helpers::format_price( $coupon->min_order_amount ) ) );
        return $coupon;
    }

    public static function calculate_discount( $coupon, $amount ) {
        if ( $coupon->discount_type === 'percentage' ) {
            $discount = $amount * ( $coupon->discount_value / 100 );
        } else {
            $discount = $coupon->discount_value;
        }
        if ( $coupon->max_discount > 0 && $discount > $coupon->max_discount ) {
            $discount = $coupon->max_discount;
        }
        return min( $discount, $amount );
    }
}
