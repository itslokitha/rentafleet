<?php
/**
 * RentAFleet Admin — Bookings Management
 *
 * The most feature-rich admin page. Handles:
 *  • List view with search, multi-filter (status, payment, vehicle, location, dates), pagination, bulk actions
 *  • Detailed single-booking view with: customer info, vehicle, pricing breakdown,
 *    extras/insurance, status timeline, payments history, admin notes
 *  • Edit booking form (dates, vehicle, locations, driver info, mileage/fuel, notes)
 *  • Manual booking creation by admin
 *  • Status change workflow with email triggers
 *  • Payment recording (manual payment entry)
 *  • Admin note system
 *
 * @package RentAFleet
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RAF_Admin_Bookings {

    const PER_PAGE = 20;

    /* ================================================================
     *  ROUTER
     * ============================================================= */

    public static function render() {
        $action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';

        echo '<div class="wrap raf-bookings-wrap">';

        switch ( $action ) {
            case 'view':
                self::render_view();
                break;
            case 'edit':
            case 'add':
                self::render_form();
                break;
            default:
                self::render_list();
                break;
        }

        echo '</div>';
    }

    /* ================================================================
     *  ACTION HANDLER
     * ============================================================= */

    public static function handle_action( $action ) {
        switch ( $action ) {
            case 'save_booking':
                self::handle_save_booking();
                break;
            case 'change_status':
                self::handle_change_status();
                break;
            case 'record_payment':
                self::handle_record_payment();
                break;
            case 'add_note':
                self::handle_add_note();
                break;
            case 'delete_booking':
                self::handle_delete_booking();
                break;
            case 'bulk_bookings':
                self::handle_bulk_action();
                break;
        }
    }

    /* ================================================================
     *  LIST VIEW
     * ============================================================= */

    private static function render_list() {
        global $wpdb;

        // Query params
        $search         = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
        $status         = isset( $_GET['booking_status'] ) ? sanitize_text_field( $_GET['booking_status'] ) : '';
        $payment_status = isset( $_GET['payment_status'] ) ? sanitize_text_field( $_GET['payment_status'] ) : '';
        $vehicle_id     = isset( $_GET['vehicle_id'] ) ? absint( $_GET['vehicle_id'] ) : 0;
        $date_from      = isset( $_GET['date_from'] ) ? sanitize_text_field( $_GET['date_from'] ) : '';
        $date_to        = isset( $_GET['date_to'] ) ? sanitize_text_field( $_GET['date_to'] ) : '';
        $orderby        = isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : 'created_at';
        $order          = isset( $_GET['order'] ) && strtoupper( $_GET['order'] ) === 'ASC' ? 'ASC' : 'DESC';
        $paged          = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
        $offset         = ( $paged - 1 ) * self::PER_PAGE;

        $allowed_orderby = array( 'booking_number', 'pickup_date', 'dropoff_date', 'total_price', 'status', 'payment_status', 'created_at' );
        if ( ! in_array( $orderby, $allowed_orderby, true ) ) $orderby = 'created_at';

        // Build query
        $b_table = RAF_Helpers::table( 'bookings' );
        $c_table = RAF_Helpers::table( 'customers' );
        $v_table = RAF_Helpers::table( 'vehicles' );
        $l_table = RAF_Helpers::table( 'locations' );

        $where  = array( '1=1' );
        $values = array();

        if ( $status ) {
            $where[]  = 'b.status = %s';
            $values[] = $status;
        }
        if ( $payment_status ) {
            $where[]  = 'b.payment_status = %s';
            $values[] = $payment_status;
        }
        if ( $vehicle_id ) {
            $where[]  = 'b.vehicle_id = %d';
            $values[] = $vehicle_id;
        }
        if ( $date_from ) {
            $where[]  = 'b.pickup_date >= %s';
            $values[] = $date_from . ' 00:00:00';
        }
        if ( $date_to ) {
            $where[]  = 'b.dropoff_date <= %s';
            $values[] = $date_to . ' 23:59:59';
        }
        if ( $search ) {
            $like     = '%' . $wpdb->esc_like( $search ) . '%';
            $where[]  = '(b.booking_number LIKE %s OR b.driver_name LIKE %s OR c.first_name LIKE %s OR c.last_name LIKE %s OR c.email LIKE %s)';
            $values   = array_merge( $values, array( $like, $like, $like, $like, $like ) );
        }

        $where_sql = implode( ' AND ', $where );

        // Count
        $count_sql = "SELECT COUNT(*) FROM $b_table b LEFT JOIN $c_table c ON b.customer_id = c.id WHERE $where_sql";
        $total_items = $values ? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $values ) ) : (int) $wpdb->get_var( $count_sql );
        $total_pages = ceil( $total_items / self::PER_PAGE );

        // Fetch
        $sql = "SELECT b.*, c.first_name, c.last_name, c.email AS customer_email, c.phone AS customer_phone,
                       v.name AS vehicle_name, v.make, v.model,
                       lp.name AS pickup_location_name, ld.name AS dropoff_location_name
                FROM $b_table b
                LEFT JOIN $c_table c ON b.customer_id = c.id
                LEFT JOIN $v_table v ON b.vehicle_id = v.id
                LEFT JOIN $l_table lp ON b.pickup_location_id = lp.id
                LEFT JOIN $l_table ld ON b.dropoff_location_id = ld.id
                WHERE $where_sql
                ORDER BY b.{$orderby} {$order}
                LIMIT %d OFFSET %d";
        $values_page   = $values;
        $values_page[] = self::PER_PAGE;
        $values_page[] = $offset;
        $bookings = $wpdb->get_results( $wpdb->prepare( $sql, $values_page ) );

        // Status counts
        $status_counts = self::get_status_counts();
        $all_count     = array_sum( $status_counts );
        $statuses      = RAF_Helpers::get_booking_statuses();

        // Vehicles for filter dropdown
        $vehicles_list = $wpdb->get_results( "SELECT id, name FROM $v_table WHERE status = 'active' ORDER BY name" );
        ?>

        <h1 class="wp-heading-inline"><?php esc_html_e( 'Bookings', 'rentafleet' ); ?></h1>
        <a href="<?php echo esc_url( RAF_Admin::admin_url( 'raf-bookings', array( 'action' => 'add' ) ) ); ?>" class="page-title-action">
            <?php esc_html_e( 'Add Manual Booking', 'rentafleet' ); ?>
        </a>
        <hr class="wp-header-end">

        <?php /* Status filter links */ ?>
        <ul class="subsubsub">
            <?php
            $links   = array();
            $class   = ( ! $status ) ? ' class="current"' : '';
            $links[] = sprintf( '<li><a href="%s"%s>%s <span class="count">(%d)</span></a></li>',
                esc_url( RAF_Admin::admin_url( 'raf-bookings' ) ), $class, esc_html__( 'All', 'rentafleet' ), $all_count );
            foreach ( $statuses as $key => $label ) {
                $cnt     = isset( $status_counts[ $key ] ) ? $status_counts[ $key ] : 0;
                $class   = ( $status === $key ) ? ' class="current"' : '';
                $links[] = sprintf( '<li><a href="%s"%s>%s <span class="count">(%d)</span></a></li>',
                    esc_url( RAF_Admin::admin_url( 'raf-bookings', array( 'booking_status' => $key ) ) ), $class, esc_html( $label ), $cnt );
            }
            echo implode( ' | ', $links );
            ?>
        </ul>

        <?php /* Filter bar */ ?>
        <form method="get" class="raf-filter-form">
            <input type="hidden" name="page" value="raf-bookings">
            <?php if ( $status ) : ?><input type="hidden" name="booking_status" value="<?php echo esc_attr( $status ); ?>"><?php endif; ?>
            <div class="raf-filter-bar">
                <select name="payment_status">
                    <option value=""><?php esc_html_e( 'All Payments', 'rentafleet' ); ?></option>
                    <?php foreach ( RAF_Helpers::get_payment_statuses() as $k => $lbl ) : ?>
                        <option value="<?php echo esc_attr( $k ); ?>" <?php selected( $payment_status, $k ); ?>><?php echo esc_html( $lbl ); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="vehicle_id">
                    <option value=""><?php esc_html_e( 'All Vehicles', 'rentafleet' ); ?></option>
                    <?php foreach ( $vehicles_list as $veh ) : ?>
                        <option value="<?php echo esc_attr( $veh->id ); ?>" <?php selected( $vehicle_id, $veh->id ); ?>><?php echo esc_html( $veh->name ); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" placeholder="<?php esc_attr_e( 'From', 'rentafleet' ); ?>" title="<?php esc_attr_e( 'Pickup from', 'rentafleet' ); ?>">
                <input type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>" placeholder="<?php esc_attr_e( 'To', 'rentafleet' ); ?>" title="<?php esc_attr_e( 'Return by', 'rentafleet' ); ?>">
                <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search bookings…', 'rentafleet' ); ?>">
                <button type="submit" class="button"><?php esc_html_e( 'Filter', 'rentafleet' ); ?></button>
                <?php if ( $search || $payment_status || $vehicle_id || $date_from || $date_to ) : ?>
                    <a href="<?php echo esc_url( RAF_Admin::admin_url( 'raf-bookings', $status ? array( 'booking_status' => $status ) : array() ) ); ?>" class="button"><?php esc_html_e( 'Reset', 'rentafleet' ); ?></a>
                <?php endif; ?>
            </div>
        </form>

        <?php /* Bulk actions + Table */ ?>
        <form method="post" id="raf-bookings-list-form">
            <input type="hidden" name="raf_action" value="bulk_bookings">
            <input type="hidden" name="page" value="raf-bookings">
            <?php RAF_Admin::nonce_field( 'bulk_bookings' ); ?>

            <div class="tablenav top">
                <div class="alignleft actions bulkactions">
                    <select name="bulk_action">
                        <option value=""><?php esc_html_e( 'Bulk Actions', 'rentafleet' ); ?></option>
                        <option value="confirm"><?php esc_html_e( 'Confirm', 'rentafleet' ); ?></option>
                        <option value="cancel"><?php esc_html_e( 'Cancel', 'rentafleet' ); ?></option>
                        <option value="delete"><?php esc_html_e( 'Delete', 'rentafleet' ); ?></option>
                    </select>
                    <button type="submit" class="button action" onclick="return this.form.bulk_action.value === 'delete' ? confirm('<?php esc_attr_e( 'Delete selected bookings permanently?', 'rentafleet' ); ?>') : true;">
                        <?php esc_html_e( 'Apply', 'rentafleet' ); ?>
                    </button>
                </div>
                <?php self::render_pagination( $total_items, $total_pages, $paged ); ?>
            </div>

            <table class="wp-list-table widefat fixed striped raf-bookings-table">
                <thead>
                    <tr>
                        <td class="manage-column column-cb check-column"><input type="checkbox" id="cb-select-all-1"></td>
                        <th class="manage-column column-booking-number"><a href="<?php echo esc_url( self::sort_url( 'booking_number', $orderby, $order ) ); ?>"><?php esc_html_e( 'Booking', 'rentafleet' ); ?><?php echo self::sort_indicator( 'booking_number', $orderby, $order ); ?></a></th>
                        <th class="manage-column"><?php esc_html_e( 'Customer', 'rentafleet' ); ?></th>
                        <th class="manage-column"><?php esc_html_e( 'Vehicle', 'rentafleet' ); ?></th>
                        <th class="manage-column"><a href="<?php echo esc_url( self::sort_url( 'pickup_date', $orderby, $order ) ); ?>"><?php esc_html_e( 'Pickup', 'rentafleet' ); ?><?php echo self::sort_indicator( 'pickup_date', $orderby, $order ); ?></a></th>
                        <th class="manage-column"><a href="<?php echo esc_url( self::sort_url( 'dropoff_date', $orderby, $order ) ); ?>"><?php esc_html_e( 'Return', 'rentafleet' ); ?><?php echo self::sort_indicator( 'dropoff_date', $orderby, $order ); ?></a></th>
                        <th class="manage-column"><a href="<?php echo esc_url( self::sort_url( 'total_price', $orderby, $order ) ); ?>"><?php esc_html_e( 'Total', 'rentafleet' ); ?><?php echo self::sort_indicator( 'total_price', $orderby, $order ); ?></a></th>
                        <th class="manage-column"><?php esc_html_e( 'Status', 'rentafleet' ); ?></th>
                        <th class="manage-column"><?php esc_html_e( 'Payment', 'rentafleet' ); ?></th>
                        <th class="manage-column"><a href="<?php echo esc_url( self::sort_url( 'created_at', $orderby, $order ) ); ?>"><?php esc_html_e( 'Created', 'rentafleet' ); ?><?php echo self::sort_indicator( 'created_at', $orderby, $order ); ?></a></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( empty( $bookings ) ) : ?>
                    <tr><td colspan="10"><?php esc_html_e( 'No bookings found.', 'rentafleet' ); ?></td></tr>
                <?php else : ?>
                    <?php foreach ( $bookings as $bk ) : ?>
                        <?php
                        $view_url = RAF_Admin::admin_url( 'raf-bookings', array( 'action' => 'view', 'id' => $bk->id ) );
                        $edit_url = RAF_Admin::admin_url( 'raf-bookings', array( 'action' => 'edit', 'id' => $bk->id ) );
                        ?>
                        <tr>
                            <th scope="row" class="check-column">
                                <input type="checkbox" name="booking_ids[]" value="<?php echo esc_attr( $bk->id ); ?>">
                            </th>
                            <td class="column-primary">
                                <strong><a href="<?php echo esc_url( $view_url ); ?>">#<?php echo esc_html( $bk->booking_number ); ?></a></strong>
                                <div class="row-actions">
                                    <span class="view"><a href="<?php echo esc_url( $view_url ); ?>"><?php esc_html_e( 'View', 'rentafleet' ); ?></a> | </span>
                                    <span class="edit"><a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'rentafleet' ); ?></a></span>
                                </div>
                            </td>
                            <td>
                                <?php if ( $bk->first_name ) : ?>
                                    <?php echo esc_html( $bk->first_name . ' ' . $bk->last_name ); ?>
                                    <br><small class="raf-muted"><?php echo esc_html( $bk->customer_email ); ?></small>
                                <?php else : ?>
                                    <span class="raf-muted"><?php esc_html_e( 'N/A', 'rentafleet' ); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html( $bk->vehicle_name ? $bk->vehicle_name : '—' ); ?></td>
                            <td>
                                <?php echo esc_html( RAF_Helpers::format_datetime( $bk->pickup_date ) ); ?>
                                <?php if ( $bk->pickup_location_name ) : ?>
                                    <br><small class="raf-muted"><?php echo esc_html( $bk->pickup_location_name ); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo esc_html( RAF_Helpers::format_datetime( $bk->dropoff_date ) ); ?>
                                <?php if ( $bk->dropoff_location_name ) : ?>
                                    <br><small class="raf-muted"><?php echo esc_html( $bk->dropoff_location_name ); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo RAF_Helpers::format_price( $bk->total_price ); ?></strong></td>
                            <td><?php echo RAF_Helpers::status_badge( $bk->status ); ?></td>
                            <td><?php echo RAF_Helpers::status_badge( $bk->payment_status ); ?></td>
                            <td><?php echo esc_html( RAF_Helpers::format_datetime( $bk->created_at ) ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <div class="tablenav bottom">
                <?php self::render_pagination( $total_items, $total_pages, $paged ); ?>
            </div>
        </form>
        <?php
    }

    /* ================================================================
     *  SINGLE BOOKING VIEW (Detail Page)
     * ============================================================= */

    private static function render_view() {
        $id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
        $booking = $id ? RAF_Booking_Model::get( $id ) : null;

        if ( ! $booking ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Booking not found.', 'rentafleet' ) . '</p></div>';
            return;
        }

        $customer   = $booking->customer_id ? RAF_Customer::get( $booking->customer_id ) : null;
        $vehicle    = $booking->vehicle_id ? RAF_Vehicle::get( $booking->vehicle_id ) : null;
        $extras     = RAF_Booking_Model::get_extras( $id );
        $insurance  = RAF_Booking_Model::get_insurance( $id );
        $log        = RAF_Booking_Model::get_log( $id );
        $payments   = RAF_Payment_Manager::get_booking_payments( $id );
        $total_paid = RAF_Payment_Manager::get_total_paid( $id );
        $balance    = $booking->total_price - $total_paid;

        $pickup_loc  = $booking->pickup_location_id ? RAF_Location::get( $booking->pickup_location_id ) : null;
        $dropoff_loc = $booking->dropoff_location_id ? RAF_Location::get( $booking->dropoff_location_id ) : null;

        $statuses    = RAF_Helpers::get_booking_statuses();
        $edit_url    = RAF_Admin::admin_url( 'raf-bookings', array( 'action' => 'edit', 'id' => $id ) );

        ?>
        <h1>
            <?php printf( esc_html__( 'Booking #%s', 'rentafleet' ), esc_html( $booking->booking_number ) ); ?>
            <?php echo RAF_Helpers::status_badge( $booking->status ); ?>
        </h1>
        <?php RAF_Admin::back_link( 'raf-bookings', __( '← Back to Bookings', 'rentafleet' ) ); ?>
        <a href="<?php echo esc_url( $edit_url ); ?>" class="page-title-action"><?php esc_html_e( 'Edit Booking', 'rentafleet' ); ?></a>

        <?php /* PDF link */ ?>
        <?php if ( class_exists( 'RAF_PDF_Generator' ) ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=raf_generate_pdf&booking_id=' . $id . '&_wpnonce=' . wp_create_nonce( 'raf_generate_pdf' ) ) ); ?>" class="page-title-action" target="_blank"><?php esc_html_e( 'Print Invoice', 'rentafleet' ); ?></a>
        <?php endif; ?>

        <div class="raf-view-grid">

            <?php /* ── Left column ── */ ?>
            <div class="raf-view-main">

                <?php /* Booking Details Panel */ ?>
                <div class="raf-panel">
                    <h2><?php esc_html_e( 'Booking Details', 'rentafleet' ); ?></h2>
                    <table class="raf-detail-table">
                        <tr>
                            <th><?php esc_html_e( 'Booking Number', 'rentafleet' ); ?></th>
                            <td><strong>#<?php echo esc_html( $booking->booking_number ); ?></strong></td>
                            <th><?php esc_html_e( 'Source', 'rentafleet' ); ?></th>
                            <td><?php echo esc_html( ucfirst( $booking->source ) ); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Pickup Date', 'rentafleet' ); ?></th>
                            <td><?php echo esc_html( RAF_Helpers::format_datetime( $booking->pickup_date ) ); ?></td>
                            <th><?php esc_html_e( 'Pickup Location', 'rentafleet' ); ?></th>
                            <td><?php echo esc_html( $pickup_loc ? $pickup_loc->name : '—' ); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Return Date', 'rentafleet' ); ?></th>
                            <td><?php echo esc_html( RAF_Helpers::format_datetime( $booking->dropoff_date ) ); ?></td>
                            <th><?php esc_html_e( 'Return Location', 'rentafleet' ); ?></th>
                            <td><?php echo esc_html( $dropoff_loc ? $dropoff_loc->name : '—' ); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Duration', 'rentafleet' ); ?></th>
                            <td>
                                <?php echo esc_html( $booking->rental_days ); ?> <?php esc_html_e( 'day(s)', 'rentafleet' ); ?>
                                <?php if ( $booking->rental_hours ) : ?>
                                    , <?php echo esc_html( $booking->rental_hours ); ?> <?php esc_html_e( 'hour(s)', 'rentafleet' ); ?>
                                <?php endif; ?>
                            </td>
                            <th><?php esc_html_e( 'Created', 'rentafleet' ); ?></th>
                            <td><?php echo esc_html( RAF_Helpers::format_datetime( $booking->created_at ) ); ?></td>
                        </tr>
                        <?php if ( $booking->actual_pickup_date || $booking->actual_dropoff_date ) : ?>
                        <tr>
                            <th><?php esc_html_e( 'Actual Pickup', 'rentafleet' ); ?></th>
                            <td><?php echo $booking->actual_pickup_date ? esc_html( RAF_Helpers::format_datetime( $booking->actual_pickup_date ) ) : '—'; ?></td>
                            <th><?php esc_html_e( 'Actual Return', 'rentafleet' ); ?></th>
                            <td><?php echo $booking->actual_dropoff_date ? esc_html( RAF_Helpers::format_datetime( $booking->actual_dropoff_date ) ) : '—'; ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>

                <?php /* Vehicle Panel */ ?>
                <div class="raf-panel">
                    <h2><?php esc_html_e( 'Vehicle', 'rentafleet' ); ?></h2>
                    <?php if ( $vehicle ) : ?>
                        <div class="raf-vehicle-summary">
                            <?php if ( $vehicle->featured_image_id ) : ?>
                                <div class="raf-vehicle-thumb"><?php echo wp_get_attachment_image( $vehicle->featured_image_id, array( 100, 70 ) ); ?></div>
                            <?php endif; ?>
                            <div class="raf-vehicle-info">
                                <strong><?php echo esc_html( $vehicle->name ); ?></strong>
                                <br><?php echo esc_html( $vehicle->make . ' ' . $vehicle->model . ( $vehicle->year ? ' (' . $vehicle->year . ')' : '' ) ); ?>
                                <?php if ( $vehicle->license_plate ) : ?>
                                    <br><span class="raf-license-plate"><?php echo esc_html( $vehicle->license_plate ); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php /* Mileage & Fuel */ ?>
                        <?php if ( $booking->mileage_start || $booking->mileage_end || $booking->fuel_level_start ) : ?>
                        <table class="raf-detail-table" style="margin-top:12px;">
                            <tr>
                                <th><?php esc_html_e( 'Mileage Start', 'rentafleet' ); ?></th>
                                <td><?php echo $booking->mileage_start ? esc_html( number_format_i18n( $booking->mileage_start ) ) . ' km' : '—'; ?></td>
                                <th><?php esc_html_e( 'Mileage End', 'rentafleet' ); ?></th>
                                <td><?php echo $booking->mileage_end ? esc_html( number_format_i18n( $booking->mileage_end ) ) . ' km' : '—'; ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Fuel Start', 'rentafleet' ); ?></th>
                                <td><?php echo $booking->fuel_level_start ? esc_html( ucfirst( $booking->fuel_level_start ) ) : '—'; ?></td>
                                <th><?php esc_html_e( 'Fuel End', 'rentafleet' ); ?></th>
                                <td><?php echo $booking->fuel_level_end ? esc_html( ucfirst( $booking->fuel_level_end ) ) : '—'; ?></td>
                            </tr>
                            <?php if ( $booking->mileage_start && $booking->mileage_end ) : ?>
                            <tr>
                                <th><?php esc_html_e( 'Total Driven', 'rentafleet' ); ?></th>
                                <td colspan="3"><strong><?php echo esc_html( number_format_i18n( $booking->mileage_end - $booking->mileage_start ) ); ?> km</strong></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                        <?php endif; ?>
                    <?php else : ?>
                        <p class="raf-muted" style="padding:12px 16px;"><?php esc_html_e( 'Vehicle not found.', 'rentafleet' ); ?></p>
                    <?php endif; ?>
                </div>

                <?php /* Pricing Breakdown Panel */ ?>
                <div class="raf-panel">
                    <h2><?php esc_html_e( 'Pricing Breakdown', 'rentafleet' ); ?></h2>
                    <table class="raf-pricing-table">
                        <tr>
                            <td><?php esc_html_e( 'Base Rental', 'rentafleet' ); ?></td>
                            <td class="raf-price-col"><?php echo RAF_Helpers::format_price( $booking->base_price ); ?></td>
                        </tr>
                        <?php if ( ! empty( $extras ) ) : ?>
                            <?php foreach ( $extras as $ex ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $ex->name ); ?> (×<?php echo esc_html( $ex->quantity ); ?>)</td>
                                    <td class="raf-price-col"><?php echo RAF_Helpers::format_price( $ex->total ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <?php if ( $booking->extras_total > 0 && empty( $extras ) ) : ?>
                            <tr><td><?php esc_html_e( 'Extras', 'rentafleet' ); ?></td><td class="raf-price-col"><?php echo RAF_Helpers::format_price( $booking->extras_total ); ?></td></tr>
                        <?php endif; ?>
                        <?php if ( ! empty( $insurance ) ) : ?>
                            <?php foreach ( $insurance as $ins ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $ins->name ); ?></td>
                                    <td class="raf-price-col"><?php echo RAF_Helpers::format_price( $ins->total ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <?php if ( $booking->insurance_total > 0 && empty( $insurance ) ) : ?>
                            <tr><td><?php esc_html_e( 'Insurance', 'rentafleet' ); ?></td><td class="raf-price-col"><?php echo RAF_Helpers::format_price( $booking->insurance_total ); ?></td></tr>
                        <?php endif; ?>
                        <?php if ( $booking->location_fees > 0 ) : ?>
                            <tr><td><?php esc_html_e( 'Location Fees', 'rentafleet' ); ?></td><td class="raf-price-col"><?php echo RAF_Helpers::format_price( $booking->location_fees ); ?></td></tr>
                        <?php endif; ?>
                        <?php if ( $booking->tax_amount > 0 ) : ?>
                            <tr><td><?php esc_html_e( 'Tax', 'rentafleet' ); ?></td><td class="raf-price-col"><?php echo RAF_Helpers::format_price( $booking->tax_amount ); ?></td></tr>
                        <?php endif; ?>
                        <?php if ( $booking->discount_amount > 0 ) : ?>
                            <tr class="raf-discount-row">
                                <td>
                                    <?php esc_html_e( 'Discount', 'rentafleet' ); ?>
                                    <?php if ( $booking->coupon_code ) : ?>
                                        <span class="raf-coupon-tag"><?php echo esc_html( $booking->coupon_code ); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="raf-price-col">-<?php echo RAF_Helpers::format_price( $booking->discount_amount ); ?></td>
                            </tr>
                        <?php endif; ?>
                        <tr class="raf-total-row">
                            <td><strong><?php esc_html_e( 'Total', 'rentafleet' ); ?></strong></td>
                            <td class="raf-price-col"><strong><?php echo RAF_Helpers::format_price( $booking->total_price ); ?></strong></td>
                        </tr>
                        <?php if ( $booking->deposit_amount > 0 ) : ?>
                            <tr>
                                <td><?php esc_html_e( 'Deposit Required', 'rentafleet' ); ?></td>
                                <td class="raf-price-col">
                                    <?php echo RAF_Helpers::format_price( $booking->deposit_amount ); ?>
                                    <?php echo $booking->deposit_paid ? '<span class="raf-badge raf-badge-paid">' . esc_html__( 'Paid', 'rentafleet' ) . '</span>' : '<span class="raf-badge raf-badge-pending">' . esc_html__( 'Unpaid', 'rentafleet' ) . '</span>'; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <tr class="raf-total-row">
                            <td><strong><?php esc_html_e( 'Amount Paid', 'rentafleet' ); ?></strong></td>
                            <td class="raf-price-col"><strong><?php echo RAF_Helpers::format_price( $total_paid ); ?></strong></td>
                        </tr>
                        <?php if ( $balance > 0 ) : ?>
                            <tr class="raf-balance-row">
                                <td><strong><?php esc_html_e( 'Balance Due', 'rentafleet' ); ?></strong></td>
                                <td class="raf-price-col"><strong class="raf-text-danger"><?php echo RAF_Helpers::format_price( $balance ); ?></strong></td>
                            </tr>
                        <?php endif; ?>
                    </table>
                </div>

                <?php /* Driver Info */ ?>
                <?php if ( $booking->driver_name || $booking->driver_license ) : ?>
                <div class="raf-panel">
                    <h2><?php esc_html_e( 'Driver Information', 'rentafleet' ); ?></h2>
                    <table class="raf-detail-table">
                        <?php if ( $booking->driver_name ) : ?>
                        <tr><th><?php esc_html_e( 'Driver Name', 'rentafleet' ); ?></th><td><?php echo esc_html( $booking->driver_name ); ?></td>
                            <th><?php esc_html_e( 'Driver Age', 'rentafleet' ); ?></th><td><?php echo $booking->driver_age ? esc_html( $booking->driver_age ) : '—'; ?></td></tr>
                        <?php endif; ?>
                        <?php if ( $booking->driver_license ) : ?>
                        <tr><th><?php esc_html_e( 'License #', 'rentafleet' ); ?></th><td colspan="3"><?php echo esc_html( $booking->driver_license ); ?></td></tr>
                        <?php endif; ?>
                        <?php if ( $booking->additional_drivers ) : ?>
                        <tr><th><?php esc_html_e( 'Additional Drivers', 'rentafleet' ); ?></th><td colspan="3"><?php echo nl2br( esc_html( $booking->additional_drivers ) ); ?></td></tr>
                        <?php endif; ?>
                    </table>
                </div>
                <?php endif; ?>

                <?php /* Notes */ ?>
                <?php if ( $booking->notes || $booking->admin_notes ) : ?>
                <div class="raf-panel">
                    <h2><?php esc_html_e( 'Notes', 'rentafleet' ); ?></h2>
                    <div style="padding:12px 16px;">
                        <?php if ( $booking->notes ) : ?>
                            <p><strong><?php esc_html_e( 'Customer Notes:', 'rentafleet' ); ?></strong><br><?php echo nl2br( esc_html( $booking->notes ) ); ?></p>
                        <?php endif; ?>
                        <?php if ( $booking->admin_notes ) : ?>
                            <p><strong><?php esc_html_e( 'Admin Notes:', 'rentafleet' ); ?></strong><br><?php echo nl2br( esc_html( $booking->admin_notes ) ); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php /* Payments History */ ?>
                <div class="raf-panel">
                    <h2><?php esc_html_e( 'Payment History', 'rentafleet' ); ?></h2>
                    <?php if ( $payments ) : ?>
                        <table class="widefat striped" style="border:none;box-shadow:none;">
                            <thead><tr>
                                <th><?php esc_html_e( 'Date', 'rentafleet' ); ?></th>
                                <th><?php esc_html_e( 'Amount', 'rentafleet' ); ?></th>
                                <th><?php esc_html_e( 'Method', 'rentafleet' ); ?></th>
                                <th><?php esc_html_e( 'Transaction ID', 'rentafleet' ); ?></th>
                                <th><?php esc_html_e( 'Status', 'rentafleet' ); ?></th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ( $payments as $pmt ) : ?>
                                <tr>
                                    <td><?php echo esc_html( RAF_Helpers::format_datetime( $pmt->created_at ) ); ?></td>
                                    <td><?php echo RAF_Helpers::format_price( $pmt->amount ); ?></td>
                                    <td><?php echo esc_html( $pmt->payment_method ? ucfirst( $pmt->payment_method ) : ( $pmt->gateway ?? '—' ) ); ?></td>
                                    <td><code><?php echo esc_html( $pmt->transaction_id ? $pmt->transaction_id : '—' ); ?></code></td>
                                    <td><?php echo RAF_Helpers::status_badge( $pmt->status ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p style="padding:12px 16px;" class="raf-muted"><?php esc_html_e( 'No payments recorded.', 'rentafleet' ); ?></p>
                    <?php endif; ?>
                </div>

                <?php /* Status Log (Timeline) */ ?>
                <div class="raf-panel">
                    <h2><?php esc_html_e( 'Activity Log', 'rentafleet' ); ?></h2>
                    <?php if ( $log ) : ?>
                        <div class="raf-timeline">
                            <?php foreach ( $log as $entry ) : ?>
                                <div class="raf-timeline-item">
                                    <div class="raf-timeline-dot"></div>
                                    <div class="raf-timeline-content">
                                        <span class="raf-timeline-date"><?php echo esc_html( RAF_Helpers::format_datetime( $entry->created_at ) ); ?></span>
                                        <?php if ( $entry->old_status && $entry->new_status ) : ?>
                                            <span>
                                                <?php echo RAF_Helpers::status_badge( $entry->old_status ); ?>
                                                → <?php echo RAF_Helpers::status_badge( $entry->new_status ); ?>
                                            </span>
                                        <?php elseif ( $entry->new_status ) : ?>
                                            <span><?php echo RAF_Helpers::status_badge( $entry->new_status ); ?></span>
                                        <?php endif; ?>
                                        <?php if ( $entry->note ) : ?>
                                            <p class="raf-timeline-note"><?php echo esc_html( $entry->note ); ?></p>
                                        <?php endif; ?>
                                        <?php if ( $entry->changed_by ) : ?>
                                            <small class="raf-muted"><?php $u = get_userdata( $entry->changed_by ); echo $u ? esc_html( $u->display_name ) : '#' . $entry->changed_by; ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?>
                        <p style="padding:12px 16px;" class="raf-muted"><?php esc_html_e( 'No activity recorded.', 'rentafleet' ); ?></p>
                    <?php endif; ?>
                </div>

            </div><?php /* /raf-view-main */ ?>

            <?php /* ── Right sidebar ── */ ?>
            <div class="raf-view-sidebar">

                <?php /* Status Change */ ?>
                <div class="raf-panel">
                    <h2><?php esc_html_e( 'Change Status', 'rentafleet' ); ?></h2>
                    <form method="post" style="padding:12px 16px;">
                        <input type="hidden" name="raf_action" value="change_status">
                        <input type="hidden" name="page" value="raf-bookings">
                        <input type="hidden" name="booking_id" value="<?php echo esc_attr( $id ); ?>">
                        <?php RAF_Admin::nonce_field( 'change_status' ); ?>
                        <div class="raf-field" style="margin-bottom:8px;">
                            <select name="new_status" class="widefat">
                                <?php foreach ( $statuses as $sk => $sl ) : ?>
                                    <option value="<?php echo esc_attr( $sk ); ?>" <?php selected( $booking->status, $sk ); ?>><?php echo esc_html( $sl ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="raf-field" style="margin-bottom:8px;">
                            <textarea name="status_note" rows="2" class="widefat" placeholder="<?php esc_attr_e( 'Note (optional)…', 'rentafleet' ); ?>"></textarea>
                        </div>
                        <button type="submit" class="button button-primary widefat"><?php esc_html_e( 'Update Status', 'rentafleet' ); ?></button>
                    </form>
                </div>

                <?php /* Record Payment */ ?>
                <div class="raf-panel">
                    <h2><?php esc_html_e( 'Record Payment', 'rentafleet' ); ?></h2>
                    <form method="post" style="padding:12px 16px;">
                        <input type="hidden" name="raf_action" value="record_payment">
                        <input type="hidden" name="page" value="raf-bookings">
                        <input type="hidden" name="booking_id" value="<?php echo esc_attr( $id ); ?>">
                        <?php RAF_Admin::nonce_field( 'record_payment' ); ?>
                        <div class="raf-field" style="margin-bottom:8px;">
                            <label><?php esc_html_e( 'Amount', 'rentafleet' ); ?></label>
                            <input type="number" name="payment_amount" step="0.01" min="0.01" value="<?php echo esc_attr( $balance > 0 ? number_format( $balance, 2, '.', '' ) : '' ); ?>" class="widefat" required>
                        </div>
                        <div class="raf-field" style="margin-bottom:8px;">
                            <label><?php esc_html_e( 'Method', 'rentafleet' ); ?></label>
                            <select name="payment_method" class="widefat">
                                <option value="cash"><?php esc_html_e( 'Cash', 'rentafleet' ); ?></option>
                                <option value="card"><?php esc_html_e( 'Credit/Debit Card', 'rentafleet' ); ?></option>
                                <option value="bank_transfer"><?php esc_html_e( 'Bank Transfer', 'rentafleet' ); ?></option>
                                <option value="check"><?php esc_html_e( 'Check', 'rentafleet' ); ?></option>
                                <option value="other"><?php esc_html_e( 'Other', 'rentafleet' ); ?></option>
                            </select>
                        </div>
                        <div class="raf-field" style="margin-bottom:8px;">
                            <label><?php esc_html_e( 'Transaction ID', 'rentafleet' ); ?></label>
                            <input type="text" name="payment_transaction_id" class="widefat" placeholder="<?php esc_attr_e( 'Optional', 'rentafleet' ); ?>">
                        </div>
                        <div class="raf-field" style="margin-bottom:8px;">
                            <label><?php esc_html_e( 'Notes', 'rentafleet' ); ?></label>
                            <input type="text" name="payment_notes" class="widefat" placeholder="<?php esc_attr_e( 'Optional', 'rentafleet' ); ?>">
                        </div>
                        <button type="submit" class="button button-primary widefat"><?php esc_html_e( 'Record Payment', 'rentafleet' ); ?></button>
                    </form>
                </div>

                <?php /* Customer Info */ ?>
                <div class="raf-panel">
                    <h2><?php esc_html_e( 'Customer', 'rentafleet' ); ?></h2>
                    <?php if ( $customer ) : ?>
                        <div style="padding:12px 16px;">
                            <strong><?php echo esc_html( RAF_Customer::get_full_name( $customer ) ); ?></strong>
                            <br><a href="mailto:<?php echo esc_attr( $customer->email ); ?>"><?php echo esc_html( $customer->email ); ?></a>
                            <?php if ( $customer->phone ) : ?>
                                <br><a href="tel:<?php echo esc_attr( $customer->phone ); ?>"><?php echo esc_html( $customer->phone ); ?></a>
                            <?php endif; ?>
                            <?php if ( $customer->city || $customer->country ) : ?>
                                <br><span class="raf-muted"><?php echo esc_html( implode( ', ', array_filter( array( $customer->city, $customer->state, $customer->country ) ) ) ); ?></span>
                            <?php endif; ?>
                            <?php if ( $customer->license_number ) : ?>
                                <hr style="margin:8px 0;">
                                <small><?php esc_html_e( 'License:', 'rentafleet' ); ?> <?php echo esc_html( $customer->license_number ); ?></small>
                                <?php if ( $customer->license_expiry ) : ?>
                                    <br><small><?php esc_html_e( 'Expires:', 'rentafleet' ); ?> <?php echo esc_html( RAF_Helpers::format_date( $customer->license_expiry ) ); ?></small>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if ( $customer->total_bookings ) : ?>
                                <hr style="margin:8px 0;">
                                <small><?php echo esc_html( $customer->total_bookings ); ?> <?php esc_html_e( 'bookings', 'rentafleet' ); ?> · <?php echo RAF_Helpers::format_price( $customer->total_spent ); ?> <?php esc_html_e( 'total', 'rentafleet' ); ?></small>
                            <?php endif; ?>
                        </div>
                    <?php else : ?>
                        <p style="padding:12px 16px;" class="raf-muted"><?php esc_html_e( 'No customer data.', 'rentafleet' ); ?></p>
                    <?php endif; ?>
                </div>

                <?php /* Add Admin Note */ ?>
                <div class="raf-panel">
                    <h2><?php esc_html_e( 'Add Note', 'rentafleet' ); ?></h2>
                    <form method="post" style="padding:12px 16px;">
                        <input type="hidden" name="raf_action" value="add_note">
                        <input type="hidden" name="page" value="raf-bookings">
                        <input type="hidden" name="booking_id" value="<?php echo esc_attr( $id ); ?>">
                        <?php RAF_Admin::nonce_field( 'add_note' ); ?>
                        <textarea name="admin_note" rows="3" class="widefat" placeholder="<?php esc_attr_e( 'Add an internal note…', 'rentafleet' ); ?>" required></textarea>
                        <br><button type="submit" class="button" style="margin-top:6px;"><?php esc_html_e( 'Add Note', 'rentafleet' ); ?></button>
                    </form>
                </div>

            </div><?php /* /raf-view-sidebar */ ?>

        </div><?php /* /raf-view-grid */ ?>
        <?php
    }

    /* ================================================================
     *  ADD / EDIT FORM
     * ============================================================= */

    private static function render_form() {
        global $wpdb;

        $id      = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
        $booking = $id ? RAF_Booking_Model::get( $id ) : null;
        $is_edit = ! empty( $booking );
        $title   = $is_edit ? __( 'Edit Booking', 'rentafleet' ) : __( 'New Manual Booking', 'rentafleet' );

        $defaults = (object) array(
            'id'                  => 0,
            'booking_number'      => '',
            'customer_id'         => 0,
            'vehicle_id'          => 0,
            'pickup_location_id'  => 0,
            'dropoff_location_id' => 0,
            'pickup_date'         => '',
            'dropoff_date'        => '',
            'actual_pickup_date'  => '',
            'actual_dropoff_date' => '',
            'rental_days'         => 1,
            'rental_hours'        => 0,
            'base_price'          => '0.00',
            'extras_total'        => '0.00',
            'insurance_total'     => '0.00',
            'location_fees'       => '0.00',
            'tax_amount'          => '0.00',
            'discount_amount'     => '0.00',
            'coupon_code'         => '',
            'total_price'         => '0.00',
            'deposit_amount'      => '0.00',
            'deposit_paid'        => 0,
            'amount_paid'         => '0.00',
            'payment_status'      => 'pending',
            'payment_method'      => '',
            'status'              => 'pending',
            'driver_name'         => '',
            'driver_age'          => '',
            'driver_license'      => '',
            'additional_drivers'  => '',
            'mileage_start'       => '',
            'mileage_end'         => '',
            'fuel_level_start'    => '',
            'fuel_level_end'      => '',
            'notes'               => '',
            'admin_notes'         => '',
            'source'              => 'admin',
        );
        $b = $is_edit ? $booking : $defaults;

        // Dropdowns
        $vehicles   = $wpdb->get_results( "SELECT id, name FROM " . RAF_Helpers::table( 'vehicles' ) . " WHERE status = 'active' ORDER BY name" );
        $locations  = RAF_Admin::get_locations_dropdown();
        $customers  = $wpdb->get_results( "SELECT id, first_name, last_name, email FROM " . RAF_Helpers::table( 'customers' ) . " ORDER BY first_name, last_name LIMIT 500" );
        $statuses   = RAF_Helpers::get_booking_statuses();
        $pay_stats  = RAF_Helpers::get_payment_statuses();
        $fuel_levels = array( '' => '—', 'full' => __( 'Full', 'rentafleet' ), '3/4' => '3/4', '1/2' => '1/2', '1/4' => '1/4', 'empty' => __( 'Empty', 'rentafleet' ) );

        // Format dates for datetime-local input
        $pickup_dt  = $b->pickup_date  ? date( 'Y-m-d\TH:i', strtotime( $b->pickup_date ) ) : '';
        $dropoff_dt = $b->dropoff_date ? date( 'Y-m-d\TH:i', strtotime( $b->dropoff_date ) ) : '';
        $actual_pickup_dt  = $b->actual_pickup_date  ? date( 'Y-m-d\TH:i', strtotime( $b->actual_pickup_date ) ) : '';
        $actual_dropoff_dt = $b->actual_dropoff_date ? date( 'Y-m-d\TH:i', strtotime( $b->actual_dropoff_date ) ) : '';

        ?>
        <h1><?php echo esc_html( $title ); ?></h1>
        <?php RAF_Admin::back_link( 'raf-bookings', __( '← Back to Bookings', 'rentafleet' ) ); ?>
        <?php if ( $is_edit ) : ?>
            <a href="<?php echo esc_url( RAF_Admin::admin_url( 'raf-bookings', array( 'action' => 'view', 'id' => $id ) ) ); ?>" class="page-title-action"><?php esc_html_e( 'View Booking', 'rentafleet' ); ?></a>
        <?php endif; ?>

        <form method="post" id="raf-booking-form" class="raf-form">
            <input type="hidden" name="raf_action" value="save_booking">
            <input type="hidden" name="page" value="raf-bookings">
            <input type="hidden" name="booking_id" value="<?php echo esc_attr( $b->id ); ?>">
            <?php RAF_Admin::nonce_field( 'save_booking' ); ?>

            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">

                    <div id="post-body-content">

                        <?php /* Core Booking Info */ ?>
                        <div class="raf-panel">
                            <h2><?php esc_html_e( 'Booking Details', 'rentafleet' ); ?></h2>
                            <table class="form-table">
                                <?php if ( ! $is_edit ) : ?>
                                <tr>
                                    <th><label><?php esc_html_e( 'Customer', 'rentafleet' ); ?></label></th>
                                    <td>
                                        <select name="customer_id" class="regular-text">
                                            <option value="0"><?php esc_html_e( '— Select Customer —', 'rentafleet' ); ?></option>
                                            <?php foreach ( $customers as $cust ) : ?>
                                                <option value="<?php echo esc_attr( $cust->id ); ?>" <?php selected( $b->customer_id, $cust->id ); ?>><?php echo esc_html( $cust->first_name . ' ' . $cust->last_name . ' (' . $cust->email . ')' ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <p class="description"><?php esc_html_e( 'Or create a customer in the Customers page first.', 'rentafleet' ); ?></p>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <th><label><?php esc_html_e( 'Vehicle *', 'rentafleet' ); ?></label></th>
                                    <td>
                                        <select name="vehicle_id" required>
                                            <option value=""><?php esc_html_e( '— Select Vehicle —', 'rentafleet' ); ?></option>
                                            <?php foreach ( $vehicles as $veh ) : ?>
                                                <option value="<?php echo esc_attr( $veh->id ); ?>" <?php selected( $b->vehicle_id, $veh->id ); ?>><?php echo esc_html( $veh->name ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label><?php esc_html_e( 'Pickup Location *', 'rentafleet' ); ?></label></th>
                                    <td>
                                        <select name="pickup_location_id" required>
                                            <option value=""><?php esc_html_e( '— Select —', 'rentafleet' ); ?></option>
                                            <?php foreach ( $locations as $lid => $lname ) : ?>
                                                <option value="<?php echo esc_attr( $lid ); ?>" <?php selected( $b->pickup_location_id, $lid ); ?>><?php echo esc_html( $lname ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label><?php esc_html_e( 'Return Location *', 'rentafleet' ); ?></label></th>
                                    <td>
                                        <select name="dropoff_location_id" required>
                                            <option value=""><?php esc_html_e( '— Select —', 'rentafleet' ); ?></option>
                                            <?php foreach ( $locations as $lid => $lname ) : ?>
                                                <option value="<?php echo esc_attr( $lid ); ?>" <?php selected( $b->dropoff_location_id, $lid ); ?>><?php echo esc_html( $lname ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label><?php esc_html_e( 'Pickup Date/Time *', 'rentafleet' ); ?></label></th>
                                    <td><input type="datetime-local" name="pickup_date" value="<?php echo esc_attr( $pickup_dt ); ?>" required></td>
                                </tr>
                                <tr>
                                    <th><label><?php esc_html_e( 'Return Date/Time *', 'rentafleet' ); ?></label></th>
                                    <td><input type="datetime-local" name="dropoff_date" value="<?php echo esc_attr( $dropoff_dt ); ?>" required></td>
                                </tr>
                                <?php if ( $is_edit ) : ?>
                                <tr>
                                    <th><label><?php esc_html_e( 'Actual Pickup', 'rentafleet' ); ?></label></th>
                                    <td><input type="datetime-local" name="actual_pickup_date" value="<?php echo esc_attr( $actual_pickup_dt ); ?>"></td>
                                </tr>
                                <tr>
                                    <th><label><?php esc_html_e( 'Actual Return', 'rentafleet' ); ?></label></th>
                                    <td><input type="datetime-local" name="actual_dropoff_date" value="<?php echo esc_attr( $actual_dropoff_dt ); ?>"></td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>

                        <?php /* Pricing (admin can override) */ ?>
                        <div class="raf-panel">
                            <h2><?php esc_html_e( 'Pricing', 'rentafleet' ); ?></h2>
                            <p class="description" style="padding:8px 16px 0;"><?php esc_html_e( 'For manual bookings, enter pricing directly. For edits, these override the calculated values.', 'rentafleet' ); ?></p>
                            <table class="form-table">
                                <tr>
                                    <th><label><?php esc_html_e( 'Rental Days', 'rentafleet' ); ?></label></th>
                                    <td><input type="number" name="rental_days" value="<?php echo esc_attr( $b->rental_days ); ?>" min="0" class="small-text"></td>
                                </tr>
                                <tr>
                                    <th><label><?php esc_html_e( 'Rental Hours', 'rentafleet' ); ?></label></th>
                                    <td><input type="number" name="rental_hours" value="<?php echo esc_attr( $b->rental_hours ); ?>" min="0" class="small-text"></td>
                                </tr>
                                <tr>
                                    <th><label><?php esc_html_e( 'Base Price', 'rentafleet' ); ?></label></th>
                                    <td><input type="number" name="base_price" value="<?php echo esc_attr( $b->base_price ); ?>" step="0.01" min="0" class="small-text"></td>
                                </tr>
                                <tr>
                                    <th><label><?php esc_html_e( 'Extras Total', 'rentafleet' ); ?></label></th>
                                    <td><input type="number" name="extras_total" value="<?php echo esc_attr( $b->extras_total ); ?>" step="0.01" min="0" class="small-text"></td>
                                </tr>
                                <tr>
                                    <th><label><?php esc_html_e( 'Insurance Total', 'rentafleet' ); ?></label></th>
                                    <td><input type="number" name="insurance_total" value="<?php echo esc_attr( $b->insurance_total ); ?>" step="0.01" min="0" class="small-text"></td>
                                </tr>
                                <tr>
                                    <th><label><?php esc_html_e( 'Location Fees', 'rentafleet' ); ?></label></th>
                                    <td><input type="number" name="location_fees" value="<?php echo esc_attr( $b->location_fees ); ?>" step="0.01" min="0" class="small-text"></td>
                                </tr>
                                <tr>
                                    <th><label><?php esc_html_e( 'Tax', 'rentafleet' ); ?></label></th>
                                    <td><input type="number" name="tax_amount" value="<?php echo esc_attr( $b->tax_amount ); ?>" step="0.01" min="0" class="small-text"></td>
                                </tr>
                                <tr>
                                    <th><label><?php esc_html_e( 'Discount', 'rentafleet' ); ?></label></th>
                                    <td>
                                        <input type="number" name="discount_amount" value="<?php echo esc_attr( $b->discount_amount ); ?>" step="0.01" min="0" class="small-text">
                                        <input type="text" name="coupon_code" value="<?php echo esc_attr( $b->coupon_code ); ?>" placeholder="<?php esc_attr_e( 'Coupon code', 'rentafleet' ); ?>" class="regular-text" style="width:120px;">
                                    </td>
                                </tr>
                                <tr>
                                    <th><label><?php esc_html_e( 'Total Price *', 'rentafleet' ); ?></label></th>
                                    <td><input type="number" name="total_price" value="<?php echo esc_attr( $b->total_price ); ?>" step="0.01" min="0" class="small-text" required></td>
                                </tr>
                                <tr>
                                    <th><label><?php esc_html_e( 'Deposit', 'rentafleet' ); ?></label></th>
                                    <td>
                                        <input type="number" name="deposit_amount" value="<?php echo esc_attr( $b->deposit_amount ); ?>" step="0.01" min="0" class="small-text">
                                        <label><input type="checkbox" name="deposit_paid" value="1" <?php checked( $b->deposit_paid ); ?>> <?php esc_html_e( 'Deposit paid', 'rentafleet' ); ?></label>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <?php /* Driver Info */ ?>
                        <div class="raf-panel">
                            <h2><?php esc_html_e( 'Driver Information', 'rentafleet' ); ?></h2>
                            <table class="form-table">
                                <tr>
                                    <th><label><?php esc_html_e( 'Driver Name', 'rentafleet' ); ?></label></th>
                                    <td><input type="text" name="driver_name" value="<?php echo esc_attr( $b->driver_name ); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th><label><?php esc_html_e( 'Driver Age', 'rentafleet' ); ?></label></th>
                                    <td><input type="number" name="driver_age" value="<?php echo esc_attr( $b->driver_age ); ?>" min="16" max="99" class="small-text"></td>
                                </tr>
                                <tr>
                                    <th><label><?php esc_html_e( 'License Number', 'rentafleet' ); ?></label></th>
                                    <td><input type="text" name="driver_license" value="<?php echo esc_attr( $b->driver_license ); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th><label><?php esc_html_e( 'Additional Drivers', 'rentafleet' ); ?></label></th>
                                    <td><textarea name="additional_drivers" rows="3" class="large-text"><?php echo esc_textarea( $b->additional_drivers ); ?></textarea></td>
                                </tr>
                            </table>
                        </div>

                        <?php /* Mileage & Fuel */ ?>
                        <div class="raf-panel">
                            <h2><?php esc_html_e( 'Mileage & Fuel', 'rentafleet' ); ?></h2>
                            <table class="form-table">
                                <tr>
                                    <th><label><?php esc_html_e( 'Mileage Start (km)', 'rentafleet' ); ?></label></th>
                                    <td><input type="number" name="mileage_start" value="<?php echo esc_attr( $b->mileage_start ); ?>" min="0" class="small-text"></td>
                                </tr>
                                <tr>
                                    <th><label><?php esc_html_e( 'Mileage End (km)', 'rentafleet' ); ?></label></th>
                                    <td><input type="number" name="mileage_end" value="<?php echo esc_attr( $b->mileage_end ); ?>" min="0" class="small-text"></td>
                                </tr>
                                <tr>
                                    <th><label><?php esc_html_e( 'Fuel Level Start', 'rentafleet' ); ?></label></th>
                                    <td>
                                        <select name="fuel_level_start">
                                            <?php foreach ( $fuel_levels as $fk => $fl ) : ?>
                                                <option value="<?php echo esc_attr( $fk ); ?>" <?php selected( $b->fuel_level_start, $fk ); ?>><?php echo esc_html( $fl ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label><?php esc_html_e( 'Fuel Level End', 'rentafleet' ); ?></label></th>
                                    <td>
                                        <select name="fuel_level_end">
                                            <?php foreach ( $fuel_levels as $fk => $fl ) : ?>
                                                <option value="<?php echo esc_attr( $fk ); ?>" <?php selected( $b->fuel_level_end, $fk ); ?>><?php echo esc_html( $fl ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <?php /* Notes */ ?>
                        <div class="raf-panel">
                            <h2><?php esc_html_e( 'Notes', 'rentafleet' ); ?></h2>
                            <table class="form-table">
                                <tr>
                                    <th><label><?php esc_html_e( 'Customer Notes', 'rentafleet' ); ?></label></th>
                                    <td><textarea name="notes" rows="3" class="large-text"><?php echo esc_textarea( $b->notes ); ?></textarea></td>
                                </tr>
                                <tr>
                                    <th><label><?php esc_html_e( 'Admin Notes', 'rentafleet' ); ?></label></th>
                                    <td><textarea name="admin_notes" rows="3" class="large-text"><?php echo esc_textarea( $b->admin_notes ); ?></textarea></td>
                                </tr>
                            </table>
                        </div>

                    </div>

                    <?php /* Sidebar */ ?>
                    <div id="postbox-container-1" class="postbox-container">
                        <div class="raf-panel raf-publish-box">
                            <h2><?php esc_html_e( 'Publish', 'rentafleet' ); ?></h2>
                            <div class="raf-publish-inside">
                                <div class="raf-field">
                                    <label><?php esc_html_e( 'Status', 'rentafleet' ); ?></label>
                                    <select name="status" class="widefat">
                                        <?php foreach ( $statuses as $sk => $sl ) : ?>
                                            <option value="<?php echo esc_attr( $sk ); ?>" <?php selected( $b->status, $sk ); ?>><?php echo esc_html( $sl ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="raf-field">
                                    <label><?php esc_html_e( 'Payment Status', 'rentafleet' ); ?></label>
                                    <select name="payment_status" class="widefat">
                                        <?php foreach ( $pay_stats as $pk => $pl ) : ?>
                                            <option value="<?php echo esc_attr( $pk ); ?>" <?php selected( $b->payment_status, $pk ); ?>><?php echo esc_html( $pl ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="raf-field">
                                    <label><?php esc_html_e( 'Payment Method', 'rentafleet' ); ?></label>
                                    <input type="text" name="payment_method" value="<?php echo esc_attr( $b->payment_method ); ?>" class="widefat" placeholder="cash, card, etc.">
                                </div>
                                <div class="raf-publish-actions">
                                    <?php if ( $is_edit ) : ?>
                                        <a href="<?php echo esc_url( RAF_Admin::admin_url( 'raf-bookings', array( 'action' => 'view', 'id' => $id ) ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'rentafleet' ); ?></a>
                                    <?php else : ?>
                                        <span></span>
                                    <?php endif; ?>
                                    <button type="submit" class="button button-primary button-large">
                                        <?php echo $is_edit ? esc_html__( 'Update Booking', 'rentafleet' ) : esc_html__( 'Create Booking', 'rentafleet' ); ?>
                                    </button>
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
     *  FORM HANDLERS
     * ============================================================= */

    private static function handle_save_booking() {
        if ( ! RAF_Admin::verify_nonce( 'save_booking' ) ) {
            RAF_Admin::add_notice( __( 'Security check failed.', 'rentafleet' ), 'error' );
            return;
        }

        $id = absint( $_POST['booking_id'] );
        $is_edit = $id > 0;

        // Parse datetime-local inputs
        $pickup_date  = ! empty( $_POST['pickup_date'] )  ? date( 'Y-m-d H:i:s', strtotime( $_POST['pickup_date'] ) ) : '';
        $dropoff_date = ! empty( $_POST['dropoff_date'] ) ? date( 'Y-m-d H:i:s', strtotime( $_POST['dropoff_date'] ) ) : '';

        $data = array(
            'vehicle_id'          => absint( $_POST['vehicle_id'] ),
            'pickup_location_id'  => absint( $_POST['pickup_location_id'] ),
            'dropoff_location_id' => absint( $_POST['dropoff_location_id'] ),
            'pickup_date'         => $pickup_date,
            'dropoff_date'        => $dropoff_date,
            'rental_days'         => absint( $_POST['rental_days'] ),
            'rental_hours'        => absint( $_POST['rental_hours'] ),
            'base_price'          => floatval( $_POST['base_price'] ),
            'extras_total'        => floatval( $_POST['extras_total'] ),
            'insurance_total'     => floatval( $_POST['insurance_total'] ),
            'location_fees'       => floatval( $_POST['location_fees'] ),
            'tax_amount'          => floatval( $_POST['tax_amount'] ),
            'discount_amount'     => floatval( $_POST['discount_amount'] ),
            'coupon_code'         => sanitize_text_field( $_POST['coupon_code'] ),
            'total_price'         => floatval( $_POST['total_price'] ),
            'deposit_amount'      => floatval( $_POST['deposit_amount'] ),
            'deposit_paid'        => isset( $_POST['deposit_paid'] ) ? 1 : 0,
            'payment_status'      => sanitize_text_field( $_POST['payment_status'] ),
            'payment_method'      => sanitize_text_field( $_POST['payment_method'] ),
            'status'              => sanitize_text_field( $_POST['status'] ),
            'driver_name'         => sanitize_text_field( $_POST['driver_name'] ),
            'driver_age'          => ! empty( $_POST['driver_age'] ) ? absint( $_POST['driver_age'] ) : null,
            'driver_license'      => sanitize_text_field( $_POST['driver_license'] ),
            'additional_drivers'  => sanitize_textarea_field( $_POST['additional_drivers'] ),
            'mileage_start'       => ! empty( $_POST['mileage_start'] ) ? absint( $_POST['mileage_start'] ) : null,
            'mileage_end'         => ! empty( $_POST['mileage_end'] ) ? absint( $_POST['mileage_end'] ) : null,
            'fuel_level_start'    => sanitize_text_field( $_POST['fuel_level_start'] ),
            'fuel_level_end'      => sanitize_text_field( $_POST['fuel_level_end'] ),
            'notes'               => sanitize_textarea_field( $_POST['notes'] ),
            'admin_notes'         => sanitize_textarea_field( $_POST['admin_notes'] ),
        );

        // Actual dates (edit only)
        if ( $is_edit ) {
            $data['actual_pickup_date']  = ! empty( $_POST['actual_pickup_date'] )  ? date( 'Y-m-d H:i:s', strtotime( $_POST['actual_pickup_date'] ) ) : null;
            $data['actual_dropoff_date'] = ! empty( $_POST['actual_dropoff_date'] ) ? date( 'Y-m-d H:i:s', strtotime( $_POST['actual_dropoff_date'] ) ) : null;
        }

        // Validate
        if ( ! $data['vehicle_id'] || ! $data['pickup_location_id'] || ! $data['dropoff_location_id'] ) {
            RAF_Admin::add_notice( __( 'Vehicle and locations are required.', 'rentafleet' ), 'error' );
            return;
        }
        if ( ! $pickup_date || ! $dropoff_date ) {
            RAF_Admin::add_notice( __( 'Pickup and return dates are required.', 'rentafleet' ), 'error' );
            return;
        }

        if ( $is_edit ) {
            // Track status change
            $old = RAF_Booking_Model::get( $id );
            RAF_Booking_Model::update( $id, $data );
            if ( $old && $old->status !== $data['status'] ) {
                RAF_Booking_Model::log_status_change( $id, $old->status, $data['status'], 'Status changed via edit form' );
                do_action( 'raf_booking_status_changed', $id, $old->status, $data['status'] );
            }
            $booking_id = $id;
            RAF_Admin::add_notice( __( 'Booking updated.', 'rentafleet' ) );
        } else {
            // New booking
            $data['customer_id'] = absint( $_POST['customer_id'] );
            $data['source']      = 'admin';
            $data['currency']    = get_option( 'raf_currency', 'USD' );
            $booking_id = RAF_Booking_Model::create( $data );

            if ( ! $booking_id ) {
                RAF_Admin::add_notice( __( 'Failed to create booking.', 'rentafleet' ), 'error' );
                return;
            }

            // Update customer stats
            if ( $data['customer_id'] ) {
                RAF_Customer::update_stats( $data['customer_id'] );
            }

            RAF_Admin::add_notice( __( 'Booking created successfully.', 'rentafleet' ) );
        }

        RAF_Admin::redirect( 'raf-bookings', array( 'action' => 'view', 'id' => $booking_id ) );
    }

    private static function handle_change_status() {
        if ( ! RAF_Admin::verify_nonce( 'change_status' ) ) {
            RAF_Admin::add_notice( __( 'Security check failed.', 'rentafleet' ), 'error' );
            return;
        }

        $id     = absint( $_POST['booking_id'] );
        $status = sanitize_text_field( $_POST['new_status'] );
        $note   = sanitize_textarea_field( $_POST['status_note'] );

        $booking = RAF_Booking_Model::get( $id );
        if ( ! $booking ) {
            RAF_Admin::add_notice( __( 'Booking not found.', 'rentafleet' ), 'error' );
            return;
        }

        if ( $booking->status === $status ) {
            // Just add a note if status unchanged
            if ( $note ) {
                RAF_Booking_Model::log_status_change( $id, $status, $status, $note );
            }
            RAF_Admin::add_notice( __( 'Note added.', 'rentafleet' ) );
        } else {
            RAF_Booking_Model::update_status( $id, $status, $note ? $note : 'Status changed by admin' );
            RAF_Admin::add_notice( sprintf( __( 'Booking status changed to %s.', 'rentafleet' ), $status ) );
        }

        RAF_Admin::redirect( 'raf-bookings', array( 'action' => 'view', 'id' => $id ) );
    }

    private static function handle_record_payment() {
        if ( ! RAF_Admin::verify_nonce( 'record_payment' ) ) {
            RAF_Admin::add_notice( __( 'Security check failed.', 'rentafleet' ), 'error' );
            return;
        }

        $booking_id = absint( $_POST['booking_id'] );
        $amount     = floatval( $_POST['payment_amount'] );
        $method     = sanitize_text_field( $_POST['payment_method'] );
        $txn_id     = sanitize_text_field( $_POST['payment_transaction_id'] );
        $notes      = sanitize_text_field( $_POST['payment_notes'] );

        if ( $amount <= 0 ) {
            RAF_Admin::add_notice( __( 'Payment amount must be greater than zero.', 'rentafleet' ), 'error' );
            RAF_Admin::redirect( 'raf-bookings', array( 'action' => 'view', 'id' => $booking_id ) );
        }

        global $wpdb;
        $wpdb->insert( RAF_Helpers::table( 'payments' ), array(
            'booking_id'     => $booking_id,
            'amount'         => $amount,
            'currency'       => get_option( 'raf_currency', 'USD' ),
            'payment_method' => $method,
            'transaction_id' => $txn_id ? $txn_id : 'manual_' . time(),
            'status'         => 'completed',
            'notes'          => $notes,
            'created_at'     => current_time( 'mysql' ),
        ) );

        // Update booking amount_paid and payment_status
        $total_paid = RAF_Payment_Manager::get_total_paid( $booking_id );
        $booking    = RAF_Booking_Model::get( $booking_id );
        $update     = array( 'amount_paid' => $total_paid );

        if ( $booking ) {
            if ( $total_paid >= $booking->total_price ) {
                $update['payment_status'] = 'paid';
            } elseif ( $total_paid > 0 ) {
                $update['payment_status'] = 'partial';
            }
        }
        RAF_Booking_Model::update( $booking_id, $update );

        // Log it
        RAF_Booking_Model::log_status_change( $booking_id, '', '', sprintf( 'Payment of %s recorded (%s)', RAF_Helpers::format_price( $amount ), $method ) );

        RAF_Admin::add_notice( sprintf( __( 'Payment of %s recorded successfully.', 'rentafleet' ), RAF_Helpers::format_price( $amount ) ) );
        RAF_Admin::redirect( 'raf-bookings', array( 'action' => 'view', 'id' => $booking_id ) );
    }

    private static function handle_add_note() {
        if ( ! RAF_Admin::verify_nonce( 'add_note' ) ) {
            RAF_Admin::add_notice( __( 'Security check failed.', 'rentafleet' ), 'error' );
            return;
        }

        $booking_id = absint( $_POST['booking_id'] );
        $note       = sanitize_textarea_field( $_POST['admin_note'] );

        if ( empty( $note ) ) {
            RAF_Admin::add_notice( __( 'Note cannot be empty.', 'rentafleet' ), 'error' );
            RAF_Admin::redirect( 'raf-bookings', array( 'action' => 'view', 'id' => $booking_id ) );
        }

        // Append to admin_notes field
        $booking = RAF_Booking_Model::get( $booking_id );
        if ( $booking ) {
            $current = $booking->admin_notes ? $booking->admin_notes : '';
            $timestamp = current_time( 'Y-m-d H:i' );
            $user = wp_get_current_user();
            $new_note = '[' . $timestamp . ' — ' . $user->display_name . '] ' . $note;
            $updated = $current ? $current . "\n\n" . $new_note : $new_note;
            RAF_Booking_Model::update( $booking_id, array( 'admin_notes' => $updated ) );
        }

        // Also log it
        RAF_Booking_Model::log_status_change( $booking_id, '', '', 'Admin note: ' . $note );

        RAF_Admin::add_notice( __( 'Note added.', 'rentafleet' ) );
        RAF_Admin::redirect( 'raf-bookings', array( 'action' => 'view', 'id' => $booking_id ) );
    }

    private static function handle_delete_booking() {
        $id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
        if ( ! $id || ! wp_verify_nonce( $_GET['_wpnonce'], 'raf_delete_booking_' . $id ) ) {
            RAF_Admin::add_notice( __( 'Invalid request.', 'rentafleet' ), 'error' );
            return;
        }

        RAF_Booking_Model::delete( $id );
        RAF_Admin::add_notice( __( 'Booking deleted.', 'rentafleet' ) );
        RAF_Admin::redirect( 'raf-bookings' );
    }

    private static function handle_bulk_action() {
        if ( ! RAF_Admin::verify_nonce( 'bulk_bookings' ) ) {
            RAF_Admin::add_notice( __( 'Security check failed.', 'rentafleet' ), 'error' );
            return;
        }

        $action = ! empty( $_POST['bulk_action'] ) ? sanitize_text_field( $_POST['bulk_action'] ) : '';
        $ids    = isset( $_POST['booking_ids'] ) && is_array( $_POST['booking_ids'] ) ? array_map( 'absint', $_POST['booking_ids'] ) : array();

        if ( empty( $action ) || empty( $ids ) ) return;

        $count = 0;
        switch ( $action ) {
            case 'confirm':
                foreach ( $ids as $bid ) {
                    RAF_Booking_Model::update_status( $bid, 'confirmed', 'Bulk confirmed by admin' );
                    $count++;
                }
                RAF_Admin::add_notice( sprintf( __( '%d booking(s) confirmed.', 'rentafleet' ), $count ) );
                break;
            case 'cancel':
                foreach ( $ids as $bid ) {
                    RAF_Booking_Model::update_status( $bid, 'cancelled', 'Bulk cancelled by admin' );
                    $count++;
                }
                RAF_Admin::add_notice( sprintf( __( '%d booking(s) cancelled.', 'rentafleet' ), $count ) );
                break;
            case 'delete':
                foreach ( $ids as $bid ) {
                    RAF_Booking_Model::delete( $bid );
                    $count++;
                }
                RAF_Admin::add_notice( sprintf( __( '%d booking(s) deleted.', 'rentafleet' ), $count ) );
                break;
        }

        RAF_Admin::redirect( 'raf-bookings' );
    }

    /* ================================================================
     *  PRIVATE HELPERS
     * ============================================================= */

    private static function get_status_counts() {
        global $wpdb;
        $results = $wpdb->get_results( "SELECT status, COUNT(*) as cnt FROM " . RAF_Helpers::table( 'bookings' ) . " GROUP BY status" );
        $counts  = array();
        foreach ( $results as $row ) $counts[ $row->status ] = (int) $row->cnt;
        return $counts;
    }

    private static function sort_url( $column, $current_orderby, $current_order ) {
        $new_order = ( $current_orderby === $column && $current_order === 'ASC' ) ? 'DESC' : 'ASC';
        return add_query_arg( array( 'orderby' => $column, 'order' => $new_order ) );
    }

    private static function sort_indicator( $column, $current_orderby, $current_order ) {
        if ( $current_orderby !== $column ) return '';
        return ( $current_order === 'ASC' ) ? ' ▲' : ' ▼';
    }

    private static function render_pagination( $total_items, $total_pages, $current ) {
        if ( $total_pages <= 1 ) {
            echo '<div class="tablenav-pages one-page"><span class="displaying-num">' . sprintf( _n( '%s item', '%s items', $total_items, 'rentafleet' ), number_format_i18n( $total_items ) ) . '</span></div>';
            return;
        }
        $output = '<div class="tablenav-pages">';
        $output .= '<span class="displaying-num">' . sprintf( _n( '%s item', '%s items', $total_items, 'rentafleet' ), number_format_i18n( $total_items ) ) . '</span>';
        $output .= '<span class="pagination-links">';
        if ( $current > 1 ) {
            $output .= sprintf( '<a class="first-page button" href="%s">«</a> ', esc_url( add_query_arg( 'paged', 1 ) ) );
            $output .= sprintf( '<a class="prev-page button" href="%s">‹</a> ', esc_url( add_query_arg( 'paged', $current - 1 ) ) );
        } else {
            $output .= '<span class="tablenav-pages-navspan button disabled">«</span> <span class="tablenav-pages-navspan button disabled">‹</span> ';
        }
        $output .= sprintf( '<span class="paging-input">%d of <span class="total-pages">%d</span></span>', $current, $total_pages );
        if ( $current < $total_pages ) {
            $output .= sprintf( ' <a class="next-page button" href="%s">›</a>', esc_url( add_query_arg( 'paged', $current + 1 ) ) );
            $output .= sprintf( ' <a class="last-page button" href="%s">»</a>', esc_url( add_query_arg( 'paged', $total_pages ) ) );
        } else {
            $output .= ' <span class="tablenav-pages-navspan button disabled">›</span> <span class="tablenav-pages-navspan button disabled">»</span>';
        }
        $output .= '</span></div>';
        echo $output;
    }
}
