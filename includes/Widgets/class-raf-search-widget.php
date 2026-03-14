<?php
/**
 * RentAFleet — Search Widget
 *
 * @package RentAFleet
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class RAF_Search_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct( 'raf_search', __( 'RentAFleet Search', 'rentafleet' ), array(
            'description' => __( 'Compact vehicle search form widget for sidebars.', 'rentafleet' ),
            'classname'   => 'raf-search-widget',
        ) );
    }

    public function widget( $args, $instance ) {
        $title = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Rent a Bike', 'rentafleet' );
        $title = apply_filters( 'widget_title', $title, $instance, $this->id_base );

        $locations  = RAF_Location::get_all( array( 'type' => 'pickup' ) );
        $time_slots = RAF_Helpers::get_time_slots();
        $target_url = ! empty( $instance['target_page'] ) ? get_permalink( $instance['target_page'] ) : '';

        echo $args['before_widget'];

        if ( $title ) {
            echo $args['before_title'] . esc_html( $title ) . $args['after_title'];
        }
        ?>
        <form class="raf-widget-search-form" method="get" action="<?php echo esc_url( $target_url ?: home_url() ); ?>">
            <div class="raf-widget-field">
                <label><?php esc_html_e( 'Pick-up Location', 'rentafleet' ); ?></label>
                <select name="pickup_location_id" required>
                    <option value=""><?php esc_html_e( 'Select...', 'rentafleet' ); ?></option>
                    <?php foreach ( $locations as $loc ) : ?>
                        <option value="<?php echo esc_attr( $loc->id ); ?>"><?php echo esc_html( $loc->name ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="raf-widget-field">
                <label><?php esc_html_e( 'Pick-up Date', 'rentafleet' ); ?></label>
                <input type="date" name="pickup_date" required min="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>">
            </div>
            <div class="raf-widget-field">
                <label><?php esc_html_e( 'Pick-up Time', 'rentafleet' ); ?></label>
                <select name="pickup_time">
                    <?php foreach ( $time_slots as $val => $label ) : ?>
                        <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $val, '10:00' ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="raf-widget-field">
                <label><?php esc_html_e( 'Return Date', 'rentafleet' ); ?></label>
                <input type="date" name="dropoff_date" required min="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>">
            </div>
            <div class="raf-widget-field">
                <label><?php esc_html_e( 'Return Time', 'rentafleet' ); ?></label>
                <select name="dropoff_time">
                    <?php foreach ( $time_slots as $val => $label ) : ?>
                        <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $val, '10:00' ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="raf-btn raf-btn-primary raf-widget-submit"><?php esc_html_e( 'Search', 'rentafleet' ); ?></button>
        </form>
        <?php
        echo $args['after_widget'];
    }

    public function form( $instance ) {
        $title       = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Rent a Bike', 'rentafleet' );
        $target_page = ! empty( $instance['target_page'] ) ? $instance['target_page'] : '';

        $pages = get_pages();
        ?>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'rentafleet' ); ?></label>
            <input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'target_page' ) ); ?>"><?php esc_html_e( 'Results Page:', 'rentafleet' ); ?></label>
            <select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'target_page' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'target_page' ) ); ?>">
                <option value=""><?php esc_html_e( '— Select Page —', 'rentafleet' ); ?></option>
                <?php foreach ( $pages as $page ) : ?>
                    <option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( $target_page, $page->ID ); ?>><?php echo esc_html( $page->post_title ); ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <?php
    }

    public function update( $new_instance, $old_instance ) {
        $instance = array();
        $instance['title']       = sanitize_text_field( $new_instance['title'] ?? '' );
        $instance['target_page'] = intval( $new_instance['target_page'] ?? 0 );
        return $instance;
    }
}
