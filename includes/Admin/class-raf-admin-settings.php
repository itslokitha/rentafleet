<?php
/**
 * RentAFleet Admin — Settings
 *
 * Tabbed settings page covering:
 *  • General:       Currency, date/time formats, tax defaults
 *  • Booking Rules: Auto-confirm, min advance, time slots, require login, cancellation policy
 *  • Deposit:       Deposit requirements, one-way fees, payment gateways
 *  • Email:         From name/address, notification toggles
 *  • Company:       Company name, address, phone, email, logo
 *  • Pages:         Confirmation page, My Bookings page
 *
 * All settings use WP get_option/update_option with raf_ prefix.
 *
 * @package RentAFleet
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RAF_Admin_Settings {

    /* ================================================================
     *  ROUTER
     * ============================================================= */

    public static function render() {
        $tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';

        echo '<div class="wrap raf-settings-wrap">';
        echo '<h1>' . esc_html__( 'Settings', 'rentafleet' ) . '</h1>';
        self::render_tabs( $tab );

        switch ( $tab ) {
            case 'booking':       self::render_booking();       break;
            case 'deposit':       self::render_deposit();       break;
            case 'email':         self::render_email();         break;
            case 'email_design':  self::render_email_design();  break;
            case 'company':       self::render_company();       break;
            case 'pages':         self::render_pages();         break;
            default:              self::render_general();       break;
        }

        echo '</div>';
    }

    private static function render_tabs( $active ) {
        $tabs = array(
            'general'      => __( 'General', 'rentafleet' ),
            'booking'      => __( 'Booking Rules', 'rentafleet' ),
            'deposit'      => __( 'Deposit & Payments', 'rentafleet' ),
            'email'        => __( 'Email', 'rentafleet' ),
            'email_design' => __( 'Email Design', 'rentafleet' ),
            'company'      => __( 'Company', 'rentafleet' ),
            'pages'        => __( 'Pages', 'rentafleet' ),
        );
        echo '<nav class="nav-tab-wrapper raf-tab-wrapper">';
        foreach ( $tabs as $slug => $label ) {
            $url   = RAF_Admin::admin_url( 'raf-settings', array( 'tab' => $slug ) );
            $class = ( $slug === $active ) ? 'nav-tab nav-tab-active' : 'nav-tab';
            printf( '<a href="%s" class="%s">%s</a>', esc_url( $url ), esc_attr( $class ), esc_html( $label ) );
        }
        echo '</nav>';
    }

    /* ================================================================
     *  ACTION HANDLER
     * ============================================================= */

    public static function handle_action( $action ) {
        if ( $action === 'save_settings' ) {
            self::handle_save();
        }
    }

    /* ================================================================
     *  TAB 1: GENERAL
     * ============================================================= */

    private static function render_general() {
        $currencies = RAF_Helpers::get_currencies();
        ?>
        <form method="post">
            <input type="hidden" name="raf_action" value="save_settings">
            <input type="hidden" name="page" value="raf-settings">
            <input type="hidden" name="settings_tab" value="general">
            <?php RAF_Admin::nonce_field( 'save_settings' ); ?>

            <div class="raf-panel" style="max-width:800px;">
                <h2><?php esc_html_e( 'Currency & Formatting', 'rentafleet' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label><?php esc_html_e( 'Currency', 'rentafleet' ); ?></label></th>
                        <td>
                            <select name="raf_currency">
                                <?php foreach ( $currencies as $code => $cur ) : ?>
                                    <option value="<?php echo esc_attr( $code ); ?>" <?php selected( get_option( 'raf_currency', 'USD' ), $code ); ?>>
                                        <?php echo esc_html( $cur['symbol'] . ' — ' . $code . ' (' . $cur['name'] . ')' ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e( 'Currency Position', 'rentafleet' ); ?></label></th>
                        <td>
                            <select name="raf_currency_position">
                                <option value="before" <?php selected( get_option( 'raf_currency_position', 'before' ), 'before' ); ?>><?php esc_html_e( 'Before ($99)', 'rentafleet' ); ?></option>
                                <option value="after" <?php selected( get_option( 'raf_currency_position', 'before' ), 'after' ); ?>><?php esc_html_e( 'After (99$)', 'rentafleet' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e( 'Date Format', 'rentafleet' ); ?></label></th>
                        <td>
                            <select name="raf_date_format">
                                <?php
                                $date_formats = array( 'Y-m-d' => '2026-03-01', 'd/m/Y' => '01/03/2026', 'm/d/Y' => '03/01/2026', 'd.m.Y' => '01.03.2026', 'M d, Y' => 'Mar 01, 2026', 'd M Y' => '01 Mar 2026' );
                                foreach ( $date_formats as $fmt => $example ) : ?>
                                    <option value="<?php echo esc_attr( $fmt ); ?>" <?php selected( get_option( 'raf_date_format', 'Y-m-d' ), $fmt ); ?>><?php echo esc_html( $example . ' (' . $fmt . ')' ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e( 'Time Format', 'rentafleet' ); ?></label></th>
                        <td>
                            <select name="raf_time_format">
                                <option value="H:i" <?php selected( get_option( 'raf_time_format', 'H:i' ), 'H:i' ); ?>><?php esc_html_e( '24-hour (14:30)', 'rentafleet' ); ?></option>
                                <option value="g:i A" <?php selected( get_option( 'raf_time_format', 'H:i' ), 'g:i A' ); ?>><?php esc_html_e( '12-hour (2:30 PM)', 'rentafleet' ); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="raf-panel" style="max-width:800px;">
                <h2><?php esc_html_e( 'Tax Defaults', 'rentafleet' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label><?php esc_html_e( 'Default Tax Rate (%)', 'rentafleet' ); ?></label></th>
                        <td>
                            <input type="number" name="raf_default_tax_rate" value="<?php echo esc_attr( get_option( 'raf_default_tax_rate', 0 ) ); ?>" step="0.001" min="0" max="100" class="small-text">
                            <p class="description"><?php esc_html_e( 'Applied when no location-specific tax rate matches. Set per-location rates in Pricing → Tax Rates.', 'rentafleet' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'rentafleet' ); ?></button></p>
        </form>
        <?php
    }

    /* ================================================================
     *  TAB 2: BOOKING RULES
     * ============================================================= */

    private static function render_booking() {
        ?>
        <form method="post">
            <input type="hidden" name="raf_action" value="save_settings">
            <input type="hidden" name="page" value="raf-settings">
            <input type="hidden" name="settings_tab" value="booking">
            <?php RAF_Admin::nonce_field( 'save_settings' ); ?>

            <div class="raf-panel" style="max-width:800px;">
                <h2><?php esc_html_e( 'Booking Behaviour', 'rentafleet' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label><?php esc_html_e( 'Auto-Confirm Bookings', 'rentafleet' ); ?></label></th>
                        <td>
                            <label><input type="checkbox" name="raf_auto_confirm" value="1" <?php checked( get_option( 'raf_auto_confirm', 0 ) ); ?>>
                            <?php esc_html_e( 'Automatically confirm new bookings (skip pending status)', 'rentafleet' ); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e( 'Require Login', 'rentafleet' ); ?></label></th>
                        <td>
                            <label><input type="checkbox" name="raf_require_login" value="1" <?php checked( get_option( 'raf_require_login', 0 ) ); ?>>
                            <?php esc_html_e( 'Customers must be logged in to book', 'rentafleet' ); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e( 'Booking Number Prefix', 'rentafleet' ); ?></label></th>
                        <td>
                            <input type="text" name="raf_booking_prefix" value="<?php echo esc_attr( get_option( 'raf_booking_prefix', 'RAF' ) ); ?>" class="small-text">
                            <p class="description"><?php esc_html_e( 'e.g. RAF → RAF-20260301-001', 'rentafleet' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e( 'Min Booking Advance (hours)', 'rentafleet' ); ?></label></th>
                        <td>
                            <input type="number" name="raf_min_booking_advance" value="<?php echo esc_attr( get_option( 'raf_min_booking_advance', 1 ) ); ?>" min="0" class="small-text">
                            <p class="description"><?php esc_html_e( 'Minimum hours between now and pickup time. 0 = same-day allowed.', 'rentafleet' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e( 'Minimum Advance Booking (hours)', 'rentafleet' ); ?></label></th>
                        <td>
                            <input type="number" name="raf_min_advance_hours" value="<?php echo esc_attr( get_option( 'raf_min_advance_hours', 24 ) ); ?>" min="0" step="1" class="small-text">
                            <p class="description"><?php esc_html_e( 'Customers must book at least this many hours before the pickup time. Set to 0 to disable.', 'rentafleet' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e( 'Plugin Timezone', 'rentafleet' ); ?></label></th>
                        <td>
                            <?php $current_tz = get_option( 'raf_timezone', get_option( 'timezone_string', 'UTC' ) ) ?: 'UTC'; ?>
                            <select name="raf_timezone">
                                <?php foreach ( DateTimeZone::listIdentifiers() as $tz_id ) : ?>
                                    <option value="<?php echo esc_attr( $tz_id ); ?>" <?php selected( $current_tz, $tz_id ); ?>>
                                        <?php echo esc_html( $tz_id ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e( 'Used to calculate minimum advance booking times. Should match your business location.', 'rentafleet' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e( 'Time Slot Interval (min)', 'rentafleet' ); ?></label></th>
                        <td>
                            <select name="raf_time_slot_interval">
                                <?php foreach ( array( 15, 30, 60, 120 ) as $interval ) : ?>
                                    <option value="<?php echo esc_attr( $interval ); ?>" <?php selected( get_option( 'raf_time_slot_interval', 30 ), $interval ); ?>>
                                        <?php echo esc_html( $interval . ' ' . __( 'minutes', 'rentafleet' ) ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e( 'Pickup/return time dropdown increments.', 'rentafleet' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="raf-panel" style="max-width:800px;">
                <h2><?php esc_html_e( 'Cancellation Policy', 'rentafleet' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label><?php esc_html_e( 'Policy Text', 'rentafleet' ); ?></label></th>
                        <td>
                            <textarea name="raf_cancellation_policy" rows="5" class="large-text"><?php echo esc_textarea( get_option( 'raf_cancellation_policy', '' ) ); ?></textarea>
                            <p class="description"><?php esc_html_e( 'Displayed to customers during booking. Leave empty to hide.', 'rentafleet' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="raf-panel" style="max-width:800px;">
                <h2><?php esc_html_e( 'Terms & Conditions', 'rentafleet' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label><?php esc_html_e( 'Terms & Conditions Text', 'rentafleet' ); ?></label></th>
                        <td>
                            <textarea name="raf_terms_content" rows="10" class="large-text"><?php echo esc_textarea( get_option( 'raf_terms_content', '' ) ); ?></textarea>
                            <p class="description"><?php esc_html_e( 'Displayed in Step 3 of the booking modal. Customers must agree before confirming. HTML allowed.', 'rentafleet' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e( 'Security Deposit Amount', 'rentafleet' ); ?></label></th>
                        <td>
                            <input type="number" name="raf_security_deposit" value="<?php echo esc_attr( get_option( 'raf_security_deposit', 100 ) ); ?>" step="0.01" min="0" class="small-text">
                            <p class="description"><?php esc_html_e( 'Refundable security deposit amount shown in the booking modal price summary.', 'rentafleet' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'rentafleet' ); ?></button></p>
        </form>
        <?php
    }

    /* ================================================================
     *  TAB 3: DEPOSIT & PAYMENTS
     * ============================================================= */

    private static function render_deposit() {
        $gateways = array(
            'offline'        => __( 'Pay at Pickup (Offline)', 'rentafleet' ),
            'bank_transfer'  => __( 'Bank Transfer', 'rentafleet' ),
            'stripe'         => __( 'Stripe', 'rentafleet' ),
            'paypal'         => __( 'PayPal', 'rentafleet' ),
        );
        $enabled = get_option( 'raf_payment_gateways', array( 'offline' ) );
        if ( is_string( $enabled ) ) $enabled = array( $enabled );
        ?>
        <form method="post">
            <input type="hidden" name="raf_action" value="save_settings">
            <input type="hidden" name="page" value="raf-settings">
            <input type="hidden" name="settings_tab" value="deposit">
            <?php RAF_Admin::nonce_field( 'save_settings' ); ?>

            <div class="raf-panel" style="max-width:800px;">
                <h2><?php esc_html_e( 'Deposit Settings', 'rentafleet' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label><?php esc_html_e( 'Require Deposit', 'rentafleet' ); ?></label></th>
                        <td>
                            <label><input type="checkbox" name="raf_require_deposit" value="1" <?php checked( get_option( 'raf_require_deposit', 0 ) ); ?>>
                            <?php esc_html_e( 'Require a deposit when booking', 'rentafleet' ); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e( 'Deposit Type', 'rentafleet' ); ?></label></th>
                        <td>
                            <select name="raf_deposit_type">
                                <option value="percentage" <?php selected( get_option( 'raf_deposit_type', 'percentage' ), 'percentage' ); ?>><?php esc_html_e( 'Percentage of total', 'rentafleet' ); ?></option>
                                <option value="fixed" <?php selected( get_option( 'raf_deposit_type', 'percentage' ), 'fixed' ); ?>><?php esc_html_e( 'Fixed amount', 'rentafleet' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e( 'Deposit Value', 'rentafleet' ); ?></label></th>
                        <td>
                            <input type="number" name="raf_deposit_value" value="<?php echo esc_attr( get_option( 'raf_deposit_value', 20 ) ); ?>" step="0.01" min="0" class="small-text">
                            <p class="description"><?php esc_html_e( 'e.g. 20 for 20% or $20 depending on type above. Vehicle-specific deposits (set in vehicle editor) override this.', 'rentafleet' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="raf-panel" style="max-width:800px;">
                <h2><?php esc_html_e( 'Location Fees', 'rentafleet' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label><?php esc_html_e( 'One-Way Fee', 'rentafleet' ); ?></label></th>
                        <td>
                            <input type="number" name="raf_one_way_fee" value="<?php echo esc_attr( get_option( 'raf_one_way_fee', 0 ) ); ?>" step="0.01" min="0" class="small-text">
                            <p class="description"><?php esc_html_e( 'Extra fee when pickup and return locations differ.', 'rentafleet' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="raf-panel" style="max-width:800px;">
                <h2><?php esc_html_e( 'Payment Gateways', 'rentafleet' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label><?php esc_html_e( 'Enabled Gateways', 'rentafleet' ); ?></label></th>
                        <td>
                            <?php foreach ( $gateways as $gid => $glabel ) : ?>
                                <label style="display:block;margin-bottom:6px;">
                                    <input type="checkbox" name="raf_payment_gateways[]" value="<?php echo esc_attr( $gid ); ?>" <?php checked( in_array( $gid, $enabled ) ); ?>>
                                    <?php echo esc_html( $glabel ); ?>
                                    <?php if ( in_array( $gid, array( 'stripe', 'paypal' ) ) ) : ?>
                                        <span class="description">(<?php esc_html_e( 'coming soon', 'rentafleet' ); ?>)</span>
                                    <?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                </table>
            </div>

            <p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'rentafleet' ); ?></button></p>
        </form>
        <?php
    }

    /* ================================================================
     *  TAB 4: EMAIL
     * ============================================================= */

    private static function render_email() {
        ?>
        <form method="post">
            <input type="hidden" name="raf_action" value="save_settings">
            <input type="hidden" name="page" value="raf-settings">
            <input type="hidden" name="settings_tab" value="email">
            <?php RAF_Admin::nonce_field( 'save_settings' ); ?>

            <div class="raf-panel" style="max-width:800px;">
                <h2><?php esc_html_e( 'Email Sender', 'rentafleet' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label><?php esc_html_e( 'From Name', 'rentafleet' ); ?></label></th>
                        <td><input type="text" name="raf_email_from_name" value="<?php echo esc_attr( get_option( 'raf_email_from_name', get_bloginfo( 'name' ) ) ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e( 'From Email', 'rentafleet' ); ?></label></th>
                        <td><input type="email" name="raf_email_from_address" value="<?php echo esc_attr( get_option( 'raf_email_from_address', get_bloginfo( 'admin_email' ) ) ); ?>" class="regular-text"></td>
                    </tr>
                </table>
            </div>

            <div class="raf-panel" style="max-width:800px;">
                <h2><?php esc_html_e( 'Notification Toggles', 'rentafleet' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label><?php esc_html_e( 'Admin Notifications', 'rentafleet' ); ?></label></th>
                        <td>
                            <label><input type="checkbox" name="raf_admin_email_notifications" value="1" <?php checked( get_option( 'raf_admin_email_notifications', 1 ) ); ?>>
                            <?php esc_html_e( 'Send email to admin on new bookings and status changes', 'rentafleet' ); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e( 'Customer Notifications', 'rentafleet' ); ?></label></th>
                        <td>
                            <label><input type="checkbox" name="raf_customer_email_notifications" value="1" <?php checked( get_option( 'raf_customer_email_notifications', 1 ) ); ?>>
                            <?php esc_html_e( 'Send confirmation and status update emails to customers', 'rentafleet' ); ?></label>
                        </td>
                    </tr>
                </table>
            </div>

            <p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'rentafleet' ); ?></button></p>
        </form>
        <?php
    }

    /* ================================================================
     *  TAB 5: COMPANY
     * ============================================================= */

    private static function render_company() {
        $logo_id  = get_option( 'raf_company_logo', 0 );
        $logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
        ?>
        <form method="post">
            <input type="hidden" name="raf_action" value="save_settings">
            <input type="hidden" name="page" value="raf-settings">
            <input type="hidden" name="settings_tab" value="company">
            <?php RAF_Admin::nonce_field( 'save_settings' ); ?>

            <div class="raf-panel" style="max-width:800px;">
                <h2><?php esc_html_e( 'Company Details', 'rentafleet' ); ?></h2>
                <p class="description" style="padding:0 16px;"><?php esc_html_e( 'Used in invoices, emails, and the frontend.', 'rentafleet' ); ?></p>
                <table class="form-table">
                    <tr>
                        <th><label><?php esc_html_e( 'Company Name', 'rentafleet' ); ?></label></th>
                        <td><input type="text" name="raf_company_name" value="<?php echo esc_attr( get_option( 'raf_company_name', get_bloginfo( 'name' ) ) ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e( 'Address', 'rentafleet' ); ?></label></th>
                        <td><textarea name="raf_company_address" rows="3" class="large-text"><?php echo esc_textarea( get_option( 'raf_company_address', '' ) ); ?></textarea></td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e( 'Phone', 'rentafleet' ); ?></label></th>
                        <td><input type="text" name="raf_company_phone" value="<?php echo esc_attr( get_option( 'raf_company_phone', '' ) ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e( 'Email', 'rentafleet' ); ?></label></th>
                        <td><input type="email" name="raf_company_email" value="<?php echo esc_attr( get_option( 'raf_company_email', get_bloginfo( 'admin_email' ) ) ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e( 'Logo', 'rentafleet' ); ?></label></th>
                        <td>
                            <input type="hidden" name="raf_company_logo" id="raf-company-logo-id" value="<?php echo esc_attr( $logo_id ); ?>">
                            <div id="raf-company-logo-preview" style="margin-bottom:8px;">
                                <?php if ( $logo_url ) : ?>
                                    <img src="<?php echo esc_url( $logo_url ); ?>" style="max-width:200px;height:auto;">
                                <?php endif; ?>
                            </div>
                            <button type="button" class="button" id="raf-company-logo-upload"><?php esc_html_e( 'Select Logo', 'rentafleet' ); ?></button>
                            <button type="button" class="button raf-remove-btn" id="raf-company-logo-remove" style="<?php echo $logo_id ? '' : 'display:none;'; ?>"><?php esc_html_e( 'Remove', 'rentafleet' ); ?></button>
                        </td>
                    </tr>
                </table>
            </div>

            <p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'rentafleet' ); ?></button></p>
        </form>
        <?php
    }

    /* ================================================================
     *  TAB 6: PAGES
     * ============================================================= */

    private static function render_pages() {
        $pages = get_pages( array( 'sort_column' => 'post_title', 'sort_order' => 'ASC' ) );
        ?>
        <form method="post">
            <input type="hidden" name="raf_action" value="save_settings">
            <input type="hidden" name="page" value="raf-settings">
            <input type="hidden" name="settings_tab" value="pages">
            <?php RAF_Admin::nonce_field( 'save_settings' ); ?>

            <div class="raf-panel" style="max-width:800px;">
                <h2><?php esc_html_e( 'Page Assignments', 'rentafleet' ); ?></h2>
                <p class="description" style="padding:0 16px;"><?php esc_html_e( 'Select which WordPress pages contain the RentAFleet shortcodes.', 'rentafleet' ); ?></p>
                <table class="form-table">
                    <tr>
                        <th><label><?php esc_html_e( 'Confirmation Page', 'rentafleet' ); ?></label></th>
                        <td>
                            <select name="raf_confirmation_page">
                                <option value=""><?php esc_html_e( '— Select —', 'rentafleet' ); ?></option>
                                <?php foreach ( $pages as $pg ) : ?>
                                    <option value="<?php echo esc_attr( $pg->ID ); ?>" <?php selected( get_option( 'raf_confirmation_page' ), $pg->ID ); ?>>
                                        <?php echo esc_html( $pg->post_title ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e( 'Page with [raf_confirmation] shortcode. Customers land here after booking.', 'rentafleet' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e( 'My Bookings Page', 'rentafleet' ); ?></label></th>
                        <td>
                            <select name="raf_my_bookings_page">
                                <option value=""><?php esc_html_e( '— Select —', 'rentafleet' ); ?></option>
                                <?php foreach ( $pages as $pg ) : ?>
                                    <option value="<?php echo esc_attr( $pg->ID ); ?>" <?php selected( get_option( 'raf_my_bookings_page' ), $pg->ID ); ?>>
                                        <?php echo esc_html( $pg->post_title ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e( 'Page with [raf_my_bookings] shortcode. Where logged-in customers view their bookings.', 'rentafleet' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="raf-panel" style="max-width:800px;">
                <h2><?php esc_html_e( 'Available Shortcodes', 'rentafleet' ); ?></h2>
                <table class="widefat" style="border:none;box-shadow:none;">
                    <tbody>
                        <tr><td><code>[raf_search]</code></td><td><?php esc_html_e( 'Vehicle search form (dates, location)', 'rentafleet' ); ?></td></tr>
                        <tr><td><code>[raf_vehicles]</code></td><td><?php esc_html_e( 'Vehicle listing / results grid', 'rentafleet' ); ?></td></tr>
                        <tr><td><code>[raf_booking]</code></td><td><?php esc_html_e( 'Booking form (on single vehicle page)', 'rentafleet' ); ?></td></tr>
                        <tr><td><code>[raf_confirmation]</code></td><td><?php esc_html_e( 'Booking confirmation / thank-you page', 'rentafleet' ); ?></td></tr>
                        <tr><td><code>[raf_my_bookings]</code></td><td><?php esc_html_e( 'Customer booking history (requires login)', 'rentafleet' ); ?></td></tr>
                    </tbody>
                </table>
            </div>

            <p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'rentafleet' ); ?></button></p>
        </form>
        <?php
    }

    /* ================================================================
     *  TAB 7: EMAIL DESIGN
     * ============================================================= */

    private static function render_email_design() {
        $logo_id    = absint( get_option( 'raf_email_logo_id', 0 ) );
        $logo_url   = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
        $header_bg  = get_option( 'raf_email_header_bg', '#1a1a2e' );
        $header_txt = get_option( 'raf_email_header_text', '#ffffff' );
        $accent     = get_option( 'raf_email_accent_color', '#E85C24' );
        $body_bg    = get_option( 'raf_email_body_bg', '#ffffff' );
        $footer_bg  = get_option( 'raf_email_footer_bg', '#f5f5f5' );
        $footer_clr = get_option( 'raf_email_footer_text_color', '#999999' );
        $footer_txt = get_option( 'raf_email_footer_text', '© {year} {company_name}. All rights reserved.' );
        $company    = get_option( 'raf_company_name', get_bloginfo( 'name' ) );
        ?>
        <form method="post">
            <input type="hidden" name="raf_action" value="save_settings">
            <input type="hidden" name="page" value="raf-settings">
            <input type="hidden" name="settings_tab" value="email_design">
            <?php RAF_Admin::nonce_field( 'save_settings' ); ?>

            <div style="display:flex;gap:24px;flex-wrap:wrap;align-items:flex-start;">

                <!-- LEFT: Settings Form -->
                <div style="flex:1;min-width:380px;max-width:520px;">

                    <div class="raf-panel">
                        <h2><?php esc_html_e( 'Email Logo', 'rentafleet' ); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th><label><?php esc_html_e( 'Logo Image', 'rentafleet' ); ?></label></th>
                                <td>
                                    <input type="hidden" name="raf_email_logo_id" id="raf-email-logo-id" value="<?php echo esc_attr( $logo_id ); ?>">
                                    <div id="raf-email-logo-preview" style="margin-bottom:8px;">
                                        <?php if ( $logo_url ) : ?>
                                            <img src="<?php echo esc_url( $logo_url ); ?>" style="max-width:180px;height:auto;">
                                        <?php endif; ?>
                                    </div>
                                    <button type="button" class="button" id="raf-email-logo-upload"><?php esc_html_e( 'Upload Logo', 'rentafleet' ); ?></button>
                                    <button type="button" class="button raf-remove-btn" id="raf-email-logo-remove" style="<?php echo $logo_id ? '' : 'display:none;'; ?>"><?php esc_html_e( 'Remove', 'rentafleet' ); ?></button>
                                    <p class="description"><?php esc_html_e( 'Recommended: transparent PNG, max 360px wide. Displayed at 180px in the email header.', 'rentafleet' ); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div class="raf-panel">
                        <h2><?php esc_html_e( 'Color Scheme', 'rentafleet' ); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th><label><?php esc_html_e( 'Header Background', 'rentafleet' ); ?></label></th>
                                <td>
                                    <input type="color" name="raf_email_header_bg" id="raf-email-header-bg" value="<?php echo esc_attr( $header_bg ); ?>">
                                    <code id="raf-email-header-bg-hex"><?php echo esc_html( $header_bg ); ?></code>
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php esc_html_e( 'Header Text Color', 'rentafleet' ); ?></label></th>
                                <td>
                                    <input type="color" name="raf_email_header_text" id="raf-email-header-text" value="<?php echo esc_attr( $header_txt ); ?>">
                                    <code id="raf-email-header-text-hex"><?php echo esc_html( $header_txt ); ?></code>
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php esc_html_e( 'Accent Color', 'rentafleet' ); ?></label></th>
                                <td>
                                    <input type="color" name="raf_email_accent_color" id="raf-email-accent-color" value="<?php echo esc_attr( $accent ); ?>">
                                    <code id="raf-email-accent-color-hex"><?php echo esc_html( $accent ); ?></code>
                                    <p class="description"><?php esc_html_e( 'Used for buttons and highlights.', 'rentafleet' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php esc_html_e( 'Body Background', 'rentafleet' ); ?></label></th>
                                <td>
                                    <input type="color" name="raf_email_body_bg" id="raf-email-body-bg" value="<?php echo esc_attr( $body_bg ); ?>">
                                    <code id="raf-email-body-bg-hex"><?php echo esc_html( $body_bg ); ?></code>
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php esc_html_e( 'Footer Background', 'rentafleet' ); ?></label></th>
                                <td>
                                    <input type="color" name="raf_email_footer_bg" id="raf-email-footer-bg" value="<?php echo esc_attr( $footer_bg ); ?>">
                                    <code id="raf-email-footer-bg-hex"><?php echo esc_html( $footer_bg ); ?></code>
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php esc_html_e( 'Footer Text Color', 'rentafleet' ); ?></label></th>
                                <td>
                                    <input type="color" name="raf_email_footer_text_color" id="raf-email-footer-text-color" value="<?php echo esc_attr( $footer_clr ); ?>">
                                    <code id="raf-email-footer-text-color-hex"><?php echo esc_html( $footer_clr ); ?></code>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div class="raf-panel">
                        <h2><?php esc_html_e( 'Footer Content', 'rentafleet' ); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th><label><?php esc_html_e( 'Footer Text', 'rentafleet' ); ?></label></th>
                                <td>
                                    <textarea name="raf_email_footer_text" id="raf-email-footer-text-input" rows="3" class="large-text"><?php echo esc_textarea( $footer_txt ); ?></textarea>
                                    <p class="description"><?php esc_html_e( 'Supports {year} and {company_name} placeholders.', 'rentafleet' ); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <p class="submit" style="display:flex;gap:12px;align-items:center;">
                        <button type="submit" class="button button-primary"><?php esc_html_e( 'Save Design', 'rentafleet' ); ?></button>
                        <button type="button" class="button" id="raf-send-test-email"><?php esc_html_e( 'Send Test Email', 'rentafleet' ); ?></button>
                        <span id="raf-test-email-status" style="font-size:13px;"></span>
                    </p>
                </div>

                <!-- RIGHT: Live Preview Panel -->
                <div style="flex:1;min-width:340px;max-width:640px;position:sticky;top:32px;">
                    <div class="raf-panel">
                        <h2><?php esc_html_e( 'Live Preview', 'rentafleet' ); ?></h2>
                        <div style="padding:16px;background:#f0f0f0;border-radius:0 0 4px 4px;">
                            <div class="raf-email-preview" id="raf-email-preview" style="max-width:600px;margin:0 auto;font-family:Arial,Helvetica,sans-serif;">
                                <!-- Header -->
                                <div id="raf-preview-header" style="background-color:<?php echo esc_attr( $header_bg ); ?>;padding:28px 30px;text-align:center;border-radius:8px 8px 0 0;">
                                    <?php if ( $logo_url ) : ?>
                                        <img id="raf-preview-logo" src="<?php echo esc_url( $logo_url ); ?>" alt="" style="max-width:180px;height:auto;">
                                    <?php else : ?>
                                        <span id="raf-preview-logo" style="display:none;"></span>
                                    <?php endif; ?>
                                    <h1 id="raf-preview-company" style="margin:0;font-size:26px;font-weight:700;color:<?php echo esc_attr( $header_txt ); ?>;<?php echo $logo_url ? 'display:none;' : ''; ?>"><?php echo esc_html( $company ); ?></h1>
                                </div>
                                <!-- Body -->
                                <div id="raf-preview-body" style="background-color:<?php echo esc_attr( $body_bg ); ?>;padding:32px 30px;font-size:15px;line-height:1.6;color:#333;border-left:1px solid #e0e0e0;border-right:1px solid #e0e0e0;">
                                    <h2 style="margin:0 0 12px;font-size:22px;font-weight:700;color:#333;">Booking Confirmed</h2>
                                    <p>Dear <strong>John Smith</strong>,</p>
                                    <p>Your booking <strong>#RAF-20260321-001</strong> has been confirmed.</p>
                                    <h3 style="margin:16px 0 8px;font-size:17px;font-weight:600;color:#333;">Booking Details</h3>
                                    <p>
                                        <strong>Vehicle:</strong> Honda CBR 500R<br>
                                        <strong>Pickup:</strong> March 25, 2026 at 09:00<br>
                                        <strong>Return:</strong> March 28, 2026 at 17:00<br>
                                        <strong>Total:</strong> $225.00
                                    </p>
                                </div>
                                <!-- Footer -->
                                <div id="raf-preview-footer" style="background-color:<?php echo esc_attr( $footer_bg ); ?>;padding:20px 30px;text-align:center;border-radius:0 0 8px 8px;">
                                    <p id="raf-preview-footer-text" style="margin:0;font-size:12px;line-height:1.5;color:<?php echo esc_attr( $footer_clr ); ?>;">
                                        <?php
                                        echo esc_html( str_replace(
                                            array( '{year}', '{company_name}' ),
                                            array( date( 'Y' ), $company ),
                                            $footer_txt
                                        ) );
                                        ?>
                                    </p>
                                    <?php
                                    $preview_phone = get_option( 'raf_company_phone', '' );
                                    $preview_email = get_option( 'raf_company_email', '' );
                                    $preview_contact = array_filter( array( $preview_phone, $preview_email ) );
                                    if ( ! empty( $preview_contact ) ) : ?>
                                    <p id="raf-preview-contact" style="margin:8px 0 0;font-size:12px;line-height:1.5;color:<?php echo esc_attr( $footer_clr ); ?>;">
                                        You can contact us at <?php echo esc_html( implode( ' or ', $preview_contact ) ); ?>
                                    </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </form>
        <?php
    }

    /* ================================================================
     *  SAVE HANDLER
     * ============================================================= */

    private static function handle_save() {
        if ( ! RAF_Admin::verify_nonce( 'save_settings' ) ) {
            RAF_Admin::add_notice( __( 'Security check failed.', 'rentafleet' ), 'error' );
            return;
        }

        $tab = sanitize_text_field( $_POST['settings_tab'] );

        // Each tab only saves its own options
        $option_map = array(
            'general' => array(
                'raf_currency'           => 'text',
                'raf_currency_position'  => 'text',
                'raf_date_format'        => 'text',
                'raf_time_format'        => 'text',
                'raf_default_tax_rate'   => 'float',
            ),
            'booking' => array(
                'raf_auto_confirm'         => 'bool',
                'raf_require_login'        => 'bool',
                'raf_booking_prefix'       => 'text',
                'raf_min_booking_advance'  => 'int',
                'raf_min_advance_hours'    => 'int',
                'raf_timezone'             => 'timezone',
                'raf_time_slot_interval'   => 'int',
                'raf_cancellation_policy'  => 'textarea',
                'raf_terms_content'        => 'html',
                'raf_security_deposit'     => 'float',
            ),
            'deposit' => array(
                'raf_require_deposit'    => 'bool',
                'raf_deposit_type'       => 'text',
                'raf_deposit_value'      => 'float',
                'raf_one_way_fee'        => 'float',
                'raf_payment_gateways'   => 'array',
            ),
            'email' => array(
                'raf_email_from_name'              => 'text',
                'raf_email_from_address'            => 'email',
                'raf_admin_email_notifications'     => 'bool',
                'raf_customer_email_notifications'  => 'bool',
            ),
            'company' => array(
                'raf_company_name'    => 'text',
                'raf_company_address' => 'textarea',
                'raf_company_phone'   => 'text',
                'raf_company_email'   => 'email',
                'raf_company_logo'    => 'int',
            ),
            'email_design' => array(
                'raf_email_logo_id'           => 'int',
                'raf_email_header_bg'         => 'color',
                'raf_email_header_text'       => 'color',
                'raf_email_accent_color'      => 'color',
                'raf_email_body_bg'           => 'color',
                'raf_email_footer_bg'         => 'color',
                'raf_email_footer_text_color' => 'color',
                'raf_email_footer_text'       => 'textarea',
            ),
            'pages' => array(
                'raf_confirmation_page' => 'int',
                'raf_my_bookings_page'  => 'int',
            ),
        );

        if ( ! isset( $option_map[ $tab ] ) ) return;

        foreach ( $option_map[ $tab ] as $option_name => $type ) {
            switch ( $type ) {
                case 'text':
                    $val = isset( $_POST[ $option_name ] ) ? sanitize_text_field( $_POST[ $option_name ] ) : '';
                    break;
                case 'textarea':
                    $val = isset( $_POST[ $option_name ] ) ? sanitize_textarea_field( $_POST[ $option_name ] ) : '';
                    break;
                case 'html':
                    $val = isset( $_POST[ $option_name ] ) ? wp_kses_post( wp_unslash( $_POST[ $option_name ] ) ) : '';
                    break;
                case 'email':
                    $val = isset( $_POST[ $option_name ] ) ? sanitize_email( $_POST[ $option_name ] ) : '';
                    break;
                case 'int':
                    $val = isset( $_POST[ $option_name ] ) ? absint( $_POST[ $option_name ] ) : 0;
                    break;
                case 'float':
                    $val = isset( $_POST[ $option_name ] ) ? floatval( $_POST[ $option_name ] ) : 0;
                    break;
                case 'color':
                    $val = isset( $_POST[ $option_name ] ) ? sanitize_hex_color( $_POST[ $option_name ] ) : '';
                    if ( ! $val ) {
                        $val = '#000000';
                    }
                    break;
                case 'bool':
                    $val = isset( $_POST[ $option_name ] ) ? 1 : 0;
                    break;
                case 'timezone':
                    $val = isset( $_POST[ $option_name ] ) ? sanitize_text_field( $_POST[ $option_name ] ) : 'UTC';
                    if ( ! in_array( $val, DateTimeZone::listIdentifiers(), true ) ) {
                        $val = 'UTC';
                    }
                    break;
                case 'array':
                    $val = isset( $_POST[ $option_name ] ) && is_array( $_POST[ $option_name ] )
                        ? array_map( 'sanitize_text_field', $_POST[ $option_name ] )
                        : array();
                    break;
                default:
                    $val = '';
            }
            update_option( $option_name, $val );
        }

        RAF_Admin::add_notice( __( 'Settings saved.', 'rentafleet' ) );
        RAF_Admin::redirect( 'raf-settings', array( 'tab' => $tab ) );
    }
}
