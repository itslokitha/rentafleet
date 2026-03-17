<?php
/**
 * Plugin Name: RentAFleet
 * Plugin URI: https://rentafleet.com
 * Description: Professional car rental management system for WordPress. Manage vehicles, locations, bookings, pricing, customers, calendar, and more.
 * Version: 1.0.0
 * Author: RentAFleet
 * Author URI: https://rentafleet.com
 * Text Domain: rentafleet
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPL v2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'RAF_VERSION', '1.0.0' );
define( 'RAF_PLUGIN_FILE', __FILE__ );
define( 'RAF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RAF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'RAF_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'RAF_DB_VERSION', '1.0.0' );

final class RentAFleet {

    private static $instance = null;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    private function load_dependencies() {
        // Core
        require_once RAF_PLUGIN_DIR . 'includes/class-raf-activator.php';
        require_once RAF_PLUGIN_DIR . 'includes/class-raf-deactivator.php';
        require_once RAF_PLUGIN_DIR . 'includes/class-raf-helpers.php';

        // Models
        foreach ( glob( RAF_PLUGIN_DIR . 'includes/Models/*.php' ) as $model ) {
            require_once $model;
        }

        // Engines & Managers
        require_once RAF_PLUGIN_DIR . 'includes/Pricing/class-raf-pricing-engine.php';
        require_once RAF_PLUGIN_DIR . 'includes/Booking/class-raf-booking-engine.php';
        require_once RAF_PLUGIN_DIR . 'includes/Booking/class-raf-availability-checker.php';
        require_once RAF_PLUGIN_DIR . 'includes/Calendar/class-raf-calendar.php';
        require_once RAF_PLUGIN_DIR . 'includes/Email/class-raf-email-manager.php';
        require_once RAF_PLUGIN_DIR . 'includes/PDF/class-raf-pdf-generator.php';
        require_once RAF_PLUGIN_DIR . 'includes/Statistics/class-raf-statistics.php';
        require_once RAF_PLUGIN_DIR . 'includes/Coupons/class-raf-coupon-manager.php';
        require_once RAF_PLUGIN_DIR . 'includes/Damage/class-raf-damage-manager.php';
        require_once RAF_PLUGIN_DIR . 'includes/Payment/class-raf-payment-manager.php';

        // Admin
        if ( is_admin() ) {
            foreach ( glob( RAF_PLUGIN_DIR . 'includes/Admin/*.php' ) as $admin ) {
                require_once $admin;
            }
        }

        // Public
        require_once RAF_PLUGIN_DIR . 'includes/class-raf-shortcodes.php';

        // Widgets
        require_once RAF_PLUGIN_DIR . 'includes/Widgets/class-raf-search-widget.php';
        require_once RAF_PLUGIN_DIR . 'includes/Widgets/class-raf-vehicles-widget.php';

        // REST API
        require_once RAF_PLUGIN_DIR . 'includes/API/class-raf-rest-api.php';
    }

    private function init_hooks() {
        add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
        add_action( 'init', array( $this, 'init' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'public_assets' ) );
        add_action( 'widgets_init', array( $this, 'register_widgets' ) );
    }

    public function on_plugins_loaded() {
        load_plugin_textdomain( 'rentafleet', false, dirname( RAF_PLUGIN_BASENAME ) . '/languages' );

        $installed = get_option( 'raf_db_version', '0' );
        if ( version_compare( $installed, RAF_DB_VERSION, '<' ) ) {
            RAF_Activator::create_tables();
            RAF_Activator::create_default_options();
            RAF_Activator::create_pages();
            update_option( 'raf_db_version', RAF_DB_VERSION );
        }

        // Safety net: ensure pages exist. Uses a transient to avoid running on every page load.
        if ( false === get_transient( 'raf_pages_checked' ) ) {
            RAF_Activator::create_pages();
            set_transient( 'raf_pages_checked', 1, DAY_IN_SECONDS );
        }
    }

    public function init() {
        new RAF_Pricing_Engine();
        new RAF_Booking_Engine();
        new RAF_Calendar();
        new RAF_Email_Manager();
        new RAF_PDF_Generator();
        new RAF_Statistics();
        new RAF_Coupon_Manager();
        new RAF_Damage_Manager();
        new RAF_Payment_Manager();
        new RAF_REST_API();
        new RAF_Shortcodes();

        if ( is_admin() ) {
            new RAF_Admin();
        }
    }

    public function admin_assets( $hook ) {
        if ( strpos( $hook, 'rentafleet' ) === false ) {
            return;
        }

        wp_enqueue_style( 'raf-admin', RAF_PLUGIN_URL . 'assets/admin/css/admin.css', array(), RAF_VERSION );
        wp_enqueue_style( 'raf-calendar', RAF_PLUGIN_URL . 'assets/admin/css/calendar.css', array(), RAF_VERSION );

        wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', array(), '4.4.0', true );
        wp_enqueue_script( 'raf-admin', RAF_PLUGIN_URL . 'assets/admin/js/admin.js', array( 'jquery', 'wp-util', 'jquery-ui-datepicker', 'jquery-ui-sortable' ), RAF_VERSION, true );
        wp_enqueue_script( 'raf-calendar', RAF_PLUGIN_URL . 'assets/admin/js/calendar.js', array( 'jquery' ), RAF_VERSION, true );

        wp_enqueue_media();

        wp_localize_script( 'raf-admin', 'rafAdmin', array(
            'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
            'adminUrl'  => admin_url( 'admin.php' ),
            'restUrl'   => rest_url( 'rentafleet/v1/' ),
            'nonce'     => wp_create_nonce( 'raf_admin_nonce' ),
            'restNonce' => wp_create_nonce( 'wp_rest' ),
            'currency'  => RAF_Helpers::get_currency_symbol(),
            'i18n'      => array(
                'confirm_delete' => __( 'Are you sure?', 'rentafleet' ),
                'saving'         => __( 'Saving...', 'rentafleet' ),
                'saved'          => __( 'Saved!', 'rentafleet' ),
                'error'          => __( 'An error occurred.', 'rentafleet' ),
            ),
        ) );
    }

    public function public_assets() {
        wp_enqueue_style( 'raf-public', RAF_PLUGIN_URL . 'assets/public/css/public.css', array(), RAF_VERSION );
        wp_enqueue_script( 'raf-public', RAF_PLUGIN_URL . 'assets/public/js/public.js', array( 'jquery' ), RAF_VERSION, true );

        wp_localize_script( 'raf-public', 'rafPublic', array(
            'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
            'restUrl'   => rest_url( 'rentafleet/v1/' ),
            'nonce'     => wp_create_nonce( 'raf_public_nonce' ),
            'restNonce' => wp_create_nonce( 'wp_rest' ),
            'currency'  => RAF_Helpers::get_currency_symbol(),
            'i18n'      => array(
                'select_dates'    => __( 'Please select pickup and return dates.', 'rentafleet' ),
                'no_vehicles'     => __( 'No vehicles available.', 'rentafleet' ),
                'booking_success' => __( 'Booking confirmed!', 'rentafleet' ),
                'processing'      => __( 'Processing...', 'rentafleet' ),
            ),
        ) );
    }

    public function register_widgets() {
        register_widget( 'RAF_Search_Widget' );
        register_widget( 'RAF_Vehicles_Widget' );
    }
}

function RentAFleet() {
    return RentAFleet::instance();
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-raf-activator.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-raf-deactivator.php';
register_activation_hook( __FILE__, array( 'RAF_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'RAF_Deactivator', 'deactivate' ) );

RentAFleet();
