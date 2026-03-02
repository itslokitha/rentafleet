<?php
/**
 * RentAFleet — REST API (Stub)
 *
 * Placeholder class. Will be fully built when the API module is developed.
 *
 * @package RentAFleet
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class RAF_REST_API {

    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        // Routes will be registered when the API module is built.
    }
}
