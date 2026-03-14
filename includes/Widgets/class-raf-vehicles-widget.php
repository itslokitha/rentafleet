<?php
/**
 * RentAFleet — Vehicles Widget
 *
 * @package RentAFleet
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class RAF_Vehicles_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct( 'raf_vehicles', __( 'RentAFleet Vehicles', 'rentafleet' ), array(
            'description' => __( 'Display featured vehicles in a widget.', 'rentafleet' ),
            'classname'   => 'raf-vehicles-widget',
        ) );
    }

    public function widget( $args, $instance ) {
        $title    = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Our Bikes', 'rentafleet' );
        $title    = apply_filters( 'widget_title', $title, $instance, $this->id_base );
        $count    = ! empty( $instance['count'] ) ? intval( $instance['count'] ) : 3;
        $category = ! empty( $instance['category'] ) ? intval( $instance['category'] ) : 0;

        $vargs = array( 'status' => 'active', 'limit' => $count );
        if ( $category ) {
            $vargs['category_id'] = $category;
        }

        $vehicles = RAF_Vehicle::get_all( $vargs );
        $pricing  = new RAF_Pricing_Engine();

        echo $args['before_widget'];

        if ( $title ) {
            echo $args['before_title'] . esc_html( $title ) . $args['after_title'];
        }

        if ( ! empty( $vehicles ) ) :
        ?>
        <div class="raf-widget-vehicles">
            <?php foreach ( $vehicles as $vehicle ) :
                $daily_rate = $pricing->get_display_price( $vehicle->id );
                $image_url  = $vehicle->featured_image_id ? wp_get_attachment_image_url( $vehicle->featured_image_id, 'medium' ) : '';
                $booking_url = add_query_arg( 'vehicle_id', $vehicle->id, get_permalink( get_option( 'raf_booking_page' ) ) );
            ?>
            <div class="raf-widget-vehicle-item">
                <?php if ( $image_url ) : ?>
                    <a href="<?php echo esc_url( $booking_url ); ?>">
                        <img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $vehicle->name ); ?>" loading="lazy">
                    </a>
                <?php endif; ?>
                <div class="raf-widget-vehicle-info">
                    <h4><a href="<?php echo esc_url( $booking_url ); ?>"><?php echo esc_html( $vehicle->name ); ?></a></h4>
                    <div class="raf-widget-vehicle-meta">
                        <?php if ( $vehicle->engine_cc ) : ?>
                            <span><?php echo esc_html( $vehicle->engine_cc . 'cc' ); ?></span>
                        <?php endif; ?>
                        <?php if ( $vehicle->bike_type ) :
                            $bike_types = RAF_Helpers::get_bike_types();
                        ?>
                            <span><?php echo esc_html( isset( $bike_types[ $vehicle->bike_type ] ) ? $bike_types[ $vehicle->bike_type ] : ucfirst( $vehicle->bike_type ) ); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ( $daily_rate > 0 ) : ?>
                        <div class="raf-widget-vehicle-price">
                            <strong><?php echo esc_html( RAF_Helpers::format_price( $daily_rate ) ); ?></strong>/<?php esc_html_e( 'day', 'rentafleet' ); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
        else :
            echo '<p>' . esc_html__( 'No vehicles available.', 'rentafleet' ) . '</p>';
        endif;

        echo $args['after_widget'];
    }

    public function form( $instance ) {
        $title    = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Our Bikes', 'rentafleet' );
        $count    = ! empty( $instance['count'] ) ? intval( $instance['count'] ) : 3;
        $category = ! empty( $instance['category'] ) ? intval( $instance['category'] ) : 0;

        global $wpdb;
        $categories = $wpdb->get_results(
            "SELECT id, name FROM " . RAF_Helpers::table( 'vehicle_categories' ) . " WHERE status = 'active' ORDER BY name ASC"
        );
        ?>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'rentafleet' ); ?></label>
            <input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>"><?php esc_html_e( 'Number of vehicles:', 'rentafleet' ); ?></label>
            <input class="tiny-text" id="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'count' ) ); ?>" type="number" value="<?php echo esc_attr( $count ); ?>" min="1" max="10">
        </p>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'category' ) ); ?>"><?php esc_html_e( 'Category:', 'rentafleet' ); ?></label>
            <select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'category' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'category' ) ); ?>">
                <option value="0"><?php esc_html_e( 'All Categories', 'rentafleet' ); ?></option>
                <?php foreach ( $categories as $cat ) : ?>
                    <option value="<?php echo esc_attr( $cat->id ); ?>" <?php selected( $category, $cat->id ); ?>><?php echo esc_html( $cat->name ); ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <?php
    }

    public function update( $new_instance, $old_instance ) {
        $instance = array();
        $instance['title']    = sanitize_text_field( $new_instance['title'] ?? '' );
        $instance['count']    = max( 1, intval( $new_instance['count'] ?? 3 ) );
        $instance['category'] = intval( $new_instance['category'] ?? 0 );
        return $instance;
    }
}
