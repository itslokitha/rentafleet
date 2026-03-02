<?php
/**
 * RentAFleet — Shortcodes (Stub)
 *
 * Placeholder class. Will be fully built when the Frontend module is developed.
 *
 * @package RentAFleet
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class RAF_Shortcodes {

    public function __construct() {
        add_shortcode( 'raf_search', array( $this, 'search_shortcode' ) );
        add_shortcode( 'raf_vehicles', array( $this, 'vehicles_shortcode' ) );
        add_shortcode( 'raf_booking', array( $this, 'booking_shortcode' ) );
        add_shortcode( 'raf_confirmation', array( $this, 'confirmation_shortcode' ) );
        add_shortcode( 'raf_my_bookings', array( $this, 'my_bookings_shortcode' ) );
    }

    public function search_shortcode( $atts ) {
        return '<div class="raf-shortcode-placeholder"><p>' . esc_html__( 'RentAFleet Search — Coming soon.', 'rentafleet' ) . '</p></div>';
    }

    public function vehicles_shortcode( $atts ) {
        return '<div class="raf-shortcode-placeholder"><p>' . esc_html__( 'RentAFleet Vehicles — Coming soon.', 'rentafleet' ) . '</p></div>';
    }

    public function booking_shortcode( $atts ) {
        return '<div class="raf-shortcode-placeholder"><p>' . esc_html__( 'RentAFleet Booking — Coming soon.', 'rentafleet' ) . '</p></div>';
    }

    public function confirmation_shortcode( $atts ) {
        return '<div class="raf-shortcode-placeholder"><p>' . esc_html__( 'RentAFleet Confirmation — Coming soon.', 'rentafleet' ) . '</p></div>';
    }

    public function my_bookings_shortcode( $atts ) {
        return '<div class="raf-shortcode-placeholder"><p>' . esc_html__( 'RentAFleet My Bookings — Coming soon.', 'rentafleet' ) . '</p></div>';
    }
}
