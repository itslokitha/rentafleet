<?php
/**
 * Plugin Name: RentAFleet
 * Plugin URI: https://rentafleet.com
 * Description: Professional car rental management system for WordPress. Manage vehicles, locations, bookings, pricing, customers, calendar, and more.
 * Version: 1.2.0
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

define( 'RAF_VERSION', '1.2.0' );
define( 'RAF_PLUGIN_FILE', __FILE__ );
define( 'RAF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RAF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'RAF_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'RAF_DB_VERSION', '1.0.1' );

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

        // Hide internal booking-flow pages from front-end nav menus.
        add_filter( 'wp_nav_menu_objects', array( $this, 'hide_booking_pages_from_nav' ), 10, 2 );
    }

    /**
     * Remove internal booking-flow pages from all rendered nav menus.
     *
     * Pages like "Complete Booking", "Booking Confirmation", and "My Bookings"
     * are created automatically by the plugin but should not appear in site
     * navigation — they are accessed only via the booking flow or direct links.
     *
     * @param array  $items Sorted array of menu item objects.
     * @param object $args  An object of wp_nav_menu() arguments.
     * @return array Filtered menu items.
     */
    public function hide_booking_pages_from_nav( $items, $args ) {
        $exclude_page_ids = array_filter( array(
            (int) get_option( 'raf_booking_page' ),
            (int) get_option( 'raf_confirmation_page' ),
            (int) get_option( 'raf_my_bookings_page' ),
        ) );

        if ( empty( $exclude_page_ids ) ) {
            return $items;
        }

        return array_filter( $items, function ( $item ) use ( $exclude_page_ids ) {
            return ! in_array( (int) $item->object_id, $exclude_page_ids, true );
        } );
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

        // One-time force-update of terms content to the official agreement text.
        if ( get_option( 'raf_terms_content_version' ) !== '1.0.2' ) {
            update_option( 'raf_terms_content', '<p>I, the undersigned, confirm that the information provided above is true and correct. I agree to the following conditions while renting and operating the vehicle provided by the company.</p><p><strong>Valid License</strong> – I confirm that I hold a valid driving license that legally allows me to operate the motorcycle/scooter.</p><p><strong>Full Responsibility</strong> – I accept full responsibility for the vehicle from the moment it is handed over until it is returned to the company.</p><p><strong>Damage, Loss, or Theft</strong> – I am fully responsible for any damage, loss, theft, or mechanical issues caused to the vehicle during the rental period and agree to pay for any associated repairs or replacement costs.</p><p><strong>Traffic Violations &amp; Fines</strong> – I understand that I am responsible for all traffic violations, parking fines, tolls, or penalties incurred during the rental period.</p><p><strong>Authorized Rider Only</strong> – I agree that the vehicle will only be operated by me and will not be given, lent, or sub-rented to another person.</p><p><strong>Safe Operation</strong> – I confirm that I will operate the vehicle safely and responsibly and will not ride under the influence of alcohol, drugs, or any illegal substances.</p><p><strong>Accidents &amp; Breakdowns</strong> – In the event of an accident, breakdown, or mechanical failure, I agree to immediately notify the rental company and cooperate fully with any insurance, police, or repair procedures.</p><p><strong>Company Liability</strong> – The company is not responsible for any injury, death, loss of personal belongings, or damages resulting from the use of the rented vehicle.</p><p><strong>Late Returns</strong> – I understand that late returns may incur additional charges as specified by the rental company.</p><p><strong>Security Deposit</strong> – The security deposit may be used to cover damages or late fees.</p><p><strong>Return Condition</strong> – The vehicle must be returned in the same condition as received, excluding normal wear and tear.</p><p><strong>Illegal Use &amp; Misuse</strong> – Misuse, reckless riding, illegal activities, or violation of this agreement may result in termination of the rental and forfeiture of my deposit.</p>' );
            update_option( 'raf_terms_content_version', '1.0.2' );
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
        wp_enqueue_script( 'raf-calendar', RAF_PLUGIN_URL . 'assets/admin/js/calendar.js', array( 'jquery', 'raf-admin' ), RAF_VERSION, true );

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
        wp_enqueue_style( 'raf-montserrat', 'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap', array(), null );
        wp_enqueue_style( 'raf-public', RAF_PLUGIN_URL . 'assets/public/css/public.css', array( 'raf-montserrat' ), RAF_VERSION );
        wp_enqueue_script( 'raf-public', RAF_PLUGIN_URL . 'assets/public/js/public.js', array( 'jquery' ), RAF_VERSION, true );

        $raf_min_advance_hours = (int) get_option( 'raf_min_advance_hours', 24 );
        $raf_tz_string = get_option( 'raf_timezone', get_option( 'timezone_string', 'UTC' ) ) ?: 'UTC';
        try {
            $raf_tz = new DateTimeZone( $raf_tz_string );
        } catch ( Exception $e ) {
            $raf_tz = new DateTimeZone( 'UTC' );
        }

        $raf_security_deposit = floatval( get_option( 'raf_security_deposit', 100 ) );
        $raf_currency_symbol  = RAF_Helpers::get_currency_symbol();

        wp_localize_script( 'raf-public', 'rafPublic', array(
            'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
            'restUrl'         => rest_url( 'rentafleet/v1/' ),
            'nonce'           => wp_create_nonce( 'raf_public_nonce' ),
            'restNonce'       => wp_create_nonce( 'wp_rest' ),
            'currency'        => $raf_currency_symbol,
            'minAdvanceHours' => $raf_min_advance_hours,
            'serverNow'       => ( new DateTime( 'now', $raf_tz ) )->format( 'Y-m-d H:i:s' ),
            'minAdvanceMsg'   => sprintf(
                __( 'Please book at least %d hours in advance. Your selected pickup time is too soon.', 'rentafleet' ),
                $raf_min_advance_hours
            ),
            'depositNotice'   => sprintf(
                /* translators: %s: formatted deposit amount with currency symbol */
                __( 'Booking will be confirmed after a %s refundable security deposit is paid.', 'rentafleet' ),
                $raf_currency_symbol . number_format( $raf_security_deposit, 2 )
            ),
            'termsContent'    => wp_kses_post( get_option( 'raf_terms_content', '' ) ),
            'bikeFeatures'    => RAF_Helpers::get_bike_features(),
            'featureEmojis'   => array(
                'e_bike_assist'      => '⚡',
                'step_through_frame' => '🚲',
                'disc_brakes'        => '🛑',
                'rear_cargo_rack'    => '📦',
                'under_seat_storage' => '🗄',
                'passenger_ready'    => '👤',
                'windscreen'         => '💨',
                'usb_charger'        => '🔌',
                'centre_stand'       => '🔩',
                'phone_mount'        => '📱',
                'led_lights'         => '💡',
                'front_basket'       => '🧺',
                'fully_insured'      => '✅',
            ),
            'i18n'            => array(
                'select_dates'    => __( 'Please select pickup and return dates.', 'rentafleet' ),
                'no_vehicles'     => __( 'No vehicles available.', 'rentafleet' ),
                'booking_success' => __( 'Booking confirmed!', 'rentafleet' ),
                'processing'      => __( 'Processing...', 'rentafleet' ),
                'book_now'        => __( 'Confirm Booking', 'rentafleet' ),
                'error'           => __( 'An error occurred.', 'rentafleet' ),
                'agree_terms'     => __( 'You must agree to the Terms & Conditions to proceed.', 'rentafleet' ),
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
