<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RAF_Activator {

    public static function activate() {
        self::create_tables();
        self::create_default_options();
        self::create_pages();

        // Clear the transient so on_plugins_loaded safety net also runs after activation.
        delete_transient( 'raf_pages_checked' );

        flush_rewrite_rules();
    }

    public static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $prefix  = $wpdb->prefix . 'raf_';

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // 1. LOCATIONS
        $sql = "CREATE TABLE {$prefix}locations (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            address text,
            city varchar(100),
            state varchar(100),
            country varchar(100),
            zip varchar(20),
            latitude decimal(10,7),
            longitude decimal(10,7),
            phone varchar(50),
            email varchar(100),
            opening_hours text,
            is_pickup tinyint(1) DEFAULT 1,
            is_dropoff tinyint(1) DEFAULT 1,
            pickup_fee decimal(10,2) DEFAULT 0.00,
            dropoff_fee decimal(10,2) DEFAULT 0.00,
            image_id bigint(20) unsigned,
            notes text,
            status varchar(20) DEFAULT 'active',
            sort_order int DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_status (status),
            KEY idx_city (city),
            KEY idx_slug (slug)
        ) $charset;";
        dbDelta( $sql );

        // 2. VEHICLE CATEGORIES
        $sql = "CREATE TABLE {$prefix}vehicle_categories (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description text,
            image_id bigint(20) unsigned,
            sort_order int DEFAULT 0,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_slug (slug)
        ) $charset;";
        dbDelta( $sql );

        // 3. VEHICLES
        $sql = "CREATE TABLE {$prefix}vehicles (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            category_id bigint(20) unsigned,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description text,
            short_description varchar(500),
            make varchar(100),
            model varchar(100),
            year int,
            license_plate varchar(50),
            vin varchar(50),
            color varchar(50),
            engine_cc int DEFAULT 0,
            bike_type varchar(30) DEFAULT 'standard',
            min_rental_days int DEFAULT 1,
            max_rental_days int DEFAULT 365,
            min_driver_age int DEFAULT 21,
            deposit_amount decimal(10,2) DEFAULT 0.00,
            featured_image_id bigint(20) unsigned,
            gallery text,
            features text,
            units int DEFAULT 1,
            status varchar(20) DEFAULT 'active',
            sort_order int DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_category (category_id),
            KEY idx_status (status),
            KEY idx_slug (slug),
            KEY idx_make_model (make, model)
        ) $charset;";
        dbDelta( $sql );

        // 4. VEHICLE-LOCATION MAPPING (which vehicles available at which locations)
        $sql = "CREATE TABLE {$prefix}vehicle_locations (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            vehicle_id bigint(20) unsigned NOT NULL,
            location_id bigint(20) unsigned NOT NULL,
            units_at_location int DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY idx_vehicle_location (vehicle_id, location_id),
            KEY idx_location (location_id)
        ) $charset;";
        dbDelta( $sql );

        // 5. RATES (pricing tiers)
        $sql = "CREATE TABLE {$prefix}rates (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            vehicle_id bigint(20) unsigned,
            category_id bigint(20) unsigned,
            name varchar(255),
            rate_type varchar(20) DEFAULT 'daily',
            price decimal(10,2) NOT NULL,
            min_days int DEFAULT 1,
            max_days int DEFAULT 365,
            weekend_price decimal(10,2),
            weekly_price decimal(10,2),
            monthly_price decimal(10,2),
            hourly_price decimal(10,2),
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_vehicle (vehicle_id),
            KEY idx_category (category_id),
            KEY idx_rate_type (rate_type)
        ) $charset;";
        dbDelta( $sql );

        // 6. SEASONAL RATES (price overrides for date ranges)
        $sql = "CREATE TABLE {$prefix}seasonal_rates (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            rate_id bigint(20) unsigned,
            vehicle_id bigint(20) unsigned,
            category_id bigint(20) unsigned,
            name varchar(255) NOT NULL,
            date_from date NOT NULL,
            date_to date NOT NULL,
            daily_price decimal(10,2),
            weekend_price decimal(10,2),
            weekly_price decimal(10,2),
            monthly_price decimal(10,2),
            hourly_price decimal(10,2),
            price_modifier_type varchar(20) DEFAULT 'fixed',
            price_modifier_value decimal(10,2),
            priority int DEFAULT 0,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_dates (date_from, date_to),
            KEY idx_vehicle (vehicle_id),
            KEY idx_category (category_id)
        ) $charset;";
        dbDelta( $sql );

        // 7. INSURANCE TYPES
        $sql = "CREATE TABLE {$prefix}insurance (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description text,
            type varchar(30) DEFAULT 'cdw',
            price_per_day decimal(10,2) NOT NULL,
            price_per_rental decimal(10,2),
            coverage_amount decimal(10,2) DEFAULT 0.00,
            deductible decimal(10,2) DEFAULT 0.00,
            is_mandatory tinyint(1) DEFAULT 0,
            sort_order int DEFAULT 0,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_type (type),
            KEY idx_status (status)
        ) $charset;";
        dbDelta( $sql );

        // 8. EXTRAS / OPTIONS
        $sql = "CREATE TABLE {$prefix}extras (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description text,
            price decimal(10,2) NOT NULL,
            price_type varchar(20) DEFAULT 'per_day',
            max_quantity int DEFAULT 1,
            image_id bigint(20) unsigned,
            is_mandatory tinyint(1) DEFAULT 0,
            vehicle_ids text,
            category_ids text,
            location_ids text,
            sort_order int DEFAULT 0,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_status (status)
        ) $charset;";
        dbDelta( $sql );

        // 9. CUSTOMERS
        $sql = "CREATE TABLE {$prefix}customers (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            first_name varchar(100) NOT NULL,
            last_name varchar(100) NOT NULL,
            email varchar(200) NOT NULL,
            phone varchar(50) NOT NULL,
            passport_number varchar(100),
            citizenship varchar(100),
            notes text,
            total_bookings int DEFAULT 0,
            total_spent decimal(12,2) DEFAULT 0.00,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_email (email),
            KEY idx_name (last_name, first_name),
            KEY idx_status (status)
        ) $charset;";
        dbDelta( $sql );

        // 10. BOOKINGS (the core table)
        $sql = "CREATE TABLE {$prefix}bookings (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            booking_number varchar(50) NOT NULL,
            customer_id bigint(20) unsigned NOT NULL,
            vehicle_id bigint(20) unsigned NOT NULL,
            pickup_location_id bigint(20) unsigned NOT NULL,
            dropoff_location_id bigint(20) unsigned NOT NULL,
            pickup_date datetime NOT NULL,
            dropoff_date datetime NOT NULL,
            actual_pickup_date datetime,
            actual_dropoff_date datetime,
            rental_days int NOT NULL,
            rental_hours int DEFAULT 0,
            base_price decimal(10,2) NOT NULL,
            extras_total decimal(10,2) DEFAULT 0.00,
            insurance_total decimal(10,2) DEFAULT 0.00,
            location_fees decimal(10,2) DEFAULT 0.00,
            tax_amount decimal(10,2) DEFAULT 0.00,
            discount_amount decimal(10,2) DEFAULT 0.00,
            coupon_id bigint(20) unsigned,
            coupon_code varchar(50),
            total_price decimal(10,2) NOT NULL,
            deposit_amount decimal(10,2) DEFAULT 0.00,
            deposit_paid tinyint(1) DEFAULT 0,
            amount_paid decimal(10,2) DEFAULT 0.00,
            payment_status varchar(30) DEFAULT 'pending',
            payment_method varchar(50),
            payment_transaction_id varchar(255),
            currency varchar(10) DEFAULT 'USD',
            status varchar(30) DEFAULT 'pending',
            rider_name varchar(255),
            notes text,
            admin_notes text,
            ip_address varchar(50),
            user_agent text,
            source varchar(50) DEFAULT 'website',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_booking_number (booking_number),
            KEY idx_customer (customer_id),
            KEY idx_vehicle (vehicle_id),
            KEY idx_pickup_location (pickup_location_id),
            KEY idx_pickup_date (pickup_date),
            KEY idx_dropoff_date (dropoff_date),
            KEY idx_status (status),
            KEY idx_payment_status (payment_status),
            KEY idx_dates_status (pickup_date, dropoff_date, status),
            KEY idx_created (created_at)
        ) $charset;";
        dbDelta( $sql );

        // 11. BOOKING EXTRAS (extras attached to a booking)
        $sql = "CREATE TABLE {$prefix}booking_extras (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            booking_id bigint(20) unsigned NOT NULL,
            extra_id bigint(20) unsigned NOT NULL,
            name varchar(255) NOT NULL,
            quantity int DEFAULT 1,
            price decimal(10,2) NOT NULL,
            price_type varchar(20) DEFAULT 'per_day',
            total decimal(10,2) NOT NULL,
            PRIMARY KEY (id),
            KEY idx_booking (booking_id)
        ) $charset;";
        dbDelta( $sql );

        // 12. BOOKING INSURANCE
        $sql = "CREATE TABLE {$prefix}booking_insurance (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            booking_id bigint(20) unsigned NOT NULL,
            insurance_id bigint(20) unsigned NOT NULL,
            name varchar(255) NOT NULL,
            price_per_day decimal(10,2) NOT NULL,
            total decimal(10,2) NOT NULL,
            PRIMARY KEY (id),
            KEY idx_booking (booking_id)
        ) $charset;";
        dbDelta( $sql );

        // 13. BOOKING STATUS LOG
        $sql = "CREATE TABLE {$prefix}booking_log (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            booking_id bigint(20) unsigned NOT NULL,
            old_status varchar(30),
            new_status varchar(30),
            note text,
            changed_by bigint(20) unsigned,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_booking (booking_id)
        ) $charset;";
        dbDelta( $sql );

        // 14. COUPONS
        $sql = "CREATE TABLE {$prefix}coupons (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            code varchar(50) NOT NULL,
            name varchar(255),
            description text,
            discount_type varchar(20) DEFAULT 'percentage',
            discount_value decimal(10,2) NOT NULL,
            min_rental_days int DEFAULT 0,
            min_order_amount decimal(10,2) DEFAULT 0.00,
            max_discount decimal(10,2),
            vehicle_ids text,
            category_ids text,
            location_ids text,
            usage_limit int DEFAULT 0,
            usage_limit_per_user int DEFAULT 0,
            times_used int DEFAULT 0,
            valid_from datetime,
            valid_to datetime,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_code (code),
            KEY idx_status (status),
            KEY idx_dates (valid_from, valid_to)
        ) $charset;";
        dbDelta( $sql );

        // 15. DAMAGE REPORTS
        $sql = "CREATE TABLE {$prefix}damage_reports (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            booking_id bigint(20) unsigned NOT NULL,
            vehicle_id bigint(20) unsigned NOT NULL,
            customer_id bigint(20) unsigned NOT NULL,
            report_type varchar(30) DEFAULT 'pickup',
            damage_description text,
            damage_location varchar(255),
            severity varchar(20) DEFAULT 'minor',
            repair_cost decimal(10,2) DEFAULT 0.00,
            charged_to_customer tinyint(1) DEFAULT 0,
            images text,
            notes text,
            reported_by bigint(20) unsigned,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_booking (booking_id),
            KEY idx_vehicle (vehicle_id),
            KEY idx_customer (customer_id)
        ) $charset;";
        dbDelta( $sql );

        // 16. REVIEWS
        $sql = "CREATE TABLE {$prefix}reviews (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            booking_id bigint(20) unsigned,
            vehicle_id bigint(20) unsigned NOT NULL,
            customer_id bigint(20) unsigned NOT NULL,
            rating int NOT NULL,
            title varchar(255),
            review text,
            is_approved tinyint(1) DEFAULT 0,
            admin_reply text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_vehicle (vehicle_id),
            KEY idx_customer (customer_id),
            KEY idx_approved (is_approved)
        ) $charset;";
        dbDelta( $sql );

        // 17. TAX RATES
        $sql = "CREATE TABLE {$prefix}tax_rates (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            rate decimal(6,3) NOT NULL,
            type varchar(20) DEFAULT 'percentage',
            country varchar(100),
            state varchar(100),
            city varchar(100),
            applies_to varchar(50) DEFAULT 'all',
            priority int DEFAULT 0,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_location (country, state),
            KEY idx_status (status)
        ) $charset;";
        dbDelta( $sql );

        // 18. BLOCKED DATES (vehicle unavailability / maintenance)
        $sql = "CREATE TABLE {$prefix}blocked_dates (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            vehicle_id bigint(20) unsigned NOT NULL,
            date_from datetime NOT NULL,
            date_to datetime NOT NULL,
            reason varchar(255),
            notes text,
            created_by bigint(20) unsigned,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_vehicle (vehicle_id),
            KEY idx_dates (date_from, date_to)
        ) $charset;";
        dbDelta( $sql );

        // 19. EMAIL TEMPLATES
        $sql = "CREATE TABLE {$prefix}email_templates (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            slug varchar(100) NOT NULL,
            name varchar(255) NOT NULL,
            subject varchar(500) NOT NULL,
            body longtext NOT NULL,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_slug (slug)
        ) $charset;";
        dbDelta( $sql );

        // 20. PAYMENTS LOG
        $sql = "CREATE TABLE {$prefix}payments (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            booking_id bigint(20) unsigned NOT NULL,
            amount decimal(10,2) NOT NULL,
            currency varchar(10) DEFAULT 'USD',
            payment_method varchar(50),
            transaction_id varchar(255),
            status varchar(30) DEFAULT 'pending',
            gateway_response text,
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_booking (booking_id),
            KEY idx_status (status),
            KEY idx_transaction (transaction_id)
        ) $charset;";
        dbDelta( $sql );

        update_option( 'raf_db_version', RAF_DB_VERSION );
    }

    public static function create_default_options() {
        $defaults = array(
            'raf_company_name'         => get_bloginfo( 'name' ),
            'raf_company_email'        => get_bloginfo( 'admin_email' ),
            'raf_company_phone'        => '',
            'raf_company_address'      => '',
            'raf_currency'             => 'USD',
            'raf_currency_position'    => 'before',
            'raf_date_format'          => 'Y-m-d',
            'raf_time_format'          => 'H:i',
            'raf_time_slot_interval'   => 30,
            'raf_min_booking_advance'  => 1,
            'raf_max_booking_advance'  => 365,
            'raf_default_tax_rate'     => 0,
            'raf_terms_page_id'        => 0,
            'raf_privacy_page_id'      => 0,
            'raf_booking_prefix'       => 'RAF',
            'raf_auto_confirm'         => 0,
            'raf_require_login'        => 0,
            'raf_require_deposit'      => 0,
            'raf_deposit_type'         => 'percentage',
            'raf_deposit_value'        => 20,
            'raf_cancellation_policy'  => '',
            'raf_google_maps_api_key'  => '',
            'raf_email_from_name'      => get_bloginfo( 'name' ),
            'raf_email_from_address'   => get_bloginfo( 'admin_email' ),
            'raf_admin_email_notifications' => 1,
            'raf_customer_email_notifications' => 1,
        );

        foreach ( $defaults as $key => $value ) {
            if ( get_option( $key ) === false ) {
                add_option( $key, $value );
            }
        }

        // Insert default email templates
        self::insert_default_email_templates();
    }

    private static function insert_default_email_templates() {
        global $wpdb;
        $table = $wpdb->prefix . 'raf_email_templates';

        $templates = array(
            array(
                'slug'    => 'booking_confirmed',
                'name'    => 'Booking Confirmed',
                'subject' => 'Booking #{booking_number} Confirmed - {company_name}',
                'body'    => '<h2>Booking Confirmed</h2><p>Dear {customer_name},</p><p>Your booking <strong>#{booking_number}</strong> has been confirmed.</p><h3>Booking Details</h3><p><strong>Vehicle:</strong> {vehicle_name}<br><strong>Pickup:</strong> {pickup_date} at {pickup_location}<br><strong>Return:</strong> {dropoff_date} at {dropoff_location}<br><strong>Total:</strong> {total_price}</p><p>Thank you for choosing {company_name}!</p>',
            ),
            array(
                'slug'    => 'booking_pending',
                'name'    => 'Booking Pending Review',
                'subject' => 'Booking #{booking_number} Received - {company_name}',
                'body'    => '<h2>Booking Received</h2><p>Dear {customer_name},</p><p>We have received your booking request <strong>#{booking_number}</strong>. We will review it and confirm shortly.</p><h3>Booking Details</h3><p><strong>Vehicle:</strong> {vehicle_name}<br><strong>Pickup:</strong> {pickup_date} at {pickup_location}<br><strong>Return:</strong> {dropoff_date} at {dropoff_location}<br><strong>Total:</strong> {total_price}</p>',
            ),
            array(
                'slug'    => 'booking_cancelled',
                'name'    => 'Booking Cancelled',
                'subject' => 'Booking #{booking_number} Cancelled - {company_name}',
                'body'    => '<h2>Booking Cancelled</h2><p>Dear {customer_name},</p><p>Your booking <strong>#{booking_number}</strong> has been cancelled.</p><p>If you have any questions, please contact us.</p>',
            ),
            array(
                'slug'    => 'booking_completed',
                'name'    => 'Booking Completed',
                'subject' => 'Thank you for renting with {company_name}!',
                'body'    => '<h2>Rental Complete</h2><p>Dear {customer_name},</p><p>Your rental for booking <strong>#{booking_number}</strong> has been completed. We hope you enjoyed your experience!</p><p>We would love to hear your feedback.</p>',
            ),
            array(
                'slug'    => 'pickup_reminder',
                'name'    => 'Pickup Reminder',
                'subject' => 'Reminder: Your pickup is tomorrow - {company_name}',
                'body'    => '<h2>Pickup Reminder</h2><p>Dear {customer_name},</p><p>This is a reminder that your rental pickup is scheduled for <strong>{pickup_date}</strong> at <strong>{pickup_location}</strong>.</p><p><strong>Vehicle:</strong> {vehicle_name}<br><strong>Booking:</strong> #{booking_number}</p>',
            ),
            array(
                'slug'    => 'return_reminder',
                'name'    => 'Return Reminder',
                'subject' => 'Reminder: Your return is tomorrow - {company_name}',
                'body'    => '<h2>Return Reminder</h2><p>Dear {customer_name},</p><p>This is a reminder to return your vehicle by <strong>{dropoff_date}</strong> at <strong>{dropoff_location}</strong>.</p><p><strong>Vehicle:</strong> {vehicle_name}<br><strong>Booking:</strong> #{booking_number}</p>',
            ),
            array(
                'slug'    => 'admin_new_booking',
                'name'    => 'Admin: New Booking',
                'subject' => 'New Booking #{booking_number}',
                'body'    => '<h2>New Booking Received</h2><p>A new booking has been placed:</p><p><strong>Booking:</strong> #{booking_number}<br><strong>Customer:</strong> {customer_name} ({customer_email})<br><strong>Vehicle:</strong> {vehicle_name}<br><strong>Pickup:</strong> {pickup_date} at {pickup_location}<br><strong>Return:</strong> {dropoff_date} at {dropoff_location}<br><strong>Total:</strong> {total_price}</p>',
            ),
        );

        foreach ( $templates as $tpl ) {
            $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE slug = %s", $tpl['slug'] ) );
            if ( ! $exists ) {
                $wpdb->insert( $table, $tpl );
            }
        }
    }

    public static function create_pages() {
        $pages = array(
            'raf_search_page'   => array(
                'title'   => 'Rent a Bike',
                'content' => '[raf_search]',
            ),
            'raf_vehicles_page' => array(
                'title'   => 'Our Bikes',
                'content' => '[raf_vehicles]',
            ),
            'raf_booking_page'  => array(
                'title'   => 'Complete Booking',
                'content' => '[raf_booking]',
            ),
            'raf_confirmation_page' => array(
                'title'   => 'Booking Confirmation',
                'content' => '[raf_confirmation]',
            ),
            'raf_my_bookings_page' => array(
                'title'   => 'My Bookings',
                'content' => '[raf_my_bookings]',
            ),
        );

        foreach ( $pages as $option_key => $page ) {
            $page_id   = get_option( $option_key );
            $existing  = $page_id ? get_post( $page_id ) : null;

            if ( $existing && 'trash' === $existing->post_status ) {
                // Page was trashed -- treat as deleted.
                $existing = null;
            }

            if ( ! $page_id || ! $existing ) {
                // Page does not exist or was deleted -- create it.
                $new_id = wp_insert_post( array(
                    'post_title'   => $page['title'],
                    'post_content' => $page['content'],
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                ) );

                if ( $new_id && ! is_wp_error( $new_id ) ) {
                    update_option( $option_key, $new_id );
                }
            } elseif ( $existing && false === strpos( $existing->post_content, $page['content'] ) ) {
                // Page exists but does NOT contain the correct shortcode -- update it.
                wp_update_post( array(
                    'ID'           => $existing->ID,
                    'post_content' => $page['content'],
                ) );
            }
        }
    }
}
