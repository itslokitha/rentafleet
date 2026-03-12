<?php
/**
 * RentAFleet Admin — Customers Management
 *
 * Full CRUD for customers with:
 *  - List table with search, pagination, status filter
 *  - View customer detail with booking history
 *  - Add/Edit form with all customer fields
 *  - Delete with confirmation
 *
 * @package RentAFleet
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RAF_Admin_Customers {

    /* ================================================================
     *  CONSTANTS
     * ============================================================= */

    const PER_PAGE = 20;

    /* ================================================================
     *  ROUTER
     * ============================================================= */

    public static function render() {
        $action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';

        echo '<div class="wrap raf-customers-wrap">';

        switch ( $action ) {
            case 'view':
                self::render_view();
                break;
            case 'add':
            case 'edit':
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
            case 'save_customer':
                self::handle_save_customer();
                break;
            case 'delete_customer':
                self::handle_delete_customer();
                break;
        }
    }

    /* ================================================================
     *  LIST VIEW
     * ============================================================= */

    private static function render_list() {
        $search = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
        $status = isset( $_GET['customer_status'] ) ? sanitize_text_field( $_GET['customer_status'] ) : '';
        $paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
        $offset = ( $paged - 1 ) * self::PER_PAGE;

        // Status counts.
        $all_count    = RAF_Customer::count();
        $active_count = RAF_Customer::count( array( 'status' => 'active' ) );
        $inactive_count = $all_count - $active_count;

        $args = array(
            'status'  => $status,
            'search'  => $search,
            'orderby' => 'created_at',
            'order'   => 'DESC',
            'limit'   => self::PER_PAGE,
            'offset'  => $offset,
        );
        $customers   = RAF_Customer::get_all( $args );
        $total_items = RAF_Customer::count( $status ? array( 'status' => $status ) : array() );
        $total_pages = ceil( $total_items / self::PER_PAGE );

        ?>
        <h1 class="wp-heading-inline"><?php esc_html_e( 'Customers', 'rentafleet' ); ?></h1>
        <a href="<?php echo esc_url( RAF_Admin::admin_url( 'raf-customers', array( 'action' => 'add' ) ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add Customer', 'rentafleet' ); ?></a>
        <hr class="wp-header-end">

        <ul class="subsubsub">
            <?php
            $class_all  = ! $status ? ' class="current"' : '';
            $class_act  = $status === 'active' ? ' class="current"' : '';
            $class_inac = $status === 'inactive' ? ' class="current"' : '';
            printf( '<li><a href="%s"%s>%s <span class="count">(%d)</span></a> | </li>', esc_url( RAF_Admin::admin_url( 'raf-customers' ) ), $class_all, esc_html__( 'All', 'rentafleet' ), $all_count );
            printf( '<li><a href="%s"%s>%s <span class="count">(%d)</span></a> | </li>', esc_url( RAF_Admin::admin_url( 'raf-customers', array( 'customer_status' => 'active' ) ) ), $class_act, esc_html__( 'Active', 'rentafleet' ), $active_count );
            printf( '<li><a href="%s"%s>%s <span class="count">(%d)</span></a></li>', esc_url( RAF_Admin::admin_url( 'raf-customers', array( 'customer_status' => 'inactive' ) ) ), $class_inac, esc_html__( 'Inactive', 'rentafleet' ), $inactive_count );
            ?>
        </ul>

        <form method="get" class="raf-filter-form">
            <input type="hidden" name="page" value="raf-customers">
            <?php if ( $status ) : ?><input type="hidden" name="customer_status" value="<?php echo esc_attr( $status ); ?>"><?php endif; ?>
            <div class="raf-filter-bar">
                <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search customers…', 'rentafleet' ); ?>">
                <button type="submit" class="button"><?php esc_html_e( 'Search', 'rentafleet' ); ?></button>
                <?php if ( $search ) : ?>
                    <a href="<?php echo esc_url( RAF_Admin::admin_url( 'raf-customers', $status ? array( 'customer_status' => $status ) : array() ) ); ?>" class="button"><?php esc_html_e( 'Reset', 'rentafleet' ); ?></a>
                <?php endif; ?>
            </div>
        </form>

        <div class="tablenav top">
            <?php self::render_pagination( $total_items, $total_pages, $paged ); ?>
        </div>

        <table class="wp-list-table widefat fixed striped raf-customers-table">
            <thead>
                <tr>
                    <th class="column-primary"><?php esc_html_e( 'Name', 'rentafleet' ); ?></th>
                    <th><?php esc_html_e( 'Email', 'rentafleet' ); ?></th>
                    <th><?php esc_html_e( 'Phone', 'rentafleet' ); ?></th>
                    <th><?php esc_html_e( 'Total Bookings', 'rentafleet' ); ?></th>
                    <th><?php esc_html_e( 'Total Spent', 'rentafleet' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'rentafleet' ); ?></th>
                    <th><?php esc_html_e( 'Created', 'rentafleet' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'rentafleet' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if ( empty( $customers ) ) : ?>
                <tr><td colspan="8"><?php esc_html_e( 'No customers found.', 'rentafleet' ); ?></td></tr>
            <?php else : foreach ( $customers as $customer ) :
                $view_url = RAF_Admin::admin_url( 'raf-customers', array( 'action' => 'view', 'id' => $customer->id ) );
                $edit_url = RAF_Admin::admin_url( 'raf-customers', array( 'action' => 'edit', 'id' => $customer->id ) );
                $del_url  = wp_nonce_url( RAF_Admin::admin_url( 'raf-customers', array( 'raf_action' => 'delete_customer', 'id' => $customer->id ) ), 'raf_delete_customer_' . $customer->id );
                ?>
                <tr>
                    <td>
                        <strong><a href="<?php echo esc_url( $view_url ); ?>"><?php echo esc_html( RAF_Customer::get_full_name( $customer ) ); ?></a></strong>
                        <div class="row-actions">
                            <span class="view"><a href="<?php echo esc_url( $view_url ); ?>"><?php esc_html_e( 'View', 'rentafleet' ); ?></a> | </span>
                            <span class="edit"><a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'rentafleet' ); ?></a> | </span>
                            <span class="trash"><a href="<?php echo esc_url( $del_url ); ?>" onclick="return confirm('<?php esc_attr_e( 'Delete this customer?', 'rentafleet' ); ?>');"><?php esc_html_e( 'Delete', 'rentafleet' ); ?></a></span>
                        </div>
                    </td>
                    <td><?php echo esc_html( $customer->email ); ?></td>
                    <td><?php echo $customer->phone ? esc_html( $customer->phone ) : '&mdash;'; ?></td>
                    <td><?php echo esc_html( $customer->total_bookings ); ?></td>
                    <td><?php echo RAF_Helpers::format_price( $customer->total_spent ); ?></td>
                    <td><?php echo RAF_Helpers::status_badge( $customer->status ); ?></td>
                    <td><?php echo RAF_Helpers::format_date( $customer->created_at ); ?></td>
                    <td class="raf-actions">
                        <a href="<?php echo esc_url( $view_url ); ?>" class="button button-small"><?php esc_html_e( 'View', 'rentafleet' ); ?></a>
                        <a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small"><?php esc_html_e( 'Edit', 'rentafleet' ); ?></a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>

        <div class="tablenav bottom">
            <?php self::render_pagination( $total_items, $total_pages, $paged ); ?>
        </div>
        <?php
    }

    /* ================================================================
     *  VIEW — Customer Detail
     * ============================================================= */

    private static function render_view() {
        $id       = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
        $customer = $id ? RAF_Customer::get( $id ) : null;

        if ( ! $customer ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Customer not found.', 'rentafleet' ) . '</p></div>';
            return;
        }

        // Refresh stats.
        RAF_Customer::update_stats( $customer->id );
        $customer = RAF_Customer::get( $id );

        // Get booking history.
        $bookings = RAF_Booking_Model::get_by_customer( $customer->id, 50 );

        $edit_url = RAF_Admin::admin_url( 'raf-customers', array( 'action' => 'edit', 'id' => $customer->id ) );

        ?>
        <h1><?php echo esc_html( RAF_Customer::get_full_name( $customer ) ); ?></h1>
        <?php RAF_Admin::back_link( 'raf-customers', __( '← Back to Customers', 'rentafleet' ) ); ?>

        <div id="poststuff">
            <div id="post-body" class="metabox-holder columns-2">
                <div id="post-body-content">

                    <!-- Customer Info -->
                    <div class="raf-panel">
                        <h2>
                            <?php esc_html_e( 'Customer Information', 'rentafleet' ); ?>
                            <a href="<?php echo esc_url( $edit_url ); ?>" class="page-title-action"><?php esc_html_e( 'Edit', 'rentafleet' ); ?></a>
                        </h2>
                        <table class="form-table">
                            <tr>
                                <th><?php esc_html_e( 'Name', 'rentafleet' ); ?></th>
                                <td><?php echo esc_html( RAF_Customer::get_full_name( $customer ) ); ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Email', 'rentafleet' ); ?></th>
                                <td><a href="mailto:<?php echo esc_attr( $customer->email ); ?>"><?php echo esc_html( $customer->email ); ?></a></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Phone', 'rentafleet' ); ?></th>
                                <td><?php echo $customer->phone ? esc_html( $customer->phone ) : '&mdash;'; ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Address', 'rentafleet' ); ?></th>
                                <td>
                                    <?php
                                    $addr = array_filter( array( $customer->address, $customer->city, $customer->state, $customer->country, $customer->zip ) );
                                    echo $addr ? esc_html( implode( ', ', $addr ) ) : '&mdash;';
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'License Number', 'rentafleet' ); ?></th>
                                <td><?php echo $customer->license_number ? esc_html( $customer->license_number ) : '&mdash;'; ?></td>
                            </tr>
                            <?php if ( ! empty( $customer->notes ) ) : ?>
                            <tr>
                                <th><?php esc_html_e( 'Notes', 'rentafleet' ); ?></th>
                                <td><?php echo esc_html( $customer->notes ); ?></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>

                    <!-- Booking History -->
                    <div class="raf-panel">
                        <h2><?php esc_html_e( 'Booking History', 'rentafleet' ); ?></h2>
                        <?php if ( empty( $bookings ) ) : ?>
                            <p style="padding:0 16px 16px;"><?php esc_html_e( 'No bookings found for this customer.', 'rentafleet' ); ?></p>
                        <?php else : ?>
                            <table class="wp-list-table widefat fixed striped raf-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e( 'Booking #', 'rentafleet' ); ?></th>
                                        <th><?php esc_html_e( 'Vehicle', 'rentafleet' ); ?></th>
                                        <th><?php esc_html_e( 'Pickup', 'rentafleet' ); ?></th>
                                        <th><?php esc_html_e( 'Return', 'rentafleet' ); ?></th>
                                        <th><?php esc_html_e( 'Total', 'rentafleet' ); ?></th>
                                        <th><?php esc_html_e( 'Status', 'rentafleet' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $bookings as $booking ) :
                                        $booking_url = RAF_Admin::admin_url( 'raf-bookings', array( 'action' => 'view', 'id' => $booking->id ) );
                                        $vehicle     = RAF_Vehicle::get( $booking->vehicle_id );
                                        ?>
                                        <tr>
                                            <td>
                                                <a href="<?php echo esc_url( $booking_url ); ?>">
                                                    <?php echo esc_html( $booking->booking_number ); ?>
                                                </a>
                                            </td>
                                            <td>
                                                <?php
                                                if ( $vehicle ) {
                                                    echo esc_html( $vehicle->make . ' ' . $vehicle->model . ' (' . $vehicle->year . ')' );
                                                } else {
                                                    echo '&mdash;';
                                                }
                                                ?>
                                            </td>
                                            <td><?php echo RAF_Helpers::format_date( $booking->pickup_date ); ?></td>
                                            <td><?php echo RAF_Helpers::format_date( $booking->dropoff_date ); ?></td>
                                            <td><?php echo RAF_Helpers::format_price( $booking->total_price ); ?></td>
                                            <td><?php echo RAF_Helpers::status_badge( $booking->status ); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>

                </div>

                <!-- Sidebar -->
                <div id="postbox-container-1" class="postbox-container">
                    <div class="raf-panel">
                        <h2><?php esc_html_e( 'Summary', 'rentafleet' ); ?></h2>
                        <table class="form-table" style="margin:0;">
                            <tr>
                                <th><?php esc_html_e( 'Status', 'rentafleet' ); ?></th>
                                <td><?php echo RAF_Helpers::status_badge( $customer->status ); ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Total Bookings', 'rentafleet' ); ?></th>
                                <td><strong><?php echo esc_html( $customer->total_bookings ); ?></strong></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Total Spent', 'rentafleet' ); ?></th>
                                <td><strong><?php echo RAF_Helpers::format_price( $customer->total_spent ); ?></strong></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Member Since', 'rentafleet' ); ?></th>
                                <td><?php echo RAF_Helpers::format_date( $customer->created_at ); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /* ================================================================
     *  ADD / EDIT FORM
     * ============================================================= */

    private static function render_form() {
        $id       = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
        $customer = $id ? RAF_Customer::get( $id ) : null;
        $is_edit  = ! empty( $customer );
        $title    = $is_edit ? __( 'Edit Customer', 'rentafleet' ) : __( 'Add Customer', 'rentafleet' );

        $defaults = (object) array(
            'id'             => 0,
            'first_name'     => '',
            'last_name'      => '',
            'email'          => '',
            'phone'          => '',
            'address'        => '',
            'city'           => '',
            'state'          => '',
            'country'        => '',
            'zip'            => '',
            'license_number' => '',
            'notes'          => '',
            'status'         => 'active',
        );
        $c = $is_edit ? $customer : $defaults;

        ?>
        <h1><?php echo esc_html( $title ); ?></h1>
        <?php RAF_Admin::back_link( 'raf-customers', __( '← Back to Customers', 'rentafleet' ) ); ?>

        <form method="post" id="raf-customer-form" class="raf-form">
            <input type="hidden" name="raf_action" value="save_customer">
            <input type="hidden" name="page" value="raf-customers">
            <input type="hidden" name="customer_id" value="<?php echo esc_attr( $c->id ); ?>">
            <?php RAF_Admin::nonce_field( 'save_customer' ); ?>

            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">
                    <div id="post-body-content">

                        <!-- Personal Info -->
                        <div class="raf-panel">
                            <h2><?php esc_html_e( 'Personal Information', 'rentafleet' ); ?></h2>
                            <table class="form-table">
                                <tr>
                                    <th><label><?php esc_html_e( 'First Name *', 'rentafleet' ); ?></label></th>
                                    <td><input type="text" name="first_name" value="<?php echo esc_attr( $c->first_name ); ?>" class="regular-text" required></td>
                                </tr>
                                <tr>
                                    <th><label><?php esc_html_e( 'Last Name *', 'rentafleet' ); ?></label></th>
                                    <td><input type="text" name="last_name" value="<?php echo esc_attr( $c->last_name ); ?>" class="regular-text" required></td>
                                </tr>
                                <tr>
                                    <th><label><?php esc_html_e( 'Email *', 'rentafleet' ); ?></label></th>
                                    <td><input type="email" name="email" value="<?php echo esc_attr( $c->email ); ?>" class="regular-text" required></td>
                                </tr>
                                <tr>
                                    <th><label><?php esc_html_e( 'Phone', 'rentafleet' ); ?></label></th>
                                    <td><input type="text" name="phone" value="<?php echo esc_attr( $c->phone ); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th><label><?php esc_html_e( 'License Number', 'rentafleet' ); ?></label></th>
                                    <td><input type="text" name="license_number" value="<?php echo esc_attr( $c->license_number ); ?>" class="regular-text"></td>
                                </tr>
                            </table>
                        </div>

                        <!-- Address -->
                        <div class="raf-panel">
                            <h2><?php esc_html_e( 'Address', 'rentafleet' ); ?></h2>
                            <table class="form-table">
                                <tr>
                                    <th><label><?php esc_html_e( 'Street Address', 'rentafleet' ); ?></label></th>
                                    <td><textarea name="address" rows="2" class="large-text"><?php echo esc_textarea( $c->address ); ?></textarea></td>
                                </tr>
                                <tr>
                                    <th><label><?php esc_html_e( 'City', 'rentafleet' ); ?></label></th>
                                    <td><input type="text" name="city" value="<?php echo esc_attr( $c->city ); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th><label><?php esc_html_e( 'State / Province', 'rentafleet' ); ?></label></th>
                                    <td><input type="text" name="state" value="<?php echo esc_attr( $c->state ); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th><label><?php esc_html_e( 'Country', 'rentafleet' ); ?></label></th>
                                    <td><input type="text" name="country" value="<?php echo esc_attr( $c->country ); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th><label><?php esc_html_e( 'Zip / Postal Code', 'rentafleet' ); ?></label></th>
                                    <td><input type="text" name="zip" value="<?php echo esc_attr( $c->zip ); ?>" class="small-text"></td>
                                </tr>
                            </table>
                        </div>

                        <!-- Notes -->
                        <div class="raf-panel">
                            <h2><?php esc_html_e( 'Notes', 'rentafleet' ); ?></h2>
                            <table class="form-table">
                                <tr>
                                    <th><label><?php esc_html_e( 'Internal Notes', 'rentafleet' ); ?></label></th>
                                    <td><textarea name="notes" rows="3" class="large-text"><?php echo esc_textarea( $c->notes ); ?></textarea></td>
                                </tr>
                            </table>
                        </div>

                    </div>

                    <!-- Sidebar -->
                    <div id="postbox-container-1" class="postbox-container">
                        <div class="raf-panel raf-publish-box">
                            <h2><?php esc_html_e( 'Publish', 'rentafleet' ); ?></h2>
                            <div class="raf-publish-inside">
                                <div class="raf-field">
                                    <label><?php esc_html_e( 'Status', 'rentafleet' ); ?></label>
                                    <select name="status" class="widefat">
                                        <option value="active" <?php selected( $c->status, 'active' ); ?>><?php esc_html_e( 'Active', 'rentafleet' ); ?></option>
                                        <option value="inactive" <?php selected( $c->status, 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'rentafleet' ); ?></option>
                                    </select>
                                </div>
                                <div class="raf-publish-actions">
                                    <?php if ( $is_edit ) : ?>
                                        <a href="<?php echo esc_url( wp_nonce_url( RAF_Admin::admin_url( 'raf-customers', array( 'raf_action' => 'delete_customer', 'id' => $id ) ), 'raf_delete_customer_' . $id ) ); ?>"
                                           class="raf-delete-link"
                                           onclick="return confirm('<?php esc_attr_e( 'Delete this customer permanently?', 'rentafleet' ); ?>');">
                                            <?php esc_html_e( 'Delete', 'rentafleet' ); ?>
                                        </a>
                                    <?php else : ?>
                                        <span></span>
                                    <?php endif; ?>
                                    <button type="submit" class="button button-primary button-large">
                                        <?php echo $is_edit ? esc_html__( 'Update Customer', 'rentafleet' ) : esc_html__( 'Add Customer', 'rentafleet' ); ?>
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
     *  SAVE HANDLER
     * ============================================================= */

    private static function handle_save_customer() {
        if ( ! RAF_Admin::verify_nonce( 'save_customer' ) ) {
            RAF_Admin::add_notice( __( 'Security check failed.', 'rentafleet' ), 'error' );
            return;
        }

        $id = absint( $_POST['customer_id'] );

        $data = array(
            'first_name'     => sanitize_text_field( $_POST['first_name'] ),
            'last_name'      => sanitize_text_field( $_POST['last_name'] ),
            'email'          => sanitize_email( $_POST['email'] ),
            'phone'          => sanitize_text_field( $_POST['phone'] ),
            'address'        => sanitize_textarea_field( $_POST['address'] ),
            'city'           => sanitize_text_field( $_POST['city'] ),
            'state'          => sanitize_text_field( $_POST['state'] ),
            'country'        => sanitize_text_field( $_POST['country'] ),
            'zip'            => sanitize_text_field( $_POST['zip'] ),
            'license_number' => sanitize_text_field( $_POST['license_number'] ),
            'notes'          => sanitize_textarea_field( $_POST['notes'] ),
            'status'         => sanitize_text_field( $_POST['status'] ),
        );

        // Validate required fields.
        if ( empty( $data['first_name'] ) || empty( $data['last_name'] ) ) {
            RAF_Admin::add_notice( __( 'First name and last name are required.', 'rentafleet' ), 'error' );
            return;
        }
        if ( empty( $data['email'] ) || ! is_email( $data['email'] ) ) {
            RAF_Admin::add_notice( __( 'A valid email address is required.', 'rentafleet' ), 'error' );
            return;
        }

        // Check for duplicate email.
        $existing = RAF_Customer::get_by_email( $data['email'] );
        if ( $existing && (int) $existing->id !== $id ) {
            RAF_Admin::add_notice( __( 'A customer with this email already exists.', 'rentafleet' ), 'error' );
            return;
        }

        if ( $id ) {
            RAF_Customer::update( $id, $data );
            RAF_Admin::add_notice( __( 'Customer updated.', 'rentafleet' ) );
        } else {
            $id = RAF_Customer::create( $data );
            if ( ! $id ) {
                RAF_Admin::add_notice( __( 'Failed to create customer.', 'rentafleet' ), 'error' );
                return;
            }
            RAF_Admin::add_notice( __( 'Customer created.', 'rentafleet' ) );
        }

        RAF_Admin::redirect( 'raf-customers', array( 'action' => 'edit', 'id' => $id ) );
    }

    /* ================================================================
     *  DELETE HANDLER
     * ============================================================= */

    private static function handle_delete_customer() {
        $id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
        if ( ! $id || ! wp_verify_nonce( $_GET['_wpnonce'], 'raf_delete_customer_' . $id ) ) {
            RAF_Admin::add_notice( __( 'Invalid request.', 'rentafleet' ), 'error' );
            return;
        }

        // Check for active bookings.
        global $wpdb;
        $active_bookings = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM " . RAF_Helpers::table( 'bookings' ) .
            " WHERE customer_id = %d AND status IN ('pending','confirmed','active')",
            $id
        ) );

        if ( $active_bookings > 0 ) {
            RAF_Admin::add_notice(
                sprintf( __( 'Cannot delete: %d active booking(s) belong to this customer.', 'rentafleet' ), $active_bookings ),
                'error'
            );
            RAF_Admin::redirect( 'raf-customers' );
            return;
        }

        RAF_Customer::delete( $id );
        RAF_Admin::add_notice( __( 'Customer deleted.', 'rentafleet' ) );
        RAF_Admin::redirect( 'raf-customers' );
    }

    /* ================================================================
     *  PAGINATION HELPER
     * ============================================================= */

    private static function render_pagination( $total_items, $total_pages, $current ) {
        if ( $total_pages <= 1 ) {
            echo '<div class="tablenav-pages one-page"><span class="displaying-num">' . sprintf( _n( '%s item', '%s items', $total_items, 'rentafleet' ), number_format_i18n( $total_items ) ) . '</span></div>';
            return;
        }
        $output = '<div class="tablenav-pages">';
        $output .= '<span class="displaying-num">' . sprintf( _n( '%s item', '%s items', $total_items, 'rentafleet' ), number_format_i18n( $total_items ) ) . '</span>';
        $output .= '<span class="pagination-links">';
        if ( $current > 1 ) {
            $output .= sprintf( '<a class="first-page button" href="%s">&laquo;</a> ', esc_url( add_query_arg( 'paged', 1 ) ) );
            $output .= sprintf( '<a class="prev-page button" href="%s">&lsaquo;</a> ', esc_url( add_query_arg( 'paged', $current - 1 ) ) );
        } else {
            $output .= '<span class="tablenav-pages-navspan button disabled">&laquo;</span> <span class="tablenav-pages-navspan button disabled">&lsaquo;</span> ';
        }
        $output .= sprintf( '<span class="paging-input">%d of <span class="total-pages">%d</span></span>', $current, $total_pages );
        if ( $current < $total_pages ) {
            $output .= sprintf( ' <a class="next-page button" href="%s">&rsaquo;</a>', esc_url( add_query_arg( 'paged', $current + 1 ) ) );
            $output .= sprintf( ' <a class="last-page button" href="%s">&raquo;</a>', esc_url( add_query_arg( 'paged', $total_pages ) ) );
        } else {
            $output .= ' <span class="tablenav-pages-navspan button disabled">&rsaquo;</span> <span class="tablenav-pages-navspan button disabled">&raquo;</span>';
        }
        $output .= '</span></div>';
        echo $output;
    }
}
