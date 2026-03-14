<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RAF_Booking_Engine {

    public function __construct() {
        add_action( 'wp_ajax_raf_create_booking', array( $this, 'ajax_create_booking' ) );
        add_action( 'wp_ajax_nopriv_raf_create_booking', array( $this, 'ajax_create_booking' ) );
        add_action( 'wp_ajax_raf_search_vehicles', array( $this, 'ajax_search_vehicles' ) );
        add_action( 'wp_ajax_nopriv_raf_search_vehicles', array( $this, 'ajax_search_vehicles' ) );
        add_action( 'wp_ajax_raf_apply_coupon', array( $this, 'ajax_apply_coupon' ) );
        add_action( 'wp_ajax_nopriv_raf_apply_coupon', array( $this, 'ajax_apply_coupon' ) );

        // Cron for reminders
        add_action( 'raf_daily_cron', array( $this, 'send_reminders' ) );
        if ( ! wp_next_scheduled( 'raf_daily_cron' ) ) {
            wp_schedule_event( time(), 'daily', 'raf_daily_cron' );
        }
    }

    /**
     * Create a booking
     */
    public function create_booking( $data ) {
        // Validate required fields
        $required = array( 'vehicle_id', 'pickup_date', 'dropoff_date', 'pickup_location_id', 'dropoff_location_id' );
        foreach ( $required as $field ) {
            if ( empty( $data[ $field ] ) ) {
                return new WP_Error( 'missing_field', sprintf( __( 'Missing required field: %s', 'rentafleet' ), $field ) );
            }
        }

        // Check availability
        if ( ! RAF_Availability_Checker::is_available( $data['vehicle_id'], $data['pickup_date'], $data['dropoff_date'], $data['pickup_location_id'] ) ) {
            return new WP_Error( 'not_available', __( 'Vehicle is not available for the selected dates.', 'rentafleet' ) );
        }

        // Check min booking advance
        $min_advance = (int) get_option( 'raf_min_booking_advance', 1 );
        $pickup_dt   = new DateTime( $data['pickup_date'] );
        $now_dt      = new DateTime( current_time( 'mysql' ) );
        if ( $pickup_dt->diff( $now_dt )->days < $min_advance && $pickup_dt > $now_dt ) {
            return new WP_Error( 'too_soon', sprintf( __( 'Bookings must be made at least %d day(s) in advance.', 'rentafleet' ), $min_advance ) );
        }

        // Get or create customer
        $customer_data = array(
            'first_name'      => sanitize_text_field( $data['first_name'] ?? '' ),
            'last_name'       => sanitize_text_field( $data['last_name'] ?? '' ),
            'email'           => sanitize_email( $data['email'] ?? '' ),
            'phone'           => sanitize_text_field( $data['phone'] ?? '' ),
            'passport_number' => sanitize_text_field( $data['passport_number'] ?? '' ),
            'citizenship'     => sanitize_text_field( $data['citizenship'] ?? '' ),
        );

        $customer_id = RAF_Customer::get_or_create( $customer_data );
        if ( ! $customer_id ) {
            return new WP_Error( 'customer_error', __( 'Failed to create customer record.', 'rentafleet' ) );
        }

        // Calculate pricing
        $pricing_engine = new RAF_Pricing_Engine();
        $pricing = $pricing_engine->calculate_rental_price( array(
            'vehicle_id'          => $data['vehicle_id'],
            'pickup_date'         => $data['pickup_date'],
            'dropoff_date'        => $data['dropoff_date'],
            'extras'              => $data['extras'] ?? array(),
            'insurance'           => $data['insurance'] ?? array(),
            'coupon_code'         => $data['coupon_code'] ?? '',
            'pickup_location_id'  => $data['pickup_location_id'],
            'dropoff_location_id' => $data['dropoff_location_id'],
        ) );

        if ( is_wp_error( $pricing ) ) {
            return $pricing;
        }

        // Create booking record
        $auto_confirm = get_option( 'raf_auto_confirm', 0 );
        $status = $auto_confirm ? 'confirmed' : 'pending';

        $booking_data = array(
            'customer_id'         => $customer_id,
            'vehicle_id'          => $data['vehicle_id'],
            'pickup_location_id'  => $data['pickup_location_id'],
            'dropoff_location_id' => $data['dropoff_location_id'],
            'pickup_date'         => RAF_Helpers::sanitize_datetime( $data['pickup_date'] ),
            'dropoff_date'        => RAF_Helpers::sanitize_datetime( $data['dropoff_date'] ),
            'rental_days'         => $pricing['rental_days'],
            'base_price'          => $pricing['base_price'],
            'extras_total'        => $pricing['extras_total'],
            'insurance_total'     => $pricing['insurance_total'],
            'location_fees'       => $pricing['location_fees'],
            'tax_amount'          => $pricing['tax_amount'],
            'discount_amount'     => $pricing['discount_amount'],
            'coupon_id'           => $pricing['coupon'] ? $pricing['coupon']['id'] : null,
            'coupon_code'         => $pricing['coupon'] ? $pricing['coupon']['code'] : '',
            'total_price'         => $pricing['total'],
            'deposit_amount'      => $pricing['deposit_amount'],
            'currency'            => $pricing['currency'],
            'status'              => $status,
            'payment_status'      => 'pending',
            'rider_name'          => sanitize_text_field( $data['rider_name'] ?? '' ),
            'notes'               => sanitize_textarea_field( $data['notes'] ?? '' ),
            'ip_address'          => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent'          => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'source'              => $data['source'] ?? 'website',
        );

        $booking_id = RAF_Booking_Model::create( $booking_data );
        if ( ! $booking_id ) {
            return new WP_Error( 'booking_error', __( 'Failed to create booking.', 'rentafleet' ) );
        }

        // Save booking extras
        if ( ! empty( $pricing['extras'] ) ) {
            foreach ( $pricing['extras'] as $extra ) {
                RAF_Booking_Model::add_extra( $booking_id, array(
                    'extra_id'   => $extra['id'],
                    'name'       => $extra['name'],
                    'quantity'   => $extra['quantity'],
                    'price'      => $extra['price'],
                    'price_type' => $extra['type'],
                    'total'      => $extra['total'],
                ) );
            }
        }

        // Save booking insurance
        if ( ! empty( $pricing['insurance'] ) ) {
            foreach ( $pricing['insurance'] as $ins ) {
                RAF_Booking_Model::add_insurance( $booking_id, array(
                    'insurance_id' => $ins['id'],
                    'name'         => $ins['name'],
                    'price_per_day'=> $ins['price'],
                    'total'        => $ins['total'],
                ) );
            }
        }

        // Increment coupon usage
        if ( ! empty( $pricing['coupon'] ) ) {
            RAF_Coupon_Model::increment_usage( $pricing['coupon']['id'] );
        }

        // Update customer stats
        RAF_Customer::update_stats( $customer_id );

        // Send emails
        $booking = RAF_Booking_Model::get( $booking_id );
        do_action( 'raf_booking_created', $booking );

        $email_manager = new RAF_Email_Manager();
        if ( $status === 'confirmed' ) {
            $email_manager->send_booking_confirmed( $booking );
        } else {
            $email_manager->send_booking_pending( $booking );
        }
        $email_manager->send_admin_new_booking( $booking );

        return $booking;
    }

    /**
     * AJAX: Search available vehicles
     */
    public function ajax_search_vehicles() {
        check_ajax_referer( 'raf_public_nonce', 'nonce' );

        $pickup_date  = sanitize_text_field( $_POST['pickup_date'] ?? '' );
        $dropoff_date = sanitize_text_field( $_POST['dropoff_date'] ?? '' );
        $location_id  = intval( $_POST['location_id'] ?? 0 );
        $category_id  = intval( $_POST['category_id'] ?? 0 );

        if ( ! $pickup_date || ! $dropoff_date ) {
            wp_send_json_error( __( 'Please select pickup and return dates.', 'rentafleet' ) );
        }

        $vehicles = RAF_Availability_Checker::get_available_vehicles( $pickup_date, $dropoff_date, $location_id, $category_id );

        // Enrich with pricing
        $pricing_engine = new RAF_Pricing_Engine();
        $results = array();

        foreach ( $vehicles as $v ) {
            $price = $pricing_engine->calculate_rental_price( array(
                'vehicle_id'          => $v->id,
                'pickup_date'         => $pickup_date,
                'dropoff_date'        => $dropoff_date,
                'pickup_location_id'  => $location_id,
                'dropoff_location_id' => $location_id,
            ) );

            $results[] = array(
                'id'            => $v->id,
                'name'          => $v->name,
                'slug'          => $v->slug,
                'make'          => $v->make,
                'model'         => $v->model,
                'year'          => $v->year,
                'engine_cc'     => $v->engine_cc,
                'bike_type'     => $v->bike_type,
                'image'         => $v->featured_image_id ? wp_get_attachment_image_url( $v->featured_image_id, 'medium' ) : '',
                'features'      => RAF_Vehicle::get_features( $v ),
                'rating'        => RAF_Vehicle::get_average_rating( $v->id ),
                'daily_rate'    => is_wp_error( $price ) ? 0 : $price['daily_rate'],
                'total_price'   => is_wp_error( $price ) ? 0 : $price['total'],
                'rental_days'   => is_wp_error( $price ) ? 0 : $price['rental_days'],
                'deposit'       => $v->deposit_amount,
                'description'   => $v->short_description,
            );
        }

        wp_send_json_success( array(
            'vehicles'    => $results,
            'count'       => count( $results ),
            'rental_days' => ! empty( $results ) ? $results[0]['rental_days'] : 0,
        ) );
    }

    /**
     * AJAX: Create booking from frontend
     */
    public function ajax_create_booking() {
        check_ajax_referer( 'raf_public_nonce', 'nonce' );

        $data = array(
            'vehicle_id'          => intval( $_POST['vehicle_id'] ?? 0 ),
            'pickup_date'         => sanitize_text_field( $_POST['pickup_date'] ?? '' ),
            'dropoff_date'        => sanitize_text_field( $_POST['dropoff_date'] ?? '' ),
            'pickup_location_id'  => intval( $_POST['pickup_location_id'] ?? 0 ),
            'dropoff_location_id' => intval( $_POST['dropoff_location_id'] ?? 0 ),
            'first_name'          => sanitize_text_field( $_POST['first_name'] ?? '' ),
            'last_name'           => sanitize_text_field( $_POST['last_name'] ?? '' ),
            'email'               => sanitize_email( $_POST['email'] ?? '' ),
            'phone'               => sanitize_text_field( $_POST['phone'] ?? '' ),
            'passport_number'     => sanitize_text_field( $_POST['passport_number'] ?? '' ),
            'citizenship'         => sanitize_text_field( $_POST['citizenship'] ?? '' ),
            'rider_name'          => sanitize_text_field( $_POST['rider_name'] ?? '' ),
            'extras'              => isset( $_POST['extras'] ) ? (array) $_POST['extras'] : array(),
            'insurance'           => isset( $_POST['insurance'] ) ? array_map( 'intval', (array) $_POST['insurance'] ) : array(),
            'coupon_code'         => sanitize_text_field( $_POST['coupon_code'] ?? '' ),
            'notes'               => sanitize_textarea_field( $_POST['notes'] ?? '' ),
        );

        $result = $this->create_booking( $data );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        $confirm_url = get_permalink( get_option( 'raf_confirmation_page' ) );
        $confirm_url = add_query_arg( 'booking', $result->booking_number, $confirm_url );

        wp_send_json_success( array(
            'booking_number' => $result->booking_number,
            'redirect_url'   => $confirm_url,
            'message'        => __( 'Booking created successfully!', 'rentafleet' ),
        ) );
    }

    /**
     * AJAX: Apply coupon
     */
    public function ajax_apply_coupon() {
        check_ajax_referer( 'raf_public_nonce', 'nonce' );

        $code = sanitize_text_field( $_POST['coupon_code'] ?? '' );
        if ( ! $code ) {
            wp_send_json_error( __( 'Please enter a coupon code.', 'rentafleet' ) );
        }

        $coupon = RAF_Coupon_Model::validate( $code );
        if ( is_wp_error( $coupon ) ) {
            wp_send_json_error( $coupon->get_error_message() );
        }

        wp_send_json_success( array(
            'code'           => $coupon->code,
            'discount_type'  => $coupon->discount_type,
            'discount_value' => $coupon->discount_value,
            'message'        => sprintf( __( 'Coupon applied: %s%% off!', 'rentafleet' ),
                $coupon->discount_type === 'percentage' ? $coupon->discount_value : RAF_Helpers::format_price( $coupon->discount_value )
            ),
        ) );
    }

    /**
     * Send pickup/return reminders
     */
    public function send_reminders() {
        $email_manager = new RAF_Email_Manager();
        $tomorrow = date( 'Y-m-d', strtotime( '+1 day' ) );

        // Pickup reminders
        global $wpdb;
        $pickups = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM " . RAF_Helpers::table( 'bookings' ) .
            " WHERE DATE(pickup_date) = %s AND status = 'confirmed'",
            $tomorrow
        ) );
        foreach ( $pickups as $booking ) {
            $email_manager->send_pickup_reminder( $booking );
        }

        // Return reminders
        $returns = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM " . RAF_Helpers::table( 'bookings' ) .
            " WHERE DATE(dropoff_date) = %s AND status = 'active'",
            $tomorrow
        ) );
        foreach ( $returns as $booking ) {
            $email_manager->send_return_reminder( $booking );
        }
    }
}
