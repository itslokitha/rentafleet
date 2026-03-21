<?php
/**
 * RentAFleet Admin — Locations Management
 *
 * Full CRUD for rental locations with:
 *  • List table with search, status filter, vehicle counts
 *  • Add/Edit form: address, coordinates, contact, opening hours grid,
 *    pickup/dropoff flags & fees, featured image, notes
 *  • Delete with safety checks (active bookings)
 *
 * @package RentAFleet
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RAF_Admin_Locations {

    /* ================================================================
     *  ROUTER
     * ============================================================= */

    public static function render() {
        $action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';

        echo '<div class="wrap raf-locations-wrap">';

        switch ( $action ) {
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
            case 'save_location':
                self::handle_save_location();
                break;
            case 'delete_location':
                self::handle_delete_location();
                break;
        }
    }

    /* ================================================================
     *  LIST VIEW
     * ============================================================= */

    private static function render_list() {
        global $wpdb;

        $search = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
        $status = isset( $_GET['location_status'] ) ? sanitize_text_field( $_GET['location_status'] ) : '';

        // Status counts
        $all_count    = RAF_Location::count();
        $active_count = RAF_Location::count( array( 'status' => 'active' ) );
        $inactive_count = $all_count - $active_count;

        $args = array(
            'status'  => $status,
            'search'  => $search,
            'orderby' => 'sort_order',
            'order'   => 'ASC',
        );
        $locations = RAF_Location::get_all( $args );

        // Vehicle counts per location
        $vl_table = RAF_Helpers::table( 'vehicle_locations' );
        $veh_counts_raw = $wpdb->get_results( "SELECT location_id, COUNT(*) as cnt, SUM(units_at_location) as total_units FROM $vl_table GROUP BY location_id" );
        $veh_counts = array();
        foreach ( $veh_counts_raw as $vc ) {
            $veh_counts[ $vc->location_id ] = $vc;
        }

        ?>
        <h1 class="wp-heading-inline"><?php esc_html_e( 'Locations', 'rentafleet' ); ?></h1>
        <a href="<?php echo esc_url( RAF_Admin::admin_url( 'raf-locations', array( 'action' => 'add' ) ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add Location', 'rentafleet' ); ?></a>
        <hr class="wp-header-end">

        <ul class="subsubsub">
            <?php
            $class_all  = ! $status ? ' class="current"' : '';
            $class_act  = $status === 'active' ? ' class="current"' : '';
            $class_inac = $status === 'inactive' ? ' class="current"' : '';
            printf( '<li><a href="%s"%s>%s <span class="count">(%d)</span></a> | </li>', esc_url( RAF_Admin::admin_url( 'raf-locations' ) ), $class_all, esc_html__( 'All', 'rentafleet' ), $all_count );
            printf( '<li><a href="%s"%s>%s <span class="count">(%d)</span></a> | </li>', esc_url( RAF_Admin::admin_url( 'raf-locations', array( 'location_status' => 'active' ) ) ), $class_act, esc_html__( 'Active', 'rentafleet' ), $active_count );
            printf( '<li><a href="%s"%s>%s <span class="count">(%d)</span></a></li>', esc_url( RAF_Admin::admin_url( 'raf-locations', array( 'location_status' => 'inactive' ) ) ), $class_inac, esc_html__( 'Inactive', 'rentafleet' ), $inactive_count );
            ?>
        </ul>

        <form method="get" class="raf-filter-form">
            <input type="hidden" name="page" value="raf-locations">
            <?php if ( $status ) : ?><input type="hidden" name="location_status" value="<?php echo esc_attr( $status ); ?>"><?php endif; ?>
            <div class="raf-filter-bar">
                <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search locations…', 'rentafleet' ); ?>">
                <button type="submit" class="button"><?php esc_html_e( 'Search', 'rentafleet' ); ?></button>
                <?php if ( $search ) : ?>
                    <a href="<?php echo esc_url( RAF_Admin::admin_url( 'raf-locations', $status ? array( 'location_status' => $status ) : array() ) ); ?>" class="button"><?php esc_html_e( 'Reset', 'rentafleet' ); ?></a>
                <?php endif; ?>
            </div>
        </form>

        <table class="wp-list-table widefat fixed striped raf-locations-table">
            <thead>
                <tr>
                    <th class="column-image" style="width:60px;"></th>
                    <th class="column-primary"><?php esc_html_e( 'Name', 'rentafleet' ); ?></th>
                    <th><?php esc_html_e( 'Address', 'rentafleet' ); ?></th>
                    <th><?php esc_html_e( 'Contact', 'rentafleet' ); ?></th>
                    <th><?php esc_html_e( 'Pickup / Return', 'rentafleet' ); ?></th>
                    <th><?php esc_html_e( 'Fees', 'rentafleet' ); ?></th>
                    <th><?php esc_html_e( 'Vehicles', 'rentafleet' ); ?></th>
                    <th style="width:50px;"><?php esc_html_e( 'Order', 'rentafleet' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'rentafleet' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if ( empty( $locations ) ) : ?>
                <tr><td colspan="9"><?php esc_html_e( 'No locations found.', 'rentafleet' ); ?></td></tr>
            <?php else : foreach ( $locations as $loc ) :
                $edit_url = RAF_Admin::admin_url( 'raf-locations', array( 'action' => 'edit', 'id' => $loc->id ) );
                $del_url  = wp_nonce_url( RAF_Admin::admin_url( 'raf-locations', array( 'raf_action' => 'delete_location', 'id' => $loc->id ) ), 'raf_delete_location_' . $loc->id );
                $vc = isset( $veh_counts[ $loc->id ] ) ? $veh_counts[ $loc->id ] : null;
                $addr_parts = array_filter( array( $loc->address, $loc->city, $loc->state, $loc->country ) );
                ?>
                <tr>
                    <td>
                        <?php if ( $loc->image_id ) : ?>
                            <?php echo wp_get_attachment_image( $loc->image_id, array( 60, 40 ) ); ?>
                        <?php else : ?>
                            <span class="dashicons dashicons-location" style="font-size:30px;color:#ccc;width:60px;text-align:center;"></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $loc->name ); ?></a></strong>
                        <div class="row-actions">
                            <span class="edit"><a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'rentafleet' ); ?></a> | </span>
                            <span class="trash"><a href="<?php echo esc_url( $del_url ); ?>" onclick="return confirm('<?php esc_attr_e( 'Delete this location?', 'rentafleet' ); ?>');"><?php esc_html_e( 'Delete', 'rentafleet' ); ?></a></span>
                        </div>
                    </td>
                    <td>
                        <?php echo esc_html( implode( ', ', $addr_parts ) ); ?>
                        <?php if ( $loc->latitude && $loc->longitude ) : ?>
                            <br><small class="raf-muted"><?php echo esc_html( $loc->latitude . ', ' . $loc->longitude ); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ( $loc->phone ) : ?><span><?php echo esc_html( $loc->phone ); ?></span><br><?php endif; ?>
                        <?php if ( $loc->email ) : ?><small><?php echo esc_html( $loc->email ); ?></small><?php endif; ?>
                        <?php if ( ! $loc->phone && ! $loc->email ) echo '—'; ?>
                    </td>
                    <td>
                        <?php
                        $flags = array();
                        if ( $loc->is_pickup ) $flags[] = __( 'Pickup', 'rentafleet' );
                        if ( $loc->is_dropoff ) $flags[] = __( 'Return', 'rentafleet' );
                        echo $flags ? esc_html( implode( ' & ', $flags ) ) : '—';
                        ?>
                    </td>
                    <td>
                        <?php if ( $loc->pickup_fee > 0 || $loc->dropoff_fee > 0 ) : ?>
                            <?php if ( $loc->pickup_fee > 0 ) : ?><span><?php esc_html_e( 'P:', 'rentafleet' ); ?> <?php echo RAF_Helpers::format_price( $loc->pickup_fee ); ?></span><br><?php endif; ?>
                            <?php if ( $loc->dropoff_fee > 0 ) : ?><span><?php esc_html_e( 'R:', 'rentafleet' ); ?> <?php echo RAF_Helpers::format_price( $loc->dropoff_fee ); ?></span><?php endif; ?>
                        <?php else : ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ( $vc ) : ?>
                            <?php echo esc_html( $vc->cnt ); ?> <?php esc_html_e( 'types', 'rentafleet' ); ?>
                            <br><small class="raf-muted"><?php echo esc_html( $vc->total_units ); ?> <?php esc_html_e( 'units', 'rentafleet' ); ?></small>
                        <?php else : ?>
                            <span class="raf-muted">0</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html( $loc->sort_order ); ?></td>
                    <td><?php echo RAF_Helpers::status_badge( $loc->status ); ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        <?php
    }

    /* ================================================================
     *  ADD / EDIT FORM
     * ============================================================= */

    private static function render_form() {
        $id       = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
        $location = $id ? RAF_Location::get( $id ) : null;
        $is_edit  = ! empty( $location );
        $title    = $is_edit ? __( 'Edit Location', 'rentafleet' ) : __( 'Add Location', 'rentafleet' );

        $defaults = (object) array(
            'id' => 0, 'name' => '', 'slug' => '', 'address' => '', 'city' => '',
            'state' => '', 'country' => '', 'zip' => '', 'latitude' => '', 'longitude' => '',
            'phone' => '', 'email' => '', 'opening_hours' => '',
            'is_pickup' => 1, 'is_dropoff' => 1, 'pickup_fee' => '0.00', 'dropoff_fee' => '0.00',
            'image_id' => 0, 'notes' => '', 'status' => 'active', 'sort_order' => 0,
        );
        $l = $is_edit ? $location : $defaults;

        $hours = $is_edit ? RAF_Location::get_opening_hours( $location ) : array();
        $days  = array(
            'monday'    => __( 'Monday', 'rentafleet' ),
            'tuesday'   => __( 'Tuesday', 'rentafleet' ),
            'wednesday' => __( 'Wednesday', 'rentafleet' ),
            'thursday'  => __( 'Thursday', 'rentafleet' ),
            'friday'    => __( 'Friday', 'rentafleet' ),
            'saturday'  => __( 'Saturday', 'rentafleet' ),
            'sunday'    => __( 'Sunday', 'rentafleet' ),
        );

        $image_url = $l->image_id ? wp_get_attachment_image_url( $l->image_id, 'medium' ) : '';

        ?>
        <h1><?php echo esc_html( $title ); ?></h1>
        <?php RAF_Admin::back_link( 'raf-locations', __( '← Back to Locations', 'rentafleet' ) ); ?>

        <form method="post" id="raf-location-form" class="raf-form">
            <input type="hidden" name="raf_action" value="save_location">
            <input type="hidden" name="page" value="raf-locations">
            <input type="hidden" name="location_id" value="<?php echo esc_attr( $l->id ); ?>">
            <?php RAF_Admin::nonce_field( 'save_location' ); ?>

            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">
                    <div id="post-body-content">

                        <?php /* Basic Info */ ?>
                        <div class="raf-panel">
                            <h2><?php esc_html_e( 'Basic Information', 'rentafleet' ); ?></h2>
                            <table class="form-table">
                                <tr>
                                    <th><label><?php esc_html_e( 'Location Name *', 'rentafleet' ); ?></label></th>
                                    <td><input type="text" name="name" value="<?php echo esc_attr( $l->name ); ?>" class="regular-text" required></td>
                                </tr>
                                <tr>
                                    <th><label><?php esc_html_e( 'Slug', 'rentafleet' ); ?></label></th>
                                    <td><input type="text" name="slug" value="<?php echo esc_attr( $l->slug ); ?>" class="regular-text raf-slug-field" placeholder="<?php esc_attr_e( 'auto-generated', 'rentafleet' ); ?>"></td>
                                </tr>
                            </table>
                        </div>

                        <?php /* Address & Coordinates */ ?>
                        <div class="raf-panel">
                            <h2><?php esc_html_e( 'Address & Coordinates', 'rentafleet' ); ?></h2>
                            <table class="form-table">
                                <tr>
                                    <th><label><?php esc_html_e( 'Street Address', 'rentafleet' ); ?></label></th>
                                    <td><textarea name="address" rows="2" class="large-text"><?php echo esc_textarea( $l->address ); ?></textarea></td>
                                </tr>
                                <tr>
                                    <th><label><?php esc_html_e( 'City', 'rentafleet' ); ?></label></th>
                                    <td><input type="text" name="city" value="<?php echo esc_attr( $l->city ); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th><label><?php esc_html_e( 'State / Province', 'rentafleet' ); ?></label></th>
                                    <td><input type="text" name="state" value="<?php echo esc_attr( $l->state ); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th><label><?php esc_html_e( 'Country', 'rentafleet' ); ?></label></th>
                                    <td><input type="text" name="country" value="<?php echo esc_attr( $l->country ); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th><label><?php esc_html_e( 'Zip / Postal Code', 'rentafleet' ); ?></label></th>
                                    <td><input type="text" name="zip" value="<?php echo esc_attr( $l->zip ); ?>" class="small-text"></td>
                                </tr>
                                <tr>
                                    <th><label><?php esc_html_e( 'Latitude', 'rentafleet' ); ?></label></th>
                                    <td><input type="text" name="latitude" value="<?php echo esc_attr( $l->latitude ); ?>" class="regular-text" placeholder="e.g. 25.2048"></td>
                                </tr>
                                <tr>
                                    <th><label><?php esc_html_e( 'Longitude', 'rentafleet' ); ?></label></th>
                                    <td><input type="text" name="longitude" value="<?php echo esc_attr( $l->longitude ); ?>" class="regular-text" placeholder="e.g. 55.2708"></td>
                                </tr>
                            </table>
                        </div>

                        <?php /* Contact */ ?>
                        <div class="raf-panel">
                            <h2><?php esc_html_e( 'Contact', 'rentafleet' ); ?></h2>
                            <table class="form-table">
                                <tr>
                                    <th><label><?php esc_html_e( 'Phone', 'rentafleet' ); ?></label></th>
                                    <td><input type="text" name="phone" value="<?php echo esc_attr( $l->phone ); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th><label><?php esc_html_e( 'Email', 'rentafleet' ); ?></label></th>
                                    <td><input type="email" name="email" value="<?php echo esc_attr( $l->email ); ?>" class="regular-text"></td>
                                </tr>
                            </table>
                        </div>

                        <?php /* Opening Hours */ ?>
                        <div class="raf-panel">
                            <h2><?php esc_html_e( 'Opening Hours', 'rentafleet' ); ?></h2>
                            <table class="raf-hours-table">
                                <thead>
                                    <tr>
                                        <th style="width:30px;"></th>
                                        <th><?php esc_html_e( 'Day', 'rentafleet' ); ?></th>
                                        <th><?php esc_html_e( 'Open', 'rentafleet' ); ?></th>
                                        <th><?php esc_html_e( 'Close', 'rentafleet' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $days as $key => $label ) :
                                        $is_open = isset( $hours[ $key ]['open'] ) && $hours[ $key ]['open'] !== '';
                                        $open_time  = isset( $hours[ $key ]['open'] ) ? $hours[ $key ]['open'] : '08:00';
                                        $close_time = isset( $hours[ $key ]['close'] ) ? $hours[ $key ]['close'] : '18:00';
                                        ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox"
                                                       name="hours_enabled[<?php echo esc_attr( $key ); ?>]"
                                                       value="1"
                                                       class="raf-hours-toggle"
                                                       <?php checked( $is_open || ! $is_edit ); ?>>
                                            </td>
                                            <td><strong><?php echo esc_html( $label ); ?></strong></td>
                                            <td>
                                                <input type="time"
                                                       name="hours_open[<?php echo esc_attr( $key ); ?>]"
                                                       value="<?php echo esc_attr( $open_time ); ?>"
                                                       class="raf-hours-input">
                                            </td>
                                            <td>
                                                <input type="time"
                                                       name="hours_close[<?php echo esc_attr( $key ); ?>]"
                                                       value="<?php echo esc_attr( $close_time ); ?>"
                                                       class="raf-hours-input">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <p class="description" style="padding:8px 16px;"><?php esc_html_e( 'Uncheck a day to mark it as closed.', 'rentafleet' ); ?></p>
                        </div>

                        <?php /* Pickup / Dropoff Settings */ ?>
                        <div class="raf-panel">
                            <h2><?php esc_html_e( 'Pickup & Return Settings', 'rentafleet' ); ?></h2>
                            <table class="form-table">
                                <tr>
                                    <th><label><?php esc_html_e( 'Available For', 'rentafleet' ); ?></label></th>
                                    <td>
                                        <label style="margin-right:20px;"><input type="checkbox" name="is_pickup" value="1" <?php checked( $l->is_pickup ); ?>> <?php esc_html_e( 'Pickup', 'rentafleet' ); ?></label>
                                        <label><input type="checkbox" name="is_dropoff" value="1" <?php checked( $l->is_dropoff ); ?>> <?php esc_html_e( 'Return / Drop-off', 'rentafleet' ); ?></label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label><?php esc_html_e( 'Pickup Fee', 'rentafleet' ); ?></label></th>
                                    <td>
                                        <input type="number" name="pickup_fee" value="<?php echo esc_attr( $l->pickup_fee ); ?>" step="0.01" min="0" class="small-text">
                                        <p class="description"><?php esc_html_e( 'Additional fee charged when customer picks up from this location.', 'rentafleet' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label><?php esc_html_e( 'Return Fee', 'rentafleet' ); ?></label></th>
                                    <td>
                                        <input type="number" name="dropoff_fee" value="<?php echo esc_attr( $l->dropoff_fee ); ?>" step="0.01" min="0" class="small-text">
                                        <p class="description"><?php esc_html_e( 'Additional fee charged when customer returns to this location.', 'rentafleet' ); ?></p>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <?php /* Notes */ ?>
                        <div class="raf-panel">
                            <h2><?php esc_html_e( 'Notes', 'rentafleet' ); ?></h2>
                            <table class="form-table">
                                <tr>
                                    <th><label><?php esc_html_e( 'Internal Notes', 'rentafleet' ); ?></label></th>
                                    <td><textarea name="notes" rows="3" class="large-text"><?php echo esc_textarea( $l->notes ); ?></textarea></td>
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
                                        <option value="active" <?php selected( $l->status, 'active' ); ?>><?php esc_html_e( 'Active', 'rentafleet' ); ?></option>
                                        <option value="inactive" <?php selected( $l->status, 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'rentafleet' ); ?></option>
                                    </select>
                                </div>
                                <div class="raf-field">
                                    <label><?php esc_html_e( 'Sort Order', 'rentafleet' ); ?></label>
                                    <input type="number" name="sort_order" value="<?php echo esc_attr( $l->sort_order ); ?>" min="0" class="widefat">
                                </div>
                                <div class="raf-publish-actions">
                                    <?php if ( $is_edit ) : ?>
                                        <a href="<?php echo esc_url( wp_nonce_url( RAF_Admin::admin_url( 'raf-locations', array( 'raf_action' => 'delete_location', 'id' => $id ) ), 'raf_delete_location_' . $id ) ); ?>"
                                           class="raf-delete-link"
                                           onclick="return confirm('<?php esc_attr_e( 'Delete this location permanently?', 'rentafleet' ); ?>');">
                                            <?php esc_html_e( 'Delete', 'rentafleet' ); ?>
                                        </a>
                                    <?php else : ?>
                                        <span></span>
                                    <?php endif; ?>
                                    <button type="submit" class="button button-primary button-large">
                                        <?php echo $is_edit ? esc_html__( 'Update Location', 'rentafleet' ) : esc_html__( 'Add Location', 'rentafleet' ); ?>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <?php /* Featured Image */ ?>
                        <div class="raf-panel">
                            <h2><?php esc_html_e( 'Location Image', 'rentafleet' ); ?></h2>
                            <div class="raf-image-panel" style="padding:12px 16px;">
                                <input type="hidden" name="image_id" id="raf-location-image-id" value="<?php echo esc_attr( $l->image_id ); ?>">
                                <div id="raf-location-image-preview" style="margin-bottom:8px;">
                                    <?php if ( $image_url ) : ?>
                                        <img src="<?php echo esc_url( $image_url ); ?>" style="max-width:100%;height:auto;">
                                    <?php endif; ?>
                                </div>
                                <button type="button" class="button" id="raf-location-image-upload"><?php esc_html_e( 'Select Image', 'rentafleet' ); ?></button>
                                <button type="button" class="button raf-remove-btn" id="raf-location-image-remove" style="<?php echo $l->image_id ? '' : 'display:none;'; ?>"><?php esc_html_e( 'Remove', 'rentafleet' ); ?></button>
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

    private static function handle_save_location() {
        if ( ! RAF_Admin::verify_nonce( 'save_location' ) ) {
            RAF_Admin::add_notice( __( 'Security check failed.', 'rentafleet' ), 'error' );
            return;
        }

        $id = absint( $_POST['location_id'] );

        $data = array(
            'name'        => sanitize_text_field( $_POST['name'] ),
            'slug'        => ! empty( $_POST['slug'] ) ? sanitize_title( $_POST['slug'] ) : sanitize_title( $_POST['name'] ),
            'address'     => sanitize_textarea_field( $_POST['address'] ),
            'city'        => sanitize_text_field( $_POST['city'] ),
            'state'       => sanitize_text_field( $_POST['state'] ),
            'country'     => sanitize_text_field( $_POST['country'] ),
            'zip'         => sanitize_text_field( $_POST['zip'] ),
            'latitude'    => ! empty( $_POST['latitude'] ) ? floatval( $_POST['latitude'] ) : null,
            'longitude'   => ! empty( $_POST['longitude'] ) ? floatval( $_POST['longitude'] ) : null,
            'phone'       => sanitize_text_field( $_POST['phone'] ),
            'email'       => sanitize_email( $_POST['email'] ),
            'is_pickup'   => isset( $_POST['is_pickup'] ) ? 1 : 0,
            'is_dropoff'  => isset( $_POST['is_dropoff'] ) ? 1 : 0,
            'pickup_fee'  => floatval( $_POST['pickup_fee'] ),
            'dropoff_fee' => floatval( $_POST['dropoff_fee'] ),
            'image_id'    => absint( $_POST['image_id'] ),
            'notes'       => sanitize_textarea_field( $_POST['notes'] ),
            'status'      => sanitize_text_field( $_POST['status'] ),
            'sort_order'  => absint( $_POST['sort_order'] ),
        );

        // Build opening hours array
        $opening_hours = array();
        $all_days = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );
        foreach ( $all_days as $day ) {
            if ( ! empty( $_POST['hours_enabled'][ $day ] ) ) {
                $opening_hours[ $day ] = array(
                    'open'  => sanitize_text_field( $_POST['hours_open'][ $day ] ),
                    'close' => sanitize_text_field( $_POST['hours_close'][ $day ] ),
                );
            }
            // Unchecked days are simply not in the array (= closed)
        }
        $data['opening_hours'] = $opening_hours;

        // Validate
        if ( empty( $data['name'] ) ) {
            RAF_Admin::add_notice( __( 'Location name is required.', 'rentafleet' ), 'error' );
            return;
        }

        if ( $id ) {
            RAF_Location::update( $id, $data );
            RAF_Admin::add_notice( __( 'Location updated.', 'rentafleet' ) );
        } else {
            $id = RAF_Location::create( $data );
            if ( ! $id ) {
                RAF_Admin::add_notice( __( 'Failed to create location.', 'rentafleet' ), 'error' );
                return;
            }
            RAF_Admin::add_notice( __( 'Location created.', 'rentafleet' ) );
        }

        RAF_Admin::redirect( 'raf-locations', array( 'action' => 'edit', 'id' => $id ) );
    }

    /* ================================================================
     *  DELETE HANDLER
     * ============================================================= */

    private static function handle_delete_location() {
        $id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
        if ( ! $id || ! wp_verify_nonce( $_GET['_wpnonce'], 'raf_delete_location_' . $id ) ) {
            RAF_Admin::add_notice( __( 'Invalid request.', 'rentafleet' ), 'error' );
            return;
        }

        // Check for bookings using this location
        global $wpdb;
        $active_bookings = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM " . RAF_Helpers::table( 'bookings' ) .
            " WHERE (pickup_location_id = %d OR dropoff_location_id = %d) AND status IN ('pending','confirmed','active')",
            $id, $id
        ) );

        if ( $active_bookings > 0 ) {
            RAF_Admin::add_notice(
                sprintf( __( 'Cannot delete: %d active booking(s) use this location.', 'rentafleet' ), $active_bookings ),
                'error'
            );
            RAF_Admin::redirect( 'raf-locations' );
        }

        // Remove vehicle-location associations
        $wpdb->delete( RAF_Helpers::table( 'vehicle_locations' ), array( 'location_id' => $id ) );

        RAF_Location::delete( $id );
        RAF_Admin::add_notice( __( 'Location deleted.', 'rentafleet' ) );
        RAF_Admin::redirect( 'raf-locations' );
    }
}
