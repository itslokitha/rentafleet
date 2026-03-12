<?php
/**
 * RentAFleet — REST API
 *
 * Provides RESTful API endpoints for external integrations.
 *
 * @package RentAFleet
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class RAF_REST_API {

    private $namespace = 'rentafleet/v1';

    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {

        // Vehicles
        register_rest_route( $this->namespace, '/vehicles', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_vehicles' ),
            'permission_callback' => '__return_true',
            'args'                => array(
                'category_id' => array( 'type' => 'integer', 'default' => 0 ),
                'location_id' => array( 'type' => 'integer', 'default' => 0 ),
                'limit'       => array( 'type' => 'integer', 'default' => 20 ),
                'offset'      => array( 'type' => 'integer', 'default' => 0 ),
            ),
        ) );

        register_rest_route( $this->namespace, '/vehicles/(?P<id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_vehicle' ),
            'permission_callback' => '__return_true',
            'args'                => array(
                'id' => array( 'type' => 'integer', 'required' => true ),
            ),
        ) );

        // Locations
        register_rest_route( $this->namespace, '/locations', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_locations' ),
            'permission_callback' => '__return_true',
        ) );

        // Availability check
        register_rest_route( $this->namespace, '/availability', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'check_availability' ),
            'permission_callback' => '__return_true',
            'args'                => array(
                'vehicle_id'   => array( 'type' => 'integer', 'required' => true ),
                'pickup_date'  => array( 'type' => 'string', 'required' => true ),
                'dropoff_date' => array( 'type' => 'string', 'required' => true ),
                'location_id'  => array( 'type' => 'integer', 'default' => 0 ),
            ),
        ) );

        // Available vehicles search
        register_rest_route( $this->namespace, '/search', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'search_vehicles' ),
            'permission_callback' => '__return_true',
            'args'                => array(
                'pickup_date'  => array( 'type' => 'string', 'required' => true ),
                'dropoff_date' => array( 'type' => 'string', 'required' => true ),
                'location_id'  => array( 'type' => 'integer', 'default' => 0 ),
                'category_id'  => array( 'type' => 'integer', 'default' => 0 ),
            ),
        ) );

        // Price calculation
        register_rest_route( $this->namespace, '/price', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'calculate_price' ),
            'permission_callback' => '__return_true',
            'args'                => array(
                'vehicle_id'          => array( 'type' => 'integer', 'required' => true ),
                'pickup_date'         => array( 'type' => 'string', 'required' => true ),
                'dropoff_date'        => array( 'type' => 'string', 'required' => true ),
                'pickup_location_id'  => array( 'type' => 'integer', 'default' => 0 ),
                'dropoff_location_id' => array( 'type' => 'integer', 'default' => 0 ),
            ),
        ) );

        // Create booking
        register_rest_route( $this->namespace, '/bookings', array(
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'create_booking' ),
                'permission_callback' => '__return_true',
            ),
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_bookings' ),
                'permission_callback' => array( $this, 'admin_permission_check' ),
            ),
        ) );

        // Get booking by number
        register_rest_route( $this->namespace, '/bookings/(?P<number>[A-Za-z0-9\-]+)', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_booking' ),
            'permission_callback' => '__return_true',
            'args'                => array(
                'number' => array( 'type' => 'string', 'required' => true ),
            ),
        ) );

        // Categories
        register_rest_route( $this->namespace, '/categories', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_categories' ),
            'permission_callback' => '__return_true',
        ) );

        // Insurance options
        register_rest_route( $this->namespace, '/insurance', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_insurance' ),
            'permission_callback' => '__return_true',
        ) );

        // Extras
        register_rest_route( $this->namespace, '/extras', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_extras' ),
            'permission_callback' => '__return_true',
        ) );

        // Validate coupon
        register_rest_route( $this->namespace, '/coupons/validate', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'validate_coupon' ),
            'permission_callback' => '__return_true',
            'args'                => array(
                'code' => array( 'type' => 'string', 'required' => true ),
            ),
        ) );

        // Vehicle reviews
        register_rest_route( $this->namespace, '/vehicles/(?P<id>\d+)/reviews', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_vehicle_reviews' ),
            'permission_callback' => '__return_true',
        ) );

        // Statistics (admin only)
        register_rest_route( $this->namespace, '/stats', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_stats' ),
            'permission_callback' => array( $this, 'admin_permission_check' ),
        ) );
    }

    /* ─────────────────────────────────────────────
     *  PERMISSION CALLBACKS
     * ───────────────────────────────────────────── */

    public function admin_permission_check( $request ) {
        return current_user_can( 'manage_options' );
    }

    /* ─────────────────────────────────────────────
     *  VEHICLES
     * ───────────────────────────────────────────── */

    public function get_vehicles( $request ) {
        $args = array(
            'status'      => 'active',
            'category_id' => $request->get_param( 'category_id' ),
            'limit'       => $request->get_param( 'limit' ),
            'offset'      => $request->get_param( 'offset' ),
        );

        if ( $request->get_param( 'location_id' ) ) {
            $vehicles = RAF_Vehicle::get_by_location( $request->get_param( 'location_id' ) );
        } else {
            $vehicles = RAF_Vehicle::get_all( $args );
        }

        $pricing = new RAF_Pricing_Engine();
        $data = array();

        foreach ( $vehicles as $v ) {
            $data[] = $this->format_vehicle( $v, $pricing );
        }

        return rest_ensure_response( array(
            'vehicles' => $data,
            'total'    => RAF_Vehicle::count( array( 'status' => 'active' ) ),
        ) );
    }

    public function get_vehicle( $request ) {
        $vehicle = RAF_Vehicle::get( $request->get_param( 'id' ) );
        if ( ! $vehicle ) {
            return new WP_Error( 'not_found', __( 'Vehicle not found.', 'rentafleet' ), array( 'status' => 404 ) );
        }

        $pricing = new RAF_Pricing_Engine();
        $data = $this->format_vehicle( $vehicle, $pricing );
        $data['description'] = $vehicle->description;
        $data['gallery'] = array();

        $gallery_ids = RAF_Vehicle::get_gallery( $vehicle );
        foreach ( $gallery_ids as $img_id ) {
            $url = wp_get_attachment_image_url( $img_id, 'large' );
            if ( $url ) $data['gallery'][] = $url;
        }

        $data['locations'] = array();
        $locations = RAF_Vehicle::get_locations( $vehicle->id );
        foreach ( $locations as $loc ) {
            $data['locations'][] = array(
                'id'   => $loc->id,
                'name' => $loc->name,
                'city' => $loc->city,
            );
        }

        $data['reviews'] = array();
        $reviews = RAF_Review::get_for_vehicle( $vehicle->id );
        foreach ( $reviews as $rev ) {
            $data['reviews'][] = array(
                'rating'  => (int) $rev->rating,
                'title'   => $rev->title,
                'review'  => $rev->review,
                'author'  => trim( $rev->first_name . ' ' . $rev->last_name ),
                'date'    => $rev->created_at,
            );
        }

        return rest_ensure_response( $data );
    }

    /* ─────────────────────────────────────────────
     *  LOCATIONS
     * ───────────────────────────────────────────── */

    public function get_locations( $request ) {
        $locations = RAF_Location::get_all();
        $data = array();

        foreach ( $locations as $loc ) {
            $data[] = array(
                'id'         => (int) $loc->id,
                'name'       => $loc->name,
                'address'    => $loc->address,
                'city'       => $loc->city,
                'state'      => $loc->state,
                'country'    => $loc->country,
                'phone'      => $loc->phone,
                'email'      => $loc->email,
                'latitude'   => $loc->latitude,
                'longitude'  => $loc->longitude,
                'is_pickup'  => (bool) $loc->is_pickup,
                'is_dropoff' => (bool) $loc->is_dropoff,
                'pickup_fee' => (float) $loc->pickup_fee,
                'dropoff_fee'=> (float) $loc->dropoff_fee,
            );
        }

        return rest_ensure_response( $data );
    }

    /* ─────────────────────────────────────────────
     *  AVAILABILITY
     * ───────────────────────────────────────────── */

    public function check_availability( $request ) {
        $available = RAF_Availability_Checker::is_available(
            $request->get_param( 'vehicle_id' ),
            $request->get_param( 'pickup_date' ),
            $request->get_param( 'dropoff_date' ),
            $request->get_param( 'location_id' )
        );

        return rest_ensure_response( array( 'available' => $available ) );
    }

    public function search_vehicles( $request ) {
        $pickup_date  = sanitize_text_field( $request->get_param( 'pickup_date' ) );
        $dropoff_date = sanitize_text_field( $request->get_param( 'dropoff_date' ) );
        $location_id  = intval( $request->get_param( 'location_id' ) );
        $category_id  = intval( $request->get_param( 'category_id' ) );

        $vehicles = RAF_Availability_Checker::get_available_vehicles( $pickup_date, $dropoff_date, $location_id, $category_id );
        $pricing = new RAF_Pricing_Engine();
        $data = array();

        foreach ( $vehicles as $v ) {
            $item = $this->format_vehicle( $v, $pricing );
            $price = $pricing->calculate_rental_price( array(
                'vehicle_id'          => $v->id,
                'pickup_date'         => $pickup_date,
                'dropoff_date'        => $dropoff_date,
                'pickup_location_id'  => $location_id,
                'dropoff_location_id' => $location_id,
            ) );
            if ( ! is_wp_error( $price ) ) {
                $item['total_price'] = $price['total'];
                $item['rental_days'] = $price['rental_days'];
            }
            $data[] = $item;
        }

        return rest_ensure_response( array(
            'vehicles' => $data,
            'count'    => count( $data ),
        ) );
    }

    /* ─────────────────────────────────────────────
     *  PRICING
     * ───────────────────────────────────────────── */

    public function calculate_price( $request ) {
        $pricing = new RAF_Pricing_Engine();
        $result = $pricing->calculate_rental_price( array(
            'vehicle_id'          => $request->get_param( 'vehicle_id' ),
            'pickup_date'         => $request->get_param( 'pickup_date' ),
            'dropoff_date'        => $request->get_param( 'dropoff_date' ),
            'extras'              => $request->get_param( 'extras' ) ?: array(),
            'insurance'           => $request->get_param( 'insurance' ) ?: array(),
            'coupon_code'         => $request->get_param( 'coupon_code' ) ?: '',
            'pickup_location_id'  => $request->get_param( 'pickup_location_id' ),
            'dropoff_location_id' => $request->get_param( 'dropoff_location_id' ),
        ) );

        if ( is_wp_error( $result ) ) {
            return new WP_Error( 'price_error', $result->get_error_message(), array( 'status' => 400 ) );
        }

        return rest_ensure_response( $result );
    }

    /* ─────────────────────────────────────────────
     *  BOOKINGS
     * ───────────────────────────────────────────── */

    public function create_booking( $request ) {
        $engine = new RAF_Booking_Engine();
        $data = $request->get_json_params();

        if ( empty( $data ) ) {
            $data = $request->get_body_params();
        }

        $result = $engine->create_booking( $data );

        if ( is_wp_error( $result ) ) {
            return new WP_Error( 'booking_error', $result->get_error_message(), array( 'status' => 400 ) );
        }

        return rest_ensure_response( array(
            'booking_number' => $result->booking_number,
            'status'         => $result->status,
            'total_price'    => (float) $result->total_price,
            'message'        => __( 'Booking created successfully.', 'rentafleet' ),
        ) );
    }

    public function get_bookings( $request ) {
        $bookings = RAF_Booking_Model::get_all( array(
            'limit'  => $request->get_param( 'limit' ) ?: 20,
            'offset' => $request->get_param( 'offset' ) ?: 0,
            'status' => $request->get_param( 'status' ) ?: '',
        ) );

        $data = array();
        foreach ( $bookings as $b ) {
            $data[] = $this->format_booking( $b );
        }

        return rest_ensure_response( array(
            'bookings' => $data,
            'total'    => RAF_Booking_Model::count(),
        ) );
    }

    public function get_booking( $request ) {
        $booking = RAF_Booking_Model::get_by_number( $request->get_param( 'number' ) );
        if ( ! $booking ) {
            return new WP_Error( 'not_found', __( 'Booking not found.', 'rentafleet' ), array( 'status' => 404 ) );
        }

        return rest_ensure_response( $this->format_booking( $booking ) );
    }

    /* ─────────────────────────────────────────────
     *  CATEGORIES, INSURANCE, EXTRAS
     * ───────────────────────────────────────────── */

    public function get_categories( $request ) {
        global $wpdb;
        $results = $wpdb->get_results(
            "SELECT id, name, slug, description FROM " . RAF_Helpers::table( 'vehicle_categories' ) .
            " WHERE status = 'active' ORDER BY sort_order ASC"
        );
        return rest_ensure_response( $results );
    }

    public function get_insurance( $request ) {
        $items = RAF_Insurance::get_all();
        $data = array();
        foreach ( $items as $ins ) {
            $data[] = array(
                'id'              => (int) $ins->id,
                'name'            => $ins->name,
                'description'     => $ins->description,
                'type'            => $ins->type,
                'price_per_day'   => (float) $ins->price_per_day,
                'price_per_rental'=> (float) $ins->price_per_rental,
                'coverage_amount' => (float) $ins->coverage_amount,
                'deductible'      => (float) $ins->deductible,
                'is_mandatory'    => (bool) $ins->is_mandatory,
            );
        }
        return rest_ensure_response( $data );
    }

    public function get_extras( $request ) {
        $items = RAF_Extra::get_all();
        $data = array();
        foreach ( $items as $ex ) {
            $data[] = array(
                'id'           => (int) $ex->id,
                'name'         => $ex->name,
                'description'  => $ex->description,
                'price'        => (float) $ex->price,
                'price_type'   => $ex->price_type,
                'max_quantity' => (int) $ex->max_quantity,
            );
        }
        return rest_ensure_response( $data );
    }

    /* ─────────────────────────────────────────────
     *  COUPONS
     * ───────────────────────────────────────────── */

    public function validate_coupon( $request ) {
        $code = sanitize_text_field( $request->get_param( 'code' ) );
        $coupon = RAF_Coupon_Model::validate( $code );

        if ( is_wp_error( $coupon ) ) {
            return new WP_Error( 'invalid_coupon', $coupon->get_error_message(), array( 'status' => 400 ) );
        }

        return rest_ensure_response( array(
            'code'           => $coupon->code,
            'discount_type'  => $coupon->discount_type,
            'discount_value' => (float) $coupon->discount_value,
            'max_discount'   => (float) $coupon->max_discount,
        ) );
    }

    /* ─────────────────────────────────────────────
     *  REVIEWS
     * ───────────────────────────────────────────── */

    public function get_vehicle_reviews( $request ) {
        $reviews = RAF_Review::get_for_vehicle( $request->get_param( 'id' ) );
        $data = array();
        foreach ( $reviews as $rev ) {
            $data[] = array(
                'id'     => (int) $rev->id,
                'rating' => (int) $rev->rating,
                'title'  => $rev->title,
                'review' => $rev->review,
                'author' => trim( $rev->first_name . ' ' . $rev->last_name ),
                'date'   => $rev->created_at,
                'reply'  => $rev->admin_reply,
            );
        }
        return rest_ensure_response( $data );
    }

    /* ─────────────────────────────────────────────
     *  STATISTICS
     * ───────────────────────────────────────────── */

    public function get_stats( $request ) {
        return rest_ensure_response( RAF_Statistics::get_dashboard_stats() );
    }

    /* ─────────────────────────────────────────────
     *  FORMAT HELPERS
     * ───────────────────────────────────────────── */

    private function format_vehicle( $v, $pricing = null ) {
        if ( ! $pricing ) $pricing = new RAF_Pricing_Engine();
        return array(
            'id'           => (int) $v->id,
            'name'         => $v->name,
            'slug'         => $v->slug,
            'make'         => $v->make,
            'model'        => $v->model,
            'year'         => $v->year,
            'transmission' => $v->transmission,
            'fuel_type'    => $v->fuel_type,
            'seats'        => (int) $v->seats,
            'doors'        => (int) $v->doors,
            'luggage'      => (int) $v->luggage_capacity,
            'image'        => $v->featured_image_id ? wp_get_attachment_image_url( $v->featured_image_id, 'medium_large' ) : '',
            'features'     => RAF_Vehicle::get_features( $v ),
            'rating'       => RAF_Vehicle::get_average_rating( $v->id ),
            'daily_rate'   => $pricing->get_display_price( $v->id ),
            'deposit'      => (float) $v->deposit_amount,
            'description'  => $v->short_description,
        );
    }

    private function format_booking( $b ) {
        $vehicle  = RAF_Vehicle::get( $b->vehicle_id );
        $customer = RAF_Customer::get( $b->customer_id );
        return array(
            'id'              => (int) $b->id,
            'booking_number'  => $b->booking_number,
            'status'          => $b->status,
            'payment_status'  => $b->payment_status,
            'vehicle'         => $vehicle ? $vehicle->name : '',
            'customer'        => $customer ? RAF_Customer::get_full_name( $customer ) : '',
            'pickup_date'     => $b->pickup_date,
            'dropoff_date'    => $b->dropoff_date,
            'rental_days'     => (int) $b->rental_days,
            'base_price'      => (float) $b->base_price,
            'total_price'     => (float) $b->total_price,
            'deposit_amount'  => (float) $b->deposit_amount,
            'created_at'      => $b->created_at,
        );
    }
}
