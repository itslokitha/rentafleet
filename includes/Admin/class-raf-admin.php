<?php
/**
 * RentAFleet Admin - Master Controller
 *
 * Registers all admin menus, submenus, handles global admin hooks,
 * routes admin page renders, and manages admin notices.
 *
 * @package RentAFleet
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RAF_Admin {

    /**
     * Stored admin notices for display after redirect.
     *
     * @var array
     */
    private static $notices = array();

    /**
     * Constructor — wire up all admin hooks.
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menus' ) );
        add_action( 'admin_init', array( $this, 'handle_actions' ) );
        add_action( 'admin_notices', array( $this, 'display_notices' ) );
    }

    /* ─────────────────────────────────────────────
     *  MENU REGISTRATION
     * ───────────────────────────────────────────── */

    /**
     * Register the main RentAFleet menu and all sub-menu pages.
     */
    public function register_menus() {

        // Main menu
        add_menu_page(
            __( 'RentAFleet', 'rentafleet' ),
            __( 'RentAFleet', 'rentafleet' ),
            'manage_options',
            'rentafleet',
            array( 'RAF_Admin_Dashboard', 'render' ),
            'dashicons-car',
            26
        );

        // Submenu: Dashboard (duplicate of parent to rename first item)
        add_submenu_page(
            'rentafleet',
            __( 'Dashboard', 'rentafleet' ),
            __( 'Dashboard', 'rentafleet' ),
            'manage_options',
            'rentafleet',
            array( 'RAF_Admin_Dashboard', 'render' )
        );

        // Submenu: Vehicles
        add_submenu_page(
            'rentafleet',
            __( 'Vehicles', 'rentafleet' ),
            __( 'Vehicles', 'rentafleet' ),
            'manage_options',
            'raf-vehicles',
            array( 'RAF_Admin_Vehicles', 'render' )
        );

        // Submenu: Locations
        add_submenu_page(
            'rentafleet',
            __( 'Locations', 'rentafleet' ),
            __( 'Locations', 'rentafleet' ),
            'manage_options',
            'raf-locations',
            array( $this, 'page_placeholder' )
        );

        // Submenu: Bookings
        add_submenu_page(
            'rentafleet',
            __( 'Bookings', 'rentafleet' ),
            __( 'Bookings', 'rentafleet' ),
            'manage_options',
            'raf-bookings',
            array( 'RAF_Admin_Bookings', 'render' )
        );

        // Submenu: Customers
        add_submenu_page(
            'rentafleet',
            __( 'Customers', 'rentafleet' ),
            __( 'Customers', 'rentafleet' ),
            'manage_options',
            'raf-customers',
            array( $this, 'page_placeholder' )
        );

        // Submenu: Calendar
        add_submenu_page(
            'rentafleet',
            __( 'Calendar', 'rentafleet' ),
            __( 'Calendar', 'rentafleet' ),
            'manage_options',
            'raf-calendar',
            array( $this, 'page_placeholder' )
        );

        // Submenu: Pricing & Rates
        add_submenu_page(
            'rentafleet',
            __( 'Pricing & Rates', 'rentafleet' ),
            __( 'Pricing & Rates', 'rentafleet' ),
            'manage_options',
            'raf-pricing',
            array( $this, 'page_placeholder' )
        );

        // Submenu: Extras & Insurance
        add_submenu_page(
            'rentafleet',
            __( 'Extras & Insurance', 'rentafleet' ),
            __( 'Extras & Insurance', 'rentafleet' ),
            'manage_options',
            'raf-extras',
            array( $this, 'page_placeholder' )
        );

        // Submenu: Coupons
        add_submenu_page(
            'rentafleet',
            __( 'Coupons', 'rentafleet' ),
            __( 'Coupons', 'rentafleet' ),
            'manage_options',
            'raf-coupons',
            array( $this, 'page_placeholder' )
        );

        // Submenu: Damage Reports
        add_submenu_page(
            'rentafleet',
            __( 'Damage Reports', 'rentafleet' ),
            __( 'Damage Reports', 'rentafleet' ),
            'manage_options',
            'raf-damage',
            array( $this, 'page_placeholder' )
        );

        // Submenu: Statistics
        add_submenu_page(
            'rentafleet',
            __( 'Statistics', 'rentafleet' ),
            __( 'Statistics', 'rentafleet' ),
            'manage_options',
            'raf-statistics',
            array( $this, 'page_placeholder' )
        );

        // Submenu: Settings
        add_submenu_page(
            'rentafleet',
            __( 'Settings', 'rentafleet' ),
            __( 'Settings', 'rentafleet' ),
            'manage_options',
            'raf-settings',
            array( $this, 'page_placeholder' )
        );
    }

    /* ─────────────────────────────────────────────
     *  ACTION HANDLER — Processes form POSTs & GETs
     * ───────────────────────────────────────────── */

    /**
     * Route admin_init actions to the correct handler.
     * Every form submission goes through here before the page renders.
     */
    public function handle_actions() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $page   = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';
        $action = isset( $_REQUEST['raf_action'] ) ? sanitize_text_field( $_REQUEST['raf_action'] ) : '';

        if ( empty( $action ) ) {
            return;
        }

        // Route to the correct handler class
        switch ( $page ) {
            case 'raf-vehicles':
                RAF_Admin_Vehicles::handle_action( $action );
                break;
            case 'raf-bookings':
                RAF_Admin_Bookings::handle_action( $action );
                break;
            // Future pages will be added here as they are built:
            // case 'raf-locations':
            //     RAF_Admin_Locations::handle_action( $action );
            //     break;
        }
    }

    /* ─────────────────────────────────────────────
     *  ADMIN NOTICES
     * ───────────────────────────────────────────── */

    /**
     * Add a transient admin notice that survives redirects.
     *
     * @param string $message Notice text.
     * @param string $type    Notice type: success|error|warning|info.
     */
    public static function add_notice( $message, $type = 'success' ) {
        $notices   = get_transient( 'raf_admin_notices' );
        $notices   = is_array( $notices ) ? $notices : array();
        $notices[] = array(
            'message' => $message,
            'type'    => $type,
        );
        set_transient( 'raf_admin_notices', $notices, 60 );
    }

    /**
     * Render and clear transient admin notices.
     */
    public function display_notices() {
        $screen = get_current_screen();
        if ( ! $screen || strpos( $screen->id, 'rentafleet' ) === false ) {
            return;
        }

        $notices = get_transient( 'raf_admin_notices' );
        if ( ! is_array( $notices ) || empty( $notices ) ) {
            return;
        }

        foreach ( $notices as $notice ) {
            $type    = in_array( $notice['type'], array( 'success', 'error', 'warning', 'info' ), true ) ? $notice['type'] : 'info';
            $message = wp_kses_post( $notice['message'] );
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr( $type ),
                $message
            );
        }

        delete_transient( 'raf_admin_notices' );
    }

    /* ─────────────────────────────────────────────
     *  PLACEHOLDER for unbuilt pages
     * ───────────────────────────────────────────── */

    /**
     * Temporary placeholder for pages not yet implemented.
     */
    public function page_placeholder() {
        $page_titles = array(
            'raf-locations'  => __( 'Locations', 'rentafleet' ),
            'raf-bookings'   => __( 'Bookings', 'rentafleet' ),
            'raf-customers'  => __( 'Customers', 'rentafleet' ),
            'raf-calendar'   => __( 'Calendar', 'rentafleet' ),
            'raf-pricing'    => __( 'Pricing & Rates', 'rentafleet' ),
            'raf-extras'     => __( 'Extras & Insurance', 'rentafleet' ),
            'raf-coupons'    => __( 'Coupons', 'rentafleet' ),
            'raf-damage'     => __( 'Damage Reports', 'rentafleet' ),
            'raf-statistics' => __( 'Statistics', 'rentafleet' ),
            'raf-settings'   => __( 'Settings', 'rentafleet' ),
        );
        $page  = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';
        $title = isset( $page_titles[ $page ] ) ? $page_titles[ $page ] : __( 'Coming Soon', 'rentafleet' );
        echo '<div class="wrap"><h1>' . esc_html( $title ) . '</h1>';
        echo '<div class="raf-panel" style="padding:30px;margin-top:20px;background:#fff;border:1px solid #ccd0d4;border-radius:4px;">';
        echo '<p style="font-size:15px;color:#555;">' . esc_html__( 'This module is coming soon. Build it next!', 'rentafleet' ) . '</p>';
        echo '</div></div>';
    }

    /* ─────────────────────────────────────────────
     *  SHARED HELPERS for Admin pages
     * ───────────────────────────────────────────── */

    /**
     * Build a safe admin URL for a RentAFleet admin page.
     *
     * @param string $page   The page slug (e.g. 'raf-vehicles').
     * @param array  $args   Additional query args.
     * @return string
     */
    public static function admin_url( $page, $args = array() ) {
        $args['page'] = $page;
        return add_query_arg( $args, admin_url( 'admin.php' ) );
    }

    /**
     * Redirect to a RentAFleet admin page.
     *
     * @param string $page The page slug.
     * @param array  $args Additional query args.
     */
    public static function redirect( $page, $args = array() ) {
        wp_safe_redirect( self::admin_url( $page, $args ) );
        exit;
    }

    /**
     * Get all vehicle categories as id => name for dropdowns.
     *
     * @return array
     */
    public static function get_categories_dropdown() {
        global $wpdb;
        $table   = RAF_Helpers::table( 'vehicle_categories' );
        $results = $wpdb->get_results( "SELECT id, name FROM $table WHERE status = 'active' ORDER BY sort_order ASC, name ASC" );
        $options = array( '' => __( '— No Category —', 'rentafleet' ) );
        foreach ( $results as $row ) {
            $options[ $row->id ] = $row->name;
        }
        return $options;
    }

    /**
     * Get all active locations as id => name for dropdowns.
     *
     * @return array
     */
    public static function get_locations_dropdown() {
        global $wpdb;
        $table   = RAF_Helpers::table( 'locations' );
        $results = $wpdb->get_results( "SELECT id, name FROM $table WHERE status = 'active' ORDER BY sort_order ASC, name ASC" );
        $options = array();
        foreach ( $results as $row ) {
            $options[ $row->id ] = $row->name;
        }
        return $options;
    }

    /**
     * Render a standard "back to list" link.
     *
     * @param string $page Page slug.
     * @param string $text Link text.
     */
    public static function back_link( $page, $text = '' ) {
        if ( ! $text ) {
            $text = __( '← Back to list', 'rentafleet' );
        }
        printf(
            '<a href="%s" class="raf-back-link">%s</a>',
            esc_url( self::admin_url( $page ) ),
            esc_html( $text )
        );
    }

    /**
     * Output a nonce field for RentAFleet admin forms.
     *
     * @param string $action The nonce action name.
     */
    public static function nonce_field( $action ) {
        wp_nonce_field( 'raf_' . $action, 'raf_nonce' );
    }

    /**
     * Verify a nonce from a RentAFleet admin form.
     *
     * @param string $action The nonce action name.
     * @return bool
     */
    public static function verify_nonce( $action ) {
        return isset( $_POST['raf_nonce'] ) && wp_verify_nonce( $_POST['raf_nonce'], 'raf_' . $action );
    }
}
