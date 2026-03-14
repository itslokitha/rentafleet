<?php
/**
 * RentAFleet Admin — Vehicles Management
 *
 * Handles every aspect of vehicle administration:
 *  • List view with search, filter by category/status, pagination, bulk actions
 *  • Add / Edit form with full field set, media gallery, location assignment
 *  • Vehicle Category CRUD (inline tab)
 *  • Delete with safety checks
 *
 * @package RentAFleet
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RAF_Admin_Vehicles {

    /* ================================================================
     *  CONSTANTS
     * ============================================================= */

    const PER_PAGE = 20;

    /* ================================================================
     *  ROUTER — called by RAF_Admin::register_menus callback
     * ============================================================= */

    /**
     * Main render entry-point. Inspects query args and dispatches
     * to the correct view method.
     */
    public static function render() {
        $action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';
        $tab    = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'vehicles';

        echo '<div class="wrap raf-vehicles-wrap">';

        // Tab navigation
        self::render_tabs( $tab );

        switch ( $tab ) {
            case 'categories':
                self::render_categories_page();
                break;

            default: // 'vehicles'
                switch ( $action ) {
                    case 'add':
                    case 'edit':
                        self::render_form();
                        break;
                    default:
                        self::render_list();
                        break;
                }
                break;
        }

        echo '</div>';
    }

    /* ================================================================
     *  TAB NAVIGATION
     * ============================================================= */

    private static function render_tabs( $active ) {
        $tabs = array(
            'vehicles'   => __( 'Vehicles', 'rentafleet' ),
            'categories' => __( 'Categories', 'rentafleet' ),
        );
        echo '<nav class="nav-tab-wrapper raf-tab-wrapper">';
        foreach ( $tabs as $slug => $label ) {
            $url   = RAF_Admin::admin_url( 'raf-vehicles', array( 'tab' => $slug ) );
            $class = ( $slug === $active ) ? 'nav-tab nav-tab-active' : 'nav-tab';
            printf( '<a href="%s" class="%s">%s</a>', esc_url( $url ), esc_attr( $class ), esc_html( $label ) );
        }
        echo '</nav>';
    }

    /* ================================================================
     *  ACTION HANDLER — processes form submissions before render
     * ============================================================= */

    /**
     * Handle a submitted action. Called from RAF_Admin::handle_actions().
     *
     * @param string $action The raf_action value.
     */
    public static function handle_action( $action ) {
        switch ( $action ) {

            /* ── Vehicle save ────────────────────────── */
            case 'save_vehicle':
                self::handle_save_vehicle();
                break;

            /* ── Vehicle delete ──────────────────────── */
            case 'delete_vehicle':
                self::handle_delete_vehicle();
                break;

            /* ── Bulk actions ────────────────────────── */
            case 'bulk_vehicles':
                self::handle_bulk_action();
                break;

            /* ── Category save ───────────────────────── */
            case 'save_category':
                self::handle_save_category();
                break;

            /* ── Category delete ─────────────────────── */
            case 'delete_category':
                self::handle_delete_category();
                break;
        }
    }

    /* ================================================================
     *  VEHICLES — LIST VIEW
     * ============================================================= */

    /**
     * Render the main vehicles list with search, filters, pagination.
     */
    private static function render_list() {
        global $wpdb;

        // ── Query parameters ─────────────────────
        $search      = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
        $status      = isset( $_GET['vehicle_status'] ) ? sanitize_text_field( $_GET['vehicle_status'] ) : '';
        $category_id = isset( $_GET['category_id'] ) ? absint( $_GET['category_id'] ) : 0;
        $orderby     = isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : 'sort_order';
        $order       = isset( $_GET['order'] ) && strtoupper( $_GET['order'] ) === 'DESC' ? 'DESC' : 'ASC';
        $paged       = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
        $offset      = ( $paged - 1 ) * self::PER_PAGE;

        // Whitelist allowed orderby columns
        $allowed_orderby = array( 'name', 'make', 'model', 'year', 'category_id', 'status', 'units', 'sort_order', 'created_at' );
        if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
            $orderby = 'sort_order';
        }

        // ── Build query ──────────────────────────
        $table  = RAF_Helpers::table( 'vehicles' );
        $cat_t  = RAF_Helpers::table( 'vehicle_categories' );
        $where  = array( '1=1' );
        $values = array();

        if ( $status ) {
            $where[]  = 'v.status = %s';
            $values[] = $status;
        }
        if ( $category_id ) {
            $where[]  = 'v.category_id = %d';
            $values[] = $category_id;
        }
        if ( $search ) {
            $like     = '%' . $wpdb->esc_like( $search ) . '%';
            $where[]  = '(v.name LIKE %s OR v.make LIKE %s OR v.model LIKE %s OR v.license_plate LIKE %s)';
            $values   = array_merge( $values, array( $like, $like, $like, $like ) );
        }

        $where_sql = implode( ' AND ', $where );

        // Count total
        $count_sql   = "SELECT COUNT(*) FROM $table v WHERE $where_sql";
        $total_items = $values ? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $values ) ) : (int) $wpdb->get_var( $count_sql );
        $total_pages = ceil( $total_items / self::PER_PAGE );

        // Fetch page
        $sql  = "SELECT v.*, c.name AS category_name
                 FROM $table v
                 LEFT JOIN $cat_t c ON v.category_id = c.id
                 WHERE $where_sql
                 ORDER BY v.{$orderby} {$order}
                 LIMIT %d OFFSET %d";
        $values_page = $values;
        $values_page[] = self::PER_PAGE;
        $values_page[] = $offset;
        $vehicles = $wpdb->get_results( $wpdb->prepare( $sql, $values_page ) );

        // Status counts for filter links
        $status_counts = self::get_status_counts();

        // ── Render ───────────────────────────────
        ?>
        <h1 class="wp-heading-inline"><?php esc_html_e( 'Vehicles', 'rentafleet' ); ?></h1>
        <a href="<?php echo esc_url( RAF_Admin::admin_url( 'raf-vehicles', array( 'action' => 'add' ) ) ); ?>" class="page-title-action">
            <?php esc_html_e( 'Add New Vehicle', 'rentafleet' ); ?>
        </a>
        <hr class="wp-header-end">

        <?php /* ── Status filter links ─── */ ?>
        <ul class="subsubsub">
            <?php
            $all_count = array_sum( $status_counts );
            $links = array();

            // "All" link
            $class   = ( ! $status ) ? ' class="current"' : '';
            $links[] = sprintf(
                '<li><a href="%s"%s>%s <span class="count">(%d)</span></a></li>',
                esc_url( RAF_Admin::admin_url( 'raf-vehicles' ) ),
                $class,
                esc_html__( 'All', 'rentafleet' ),
                $all_count
            );

            $statuses = array(
                'active'   => __( 'Active', 'rentafleet' ),
                'inactive' => __( 'Inactive', 'rentafleet' ),
            );
            foreach ( $statuses as $key => $label ) {
                $cnt     = isset( $status_counts[ $key ] ) ? $status_counts[ $key ] : 0;
                $class   = ( $status === $key ) ? ' class="current"' : '';
                $links[] = sprintf(
                    '<li><a href="%s"%s>%s <span class="count">(%d)</span></a></li>',
                    esc_url( RAF_Admin::admin_url( 'raf-vehicles', array( 'vehicle_status' => $key ) ) ),
                    $class,
                    esc_html( $label ),
                    $cnt
                );
            }
            echo implode( ' | ', $links );
            ?>
        </ul>

        <?php /* ── Search / Filter bar ─── */ ?>
        <form method="get" class="raf-filter-form">
            <input type="hidden" name="page" value="raf-vehicles">
            <div class="raf-filter-bar">
                <select name="category_id">
                    <option value=""><?php esc_html_e( 'All Categories', 'rentafleet' ); ?></option>
                    <?php
                    $categories = RAF_Admin::get_categories_dropdown();
                    unset( $categories[''] ); // remove blank option
                    foreach ( $categories as $cid => $cname ) {
                        printf(
                            '<option value="%d"%s>%s</option>',
                            $cid,
                            selected( $category_id, $cid, false ),
                            esc_html( $cname )
                        );
                    }
                    ?>
                </select>
                <?php if ( $status ) : ?>
                    <input type="hidden" name="vehicle_status" value="<?php echo esc_attr( $status ); ?>">
                <?php endif; ?>
                <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search vehicles…', 'rentafleet' ); ?>">
                <button type="submit" class="button"><?php esc_html_e( 'Filter', 'rentafleet' ); ?></button>
                <?php if ( $search || $category_id ) : ?>
                    <a href="<?php echo esc_url( RAF_Admin::admin_url( 'raf-vehicles' ) ); ?>" class="button"><?php esc_html_e( 'Reset', 'rentafleet' ); ?></a>
                <?php endif; ?>
            </div>
        </form>

        <?php /* ── Bulk Actions & Table ─── */ ?>
        <form method="post" id="raf-vehicles-list-form">
            <input type="hidden" name="raf_action" value="bulk_vehicles">
            <input type="hidden" name="page" value="raf-vehicles">
            <?php RAF_Admin::nonce_field( 'bulk_vehicles' ); ?>

            <div class="tablenav top">
                <div class="alignleft actions bulkactions">
                    <select name="bulk_action" id="raf-bulk-action-top">
                        <option value=""><?php esc_html_e( 'Bulk Actions', 'rentafleet' ); ?></option>
                        <option value="activate"><?php esc_html_e( 'Set Active', 'rentafleet' ); ?></option>
                        <option value="deactivate"><?php esc_html_e( 'Set Inactive', 'rentafleet' ); ?></option>
                        <option value="delete"><?php esc_html_e( 'Delete', 'rentafleet' ); ?></option>
                    </select>
                    <button type="submit" class="button action" onclick="return this.form.bulk_action.value === 'delete' ? confirm('<?php esc_attr_e( 'Delete selected vehicles?', 'rentafleet' ); ?>') : true;">
                        <?php esc_html_e( 'Apply', 'rentafleet' ); ?>
                    </button>
                </div>
                <?php self::render_pagination( $total_items, $total_pages, $paged ); ?>
            </div>

            <table class="wp-list-table widefat fixed striped raf-vehicles-table">
                <thead>
                    <tr>
                        <td class="manage-column column-cb check-column">
                            <input type="checkbox" id="cb-select-all-1">
                        </td>
                        <?php self::render_column_header( 'featured_image', __( 'Image', 'rentafleet' ), false, $orderby, $order ); ?>
                        <?php self::render_column_header( 'name', __( 'Vehicle', 'rentafleet' ), true, $orderby, $order ); ?>
                        <?php self::render_column_header( 'category_id', __( 'Category', 'rentafleet' ), true, $orderby, $order ); ?>
                        <?php self::render_column_header( 'year', __( 'Year', 'rentafleet' ), true, $orderby, $order ); ?>
                        <th scope="col" class="manage-column"><?php esc_html_e( 'Specs', 'rentafleet' ); ?></th>
                        <?php self::render_column_header( 'units', __( 'Units', 'rentafleet' ), true, $orderby, $order ); ?>
                        <th scope="col" class="manage-column"><?php esc_html_e( 'Locations', 'rentafleet' ); ?></th>
                        <?php self::render_column_header( 'status', __( 'Status', 'rentafleet' ), true, $orderby, $order ); ?>
                        <?php self::render_column_header( 'sort_order', __( 'Order', 'rentafleet' ), true, $orderby, $order ); ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $vehicles ) ) : ?>
                        <tr><td colspan="10"><?php esc_html_e( 'No vehicles found.', 'rentafleet' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( $vehicles as $v ) : ?>
                            <?php
                            $edit_url   = RAF_Admin::admin_url( 'raf-vehicles', array( 'action' => 'edit', 'id' => $v->id ) );
                            $delete_url = wp_nonce_url(
                                RAF_Admin::admin_url( 'raf-vehicles', array( 'raf_action' => 'delete_vehicle', 'id' => $v->id ) ),
                                'raf_delete_vehicle_' . $v->id
                            );
                            $thumb      = $v->featured_image_id ? wp_get_attachment_image( $v->featured_image_id, array( 60, 60 ) ) : '<span class="raf-no-image dashicons dashicons-motorcycle"></span>';
                            $locations  = RAF_Vehicle::get_locations( $v->id );
                            $loc_names  = wp_list_pluck( $locations, 'name' );
                            ?>
                            <tr>
                                <th scope="row" class="check-column">
                                    <input type="checkbox" name="vehicle_ids[]" value="<?php echo esc_attr( $v->id ); ?>">
                                </th>
                                <td class="column-image"><?php echo $thumb; ?></td>
                                <td class="column-primary">
                                    <strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $v->name ); ?></a></strong>
                                    <?php if ( $v->license_plate ) : ?>
                                        <span class="raf-license-plate"><?php echo esc_html( $v->license_plate ); ?></span>
                                    <?php endif; ?>
                                    <div class="row-actions">
                                        <span class="edit"><a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'rentafleet' ); ?></a> | </span>
                                        <span class="trash"><a href="<?php echo esc_url( $delete_url ); ?>" class="submitdelete" onclick="return confirm('<?php esc_attr_e( 'Delete this vehicle?', 'rentafleet' ); ?>');"><?php esc_html_e( 'Delete', 'rentafleet' ); ?></a></span>
                                    </div>
                                </td>
                                <td><?php echo esc_html( $v->category_name ? $v->category_name : '—' ); ?></td>
                                <td><?php echo esc_html( $v->year ? $v->year : '—' ); ?></td>
                                <td class="column-specs">
                                    <?php
                                    $specs = array();
                                    $bike_types = RAF_Helpers::get_bike_types();
                                    if ( $v->bike_type && isset( $bike_types[ $v->bike_type ] ) ) $specs[] = $bike_types[ $v->bike_type ];
                                    if ( $v->engine_cc ) $specs[] = $v->engine_cc . 'cc';
                                    echo esc_html( implode( ' · ', $specs ) );
                                    ?>
                                </td>
                                <td><?php echo esc_html( $v->units ); ?></td>
                                <td>
                                    <?php if ( $loc_names ) : ?>
                                        <?php echo esc_html( implode( ', ', $loc_names ) ); ?>
                                    <?php else : ?>
                                        <span class="raf-muted"><?php esc_html_e( 'None', 'rentafleet' ); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo RAF_Helpers::status_badge( $v->status ); ?></td>
                                <td><?php echo esc_html( $v->sort_order ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td class="manage-column column-cb check-column"><input type="checkbox" id="cb-select-all-2"></td>
                        <th><?php esc_html_e( 'Image', 'rentafleet' ); ?></th>
                        <th><?php esc_html_e( 'Vehicle', 'rentafleet' ); ?></th>
                        <th><?php esc_html_e( 'Category', 'rentafleet' ); ?></th>
                        <th><?php esc_html_e( 'Year', 'rentafleet' ); ?></th>
                        <th><?php esc_html_e( 'Specs', 'rentafleet' ); ?></th>
                        <th><?php esc_html_e( 'Units', 'rentafleet' ); ?></th>
                        <th><?php esc_html_e( 'Locations', 'rentafleet' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'rentafleet' ); ?></th>
                        <th><?php esc_html_e( 'Order', 'rentafleet' ); ?></th>
                    </tr>
                </tfoot>
            </table>

            <div class="tablenav bottom">
                <div class="alignleft actions bulkactions">
                    <select name="bulk_action_bottom">
                        <option value=""><?php esc_html_e( 'Bulk Actions', 'rentafleet' ); ?></option>
                        <option value="activate"><?php esc_html_e( 'Set Active', 'rentafleet' ); ?></option>
                        <option value="deactivate"><?php esc_html_e( 'Set Inactive', 'rentafleet' ); ?></option>
                        <option value="delete"><?php esc_html_e( 'Delete', 'rentafleet' ); ?></option>
                    </select>
                    <button type="submit" class="button action"><?php esc_html_e( 'Apply', 'rentafleet' ); ?></button>
                </div>
                <?php self::render_pagination( $total_items, $total_pages, $paged ); ?>
            </div>
        </form>
        <?php
    }

    /* ================================================================
     *  VEHICLES — ADD / EDIT FORM
     * ============================================================= */

    /**
     * Render the vehicle add/edit form.
     */
    private static function render_form() {
        $id      = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
        $vehicle = $id ? RAF_Vehicle::get( $id ) : null;
        $is_edit = ! empty( $vehicle );
        $title   = $is_edit ? __( 'Edit Vehicle', 'rentafleet' ) : __( 'Add New Vehicle', 'rentafleet' );

        // Defaults for new vehicle
        $defaults = (object) array(
            'id'                  => 0,
            'category_id'        => 0,
            'name'               => '',
            'slug'               => '',
            'description'        => '',
            'short_description'  => '',
            'make'               => '',
            'model'              => '',
            'year'               => '',
            'license_plate'      => '',
            'vin'                => '',
            'color'              => '',
            'engine_cc'          => 0,
            'bike_type'          => 'standard',
            'min_rental_days'    => 1,
            'max_rental_days'    => 365,
            'min_driver_age'     => 21,
            'deposit_amount'     => '0.00',
            'featured_image_id'  => 0,
            'gallery'            => '',
            'features'           => '',
            'units'              => 1,
            'status'             => 'active',
            'sort_order'         => 0,
        );
        $v = $is_edit ? $vehicle : $defaults;

        $categories  = RAF_Admin::get_categories_dropdown();
        $all_locations = RAF_Admin::get_locations_dropdown();
        $vehicle_locations = $is_edit ? RAF_Vehicle::get_locations( $id ) : array();

        // Build location data: location_id => units_at_location
        $loc_map = array();
        foreach ( $vehicle_locations as $vl ) {
            $loc_map[ $vl->id ] = isset( $vl->units_at_location ) ? $vl->units_at_location : 1;
        }

        $gallery_ids  = $is_edit ? RAF_Vehicle::get_gallery( $v ) : array();
        $feature_list = RAF_Helpers::get_bike_features();
        $selected_features = $is_edit ? RAF_Vehicle::get_features( $v ) : array();
        $bike_types = RAF_Helpers::get_bike_types();

        ?>
        <h1><?php echo esc_html( $title ); ?></h1>
        <?php RAF_Admin::back_link( 'raf-vehicles', __( '← Back to Bikes', 'rentafleet' ) ); ?>

        <form method="post" id="raf-vehicle-form" class="raf-form">
            <input type="hidden" name="raf_action" value="save_vehicle">
            <input type="hidden" name="vehicle_id" value="<?php echo esc_attr( $v->id ); ?>">
            <input type="hidden" name="page" value="raf-vehicles">
            <?php RAF_Admin::nonce_field( 'save_vehicle' ); ?>

            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">

                    <?php /* ─── Main column ─── */ ?>
                    <div id="post-body-content">

                        <?php /* ── Section: Basic Info ── */ ?>
                        <div class="raf-panel">
                            <h2><?php esc_html_e( 'Basic Information', 'rentafleet' ); ?></h2>
                            <table class="form-table">
                                <tr>
                                    <th><label for="vehicle_name"><?php esc_html_e( 'Vehicle Name *', 'rentafleet' ); ?></label></th>
                                    <td>
                                        <input type="text" name="name" id="vehicle_name" value="<?php echo esc_attr( $v->name ); ?>" class="regular-text" required>
                                        <p class="description"><?php esc_html_e( 'Display name. E.g. "Honda CBR 500R — Red"', 'rentafleet' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="vehicle_slug"><?php esc_html_e( 'Slug', 'rentafleet' ); ?></label></th>
                                    <td>
                                        <input type="text" name="slug" id="vehicle_slug" value="<?php echo esc_attr( $v->slug ); ?>" class="regular-text">
                                        <p class="description"><?php esc_html_e( 'URL-friendly name. Auto-generated if blank.', 'rentafleet' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="vehicle_make"><?php esc_html_e( 'Make', 'rentafleet' ); ?></label></th>
                                    <td><input type="text" name="make" id="vehicle_make" value="<?php echo esc_attr( $v->make ); ?>" class="regular-text" placeholder="Honda"></td>
                                </tr>
                                <tr>
                                    <th><label for="vehicle_model"><?php esc_html_e( 'Model', 'rentafleet' ); ?></label></th>
                                    <td><input type="text" name="model" id="vehicle_model" value="<?php echo esc_attr( $v->model ); ?>" class="regular-text" placeholder="CBR 500R"></td>
                                </tr>
                                <tr>
                                    <th><label for="vehicle_year"><?php esc_html_e( 'Year', 'rentafleet' ); ?></label></th>
                                    <td><input type="number" name="year" id="vehicle_year" value="<?php echo esc_attr( $v->year ); ?>" min="1900" max="2099" step="1" class="small-text"></td>
                                </tr>
                                <tr>
                                    <th><label for="vehicle_category"><?php esc_html_e( 'Category', 'rentafleet' ); ?></label></th>
                                    <td>
                                        <select name="category_id" id="vehicle_category">
                                            <?php foreach ( $categories as $cid => $cname ) : ?>
                                                <option value="<?php echo esc_attr( $cid ); ?>" <?php selected( $v->category_id, $cid ); ?>><?php echo esc_html( $cname ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="vehicle_short_desc"><?php esc_html_e( 'Short Description', 'rentafleet' ); ?></label></th>
                                    <td><textarea name="short_description" id="vehicle_short_desc" rows="2" class="large-text"><?php echo esc_textarea( $v->short_description ); ?></textarea></td>
                                </tr>
                                <tr>
                                    <th><label for="vehicle_description"><?php esc_html_e( 'Full Description', 'rentafleet' ); ?></label></th>
                                    <td>
                                        <?php
                                        wp_editor( $v->description ? $v->description : '', 'vehicle_description', array(
                                            'textarea_name' => 'description',
                                            'textarea_rows' => 8,
                                            'media_buttons' => false,
                                            'teeny'         => true,
                                        ) );
                                        ?>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <?php /* ── Section: Vehicle Details ── */ ?>
                        <div class="raf-panel">
                            <h2><?php esc_html_e( 'Vehicle Details', 'rentafleet' ); ?></h2>
                            <table class="form-table">
                                <tr>
                                    <th><label for="vehicle_license"><?php esc_html_e( 'License Plate', 'rentafleet' ); ?></label></th>
                                    <td><input type="text" name="license_plate" id="vehicle_license" value="<?php echo esc_attr( $v->license_plate ); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th><label for="vehicle_vin"><?php esc_html_e( 'VIN', 'rentafleet' ); ?></label></th>
                                    <td><input type="text" name="vin" id="vehicle_vin" value="<?php echo esc_attr( $v->vin ); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th><label for="vehicle_color"><?php esc_html_e( 'Color', 'rentafleet' ); ?></label></th>
                                    <td><input type="text" name="color" id="vehicle_color" value="<?php echo esc_attr( $v->color ); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th><label for="vehicle_bike_type"><?php esc_html_e( 'Bike Type', 'rentafleet' ); ?></label></th>
                                    <td>
                                        <select name="bike_type" id="vehicle_bike_type">
                                            <?php foreach ( $bike_types as $key => $label ) : ?>
                                                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $v->bike_type, $key ); ?>><?php echo esc_html( $label ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="vehicle_engine_cc"><?php esc_html_e( 'Engine (cc)', 'rentafleet' ); ?></label></th>
                                    <td>
                                        <input type="number" name="engine_cc" id="vehicle_engine_cc" value="<?php echo esc_attr( $v->engine_cc ); ?>" min="0" max="9999" class="small-text">
                                        <span class="description"><?php esc_html_e( 'Engine displacement in cubic centimetres', 'rentafleet' ); ?></span>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <?php /* ── Section: Rental Rules ── */ ?>
                        <div class="raf-panel">
                            <h2><?php esc_html_e( 'Rental Rules & Pricing', 'rentafleet' ); ?></h2>
                            <table class="form-table">
                                <tr>
                                    <th><label for="vehicle_units"><?php esc_html_e( 'Total Units', 'rentafleet' ); ?></label></th>
                                    <td>
                                        <input type="number" name="units" id="vehicle_units" value="<?php echo esc_attr( $v->units ); ?>" min="1" max="999" class="small-text">
                                        <p class="description"><?php esc_html_e( 'How many units of this vehicle you own. Controls simultaneous rentals.', 'rentafleet' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="vehicle_min_days"><?php esc_html_e( 'Min Rental Days', 'rentafleet' ); ?></label></th>
                                    <td><input type="number" name="min_rental_days" id="vehicle_min_days" value="<?php echo esc_attr( $v->min_rental_days ); ?>" min="1" class="small-text"></td>
                                </tr>
                                <tr>
                                    <th><label for="vehicle_max_days"><?php esc_html_e( 'Max Rental Days', 'rentafleet' ); ?></label></th>
                                    <td><input type="number" name="max_rental_days" id="vehicle_max_days" value="<?php echo esc_attr( $v->max_rental_days ); ?>" min="1" class="small-text"></td>
                                </tr>
                                <tr>
                                    <th><label for="vehicle_min_age"><?php esc_html_e( 'Min Rider Age', 'rentafleet' ); ?></label></th>
                                    <td><input type="number" name="min_driver_age" id="vehicle_min_age" value="<?php echo esc_attr( $v->min_driver_age ); ?>" min="16" max="99" class="small-text"></td>
                                </tr>
                                <tr>
                                    <th><label for="vehicle_deposit"><?php esc_html_e( 'Deposit Amount', 'rentafleet' ); ?></label></th>
                                    <td>
                                        <input type="number" name="deposit_amount" id="vehicle_deposit" value="<?php echo esc_attr( $v->deposit_amount ); ?>" min="0" step="0.01" class="small-text">
                                        <span class="description"><?php echo esc_html( RAF_Helpers::get_currency_symbol() ); ?> <?php esc_html_e( '— vehicle-specific override. 0 = use global setting.', 'rentafleet' ); ?></span>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <?php /* ── Section: Features ── */ ?>
                        <div class="raf-panel">
                            <h2><?php esc_html_e( 'Features', 'rentafleet' ); ?></h2>
                            <div class="raf-checkbox-grid">
                                <?php foreach ( $feature_list as $feature_key => $feature_label ) : ?>
                                    <label class="raf-checkbox-item">
                                        <input type="checkbox" name="features[]" value="<?php echo esc_attr( $feature_key ); ?>"
                                            <?php checked( in_array( $feature_key, $selected_features, true ) ); ?>>
                                        <?php echo esc_html( $feature_label ); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <?php /* ── Section: Locations ── */ ?>
                        <div class="raf-panel">
                            <h2><?php esc_html_e( 'Available at Locations', 'rentafleet' ); ?></h2>
                            <?php if ( empty( $all_locations ) ) : ?>
                                <p class="raf-muted"><?php esc_html_e( 'No locations have been created yet. Create locations first.', 'rentafleet' ); ?></p>
                            <?php else : ?>
                                <p class="description"><?php esc_html_e( 'Select which locations this vehicle is available at and how many units per location.', 'rentafleet' ); ?></p>
                                <table class="raf-locations-table widefat" style="max-width:600px;">
                                    <thead>
                                        <tr>
                                            <th style="width:30px;"></th>
                                            <th><?php esc_html_e( 'Location', 'rentafleet' ); ?></th>
                                            <th style="width:100px;"><?php esc_html_e( 'Units', 'rentafleet' ); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ( $all_locations as $loc_id => $loc_name ) : ?>
                                            <?php
                                            $checked = isset( $loc_map[ $loc_id ] );
                                            $units   = $checked ? $loc_map[ $loc_id ] : 1;
                                            ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" name="location_ids[]" value="<?php echo esc_attr( $loc_id ); ?>"
                                                        <?php checked( $checked ); ?>
                                                        class="raf-loc-checkbox" data-loc="<?php echo esc_attr( $loc_id ); ?>">
                                                </td>
                                                <td><label><?php echo esc_html( $loc_name ); ?></label></td>
                                                <td>
                                                    <input type="number" name="location_units[<?php echo esc_attr( $loc_id ); ?>]"
                                                           value="<?php echo esc_attr( $units ); ?>" min="1" max="999"
                                                           class="small-text raf-loc-units" data-loc="<?php echo esc_attr( $loc_id ); ?>"
                                                           <?php echo $checked ? '' : 'disabled'; ?>>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>

                    </div><?php /* /post-body-content */ ?>

                    <?php /* ─── Sidebar column ─── */ ?>
                    <div id="postbox-container-1" class="postbox-container">

                        <?php /* ── Publish box ── */ ?>
                        <div class="raf-panel raf-publish-box">
                            <h2><?php esc_html_e( 'Publish', 'rentafleet' ); ?></h2>
                            <div class="raf-publish-inside">
                                <div class="raf-field">
                                    <label for="vehicle_status"><?php esc_html_e( 'Status', 'rentafleet' ); ?></label>
                                    <select name="status" id="vehicle_status">
                                        <option value="active" <?php selected( $v->status, 'active' ); ?>><?php esc_html_e( 'Active', 'rentafleet' ); ?></option>
                                        <option value="inactive" <?php selected( $v->status, 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'rentafleet' ); ?></option>
                                    </select>
                                </div>
                                <div class="raf-field">
                                    <label for="vehicle_sort"><?php esc_html_e( 'Sort Order', 'rentafleet' ); ?></label>
                                    <input type="number" name="sort_order" id="vehicle_sort" value="<?php echo esc_attr( $v->sort_order ); ?>" min="0" class="small-text">
                                </div>
                                <div class="raf-publish-actions">
                                    <?php if ( $is_edit ) : ?>
                                        <?php
                                        $delete_url = wp_nonce_url(
                                            RAF_Admin::admin_url( 'raf-vehicles', array( 'raf_action' => 'delete_vehicle', 'id' => $v->id ) ),
                                            'raf_delete_vehicle_' . $v->id
                                        );
                                        ?>
                                        <a href="<?php echo esc_url( $delete_url ); ?>" class="submitdelete" onclick="return confirm('<?php esc_attr_e( 'Delete this vehicle permanently?', 'rentafleet' ); ?>');">
                                            <?php esc_html_e( 'Delete', 'rentafleet' ); ?>
                                        </a>
                                    <?php endif; ?>
                                    <button type="submit" class="button button-primary button-large">
                                        <?php echo $is_edit ? esc_html__( 'Update Vehicle', 'rentafleet' ) : esc_html__( 'Add Vehicle', 'rentafleet' ); ?>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <?php /* ── Featured Image ── */ ?>
                        <div class="raf-panel raf-image-panel">
                            <h2><?php esc_html_e( 'Featured Image', 'rentafleet' ); ?></h2>
                            <div class="raf-featured-image-wrap">
                                <input type="hidden" name="featured_image_id" id="featured_image_id" value="<?php echo esc_attr( $v->featured_image_id ); ?>">
                                <div id="raf-featured-image-preview">
                                    <?php if ( $v->featured_image_id ) : ?>
                                        <?php echo wp_get_attachment_image( $v->featured_image_id, 'medium' ); ?>
                                    <?php endif; ?>
                                </div>
                                <p>
                                    <button type="button" class="button" id="raf-set-featured-image">
                                        <?php echo $v->featured_image_id ? esc_html__( 'Change Image', 'rentafleet' ) : esc_html__( 'Set Featured Image', 'rentafleet' ); ?>
                                    </button>
                                    <button type="button" class="button raf-remove-featured-image" id="raf-remove-featured-image" style="<?php echo $v->featured_image_id ? '' : 'display:none;'; ?>">
                                        <?php esc_html_e( 'Remove', 'rentafleet' ); ?>
                                    </button>
                                </p>
                            </div>
                        </div>

                        <?php /* ── Gallery ── */ ?>
                        <div class="raf-panel raf-image-panel">
                            <h2><?php esc_html_e( 'Gallery', 'rentafleet' ); ?></h2>
                            <input type="hidden" name="gallery" id="raf-gallery-ids" value="<?php echo esc_attr( implode( ',', $gallery_ids ) ); ?>">
                            <div id="raf-gallery-preview" class="raf-gallery-grid">
                                <?php foreach ( $gallery_ids as $gid ) : ?>
                                    <div class="raf-gallery-thumb" data-id="<?php echo esc_attr( $gid ); ?>">
                                        <?php echo wp_get_attachment_image( $gid, 'thumbnail' ); ?>
                                        <button type="button" class="raf-gallery-remove" data-id="<?php echo esc_attr( $gid ); ?>">&times;</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <p>
                                <button type="button" class="button" id="raf-add-gallery-images"><?php esc_html_e( 'Add Images', 'rentafleet' ); ?></button>
                            </p>
                        </div>

                    </div><?php /* /postbox-container-1 */ ?>

                </div><?php /* /post-body */ ?>
            </div><?php /* /poststuff */ ?>

        </form>
        <?php
    }

    /* ================================================================
     *  CATEGORIES — LIST + INLINE FORM
     * ============================================================= */

    /**
     * Render the categories management page — shows an add/edit form
     * and a list of existing categories side by side.
     */
    private static function render_categories_page() {
        global $wpdb;
        $table = RAF_Helpers::table( 'vehicle_categories' );

        $edit_id  = isset( $_GET['edit_cat'] ) ? absint( $_GET['edit_cat'] ) : 0;
        $editing  = null;
        if ( $edit_id ) {
            $editing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $edit_id ) );
        }

        $categories = $wpdb->get_results( "SELECT c.*, (SELECT COUNT(*) FROM " . RAF_Helpers::table( 'vehicles' ) . " v WHERE v.category_id = c.id) AS vehicle_count FROM $table c ORDER BY c.sort_order ASC, c.name ASC" );

        ?>
        <h1><?php esc_html_e( 'Vehicle Categories', 'rentafleet' ); ?></h1>

        <div class="raf-two-col-layout">

            <?php /* ─── Left column: Add / Edit form ─── */ ?>
            <div class="raf-col-form">
                <div class="raf-panel">
                    <h2><?php echo $editing ? esc_html__( 'Edit Category', 'rentafleet' ) : esc_html__( 'Add New Category', 'rentafleet' ); ?></h2>

                    <form method="post">
                        <input type="hidden" name="raf_action" value="save_category">
                        <input type="hidden" name="page" value="raf-vehicles">
                        <input type="hidden" name="category_id" value="<?php echo esc_attr( $editing ? $editing->id : 0 ); ?>">
                        <?php RAF_Admin::nonce_field( 'save_category' ); ?>

                        <div class="raf-field">
                            <label for="cat_name"><?php esc_html_e( 'Name *', 'rentafleet' ); ?></label>
                            <input type="text" name="cat_name" id="cat_name" value="<?php echo esc_attr( $editing ? $editing->name : '' ); ?>" class="widefat" required>
                        </div>
                        <div class="raf-field">
                            <label for="cat_slug"><?php esc_html_e( 'Slug', 'rentafleet' ); ?></label>
                            <input type="text" name="cat_slug" id="cat_slug" value="<?php echo esc_attr( $editing ? $editing->slug : '' ); ?>" class="widefat">
                            <p class="description"><?php esc_html_e( 'Auto-generated if blank.', 'rentafleet' ); ?></p>
                        </div>
                        <div class="raf-field">
                            <label for="cat_description"><?php esc_html_e( 'Description', 'rentafleet' ); ?></label>
                            <textarea name="cat_description" id="cat_description" rows="4" class="widefat"><?php echo esc_textarea( $editing ? $editing->description : '' ); ?></textarea>
                        </div>
                        <div class="raf-field">
                            <label for="cat_image_id"><?php esc_html_e( 'Image', 'rentafleet' ); ?></label>
                            <input type="hidden" name="cat_image_id" id="cat_image_id" value="<?php echo esc_attr( $editing ? $editing->image_id : 0 ); ?>">
                            <div id="raf-cat-image-preview">
                                <?php if ( $editing && $editing->image_id ) echo wp_get_attachment_image( $editing->image_id, array( 120, 80 ) ); ?>
                            </div>
                            <button type="button" class="button" id="raf-set-cat-image"><?php esc_html_e( 'Set Image', 'rentafleet' ); ?></button>
                            <button type="button" class="button" id="raf-remove-cat-image" style="<?php echo ( $editing && $editing->image_id ) ? '' : 'display:none;'; ?>"><?php esc_html_e( 'Remove', 'rentafleet' ); ?></button>
                        </div>
                        <div class="raf-field">
                            <label for="cat_sort_order"><?php esc_html_e( 'Sort Order', 'rentafleet' ); ?></label>
                            <input type="number" name="cat_sort_order" id="cat_sort_order" value="<?php echo esc_attr( $editing ? $editing->sort_order : 0 ); ?>" min="0" class="small-text">
                        </div>
                        <div class="raf-field">
                            <label for="cat_status"><?php esc_html_e( 'Status', 'rentafleet' ); ?></label>
                            <select name="cat_status" id="cat_status">
                                <option value="active" <?php $editing && selected( $editing->status, 'active' ); ?>><?php esc_html_e( 'Active', 'rentafleet' ); ?></option>
                                <option value="inactive" <?php $editing && selected( $editing->status, 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'rentafleet' ); ?></option>
                            </select>
                        </div>

                        <p>
                            <button type="submit" class="button button-primary">
                                <?php echo $editing ? esc_html__( 'Update Category', 'rentafleet' ) : esc_html__( 'Add Category', 'rentafleet' ); ?>
                            </button>
                            <?php if ( $editing ) : ?>
                                <a href="<?php echo esc_url( RAF_Admin::admin_url( 'raf-vehicles', array( 'tab' => 'categories' ) ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'rentafleet' ); ?></a>
                            <?php endif; ?>
                        </p>
                    </form>
                </div>
            </div>

            <?php /* ─── Right column: Categories list ─── */ ?>
            <div class="raf-col-list">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width:50px;"><?php esc_html_e( 'Image', 'rentafleet' ); ?></th>
                            <th><?php esc_html_e( 'Name', 'rentafleet' ); ?></th>
                            <th><?php esc_html_e( 'Slug', 'rentafleet' ); ?></th>
                            <th style="width:70px;"><?php esc_html_e( 'Vehicles', 'rentafleet' ); ?></th>
                            <th style="width:60px;"><?php esc_html_e( 'Order', 'rentafleet' ); ?></th>
                            <th style="width:80px;"><?php esc_html_e( 'Status', 'rentafleet' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $categories ) ) : ?>
                            <tr><td colspan="6"><?php esc_html_e( 'No categories yet.', 'rentafleet' ); ?></td></tr>
                        <?php else : ?>
                            <?php foreach ( $categories as $cat ) : ?>
                                <?php
                                $edit_cat_url   = RAF_Admin::admin_url( 'raf-vehicles', array( 'tab' => 'categories', 'edit_cat' => $cat->id ) );
                                $delete_cat_url = wp_nonce_url(
                                    RAF_Admin::admin_url( 'raf-vehicles', array( 'tab' => 'categories', 'raf_action' => 'delete_category', 'cat_id' => $cat->id ) ),
                                    'raf_delete_category_' . $cat->id
                                );
                                $thumb = $cat->image_id ? wp_get_attachment_image( $cat->image_id, array( 40, 40 ) ) : '—';
                                ?>
                                <tr>
                                    <td><?php echo $thumb; ?></td>
                                    <td>
                                        <strong><a href="<?php echo esc_url( $edit_cat_url ); ?>"><?php echo esc_html( $cat->name ); ?></a></strong>
                                        <div class="row-actions">
                                            <span class="edit"><a href="<?php echo esc_url( $edit_cat_url ); ?>"><?php esc_html_e( 'Edit', 'rentafleet' ); ?></a> | </span>
                                            <span class="trash"><a href="<?php echo esc_url( $delete_cat_url ); ?>" class="submitdelete" onclick="return confirm('<?php esc_attr_e( 'Delete this category?', 'rentafleet' ); ?>');"><?php esc_html_e( 'Delete', 'rentafleet' ); ?></a></span>
                                        </div>
                                    </td>
                                    <td><code><?php echo esc_html( $cat->slug ); ?></code></td>
                                    <td><?php echo esc_html( $cat->vehicle_count ); ?></td>
                                    <td><?php echo esc_html( $cat->sort_order ); ?></td>
                                    <td><?php echo RAF_Helpers::status_badge( $cat->status ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div><?php /* /raf-two-col-layout */ ?>
        <?php
    }

    /* ================================================================
     *  FORM HANDLERS
     * ============================================================= */

    /**
     * Handle vehicle save (add or update).
     */
    private static function handle_save_vehicle() {
        if ( ! RAF_Admin::verify_nonce( 'save_vehicle' ) ) {
            RAF_Admin::add_notice( __( 'Security check failed.', 'rentafleet' ), 'error' );
            return;
        }

        $id = absint( $_POST['vehicle_id'] );

        // Sanitise all fields
        $data = array(
            'name'               => sanitize_text_field( $_POST['name'] ),
            'slug'               => sanitize_title( $_POST['slug'] ? $_POST['slug'] : $_POST['name'] ),
            'category_id'        => ! empty( $_POST['category_id'] ) ? absint( $_POST['category_id'] ) : null,
            'make'               => sanitize_text_field( $_POST['make'] ),
            'model'              => sanitize_text_field( $_POST['model'] ),
            'year'               => ! empty( $_POST['year'] ) ? absint( $_POST['year'] ) : null,
            'license_plate'      => sanitize_text_field( $_POST['license_plate'] ),
            'vin'                => sanitize_text_field( $_POST['vin'] ),
            'color'              => sanitize_text_field( $_POST['color'] ),
            'description'        => wp_kses_post( $_POST['description'] ),
            'short_description'  => sanitize_textarea_field( $_POST['short_description'] ),
            'engine_cc'          => absint( $_POST['engine_cc'] ),
            'bike_type'          => sanitize_text_field( $_POST['bike_type'] ),
            'min_rental_days'    => max( 1, absint( $_POST['min_rental_days'] ) ),
            'max_rental_days'    => max( 1, absint( $_POST['max_rental_days'] ) ),
            'min_driver_age'     => max( 16, absint( $_POST['min_driver_age'] ?? 18 ) ),
            'deposit_amount'     => floatval( $_POST['deposit_amount'] ),
            'featured_image_id'  => absint( $_POST['featured_image_id'] ),
            'units'              => max( 1, absint( $_POST['units'] ) ),
            'status'             => in_array( $_POST['status'], array( 'active', 'inactive' ), true ) ? $_POST['status'] : 'active',
            'sort_order'         => absint( $_POST['sort_order'] ),
        );

        // Features (array of keys)
        $data['features'] = isset( $_POST['features'] ) && is_array( $_POST['features'] )
            ? array_map( 'sanitize_text_field', $_POST['features'] )
            : array();

        // Gallery (comma-separated IDs string → array)
        $gallery_raw = isset( $_POST['gallery'] ) ? sanitize_text_field( $_POST['gallery'] ) : '';
        $data['gallery'] = $gallery_raw ? array_filter( array_map( 'absint', explode( ',', $gallery_raw ) ) ) : array();

        // Validate required
        if ( empty( $data['name'] ) ) {
            RAF_Admin::add_notice( __( 'Vehicle name is required.', 'rentafleet' ), 'error' );
            return;
        }

        // Save
        if ( $id ) {
            RAF_Vehicle::update( $id, $data );
            $vehicle_id = $id;
            $message = __( 'Vehicle updated successfully.', 'rentafleet' );
        } else {
            $vehicle_id = RAF_Vehicle::create( $data );
            $message = __( 'Vehicle created successfully.', 'rentafleet' );
        }

        if ( ! $vehicle_id ) {
            RAF_Admin::add_notice( __( 'Failed to save vehicle.', 'rentafleet' ), 'error' );
            return;
        }

        // Save locations
        $location_ids = isset( $_POST['location_ids'] ) && is_array( $_POST['location_ids'] ) ? array_map( 'absint', $_POST['location_ids'] ) : array();
        $location_units = isset( $_POST['location_units'] ) && is_array( $_POST['location_units'] ) ? $_POST['location_units'] : array();

        $loc_data = array();
        foreach ( $location_ids as $loc_id ) {
            $units = isset( $location_units[ $loc_id ] ) ? max( 1, absint( $location_units[ $loc_id ] ) ) : 1;
            $loc_data[] = array(
                'location_id' => $loc_id,
                'units'       => $units,
            );
        }
        RAF_Vehicle::set_locations( $vehicle_id, $loc_data );

        RAF_Admin::add_notice( $message );
        RAF_Admin::redirect( 'raf-vehicles', array( 'action' => 'edit', 'id' => $vehicle_id ) );
    }

    /**
     * Handle single vehicle deletion.
     */
    private static function handle_delete_vehicle() {
        $id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

        if ( ! $id || ! wp_verify_nonce( $_GET['_wpnonce'], 'raf_delete_vehicle_' . $id ) ) {
            RAF_Admin::add_notice( __( 'Invalid request.', 'rentafleet' ), 'error' );
            return;
        }

        // Check for active bookings
        if ( self::vehicle_has_active_bookings( $id ) ) {
            RAF_Admin::add_notice( __( 'Cannot delete vehicle — it has active or pending bookings.', 'rentafleet' ), 'error' );
            RAF_Admin::redirect( 'raf-vehicles' );
        }

        // Remove location associations
        global $wpdb;
        $wpdb->delete( RAF_Helpers::table( 'vehicle_locations' ), array( 'vehicle_id' => $id ) );

        RAF_Vehicle::delete( $id );
        RAF_Admin::add_notice( __( 'Vehicle deleted.', 'rentafleet' ) );
        RAF_Admin::redirect( 'raf-vehicles' );
    }

    /**
     * Handle bulk actions on vehicles.
     */
    private static function handle_bulk_action() {
        if ( ! RAF_Admin::verify_nonce( 'bulk_vehicles' ) ) {
            RAF_Admin::add_notice( __( 'Security check failed.', 'rentafleet' ), 'error' );
            return;
        }

        $action = ! empty( $_POST['bulk_action'] ) ? sanitize_text_field( $_POST['bulk_action'] ) : '';
        if ( ! $action ) {
            $action = ! empty( $_POST['bulk_action_bottom'] ) ? sanitize_text_field( $_POST['bulk_action_bottom'] ) : '';
        }

        $ids = isset( $_POST['vehicle_ids'] ) && is_array( $_POST['vehicle_ids'] ) ? array_map( 'absint', $_POST['vehicle_ids'] ) : array();

        if ( empty( $action ) || empty( $ids ) ) {
            return;
        }

        $count = 0;
        switch ( $action ) {
            case 'activate':
                foreach ( $ids as $vid ) {
                    RAF_Vehicle::update( $vid, array( 'status' => 'active' ) );
                    $count++;
                }
                RAF_Admin::add_notice( sprintf( __( '%d vehicle(s) set to active.', 'rentafleet' ), $count ) );
                break;

            case 'deactivate':
                foreach ( $ids as $vid ) {
                    RAF_Vehicle::update( $vid, array( 'status' => 'inactive' ) );
                    $count++;
                }
                RAF_Admin::add_notice( sprintf( __( '%d vehicle(s) set to inactive.', 'rentafleet' ), $count ) );
                break;

            case 'delete':
                $skipped = 0;
                foreach ( $ids as $vid ) {
                    if ( self::vehicle_has_active_bookings( $vid ) ) {
                        $skipped++;
                        continue;
                    }
                    global $wpdb;
                    $wpdb->delete( RAF_Helpers::table( 'vehicle_locations' ), array( 'vehicle_id' => $vid ) );
                    RAF_Vehicle::delete( $vid );
                    $count++;
                }
                $msg = sprintf( __( '%d vehicle(s) deleted.', 'rentafleet' ), $count );
                if ( $skipped ) {
                    $msg .= ' ' . sprintf( __( '%d skipped (have active bookings).', 'rentafleet' ), $skipped );
                }
                RAF_Admin::add_notice( $msg );
                break;
        }

        RAF_Admin::redirect( 'raf-vehicles' );
    }

    /**
     * Handle category save.
     */
    private static function handle_save_category() {
        if ( ! RAF_Admin::verify_nonce( 'save_category' ) ) {
            RAF_Admin::add_notice( __( 'Security check failed.', 'rentafleet' ), 'error' );
            return;
        }

        global $wpdb;
        $table = RAF_Helpers::table( 'vehicle_categories' );
        $id    = absint( $_POST['category_id'] );

        $name = sanitize_text_field( $_POST['cat_name'] );
        if ( empty( $name ) ) {
            RAF_Admin::add_notice( __( 'Category name is required.', 'rentafleet' ), 'error' );
            return;
        }

        $data = array(
            'name'        => $name,
            'slug'        => sanitize_title( ! empty( $_POST['cat_slug'] ) ? $_POST['cat_slug'] : $name ),
            'description' => sanitize_textarea_field( $_POST['cat_description'] ),
            'image_id'    => absint( $_POST['cat_image_id'] ),
            'sort_order'  => absint( $_POST['cat_sort_order'] ),
            'status'      => in_array( $_POST['cat_status'], array( 'active', 'inactive' ), true ) ? $_POST['cat_status'] : 'active',
        );

        if ( $id ) {
            $wpdb->update( $table, $data, array( 'id' => $id ) );
            RAF_Admin::add_notice( __( 'Category updated.', 'rentafleet' ) );
        } else {
            $data['created_at'] = current_time( 'mysql' );
            $wpdb->insert( $table, $data );
            RAF_Admin::add_notice( __( 'Category created.', 'rentafleet' ) );
        }

        RAF_Admin::redirect( 'raf-vehicles', array( 'tab' => 'categories' ) );
    }

    /**
     * Handle category deletion.
     */
    private static function handle_delete_category() {
        $cat_id = isset( $_GET['cat_id'] ) ? absint( $_GET['cat_id'] ) : 0;

        if ( ! $cat_id || ! wp_verify_nonce( $_GET['_wpnonce'], 'raf_delete_category_' . $cat_id ) ) {
            RAF_Admin::add_notice( __( 'Invalid request.', 'rentafleet' ), 'error' );
            return;
        }

        global $wpdb;
        $table = RAF_Helpers::table( 'vehicle_categories' );

        // Unset category on vehicles that had it
        $wpdb->update(
            RAF_Helpers::table( 'vehicles' ),
            array( 'category_id' => null ),
            array( 'category_id' => $cat_id )
        );

        $wpdb->delete( $table, array( 'id' => $cat_id ) );
        RAF_Admin::add_notice( __( 'Category deleted. Vehicles were moved to "No Category".', 'rentafleet' ) );
        RAF_Admin::redirect( 'raf-vehicles', array( 'tab' => 'categories' ) );
    }

    /* ================================================================
     *  PRIVATE HELPERS
     * ============================================================= */

    /**
     * Check if a vehicle has any active/pending/confirmed bookings.
     *
     * @param int $vehicle_id
     * @return bool
     */
    private static function vehicle_has_active_bookings( $vehicle_id ) {
        global $wpdb;
        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM " . RAF_Helpers::table( 'bookings' ) .
            " WHERE vehicle_id = %d AND status IN ('pending','confirmed','active')",
            $vehicle_id
        ) );
        return $count > 0;
    }

    /**
     * Get vehicle count grouped by status.
     *
     * @return array [ 'active' => n, 'inactive' => n ]
     */
    private static function get_status_counts() {
        global $wpdb;
        $table   = RAF_Helpers::table( 'vehicles' );
        $results = $wpdb->get_results( "SELECT status, COUNT(*) as cnt FROM $table GROUP BY status" );
        $counts  = array();
        foreach ( $results as $row ) {
            $counts[ $row->status ] = (int) $row->cnt;
        }
        return $counts;
    }

    /**
     * Render a sortable column header for the list table.
     *
     * @param string $column  Column key.
     * @param string $label   Display label.
     * @param bool   $sortable Whether column is sortable.
     * @param string $current_orderby Current orderby value.
     * @param string $current_order Current order (ASC/DESC).
     */
    private static function render_column_header( $column, $label, $sortable, $current_orderby, $current_order ) {
        if ( ! $sortable ) {
            printf( '<th scope="col" class="manage-column column-%s">%s</th>', esc_attr( $column ), esc_html( $label ) );
            return;
        }

        $new_order = ( $current_orderby === $column && $current_order === 'ASC' ) ? 'DESC' : 'ASC';
        $class     = 'manage-column column-' . $column . ' sortable';
        $indicator = '';

        if ( $current_orderby === $column ) {
            $class    .= ' sorted ' . strtolower( $current_order );
            $indicator = ( $current_order === 'ASC' ) ? ' ▲' : ' ▼';
        }

        $url = add_query_arg( array(
            'orderby' => $column,
            'order'   => $new_order,
        ) );

        printf(
            '<th scope="col" class="%s"><a href="%s"><span>%s</span>%s</a></th>',
            esc_attr( $class ),
            esc_url( $url ),
            esc_html( $label ),
            $indicator
        );
    }

    /**
     * Render WordPress-style pagination.
     *
     * @param int $total_items Total number of items.
     * @param int $total_pages Total number of pages.
     * @param int $current     Current page number.
     */
    private static function render_pagination( $total_items, $total_pages, $current ) {
        if ( $total_pages <= 1 ) {
            echo '<div class="tablenav-pages one-page"><span class="displaying-num">' . sprintf( _n( '%s item', '%s items', $total_items, 'rentafleet' ), number_format_i18n( $total_items ) ) . '</span></div>';
            return;
        }

        $output = '<div class="tablenav-pages">';
        $output .= '<span class="displaying-num">' . sprintf( _n( '%s item', '%s items', $total_items, 'rentafleet' ), number_format_i18n( $total_items ) ) . '</span>';
        $output .= '<span class="pagination-links">';

        // First
        if ( $current > 1 ) {
            $output .= sprintf( '<a class="first-page button" href="%s">«</a> ', esc_url( add_query_arg( 'paged', 1 ) ) );
            $output .= sprintf( '<a class="prev-page button" href="%s">‹</a> ', esc_url( add_query_arg( 'paged', $current - 1 ) ) );
        } else {
            $output .= '<span class="tablenav-pages-navspan button disabled">«</span> ';
            $output .= '<span class="tablenav-pages-navspan button disabled">‹</span> ';
        }

        $output .= sprintf(
            '<span class="paging-input"><span class="tablenav-paging-text">%d of <span class="total-pages">%d</span></span></span>',
            $current,
            $total_pages
        );

        // Next / Last
        if ( $current < $total_pages ) {
            $output .= sprintf( ' <a class="next-page button" href="%s">›</a>', esc_url( add_query_arg( 'paged', $current + 1 ) ) );
            $output .= sprintf( ' <a class="last-page button" href="%s">»</a>', esc_url( add_query_arg( 'paged', $total_pages ) ) );
        } else {
            $output .= ' <span class="tablenav-pages-navspan button disabled">›</span>';
            $output .= ' <span class="tablenav-pages-navspan button disabled">»</span>';
        }

        $output .= '</span></div>';
        echo $output;
    }
}
