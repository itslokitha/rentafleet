<?php
/**
 * RentAFleet — Search Widget (Stub)
 *
 * @package RentAFleet
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class RAF_Search_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct( 'raf_search', __( 'RentAFleet Search', 'rentafleet' ), array(
            'description' => __( 'Vehicle search form widget.', 'rentafleet' ),
        ) );
    }

    public function widget( $args, $instance ) {
        echo $args['before_widget'];
        echo '<p>' . esc_html__( 'RentAFleet Search Widget — Coming soon.', 'rentafleet' ) . '</p>';
        echo $args['after_widget'];
    }

    public function form( $instance ) {
        echo '<p>' . esc_html__( 'No settings yet.', 'rentafleet' ) . '</p>';
    }

    public function update( $new_instance, $old_instance ) {
        return $new_instance;
    }
}
