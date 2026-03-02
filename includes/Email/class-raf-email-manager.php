<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RAF_Email_Manager {

    public function __construct() {
        add_action( 'raf_booking_status_changed', array( $this, 'on_status_changed' ), 10, 3 );
        add_filter( 'wp_mail_content_type', array( $this, 'set_html_content_type' ) );
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

    private function wrap_in_layout( $body ) {
        $company = get_option( 'raf_company_name', get_bloginfo( 'name' ) );
        return '<!DOCTYPE html><html><head><meta charset="UTF-8">
        <style>body{font-family:Arial,sans-serif;line-height:1.6;color:#333;margin:0;padding:0;}
        .email-wrapper{max-width:600px;margin:0 auto;padding:20px;}
        .email-header{background:#2c3e50;color:#fff;padding:20px;text-align:center;border-radius:5px 5px 0 0;}
        .email-body{background:#fff;padding:30px;border:1px solid #ddd;}
        .email-footer{background:#f5f5f5;padding:15px;text-align:center;font-size:12px;color:#999;border-radius:0 0 5px 5px;}
        h2{margin-top:0;} strong{color:#2c3e50;}</style></head><body>
        <div class="email-wrapper">
        <div class="email-header"><h1>' . esc_html( $company ) . '</h1></div>
        <div class="email-body">' . $body . '</div>
        <div class="email-footer">&copy; ' . date( 'Y' ) . ' ' . esc_html( $company ) . '</div>
        </div></body></html>';
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
