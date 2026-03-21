<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RAF_Email_Manager {

    public function __construct() {
        add_action( 'raf_booking_status_changed', array( $this, 'on_status_changed' ), 10, 3 );
        add_filter( 'wp_mail_content_type', array( $this, 'set_html_content_type' ) );
        add_action( 'wp_ajax_raf_send_test_email', array( $this, 'ajax_send_test_email' ) );
    }

    public function set_html_content_type() {
        return 'text/html';
    }

    private function get_template( $slug ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . RAF_Helpers::table( 'email_templates' ) . " WHERE slug = %s AND is_active = 1",
            $slug
        ) );
    }

    private function parse_placeholders( $content, $booking ) {
        $customer = RAF_Customer::get( $booking->customer_id );
        $vehicle  = RAF_Vehicle::get( $booking->vehicle_id );
        $pickup   = RAF_Location::get( $booking->pickup_location_id );
        $dropoff  = RAF_Location::get( $booking->dropoff_location_id );

        $placeholders = array(
            '{booking_number}'   => $booking->booking_number,
            '{customer_name}'    => $customer ? RAF_Customer::get_full_name( $customer ) : '',
            '{customer_email}'   => $customer ? $customer->email : '',
            '{customer_phone}'   => $customer ? $customer->phone : '',
            '{vehicle_name}'     => $vehicle ? $vehicle->name : '',
            '{pickup_date}'      => RAF_Helpers::format_datetime( $booking->pickup_date ),
            '{dropoff_date}'     => RAF_Helpers::format_datetime( $booking->dropoff_date ),
            '{pickup_location}'  => $pickup ? $pickup->name : '',
            '{dropoff_location}' => $dropoff ? $dropoff->name : '',
            '{rental_days}'      => $booking->rental_days,
            '{base_price}'       => RAF_Helpers::format_price( $booking->base_price ),
            '{total_price}'      => RAF_Helpers::format_price( $booking->total_price ),
            '{deposit_amount}'   => RAF_Helpers::format_price( $booking->deposit_amount ),
            '{status}'           => ucfirst( $booking->status ),
            '{company_name}'     => get_option( 'raf_company_name', get_bloginfo( 'name' ) ),
            '{company_email}'    => get_option( 'raf_company_email', get_bloginfo( 'admin_email' ) ),
            '{company_phone}'    => get_option( 'raf_company_phone', '' ),
            '{site_url}'         => home_url(),
            '{booking_url}'      => add_query_arg( 'booking', $booking->booking_number, get_permalink( get_option( 'raf_my_bookings_page' ) ) ),
        );

        return str_replace( array_keys( $placeholders ), array_values( $placeholders ), $content );
    }

    /**
     * Get the email design options with defaults.
     *
     * @return array Associative array of design settings.
     */
    private function get_design_options() {
        return array(
            'logo_id'           => absint( get_option( 'raf_email_logo_id', 0 ) ),
            'header_bg'         => sanitize_hex_color( get_option( 'raf_email_header_bg', '#1a1a2e' ) ) ?: '#1a1a2e',
            'header_text'       => sanitize_hex_color( get_option( 'raf_email_header_text', '#ffffff' ) ) ?: '#ffffff',
            'accent_color'      => sanitize_hex_color( get_option( 'raf_email_accent_color', '#E85C24' ) ) ?: '#E85C24',
            'body_bg'           => sanitize_hex_color( get_option( 'raf_email_body_bg', '#ffffff' ) ) ?: '#ffffff',
            'footer_bg'         => sanitize_hex_color( get_option( 'raf_email_footer_bg', '#f5f5f5' ) ) ?: '#f5f5f5',
            'footer_text_color' => sanitize_hex_color( get_option( 'raf_email_footer_text_color', '#999999' ) ) ?: '#999999',
            'footer_text'       => get_option( 'raf_email_footer_text', '© {year} {company_name}. All rights reserved.' ),
        );
    }

    /**
     * Wrap email body content in a professional HTML email template.
     *
     * Uses table-based layout for maximum email client compatibility.
     * All CSS is inlined. The design is driven by admin-configurable options.
     *
     * @param string $body The HTML body content to wrap.
     * @return string Complete HTML email document.
     */
    private function wrap_in_layout( $body ) {
        $company = esc_html( get_option( 'raf_company_name', get_bloginfo( 'name' ) ) );
        $design  = $this->get_design_options();

        // Build the logo or company name header content.
        $header_content = '';
        if ( $design['logo_id'] ) {
            $logo_url = wp_get_attachment_image_url( $design['logo_id'], 'medium' );
            if ( $logo_url ) {
                $header_content = '<img src="' . esc_url( $logo_url ) . '" alt="' . $company . '" width="180" height="auto" style="display:block;margin:0 auto;max-width:180px;height:auto;border:0;" />';
            }
        }
        if ( ! $header_content ) {
            $header_content = '<h1 style="margin:0;padding:0;font-family:Arial,Helvetica,sans-serif;font-size:26px;font-weight:700;color:' . esc_attr( $design['header_text'] ) . ';">' . $company . '</h1>';
        }

        // Parse footer placeholders.
        $footer_text = str_replace(
            array( '{year}', '{company_name}' ),
            array( date( 'Y' ), $company ),
            esc_html( $design['footer_text'] )
        );

        // Inline the CTA button styles into body content.
        // Replace <a class="raf-email-btn" ...> with inlined version.
        $btn_style = 'display:inline-block;padding:12px 28px;background-color:' . esc_attr( $design['accent_color'] ) . ';color:#ffffff;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:600;text-decoration:none;border-radius:5px;mso-padding-alt:0;';
        $body = preg_replace(
            '/<a\s+class=["\']raf-email-btn["\']\s+/i',
            '<a style="' . $btn_style . '" ',
            $body
        );

        // Inline heading styles for email body.
        $body = preg_replace(
            '/<h2(?:\s[^>]*)?>/',
            '<h2 style="margin:0 0 12px;padding:0;font-family:Arial,Helvetica,sans-serif;font-size:22px;font-weight:700;color:#333333;">',
            $body
        );
        $body = preg_replace(
            '/<h3(?:\s[^>]*)?>/',
            '<h3 style="margin:16px 0 8px;padding:0;font-family:Arial,Helvetica,sans-serif;font-size:17px;font-weight:600;color:#333333;">',
            $body
        );

        // Build the email.
        $html = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">' . "\n";
        $html .= '<html xmlns="http://www.w3.org/1999/xhtml">' . "\n";
        $html .= '<head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" /><title>' . $company . '</title></head>' . "\n";
        $html .= '<body style="margin:0;padding:0;background-color:#f0f0f0;font-family:Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">' . "\n";

        // Outer wrapper table (full-width background).
        $html .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#f0f0f0;">';
        $html .= '<tr><td align="center" style="padding:20px 10px;">';

        // Inner container table (600px max).
        $html .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width:600px;width:100%;">';

        // HEADER.
        $html .= '<tr>';
        $html .= '<td align="center" style="background-color:' . esc_attr( $design['header_bg'] ) . ';padding:28px 30px;border-radius:8px 8px 0 0;">';
        $html .= $header_content;
        $html .= '</td>';
        $html .= '</tr>';

        // BODY.
        $html .= '<tr>';
        $html .= '<td style="background-color:' . esc_attr( $design['body_bg'] ) . ';padding:32px 30px;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#333333;border-left:1px solid #e0e0e0;border-right:1px solid #e0e0e0;">';
        $html .= $body;
        $html .= '</td>';
        $html .= '</tr>';

        // FOOTER.
        $company_phone = get_option( 'raf_company_phone', '' );
        $company_email = get_option( 'raf_company_email', '' );
        $contact_parts = array_filter( array( $company_phone, $company_email ) );
        $contact_line  = '';
        if ( ! empty( $contact_parts ) ) {
            $contact_line = '<p style="margin:8px 0 0;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.5;color:' . esc_attr( $design['footer_text_color'] ) . ';">You can contact us at ' . implode( ' or ', array_map( 'esc_html', $contact_parts ) ) . '</p>';
        }

        $html .= '<tr>';
        $html .= '<td align="center" style="background-color:' . esc_attr( $design['footer_bg'] ) . ';padding:20px 30px;border-radius:0 0 8px 8px;">';
        $html .= '<p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.5;color:' . esc_attr( $design['footer_text_color'] ) . ';">';
        $html .= $footer_text;
        $html .= '</p>';
        $html .= $contact_line;
        $html .= '</td>';
        $html .= '</tr>';

        // Close inner container.
        $html .= '</table>';

        // Close outer wrapper.
        $html .= '</td></tr></table>';
        $html .= '</body></html>';

        return $html;
    }

    private function send( $to, $subject, $body ) {
        if ( ! get_option( 'raf_customer_email_notifications', 1 ) ) return false;

        $from_name  = get_option( 'raf_email_from_name', get_bloginfo( 'name' ) );
        $from_email = get_option( 'raf_email_from_address', get_bloginfo( 'admin_email' ) );

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            "From: $from_name <$from_email>",
        );

        return wp_mail( $to, $subject, $this->wrap_in_layout( $body ), $headers );
    }

    /**
     * Send a test email to the admin for previewing email design.
     *
     * AJAX handler: wp_ajax_raf_send_test_email
     */
    public function ajax_send_test_email() {
        check_ajax_referer( 'raf_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'rentafleet' ) ) );
        }

        $admin_email = get_option( 'raf_company_email', get_bloginfo( 'admin_email' ) );
        $company     = get_option( 'raf_company_name', get_bloginfo( 'name' ) );
        $design      = $this->get_design_options();

        $subject = sprintf(
            /* translators: %s: company name */
            __( '[Test] Email Design Preview — %s', 'rentafleet' ),
            $company
        );

        $body  = '<h2>Booking Confirmed</h2>';
        $body .= '<p>Dear <strong>John Smith</strong>,</p>';
        $body .= '<p>Your booking <strong>#RAF-20260321-001</strong> has been confirmed. Here are your rental details:</p>';
        $body .= '<h3>Booking Details</h3>';
        $body .= '<p>';
        $body .= '<strong>Vehicle:</strong> Honda CBR 500R<br />';
        $body .= '<strong>Pickup:</strong> March 25, 2026 at 09:00 — Downtown Location<br />';
        $body .= '<strong>Return:</strong> March 28, 2026 at 17:00 — Downtown Location<br />';
        $body .= '<strong>Duration:</strong> 3 days<br />';
        $body .= '<strong>Total:</strong> $225.00';
        $body .= '</p>';
        $body .= '<p style="font-size:13px;color:#666;">This is a test email sent from your RentAFleet email design settings. If you received this, your email delivery is working correctly.</p>';

        $from_name  = get_option( 'raf_email_from_name', get_bloginfo( 'name' ) );
        $from_email = get_option( 'raf_email_from_address', get_bloginfo( 'admin_email' ) );

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            "From: $from_name <$from_email>",
        );

        $result = wp_mail( $admin_email, $subject, $this->wrap_in_layout( $body ), $headers );

        if ( $result ) {
            wp_send_json_success( array(
                'message' => sprintf(
                    /* translators: %s: admin email address */
                    __( 'Test email sent to %s', 'rentafleet' ),
                    $admin_email
                ),
            ) );
        } else {
            wp_send_json_error( array(
                'message' => __( 'Failed to send test email. Check your server mail configuration.', 'rentafleet' ),
            ) );
        }
    }

    public function send_booking_confirmed( $booking ) {
        $tpl = $this->get_template( 'booking_confirmed' );
        if ( ! $tpl ) return;
        $customer = RAF_Customer::get( $booking->customer_id );
        if ( ! $customer ) return;
        $this->send( $customer->email, $this->parse_placeholders( $tpl->subject, $booking ), $this->parse_placeholders( $tpl->body, $booking ) );
    }

    public function send_booking_pending( $booking ) {
        $tpl = $this->get_template( 'booking_pending' );
        if ( ! $tpl ) return;
        $customer = RAF_Customer::get( $booking->customer_id );
        if ( ! $customer ) return;
        $this->send( $customer->email, $this->parse_placeholders( $tpl->subject, $booking ), $this->parse_placeholders( $tpl->body, $booking ) );
    }

    public function send_booking_cancelled( $booking ) {
        $tpl = $this->get_template( 'booking_cancelled' );
        if ( ! $tpl ) return;
        $customer = RAF_Customer::get( $booking->customer_id );
        if ( ! $customer ) return;
        $this->send( $customer->email, $this->parse_placeholders( $tpl->subject, $booking ), $this->parse_placeholders( $tpl->body, $booking ) );
    }

    public function send_booking_completed( $booking ) {
        $tpl = $this->get_template( 'booking_completed' );
        if ( ! $tpl ) return;
        $customer = RAF_Customer::get( $booking->customer_id );
        if ( ! $customer ) return;
        $this->send( $customer->email, $this->parse_placeholders( $tpl->subject, $booking ), $this->parse_placeholders( $tpl->body, $booking ) );
    }

    public function send_pickup_reminder( $booking ) {
        $tpl = $this->get_template( 'pickup_reminder' );
        if ( ! $tpl ) return;
        $customer = RAF_Customer::get( $booking->customer_id );
        if ( ! $customer ) return;
        $this->send( $customer->email, $this->parse_placeholders( $tpl->subject, $booking ), $this->parse_placeholders( $tpl->body, $booking ) );
    }

    public function send_return_reminder( $booking ) {
        $tpl = $this->get_template( 'return_reminder' );
        if ( ! $tpl ) return;
        $customer = RAF_Customer::get( $booking->customer_id );
        if ( ! $customer ) return;
        $this->send( $customer->email, $this->parse_placeholders( $tpl->subject, $booking ), $this->parse_placeholders( $tpl->body, $booking ) );
    }

    public function send_admin_new_booking( $booking ) {
        if ( ! get_option( 'raf_admin_email_notifications', 1 ) ) return;
        $tpl = $this->get_template( 'admin_new_booking' );
        if ( ! $tpl ) return;
        $admin_email = get_option( 'raf_company_email', get_bloginfo( 'admin_email' ) );
        $this->send( $admin_email, $this->parse_placeholders( $tpl->subject, $booking ), $this->parse_placeholders( $tpl->body, $booking ) );
    }

    public function on_status_changed( $booking_id, $old_status, $new_status ) {
        $booking = RAF_Booking_Model::get( $booking_id );
        if ( ! $booking ) return;

        switch ( $new_status ) {
            case 'confirmed':
                $this->send_booking_confirmed( $booking );
                break;
            case 'cancelled':
                $this->send_booking_cancelled( $booking );
                break;
            case 'completed':
                $this->send_booking_completed( $booking );
                break;
        }
    }
}
