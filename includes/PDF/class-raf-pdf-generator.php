<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RAF_PDF_Generator {

    public function __construct() {
        add_action( 'wp_ajax_raf_generate_pdf', array( $this, 'ajax_generate_pdf' ) );
    }

    /**
     * Generate booking invoice/contract as HTML (can be printed to PDF via browser)
     */
    public function generate_invoice( $booking_id ) {
        $booking  = RAF_Booking_Model::get( $booking_id );
        if ( ! $booking ) return '';

        $customer = RAF_Customer::get( $booking->customer_id );
        $vehicle  = RAF_Vehicle::get( $booking->vehicle_id );
        $pickup   = RAF_Location::get( $booking->pickup_location_id );
        $dropoff  = RAF_Location::get( $booking->dropoff_location_id );
        $extras   = RAF_Booking_Model::get_extras( $booking_id );
        $insurance= RAF_Booking_Model::get_insurance( $booking_id );
        $company  = get_option( 'raf_company_name', get_bloginfo( 'name' ) );

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title><?php printf( __( 'Invoice %s', 'rentafleet' ), $booking->booking_number ); ?></title>
            <style>
                body { font-family: 'Helvetica', Arial, sans-serif; font-size: 12px; color: #333; margin: 0; padding: 20px; }
                .invoice-header { display: flex; justify-content: space-between; margin-bottom: 30px; border-bottom: 3px solid #2c3e50; padding-bottom: 20px; }
                .company-info h1 { margin: 0; color: #2c3e50; font-size: 24px; }
                .invoice-number { text-align: right; }
                .invoice-number h2 { margin: 0; color: #2c3e50; }
                .section { margin-bottom: 20px; }
                .section h3 { color: #2c3e50; border-bottom: 1px solid #ddd; padding-bottom: 5px; margin-bottom: 10px; }
                .two-col { display: flex; gap: 30px; }
                .two-col > div { flex: 1; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
                th, td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #ddd; }
                th { background: #f5f5f5; font-weight: bold; }
                .text-right { text-align: right; }
                .total-row { font-weight: bold; font-size: 14px; background: #f0f0f0; }
                .status-badge { display: inline-block; padding: 3px 10px; border-radius: 3px; color: #fff; font-weight: bold; }
                .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 10px; color: #999; text-align: center; }
                @media print { body { padding: 0; } }
            </style>
        </head>
        <body>
            <div class="invoice-header">
                <div class="company-info">
                    <h1><?php echo esc_html( $company ); ?></h1>
                    <p><?php echo esc_html( get_option( 'raf_company_address', '' ) ); ?><br>
                    <?php echo esc_html( get_option( 'raf_company_phone', '' ) ); ?><br>
                    <?php echo esc_html( get_option( 'raf_company_email', '' ) ); ?></p>
                </div>
                <div class="invoice-number">
                    <h2><?php _e( 'RENTAL AGREEMENT', 'rentafleet' ); ?></h2>
                    <p><strong>#<?php echo esc_html( $booking->booking_number ); ?></strong><br>
                    <?php _e( 'Date:', 'rentafleet' ); ?> <?php echo RAF_Helpers::format_date( $booking->created_at ); ?><br>
                    <?php _e( 'Status:', 'rentafleet' ); ?> <?php echo esc_html( ucfirst( $booking->status ) ); ?></p>
                </div>
            </div>

            <div class="two-col">
                <div class="section">
                    <h3><?php _e( 'Customer', 'rentafleet' ); ?></h3>
                    <?php if ( $customer ) : ?>
                    <p><strong><?php echo esc_html( RAF_Customer::get_full_name( $customer ) ); ?></strong><br>
                    <?php echo esc_html( $customer->email ); ?><br>
                    <?php echo esc_html( $customer->phone ); ?><br>
                    <?php echo esc_html( $customer->address ); ?><br>
                    <?php echo esc_html( implode( ', ', array_filter( array( $customer->city, $customer->state, $customer->zip ) ) ) ); ?></p>
                    <?php endif; ?>
                </div>
                <div class="section">
                    <h3><?php _e( 'Vehicle', 'rentafleet' ); ?></h3>
                    <?php if ( $vehicle ) : ?>
                    <p><strong><?php echo esc_html( $vehicle->name ); ?></strong><br>
                    <?php echo esc_html( $vehicle->make . ' ' . $vehicle->model . ' ' . $vehicle->year ); ?><br>
                    <?php _e( 'License:', 'rentafleet' ); ?> <?php echo esc_html( $vehicle->license_plate ); ?><br>
                    <?php _e( 'Color:', 'rentafleet' ); ?> <?php echo esc_html( $vehicle->color ); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="section">
                <h3><?php _e( 'Rental Details', 'rentafleet' ); ?></h3>
                <table>
                    <tr><th><?php _e( 'Pickup', 'rentafleet' ); ?></th><td><?php echo RAF_Helpers::format_datetime( $booking->pickup_date ); ?> — <?php echo $pickup ? esc_html( $pickup->name ) : ''; ?></td></tr>
                    <tr><th><?php _e( 'Return', 'rentafleet' ); ?></th><td><?php echo RAF_Helpers::format_datetime( $booking->dropoff_date ); ?> — <?php echo $dropoff ? esc_html( $dropoff->name ) : ''; ?></td></tr>
                    <tr><th><?php _e( 'Duration', 'rentafleet' ); ?></th><td><?php printf( _n( '%d day', '%d days', $booking->rental_days, 'rentafleet' ), $booking->rental_days ); ?></td></tr>
                    <?php if ( $booking->driver_name ) : ?>
                    <tr><th><?php _e( 'Driver', 'rentafleet' ); ?></th><td><?php echo esc_html( $booking->driver_name ); ?> (<?php _e( 'License:', 'rentafleet' ); ?> <?php echo esc_html( $booking->driver_license ); ?>)</td></tr>
                    <?php endif; ?>
                </table>
            </div>

            <div class="section">
                <h3><?php _e( 'Pricing Breakdown', 'rentafleet' ); ?></h3>
                <table>
                    <tr><td><?php _e( 'Base Rental', 'rentafleet' ); ?></td><td class="text-right"><?php echo RAF_Helpers::format_price( $booking->base_price ); ?></td></tr>
                    <?php foreach ( $extras as $extra ) : ?>
                    <tr><td><?php echo esc_html( $extra->name ); ?> (x<?php echo $extra->quantity; ?>)</td><td class="text-right"><?php echo RAF_Helpers::format_price( $extra->total ); ?></td></tr>
                    <?php endforeach; ?>
                    <?php foreach ( $insurance as $ins ) : ?>
                    <tr><td><?php echo esc_html( $ins->name ); ?></td><td class="text-right"><?php echo RAF_Helpers::format_price( $ins->total ); ?></td></tr>
                    <?php endforeach; ?>
                    <?php if ( $booking->location_fees > 0 ) : ?>
                    <tr><td><?php _e( 'Location Fees', 'rentafleet' ); ?></td><td class="text-right"><?php echo RAF_Helpers::format_price( $booking->location_fees ); ?></td></tr>
                    <?php endif; ?>
                    <?php if ( $booking->discount_amount > 0 ) : ?>
                    <tr><td><?php _e( 'Discount', 'rentafleet' ); ?> <?php echo $booking->coupon_code ? '(' . esc_html( $booking->coupon_code ) . ')' : ''; ?></td><td class="text-right">-<?php echo RAF_Helpers::format_price( $booking->discount_amount ); ?></td></tr>
                    <?php endif; ?>
                    <?php if ( $booking->tax_amount > 0 ) : ?>
                    <tr><td><?php _e( 'Tax', 'rentafleet' ); ?></td><td class="text-right"><?php echo RAF_Helpers::format_price( $booking->tax_amount ); ?></td></tr>
                    <?php endif; ?>
                    <tr class="total-row"><td><?php _e( 'TOTAL', 'rentafleet' ); ?></td><td class="text-right"><?php echo RAF_Helpers::format_price( $booking->total_price ); ?></td></tr>
                    <?php if ( $booking->deposit_amount > 0 ) : ?>
                    <tr><td><?php _e( 'Deposit Required', 'rentafleet' ); ?></td><td class="text-right"><?php echo RAF_Helpers::format_price( $booking->deposit_amount ); ?></td></tr>
                    <?php endif; ?>
                </table>
            </div>

            <?php $terms = get_option( 'raf_cancellation_policy', '' ); if ( $terms ) : ?>
            <div class="section">
                <h3><?php _e( 'Terms & Conditions', 'rentafleet' ); ?></h3>
                <p style="font-size:10px;"><?php echo wp_kses_post( $terms ); ?></p>
            </div>
            <?php endif; ?>

            <div class="two-col" style="margin-top:40px;">
                <div>
                    <p>___________________________<br><?php _e( 'Customer Signature', 'rentafleet' ); ?></p>
                </div>
                <div>
                    <p>___________________________<br><?php _e( 'Company Representative', 'rentafleet' ); ?></p>
                </div>
            </div>

            <div class="footer">
                <p><?php echo esc_html( $company ); ?> — <?php _e( 'Generated on', 'rentafleet' ); ?> <?php echo RAF_Helpers::format_datetime( current_time( 'mysql' ) ); ?></p>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Generate damage report PDF HTML
     */
    public function generate_damage_report( $report_id ) {
        $report   = RAF_Damage_Report_Model::get( $report_id );
        if ( ! $report ) return '';

        $booking  = RAF_Booking_Model::get( $report->booking_id );
        $vehicle  = RAF_Vehicle::get( $report->vehicle_id );
        $customer = RAF_Customer::get( $report->customer_id );
        $company  = get_option( 'raf_company_name', get_bloginfo( 'name' ) );

        ob_start();
        ?>
        <!DOCTYPE html>
        <html><head><meta charset="UTF-8"><title><?php _e( 'Damage Report', 'rentafleet' ); ?></title>
        <style>body{font-family:Arial,sans-serif;font-size:12px;padding:20px;} h1{color:#d9534f;} table{width:100%;border-collapse:collapse;margin:15px 0;} th,td{padding:8px;border:1px solid #ddd;text-align:left;} th{background:#f5f5f5;}</style>
        </head><body>
        <h1><?php echo esc_html( $company ); ?> — <?php _e( 'Damage Report', 'rentafleet' ); ?></h1>
        <table>
            <tr><th><?php _e( 'Report Type', 'rentafleet' ); ?></th><td><?php echo esc_html( ucfirst( $report->report_type ) ); ?></td></tr>
            <tr><th><?php _e( 'Date', 'rentafleet' ); ?></th><td><?php echo RAF_Helpers::format_datetime( $report->created_at ); ?></td></tr>
            <tr><th><?php _e( 'Vehicle', 'rentafleet' ); ?></th><td><?php echo $vehicle ? esc_html( $vehicle->name . ' (' . $vehicle->license_plate . ')' ) : ''; ?></td></tr>
            <tr><th><?php _e( 'Customer', 'rentafleet' ); ?></th><td><?php echo $customer ? esc_html( RAF_Customer::get_full_name( $customer ) ) : ''; ?></td></tr>
            <tr><th><?php _e( 'Booking', 'rentafleet' ); ?></th><td><?php echo $booking ? esc_html( $booking->booking_number ) : ''; ?></td></tr>
            <tr><th><?php _e( 'Severity', 'rentafleet' ); ?></th><td><?php echo esc_html( ucfirst( $report->severity ) ); ?></td></tr>
            <tr><th><?php _e( 'Location on Vehicle', 'rentafleet' ); ?></th><td><?php echo esc_html( $report->damage_location ); ?></td></tr>
            <tr><th><?php _e( 'Description', 'rentafleet' ); ?></th><td><?php echo esc_html( $report->damage_description ); ?></td></tr>
            <tr><th><?php _e( 'Repair Cost', 'rentafleet' ); ?></th><td><?php echo RAF_Helpers::format_price( $report->repair_cost ); ?></td></tr>
            <tr><th><?php _e( 'Charged to Customer', 'rentafleet' ); ?></th><td><?php echo $report->charged_to_customer ? __( 'Yes', 'rentafleet' ) : __( 'No', 'rentafleet' ); ?></td></tr>
        </table>
        <?php if ( $report->notes ) : ?>
        <h3><?php _e( 'Notes', 'rentafleet' ); ?></h3>
        <p><?php echo esc_html( $report->notes ); ?></p>
        <?php endif; ?>
        <div style="margin-top:40px;"><p>___________________________<br><?php _e( 'Inspector Signature', 'rentafleet' ); ?></p></div>
        </body></html>
        <?php
        return ob_get_clean();
    }

    /**
     * AJAX: Generate and output PDF (as printable HTML)
     */
    public function ajax_generate_pdf() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        check_ajax_referer( 'raf_admin_nonce', 'nonce' );

        $type = sanitize_text_field( $_GET['type'] ?? 'invoice' );
        $id   = intval( $_GET['id'] ?? 0 );

        if ( $type === 'invoice' ) {
            echo $this->generate_invoice( $id );
        } elseif ( $type === 'damage' ) {
            echo $this->generate_damage_report( $id );
        }
        exit;
    }
}
