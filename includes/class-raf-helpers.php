<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RAF_Helpers {

    /**
     * Get all booking statuses
     */
    public static function get_booking_statuses() {
        return array(
            'pending'    => __( 'Pending', 'rentafleet' ),
            'confirmed'  => __( 'Confirmed', 'rentafleet' ),
            'active'     => __( 'Active (Picked Up)', 'rentafleet' ),
            'completed'  => __( 'Completed', 'rentafleet' ),
            'cancelled'  => __( 'Cancelled', 'rentafleet' ),
            'no_show'    => __( 'No Show', 'rentafleet' ),
            'refunded'   => __( 'Refunded', 'rentafleet' ),
        );
    }

    /**
     * Get payment statuses
     */
    public static function get_payment_statuses() {
        return array(
            'pending'    => __( 'Pending', 'rentafleet' ),
            'partial'    => __( 'Partial', 'rentafleet' ),
            'paid'       => __( 'Paid', 'rentafleet' ),
            'refunded'   => __( 'Refunded', 'rentafleet' ),
            'failed'     => __( 'Failed', 'rentafleet' ),
        );
    }

    /**
     * Get currencies
     */
    public static function get_currencies() {
        return array(
            'USD' => array( 'name' => 'US Dollar', 'symbol' => '$' ),
            'EUR' => array( 'name' => 'Euro', 'symbol' => '€' ),
            'GBP' => array( 'name' => 'British Pound', 'symbol' => '£' ),
            'CAD' => array( 'name' => 'Canadian Dollar', 'symbol' => 'C$' ),
            'AUD' => array( 'name' => 'Australian Dollar', 'symbol' => 'A$' ),
            'AED' => array( 'name' => 'UAE Dirham', 'symbol' => 'د.إ' ),
            'SAR' => array( 'name' => 'Saudi Riyal', 'symbol' => '﷼' ),
            'INR' => array( 'name' => 'Indian Rupee', 'symbol' => '₹' ),
            'JPY' => array( 'name' => 'Japanese Yen', 'symbol' => '¥' ),
            'CHF' => array( 'name' => 'Swiss Franc', 'symbol' => 'CHF' ),
            'SEK' => array( 'name' => 'Swedish Krona', 'symbol' => 'kr' ),
            'NOK' => array( 'name' => 'Norwegian Krone', 'symbol' => 'kr' ),
            'DKK' => array( 'name' => 'Danish Krone', 'symbol' => 'kr' ),
            'ZAR' => array( 'name' => 'South African Rand', 'symbol' => 'R' ),
            'MXN' => array( 'name' => 'Mexican Peso', 'symbol' => '$' ),
            'BRL' => array( 'name' => 'Brazilian Real', 'symbol' => 'R$' ),
            'TRY' => array( 'name' => 'Turkish Lira', 'symbol' => '₺' ),
            'THB' => array( 'name' => 'Thai Baht', 'symbol' => '฿' ),
            'MYR' => array( 'name' => 'Malaysian Ringgit', 'symbol' => 'RM' ),
            'SGD' => array( 'name' => 'Singapore Dollar', 'symbol' => 'S$' ),
            'NZD' => array( 'name' => 'New Zealand Dollar', 'symbol' => 'NZ$' ),
        );
    }

    /**
     * Get currency symbol
     */
    public static function get_currency_symbol() {
        $currency   = get_option( 'raf_currency', 'USD' );
        $currencies = self::get_currencies();
        return isset( $currencies[ $currency ] ) ? $currencies[ $currency ]['symbol'] : '$';
    }

    /**
     * Format price
     */
    public static function format_price( $amount ) {
        $symbol   = self::get_currency_symbol();
        $position = get_option( 'raf_currency_position', 'before' );
        $amount   = number_format( (float) $amount, 2, '.', ',' );

        if ( $position === 'before' ) {
            return $symbol . $amount;
        }
        return $amount . $symbol;
    }

    /**
     * Generate unique booking number
     */
    public static function generate_booking_number() {
        $prefix = get_option( 'raf_booking_prefix', 'RAF' );
        $number = $prefix . '-' . strtoupper( substr( uniqid(), -6 ) ) . '-' . wp_rand( 100, 999 );
        return $number;
    }

    /**
     * Calculate rental days between two dates
     */
    public static function calculate_rental_days( $pickup, $dropoff ) {
        $pickup_dt  = new DateTime( $pickup );
        $dropoff_dt = new DateTime( $dropoff );
        $interval   = $pickup_dt->diff( $dropoff_dt );
        $days       = $interval->days;

        // If there are remaining hours, count as extra day
        $hours = ( $interval->h > 0 || $interval->i > 0 ) ? 1 : 0;

        return max( 1, $days + $hours );
    }

    /**
     * Calculate rental hours
     */
    public static function calculate_rental_hours( $pickup, $dropoff ) {
        $pickup_dt  = new DateTime( $pickup );
        $dropoff_dt = new DateTime( $dropoff );
        $interval   = $pickup_dt->diff( $dropoff_dt );

        return ( $interval->days * 24 ) + $interval->h;
    }

    /**
     * Check if a date is weekend
     */
    public static function is_weekend( $date ) {
        $day = date( 'N', strtotime( $date ) );
        return ( $day >= 6 );
    }

    /**
     * Format date for display
     */
    public static function format_date( $date ) {
        $format = get_option( 'raf_date_format', 'Y-m-d' );
        return date_i18n( $format, strtotime( $date ) );
    }

    /**
     * Format datetime for display
     */
    public static function format_datetime( $datetime ) {
        $date_format = get_option( 'raf_date_format', 'Y-m-d' );
        $time_format = get_option( 'raf_time_format', 'H:i' );
        return date_i18n( $date_format . ' ' . $time_format, strtotime( $datetime ) );
    }

    /**
     * Get time slots for booking form
     */
    public static function get_time_slots() {
        $interval = (int) get_option( 'raf_time_slot_interval', 30 );
        $slots    = array();
        $start    = strtotime( '00:00' );
        $end      = strtotime( '23:59' );

        while ( $start <= $end ) {
            $slots[ date( 'H:i', $start ) ] = date( get_option( 'raf_time_format', 'H:i' ), $start );
            $start += $interval * 60;
        }

        return $slots;
    }

    /**
     * Get transmission types
     */
    public static function get_transmissions() {
        return array(
            'automatic' => __( 'Automatic', 'rentafleet' ),
            'manual'    => __( 'Manual', 'rentafleet' ),
            'cvt'       => __( 'CVT', 'rentafleet' ),
        );
    }

    /**
     * Get fuel types
     */
    public static function get_fuel_types() {
        return array(
            'gasoline' => __( 'Gasoline', 'rentafleet' ),
            'diesel'   => __( 'Diesel', 'rentafleet' ),
            'hybrid'   => __( 'Hybrid', 'rentafleet' ),
            'electric' => __( 'Electric', 'rentafleet' ),
            'lpg'      => __( 'LPG', 'rentafleet' ),
        );
    }

    /**
     * Get vehicle features list
     */
    public static function get_vehicle_features() {
        return array(
            'ac'              => __( 'Air Conditioning', 'rentafleet' ),
            'gps'             => __( 'GPS Navigation', 'rentafleet' ),
            'bluetooth'       => __( 'Bluetooth', 'rentafleet' ),
            'usb'             => __( 'USB Port', 'rentafleet' ),
            'cruise_control'  => __( 'Cruise Control', 'rentafleet' ),
            'parking_sensors' => __( 'Parking Sensors', 'rentafleet' ),
            'backup_camera'   => __( 'Backup Camera', 'rentafleet' ),
            'heated_seats'    => __( 'Heated Seats', 'rentafleet' ),
            'sunroof'         => __( 'Sunroof', 'rentafleet' ),
            'leather_seats'   => __( 'Leather Seats', 'rentafleet' ),
            'four_wd'         => __( '4WD/AWD', 'rentafleet' ),
            'child_seat_compatible' => __( 'Child Seat Compatible', 'rentafleet' ),
            'keyless_entry'   => __( 'Keyless Entry', 'rentafleet' ),
            'apple_carplay'   => __( 'Apple CarPlay', 'rentafleet' ),
            'android_auto'    => __( 'Android Auto', 'rentafleet' ),
        );
    }

    /**
     * Sanitize and validate date
     */
    public static function sanitize_date( $date ) {
        $timestamp = strtotime( $date );
        return $timestamp ? date( 'Y-m-d', $timestamp ) : '';
    }

    /**
     * Sanitize datetime
     */
    public static function sanitize_datetime( $datetime ) {
        $timestamp = strtotime( $datetime );
        return $timestamp ? date( 'Y-m-d H:i:s', $timestamp ) : '';
    }

    /**
     * Get table name with prefix
     */
    public static function table( $name ) {
        global $wpdb;
        return $wpdb->prefix . 'raf_' . $name;
    }

    /**
     * Admin notice helper
     */
    public static function admin_notice( $message, $type = 'success' ) {
        add_action( 'admin_notices', function() use ( $message, $type ) {
            printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $type ), esc_html( $message ) );
        } );
    }

    /**
     * Get booking status badge HTML
     */
    public static function status_badge( $status ) {
        $labels = array_merge( self::get_booking_statuses(), array(
            'active'   => __( 'Active', 'rentafleet' ),
            'inactive' => __( 'Inactive', 'rentafleet' ),
            'paid'     => __( 'Paid', 'rentafleet' ),
            'draft'    => __( 'Draft', 'rentafleet' ),
        ) );
        $label = isset( $labels[ $status ] ) ? $labels[ $status ] : ucfirst( str_replace( '_', ' ', $status ) );
        $css_status = sanitize_html_class( $status );
        return sprintf( '<span class="raf-badge raf-badge-%s">%s</span>', $css_status, esc_html( $label ) );
    }

    /**
     * Log activity
     */
    public static function log( $message, $context = array() ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'RentAFleet: ' . $message . ( $context ? ' | ' . wp_json_encode( $context ) : '' ) );
        }
    }
}
