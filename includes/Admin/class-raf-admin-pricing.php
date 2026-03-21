<?php
/**
 * RentAFleet Admin — Pricing & Rates Management
 *
 * Tabbed interface managing all pricing-related entities:
 *  • Rates         — tiered pricing per vehicle/category (daily, weekend, weekly, monthly, hourly)
 *  • Seasonal      — date-range price overrides with priority
 *  • Extras        — add-on items (GPS, child seat, etc.) with restrictions
 *  • Insurance     — CDW/LDW/SCDW types with coverage & deductible
 *  • Tax Rates     — location-based tax rules
 *
 * @package RentAFleet
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RAF_Admin_Pricing {

    /* ================================================================
     *  ROUTER
     * ============================================================= */

    public static function render() {
        $tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'rates';

        echo '<div class="wrap raf-pricing-wrap">';
        echo '<h1>' . esc_html__( 'Pricing & Rates', 'rentafleet' ) . '</h1>';
        self::render_tabs( $tab );

        switch ( $tab ) {
            case 'seasonal':    self::render_seasonal();  break;
            case 'extras':      self::render_extras();    break;
            case 'insurance':   self::render_insurance(); break;
            case 'tax':         self::render_tax();       break;
            default:            self::render_rates();     break;
        }

        echo '</div>';
    }

    private static function render_tabs( $active ) {
        $tabs = array(
            'rates'    => __( 'Rates', 'rentafleet' ),
            'seasonal' => __( 'Seasonal Rates', 'rentafleet' ),
            'extras'   => __( 'Extras / Add-ons', 'rentafleet' ),
            'insurance'=> __( 'Insurance', 'rentafleet' ),
            'tax'      => __( 'Tax Rates', 'rentafleet' ),
        );
        echo '<nav class="nav-tab-wrapper raf-tab-wrapper">';
        foreach ( $tabs as $slug => $label ) {
            $url   = RAF_Admin::admin_url( 'raf-pricing', array( 'tab' => $slug ) );
            $class = ( $slug === $active ) ? 'nav-tab nav-tab-active' : 'nav-tab';
            printf( '<a href="%s" class="%s">%s</a>', esc_url( $url ), esc_attr( $class ), esc_html( $label ) );
        }
        echo '</nav>';
    }

    /* ================================================================
     *  ACTION HANDLER
     * ============================================================= */

    public static function handle_action( $action ) {
        switch ( $action ) {
            case 'save_rate':           self::handle_save_rate(); break;
            case 'delete_rate':         self::handle_delete( 'rates', 'rate' ); break;
            case 'save_seasonal':       self::handle_save_seasonal(); break;
            case 'delete_seasonal':     self::handle_delete( 'seasonal_rates', 'seasonal' ); break;
            case 'save_extra':          self::handle_save_extra(); break;
            case 'delete_extra':        self::handle_delete( 'extras', 'extra' ); break;
            case 'save_insurance':      self::handle_save_insurance(); break;
            case 'delete_insurance':    self::handle_delete( 'insurance', 'insurance_item' ); break;
            case 'save_tax':            self::handle_save_tax(); break;
            case 'delete_tax':          self::handle_delete( 'tax_rates', 'tax' ); break;
        }
    }

    /* ================================================================
     *  TAB 1: RATES
     * ============================================================= */

    private static function render_rates() {
        global $wpdb;
        $t_rates = RAF_Helpers::table( 'rates' );
        $t_veh   = RAF_Helpers::table( 'vehicles' );
        $t_cat   = RAF_Helpers::table( 'vehicle_categories' );

        $edit_id = isset( $_GET['edit_id'] ) ? absint( $_GET['edit_id'] ) : 0;
        $editing = $edit_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t_rates WHERE id = %d", $edit_id ) ) : null;

        $rates = $wpdb->get_results(
            "SELECT r.*, v.name AS vehicle_name, c.name AS category_name
             FROM $t_rates r
             LEFT JOIN $t_veh v ON r.vehicle_id = v.id
             LEFT JOIN $t_cat c ON r.category_id = c.id
             ORDER BY r.vehicle_id, r.category_id, r.min_days ASC"
        );

        $vehicles   = $wpdb->get_results( "SELECT id, name FROM $t_veh WHERE status='active' ORDER BY name" );
        $categories = RAF_Admin::get_categories_dropdown();
        unset( $categories[''] );

        ?>
        <div class="raf-two-col-layout">
            <div class="raf-col-form">
                <div class="raf-panel">
                    <h2><?php echo $editing ? esc_html__( 'Edit Rate', 'rentafleet' ) : esc_html__( 'Add Rate', 'rentafleet' ); ?></h2>
                    <form method="post">
                        <input type="hidden" name="raf_action" value="save_rate">
                        <input type="hidden" name="page" value="raf-pricing">
                        <input type="hidden" name="item_id" value="<?php echo esc_attr( $editing ? $editing->id : 0 ); ?>">
                        <?php RAF_Admin::nonce_field( 'save_rate' ); ?>

                        <div class="raf-field"><label><?php esc_html_e( 'Name', 'rentafleet' ); ?></label>
                            <input type="text" name="name" value="<?php echo esc_attr( $editing ? $editing->name : '' ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'e.g. Standard Daily, Weekly Discount', 'rentafleet' ); ?>">
                        </div>
                        <div class="raf-field"><label><?php esc_html_e( 'Apply To', 'rentafleet' ); ?></label>
                            <select name="assign_type" id="raf-rate-assign-type" class="widefat">
                                <option value="vehicle" <?php $editing && $editing->vehicle_id && print 'selected'; ?>><?php esc_html_e( 'Specific Vehicle', 'rentafleet' ); ?></option>
                                <option value="category" <?php $editing && $editing->category_id && !$editing->vehicle_id && print 'selected'; ?>><?php esc_html_e( 'Vehicle Category', 'rentafleet' ); ?></option>
                            </select>
                        </div>
                        <div class="raf-field raf-rate-vehicle-field"><label><?php esc_html_e( 'Vehicle', 'rentafleet' ); ?></label>
                            <select name="vehicle_id" class="widefat">
                                <option value=""><?php esc_html_e( '— Select —', 'rentafleet' ); ?></option>
                                <?php foreach ( $vehicles as $v ) : ?>
                                    <option value="<?php echo esc_attr( $v->id ); ?>" <?php $editing && selected( $editing->vehicle_id, $v->id ); ?>><?php echo esc_html( $v->name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="raf-field raf-rate-category-field" style="display:none;"><label><?php esc_html_e( 'Category', 'rentafleet' ); ?></label>
                            <select name="category_id" class="widefat">
                                <option value=""><?php esc_html_e( '— Select —', 'rentafleet' ); ?></option>
                                <?php foreach ( $categories as $cid => $cn ) : ?>
                                    <option value="<?php echo esc_attr( $cid ); ?>" <?php $editing && selected( $editing->category_id, $cid ); ?>><?php echo esc_html( $cn ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <hr>

                        <div class="raf-field"><label><?php esc_html_e( 'Daily Price *', 'rentafleet' ); ?></label>
                            <input type="number" name="price" value="<?php echo esc_attr( $editing ? $editing->price : '' ); ?>" step="0.01" min="0" class="widefat" required>
                        </div>
                        <div class="raf-field"><label><?php esc_html_e( 'Weekend Price', 'rentafleet' ); ?></label>
                            <input type="number" name="weekend_price" value="<?php echo esc_attr( $editing ? $editing->weekend_price : '' ); ?>" step="0.01" min="0" class="widefat">
                            <p class="description"><?php esc_html_e( 'Leave empty to use daily price on weekends.', 'rentafleet' ); ?></p>
                        </div>
                        <div class="raf-field"><label><?php esc_html_e( 'Hourly Price', 'rentafleet' ); ?></label>
                            <input type="number" name="hourly_price" value="<?php echo esc_attr( $editing ? $editing->hourly_price : '' ); ?>" step="0.01" min="0" class="widefat">
                        </div>
                        <div class="raf-field"><label><?php esc_html_e( 'Weekly Price', 'rentafleet' ); ?></label>
                            <input type="number" name="weekly_price" value="<?php echo esc_attr( $editing ? $editing->weekly_price : '' ); ?>" step="0.01" min="0" class="widefat">
                            <p class="description"><?php esc_html_e( 'Full 7-day rate. Used when rental ≥ 7 days.', 'rentafleet' ); ?></p>
                        </div>
                        <div class="raf-field"><label><?php esc_html_e( 'Monthly Price', 'rentafleet' ); ?></label>
                            <input type="number" name="monthly_price" value="<?php echo esc_attr( $editing ? $editing->monthly_price : '' ); ?>" step="0.01" min="0" class="widefat">
                            <p class="description"><?php esc_html_e( 'Full 30-day rate. Used when rental ≥ 30 days.', 'rentafleet' ); ?></p>
                        </div>

                        <hr>

                        <div class="raf-field"><label><?php esc_html_e( 'Min Days', 'rentafleet' ); ?></label>
                            <input type="number" name="min_days" value="<?php echo esc_attr( $editing ? $editing->min_days : 1 ); ?>" min="0" class="widefat">
                        </div>
                        <div class="raf-field"><label><?php esc_html_e( 'Max Days', 'rentafleet' ); ?></label>
                            <input type="number" name="max_days" value="<?php echo esc_attr( $editing ? $editing->max_days : 365 ); ?>" min="1" class="widefat">
                        </div>
                        <div class="raf-field"><label><?php esc_html_e( 'Status', 'rentafleet' ); ?></label>
                            <select name="status" class="widefat">
                                <option value="active" <?php $editing && selected( $editing->status, 'active' ); ?>><?php esc_html_e( 'Active', 'rentafleet' ); ?></option>
                                <option value="inactive" <?php $editing && selected( $editing->status, 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'rentafleet' ); ?></option>
                            </select>
                        </div>

                        <p>
                            <button type="submit" class="button button-primary"><?php echo $editing ? esc_html__( 'Update Rate', 'rentafleet' ) : esc_html__( 'Add Rate', 'rentafleet' ); ?></button>
                            <?php if ( $editing ) : ?>
                                <a href="<?php echo esc_url( RAF_Admin::admin_url( 'raf-pricing', array( 'tab' => 'rates' ) ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'rentafleet' ); ?></a>
                            <?php endif; ?>
                        </p>
                    </form>
                </div>
            </div>

            <div class="raf-col-list">
                <table class="wp-list-table widefat fixed striped">
                    <thead><tr>
                        <th><?php esc_html_e( 'Name', 'rentafleet' ); ?></th>
                        <th><?php esc_html_e( 'Vehicle / Category', 'rentafleet' ); ?></th>
                        <th><?php esc_html_e( 'Daily', 'rentafleet' ); ?></th>
                        <th><?php esc_html_e( 'Weekend', 'rentafleet' ); ?></th>
                        <th><?php esc_html_e( 'Weekly', 'rentafleet' ); ?></th>
                        <th><?php esc_html_e( 'Monthly', 'rentafleet' ); ?></th>
                        <th><?php esc_html_e( 'Days Range', 'rentafleet' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'rentafleet' ); ?></th>
                    </tr></thead>
                    <tbody>
                    <?php if ( empty( $rates ) ) : ?>
                        <tr><td colspan="8"><?php esc_html_e( 'No rates yet. Add your first rate.', 'rentafleet' ); ?></td></tr>
                    <?php else : foreach ( $rates as $r ) :
                        $edit_url = RAF_Admin::admin_url( 'raf-pricing', array( 'tab' => 'rates', 'edit_id' => $r->id ) );
                        $del_url  = wp_nonce_url( RAF_Admin::admin_url( 'raf-pricing', array( 'tab' => 'rates', 'raf_action' => 'delete_rate', 'del_id' => $r->id ) ), 'raf_delete_rate_' . $r->id );
                        $assign   = $r->vehicle_name ? $r->vehicle_name : ( $r->category_name ? '<em>' . esc_html( $r->category_name ) . '</em> (cat)' : '—' );
                        ?>
                        <tr>
                            <td><strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $r->name ? $r->name : '#' . $r->id ); ?></a></strong>
                                <div class="row-actions"><span class="edit"><a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'rentafleet' ); ?></a> | </span><span class="trash"><a href="<?php echo esc_url( $del_url ); ?>" onclick="return confirm('Delete?');"><?php esc_html_e( 'Delete', 'rentafleet' ); ?></a></span></div></td>
                            <td><?php echo $assign; ?></td>
                            <td><?php echo RAF_Helpers::format_price( $r->price ); ?></td>
                            <td><?php echo $r->weekend_price ? RAF_Helpers::format_price( $r->weekend_price ) : '—'; ?></td>
                            <td><?php echo $r->weekly_price ? RAF_Helpers::format_price( $r->weekly_price ) : '—'; ?></td>
                            <td><?php echo $r->monthly_price ? RAF_Helpers::format_price( $r->monthly_price ) : '—'; ?></td>
                            <td><?php echo esc_html( $r->min_days . ' – ' . $r->max_days ); ?></td>
                            <td><?php echo RAF_Helpers::status_badge( $r->status ); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /* ================================================================
     *  TAB 2: SEASONAL RATES
     * ============================================================= */

    private static function render_seasonal() {
        global $wpdb;
        $t_sr  = RAF_Helpers::table( 'seasonal_rates' );
        $t_veh = RAF_Helpers::table( 'vehicles' );
        $t_cat = RAF_Helpers::table( 'vehicle_categories' );

        $edit_id = isset( $_GET['edit_id'] ) ? absint( $_GET['edit_id'] ) : 0;
        $editing = $edit_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t_sr WHERE id = %d", $edit_id ) ) : null;

        $seasonals = $wpdb->get_results(
            "SELECT s.*, v.name AS vehicle_name, c.name AS category_name
             FROM $t_sr s
             LEFT JOIN $t_veh v ON s.vehicle_id = v.id
             LEFT JOIN $t_cat c ON s.category_id = c.id
             ORDER BY s.date_from DESC"
        );
        $vehicles   = $wpdb->get_results( "SELECT id, name FROM $t_veh WHERE status='active' ORDER BY name" );
        $categories = RAF_Admin::get_categories_dropdown();
        unset( $categories[''] );

        ?>
        <div class="raf-two-col-layout">
            <div class="raf-col-form" style="flex-basis:400px;">
                <div class="raf-panel">
                    <h2><?php echo $editing ? esc_html__( 'Edit Seasonal Rate', 'rentafleet' ) : esc_html__( 'Add Seasonal Rate', 'rentafleet' ); ?></h2>
                    <form method="post">
                        <input type="hidden" name="raf_action" value="save_seasonal">
                        <input type="hidden" name="page" value="raf-pricing">
                        <input type="hidden" name="item_id" value="<?php echo esc_attr( $editing ? $editing->id : 0 ); ?>">
                        <?php RAF_Admin::nonce_field( 'save_seasonal' ); ?>

                        <div class="raf-field"><label><?php esc_html_e( 'Name *', 'rentafleet' ); ?></label>
                            <input type="text" name="name" value="<?php echo esc_attr( $editing ? $editing->name : '' ); ?>" class="widefat" required placeholder="<?php esc_attr_e( 'e.g. Summer Peak, Christmas', 'rentafleet' ); ?>">
                        </div>
                        <div class="raf-field-row">
                            <div class="raf-field"><label><?php esc_html_e( 'From *', 'rentafleet' ); ?></label>
                                <input type="date" name="date_from" value="<?php echo esc_attr( $editing ? $editing->date_from : '' ); ?>" class="widefat" required>
                            </div>
                            <div class="raf-field"><label><?php esc_html_e( 'To *', 'rentafleet' ); ?></label>
                                <input type="date" name="date_to" value="<?php echo esc_attr( $editing ? $editing->date_to : '' ); ?>" class="widefat" required>
                            </div>
                        </div>
                        <div class="raf-field"><label><?php esc_html_e( 'Vehicle', 'rentafleet' ); ?></label>
                            <select name="vehicle_id" class="widefat">
                                <option value=""><?php esc_html_e( '— All vehicles —', 'rentafleet' ); ?></option>
                                <?php foreach ( $vehicles as $v ) : ?>
                                    <option value="<?php echo esc_attr( $v->id ); ?>" <?php $editing && selected( $editing->vehicle_id, $v->id ); ?>><?php echo esc_html( $v->name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="raf-field"><label><?php esc_html_e( 'Category', 'rentafleet' ); ?></label>
                            <select name="category_id" class="widefat">
                                <option value=""><?php esc_html_e( '— All categories —', 'rentafleet' ); ?></option>
                                <?php foreach ( $categories as $cid => $cn ) : ?>
                                    <option value="<?php echo esc_attr( $cid ); ?>" <?php $editing && selected( $editing->category_id, $cid ); ?>><?php echo esc_html( $cn ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <hr>
                        <p class="description" style="padding:0 16px;"><?php esc_html_e( 'Set a fixed daily price OR a modifier to adjust the base rate.', 'rentafleet' ); ?></p>

                        <div class="raf-field"><label><?php esc_html_e( 'Daily Price Override', 'rentafleet' ); ?></label>
                            <input type="number" name="daily_price" value="<?php echo esc_attr( $editing ? $editing->daily_price : '' ); ?>" step="0.01" min="0" class="widefat">
                        </div>
                        <div class="raf-field"><label><?php esc_html_e( 'Weekend Price Override', 'rentafleet' ); ?></label>
                            <input type="number" name="weekend_price" value="<?php echo esc_attr( $editing ? $editing->weekend_price : '' ); ?>" step="0.01" min="0" class="widefat">
                        </div>
                        <div class="raf-field-row">
                            <div class="raf-field"><label><?php esc_html_e( 'Modifier Type', 'rentafleet' ); ?></label>
                                <select name="price_modifier_type" class="widefat">
                                    <option value="fixed" <?php $editing && selected( $editing->price_modifier_type, 'fixed' ); ?>><?php esc_html_e( 'Fixed (+/−)', 'rentafleet' ); ?></option>
                                    <option value="percentage" <?php $editing && selected( $editing->price_modifier_type, 'percentage' ); ?>><?php esc_html_e( 'Percentage (%)', 'rentafleet' ); ?></option>
                                </select>
                            </div>
                            <div class="raf-field"><label><?php esc_html_e( 'Modifier Value', 'rentafleet' ); ?></label>
                                <input type="number" name="price_modifier_value" value="<?php echo esc_attr( $editing ? $editing->price_modifier_value : '' ); ?>" step="0.01" class="widefat" placeholder="<?php esc_attr_e( 'e.g. 20 for +20% or +$20', 'rentafleet' ); ?>">
                            </div>
                        </div>

                        <div class="raf-field"><label><?php esc_html_e( 'Priority', 'rentafleet' ); ?></label>
                            <input type="number" name="priority" value="<?php echo esc_attr( $editing ? $editing->priority : 0 ); ?>" min="0" class="widefat">
                            <p class="description"><?php esc_html_e( 'Higher = takes precedence when dates overlap.', 'rentafleet' ); ?></p>
                        </div>
                        <div class="raf-field"><label><?php esc_html_e( 'Status', 'rentafleet' ); ?></label>
                            <select name="status" class="widefat">
                                <option value="active" <?php $editing && selected( $editing->status, 'active' ); ?>><?php esc_html_e( 'Active', 'rentafleet' ); ?></option>
                                <option value="inactive" <?php $editing && selected( $editing->status, 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'rentafleet' ); ?></option>
                            </select>
                        </div>
                        <p>
                            <button type="submit" class="button button-primary"><?php echo $editing ? esc_html__( 'Update', 'rentafleet' ) : esc_html__( 'Add Seasonal Rate', 'rentafleet' ); ?></button>
                            <?php if ( $editing ) : ?>
                                <a href="<?php echo esc_url( RAF_Admin::admin_url( 'raf-pricing', array( 'tab' => 'seasonal' ) ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'rentafleet' ); ?></a>
                            <?php endif; ?>
                        </p>
                    </form>
                </div>
            </div>
            <div class="raf-col-list">
                <table class="wp-list-table widefat fixed striped">
                    <thead><tr>
                        <th><?php esc_html_e( 'Name', 'rentafleet' ); ?></th>
                        <th><?php esc_html_e( 'Dates', 'rentafleet' ); ?></th>
                        <th><?php esc_html_e( 'Applies To', 'rentafleet' ); ?></th>
                        <th><?php esc_html_e( 'Daily', 'rentafleet' ); ?></th>
                        <th><?php esc_html_e( 'Modifier', 'rentafleet' ); ?></th>
                        <th><?php esc_html_e( 'Priority', 'rentafleet' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'rentafleet' ); ?></th>
                    </tr></thead>
                    <tbody>
                    <?php if ( empty( $seasonals ) ) : ?>
                        <tr><td colspan="7"><?php esc_html_e( 'No seasonal rates.', 'rentafleet' ); ?></td></tr>
                    <?php else : foreach ( $seasonals as $s ) :
                        $eu = RAF_Admin::admin_url( 'raf-pricing', array( 'tab' => 'seasonal', 'edit_id' => $s->id ) );
                        $du = wp_nonce_url( RAF_Admin::admin_url( 'raf-pricing', array( 'tab' => 'seasonal', 'raf_action' => 'delete_seasonal', 'del_id' => $s->id ) ), 'raf_delete_seasonal_' . $s->id );
                        $applies = $s->vehicle_name ? $s->vehicle_name : ( $s->category_name ? $s->category_name . ' (cat)' : __( 'All', 'rentafleet' ) );
                        $mod = '';
                        if ( $s->price_modifier_value ) {
                            $mod = ( $s->price_modifier_type === 'percentage' ? $s->price_modifier_value . '%' : RAF_Helpers::format_price( $s->price_modifier_value ) );
                            if ( $s->price_modifier_value > 0 ) $mod = '+' . $mod;
                        }
                        ?>
                        <tr>
                            <td><strong><a href="<?php echo esc_url( $eu ); ?>"><?php echo esc_html( $s->name ); ?></a></strong>
                                <div class="row-actions"><span class="edit"><a href="<?php echo esc_url( $eu ); ?>"><?php esc_html_e( 'Edit', 'rentafleet' ); ?></a> | </span><span class="trash"><a href="<?php echo esc_url( $du ); ?>" onclick="return confirm('Delete?');"><?php esc_html_e( 'Delete', 'rentafleet' ); ?></a></span></div></td>
                            <td><?php echo esc_html( RAF_Helpers::format_date( $s->date_from ) . ' → ' . RAF_Helpers::format_date( $s->date_to ) ); ?></td>
                            <td><?php echo esc_html( $applies ); ?></td>
                            <td><?php echo $s->daily_price ? RAF_Helpers::format_price( $s->daily_price ) : '—'; ?></td>
                            <td><?php echo $mod ? esc_html( $mod ) : '—'; ?></td>
                            <td><?php echo esc_html( $s->priority ); ?></td>
                            <td><?php echo RAF_Helpers::status_badge( $s->status ); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /* ================================================================
     *  TAB 3: EXTRAS
     * ============================================================= */

    private static function render_extras() {
        global $wpdb;
        $t = RAF_Helpers::table( 'extras' );

        $action  = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : '';
        $edit_id = isset( $_GET['edit_id'] ) ? absint( $_GET['edit_id'] ) : 0;

        if ( $action === 'edit_extra' && $edit_id ) {
            self::render_extra_form( $edit_id );
            return;
        }
        if ( $action === 'add_extra' ) {
            self::render_extra_form( 0 );
            return;
        }

        $extras = $wpdb->get_results( "SELECT * FROM $t ORDER BY sort_order ASC, name ASC" );
        ?>
        <p>
            <a href="<?php echo esc_url( RAF_Admin::admin_url( 'raf-pricing', array( 'tab' => 'extras', 'action' => 'add_extra' ) ) ); ?>" class="button button-primary"><?php esc_html_e( 'Add New Extra', 'rentafleet' ); ?></a>
        </p>
        <?php
        // Pre-fetch units used per extra from active/confirmed/pending bookings
        global $wpdb;
        $used_rows = $wpdb->get_results(
            "SELECT be.extra_id, SUM(be.quantity) as used_qty
             FROM " . RAF_Helpers::table('booking_extras') . " be
             INNER JOIN " . RAF_Helpers::table('bookings') . " b ON b.id = be.booking_id
             WHERE b.status NOT IN ('cancelled','refunded')
             GROUP BY be.extra_id"
        );
        $used_map = array();
        foreach ( $used_rows as $r ) {
            $used_map[ $r->extra_id ] = (int) $r->used_qty;
        }
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead><tr>
                <th><?php esc_html_e( 'Name', 'rentafleet' ); ?></th>
                <th><?php esc_html_e( 'Price', 'rentafleet' ); ?></th>
                <th><?php esc_html_e( 'Type', 'rentafleet' ); ?></th>
                <th><?php esc_html_e( 'Max Qty / Booking', 'rentafleet' ); ?></th>
                <th><?php esc_html_e( 'Stock', 'rentafleet' ); ?></th>
                <th><?php esc_html_e( 'Mandatory', 'rentafleet' ); ?></th>
                <th><?php esc_html_e( 'Status', 'rentafleet' ); ?></th>
            </tr></thead>
            <tbody>
            <?php if ( empty( $extras ) ) : ?>
                <tr><td colspan="7"><?php esc_html_e( 'No extras. Create GPS, child seats, etc.', 'rentafleet' ); ?></td></tr>
            <?php else : foreach ( $extras as $ex ) :
                $eu   = RAF_Admin::admin_url( 'raf-pricing', array( 'tab' => 'extras', 'action' => 'edit_extra', 'edit_id' => $ex->id ) );
                $du   = wp_nonce_url( RAF_Admin::admin_url( 'raf-pricing', array( 'tab' => 'extras', 'raf_action' => 'delete_extra', 'del_id' => $ex->id ) ), 'raf_delete_extra_' . $ex->id );
                $stock = (int) ( $ex->stock_quantity ?? 0 );
                $used  = (int) ( $used_map[ $ex->id ] ?? 0 );
                ?>
                <tr>
                    <td><strong><a href="<?php echo esc_url( $eu ); ?>"><?php echo esc_html( $ex->name ); ?></a></strong>
                        <div class="row-actions"><span class="edit"><a href="<?php echo esc_url( $eu ); ?>"><?php esc_html_e( 'Edit', 'rentafleet' ); ?></a> | </span><span class="trash"><a href="<?php echo esc_url( $du ); ?>" onclick="return confirm('Delete?');"><?php esc_html_e( 'Delete', 'rentafleet' ); ?></a></span></div></td>
                    <td><?php echo RAF_Helpers::format_price( $ex->price ); ?></td>
                    <td><?php echo esc_html( $ex->price_type === 'per_rental' ? __( 'Per Rental', 'rentafleet' ) : __( 'Per Day', 'rentafleet' ) ); ?></td>
                    <td><?php echo esc_html( $ex->max_quantity ); ?></td>
                    <td>
                        <?php if ( $stock > 0 ) : ?>
                            <span title="<?php echo esc_attr( $used . ' used in active bookings' ); ?>">
                                <?php echo esc_html( $used . ' / ' . $stock ); ?>
                            </span>
                            <?php if ( $used >= $stock ) : ?>
                                <span style="color:#c00;font-weight:600;"> ⚠ <?php esc_html_e( 'Out', 'rentafleet' ); ?></span>
                            <?php endif; ?>
                        <?php else : ?>
                            <span style="color:#888;"><?php esc_html_e( 'Unlimited', 'rentafleet' ); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $ex->is_mandatory ? '✓' : '—'; ?></td>
                    <td><?php echo RAF_Helpers::status_badge( $ex->status ); ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        <?php
    }

    private static function render_extra_form( $id ) {
        $extra = $id ? RAF_Extra::get( $id ) : null;
        $is_edit = ! empty( $extra );

        $d = $is_edit ? $extra : (object) array(
            'id' => 0, 'name' => '', 'slug' => '', 'description' => '', 'price' => '',
            'price_type' => 'per_day', 'max_quantity' => 1, 'stock_quantity' => 0, 'image_id' => 0,
            'is_mandatory' => 0, 'vehicle_ids' => '', 'category_ids' => '', 'location_ids' => '',
            'sort_order' => 0, 'status' => 'active',
        );

        global $wpdb;
        $vehicles   = $wpdb->get_results( "SELECT id, name FROM " . RAF_Helpers::table( 'vehicles' ) . " WHERE status='active' ORDER BY name" );
        $categories = RAF_Admin::get_categories_dropdown(); unset( $categories[''] );
        $locations  = RAF_Admin::get_locations_dropdown();

        $sel_vehicles   = $is_edit && $d->vehicle_ids ? json_decode( $d->vehicle_ids, true ) : array();
        $sel_categories = $is_edit && $d->category_ids ? json_decode( $d->category_ids, true ) : array();
        $sel_locations  = $is_edit && $d->location_ids ? json_decode( $d->location_ids, true ) : array();
        if ( ! is_array( $sel_vehicles ) )   $sel_vehicles   = array();
        if ( ! is_array( $sel_categories ) ) $sel_categories = array();
        if ( ! is_array( $sel_locations ) )  $sel_locations  = array();

        ?>
        <?php RAF_Admin::back_link( 'raf-pricing', __( '← Back to Extras', 'rentafleet' ) ); ?>
        <div class="raf-panel" style="max-width:700px;margin-top:12px;">
            <h2><?php echo $is_edit ? esc_html__( 'Edit Extra', 'rentafleet' ) : esc_html__( 'Add Extra', 'rentafleet' ); ?></h2>
            <form method="post" class="raf-form">
                <input type="hidden" name="raf_action" value="save_extra">
                <input type="hidden" name="page" value="raf-pricing">
                <input type="hidden" name="item_id" value="<?php echo esc_attr( $d->id ); ?>">
                <?php RAF_Admin::nonce_field( 'save_extra' ); ?>

                <table class="form-table">
                    <tr><th><label><?php esc_html_e( 'Name *', 'rentafleet' ); ?></label></th>
                        <td><input type="text" name="name" value="<?php echo esc_attr( $d->name ); ?>" class="regular-text" required></td></tr>
                    <tr><th><label><?php esc_html_e( 'Description', 'rentafleet' ); ?></label></th>
                        <td><textarea name="description" rows="3" class="large-text"><?php echo esc_textarea( $d->description ); ?></textarea></td></tr>
                    <tr><th><label><?php esc_html_e( 'Price *', 'rentafleet' ); ?></label></th>
                        <td><input type="number" name="price" value="<?php echo esc_attr( $d->price ); ?>" step="0.01" min="0" class="small-text" required></td></tr>
                    <tr><th><label><?php esc_html_e( 'Price Type', 'rentafleet' ); ?></label></th>
                        <td><select name="price_type">
                            <option value="per_day" <?php selected( $d->price_type, 'per_day' ); ?>><?php esc_html_e( 'Per Day', 'rentafleet' ); ?></option>
                            <option value="per_rental" <?php selected( $d->price_type, 'per_rental' ); ?>><?php esc_html_e( 'Per Rental (one-time)', 'rentafleet' ); ?></option>
                        </select></td></tr>
                    <tr><th><label><?php esc_html_e( 'Max Quantity', 'rentafleet' ); ?></label></th>
                        <td><input type="number" name="max_quantity" value="<?php echo esc_attr( $d->max_quantity ); ?>" min="1" class="small-text">
                        <p class="description"><?php esc_html_e( 'Maximum a customer can select per booking.', 'rentafleet' ); ?></p></td></tr>
                    <tr><th><label><?php esc_html_e( 'Stock Quantity', 'rentafleet' ); ?></label></th>
                        <td><input type="number" name="stock_quantity" value="<?php echo esc_attr( $d->stock_quantity ?? 0 ); ?>" min="0" class="small-text">
                        <p class="description"><?php esc_html_e( 'Total units your company owns. Set to 0 for unlimited.', 'rentafleet' ); ?></p></td></tr>
                    <tr><th><label><?php esc_html_e( 'Mandatory', 'rentafleet' ); ?></label></th>
                        <td><label><input type="checkbox" name="is_mandatory" value="1" <?php checked( $d->is_mandatory ); ?>> <?php esc_html_e( 'Auto-add to every booking', 'rentafleet' ); ?></label></td></tr>
                    <tr><th><label><?php esc_html_e( 'Restrict to Vehicles', 'rentafleet' ); ?></label></th>
                        <td><select name="vehicle_ids[]" multiple style="height:120px;width:100%;">
                            <?php foreach ( $vehicles as $v ) : ?>
                                <option value="<?php echo esc_attr( $v->id ); ?>" <?php echo in_array( $v->id, $sel_vehicles ) ? 'selected' : ''; ?>><?php echo esc_html( $v->name ); ?></option>
                            <?php endforeach; ?>
                        </select><p class="description"><?php esc_html_e( 'Leave empty = available for all vehicles. Ctrl/Cmd+click to multi-select.', 'rentafleet' ); ?></p></td></tr>
                    <tr><th><label><?php esc_html_e( 'Restrict to Categories', 'rentafleet' ); ?></label></th>
                        <td><select name="category_ids[]" multiple style="height:80px;width:100%;">
                            <?php foreach ( $categories as $cid => $cn ) : ?>
                                <option value="<?php echo esc_attr( $cid ); ?>" <?php echo in_array( $cid, $sel_categories ) ? 'selected' : ''; ?>><?php echo esc_html( $cn ); ?></option>
                            <?php endforeach; ?>
                        </select></td></tr>
                    <tr><th><label><?php esc_html_e( 'Restrict to Locations', 'rentafleet' ); ?></label></th>
                        <td><select name="location_ids[]" multiple style="height:80px;width:100%;">
                            <?php foreach ( $locations as $lid => $ln ) : ?>
                                <option value="<?php echo esc_attr( $lid ); ?>" <?php echo in_array( $lid, $sel_locations ) ? 'selected' : ''; ?>><?php echo esc_html( $ln ); ?></option>
                            <?php endforeach; ?>
                        </select></td></tr>
                    <tr><th><label><?php esc_html_e( 'Sort Order', 'rentafleet' ); ?></label></th>
                        <td><input type="number" name="sort_order" value="<?php echo esc_attr( $d->sort_order ); ?>" min="0" class="small-text"></td></tr>
                    <tr><th><label><?php esc_html_e( 'Status', 'rentafleet' ); ?></label></th>
                        <td><select name="status"><option value="active" <?php selected( $d->status, 'active' ); ?>><?php esc_html_e( 'Active', 'rentafleet' ); ?></option><option value="inactive" <?php selected( $d->status, 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'rentafleet' ); ?></option></select></td></tr>
                </table>
                <p style="padding:0 16px 16px;">
                    <button type="submit" class="button button-primary"><?php echo $is_edit ? esc_html__( 'Update Extra', 'rentafleet' ) : esc_html__( 'Add Extra', 'rentafleet' ); ?></button>
                </p>
            </form>
        </div>
        <?php
    }

    /* ================================================================
     *  TAB 4: INSURANCE
     * ============================================================= */

    private static function render_insurance() {
        global $wpdb;
        $t = RAF_Helpers::table( 'insurance' );

        $edit_id = isset( $_GET['edit_id'] ) ? absint( $_GET['edit_id'] ) : 0;
        $editing = $edit_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", $edit_id ) ) : null;

        $items = $wpdb->get_results( "SELECT * FROM $t ORDER BY sort_order ASC, name ASC" );

        $d = $editing ? $editing : (object) array(
            'id' => 0, 'name' => '', 'slug' => '', 'description' => '', 'type' => 'cdw',
            'price_per_day' => '', 'price_per_rental' => '', 'coverage_amount' => '0.00',
            'deductible' => '0.00', 'is_mandatory' => 0, 'sort_order' => 0, 'status' => 'active',
        );

        $types = array(
            'cdw'   => __( 'CDW — Collision Damage Waiver', 'rentafleet' ),
            'ldw'   => __( 'LDW — Loss Damage Waiver', 'rentafleet' ),
            'scdw'  => __( 'SCDW — Super CDW', 'rentafleet' ),
            'tp'    => __( 'TP — Theft Protection', 'rentafleet' ),
            'pai'   => __( 'PAI — Personal Accident', 'rentafleet' ),
            'pep'   => __( 'PEP — Personal Effects', 'rentafleet' ),
            'rsa'   => __( 'RSA — Roadside Assistance', 'rentafleet' ),
            'other' => __( 'Other', 'rentafleet' ),
        );

        ?>
        <div class="raf-two-col-layout">
            <div class="raf-col-form" style="flex-basis:380px;">
                <div class="raf-panel">
                    <h2><?php echo $editing ? esc_html__( 'Edit Insurance', 'rentafleet' ) : esc_html__( 'Add Insurance', 'rentafleet' ); ?></h2>
                    <form method="post">
                        <input type="hidden" name="raf_action" value="save_insurance">
                        <input type="hidden" name="page" value="raf-pricing">
                        <input type="hidden" name="item_id" value="<?php echo esc_attr( $d->id ); ?>">
                        <?php RAF_Admin::nonce_field( 'save_insurance' ); ?>

                        <div class="raf-field"><label><?php esc_html_e( 'Name *', 'rentafleet' ); ?></label>
                            <input type="text" name="name" value="<?php echo esc_attr( $d->name ); ?>" class="widefat" required></div>
                        <div class="raf-field"><label><?php esc_html_e( 'Type', 'rentafleet' ); ?></label>
                            <select name="type" class="widefat">
                                <?php foreach ( $types as $tk => $tl ) : ?>
                                    <option value="<?php echo esc_attr( $tk ); ?>" <?php selected( $d->type, $tk ); ?>><?php echo esc_html( $tl ); ?></option>
                                <?php endforeach; ?>
                            </select></div>
                        <div class="raf-field"><label><?php esc_html_e( 'Description', 'rentafleet' ); ?></label>
                            <textarea name="description" rows="3" class="widefat"><?php echo esc_textarea( $d->description ); ?></textarea></div>
                        <div class="raf-field"><label><?php esc_html_e( 'Price Per Day *', 'rentafleet' ); ?></label>
                            <input type="number" name="price_per_day" value="<?php echo esc_attr( $d->price_per_day ); ?>" step="0.01" min="0" class="widefat" required></div>
                        <div class="raf-field"><label><?php esc_html_e( 'Price Per Rental', 'rentafleet' ); ?></label>
                            <input type="number" name="price_per_rental" value="<?php echo esc_attr( $d->price_per_rental ); ?>" step="0.01" min="0" class="widefat">
                            <p class="description"><?php esc_html_e( 'If set, used instead of per-day pricing.', 'rentafleet' ); ?></p></div>
                        <div class="raf-field"><label><?php esc_html_e( 'Coverage Amount', 'rentafleet' ); ?></label>
                            <input type="number" name="coverage_amount" value="<?php echo esc_attr( $d->coverage_amount ); ?>" step="0.01" min="0" class="widefat"></div>
                        <div class="raf-field"><label><?php esc_html_e( 'Deductible', 'rentafleet' ); ?></label>
                            <input type="number" name="deductible" value="<?php echo esc_attr( $d->deductible ); ?>" step="0.01" min="0" class="widefat"></div>
                        <div class="raf-field"><label><input type="checkbox" name="is_mandatory" value="1" <?php checked( $d->is_mandatory ); ?>> <?php esc_html_e( 'Mandatory — auto-added to all bookings', 'rentafleet' ); ?></label></div>
                        <div class="raf-field"><label><?php esc_html_e( 'Sort Order', 'rentafleet' ); ?></label>
                            <input type="number" name="sort_order" value="<?php echo esc_attr( $d->sort_order ); ?>" min="0" class="widefat"></div>
                        <div class="raf-field"><label><?php esc_html_e( 'Status', 'rentafleet' ); ?></label>
                            <select name="status" class="widefat"><option value="active" <?php selected( $d->status, 'active' ); ?>><?php esc_html_e( 'Active', 'rentafleet' ); ?></option><option value="inactive" <?php selected( $d->status, 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'rentafleet' ); ?></option></select></div>
                        <p>
                            <button type="submit" class="button button-primary"><?php echo $editing ? esc_html__( 'Update', 'rentafleet' ) : esc_html__( 'Add Insurance', 'rentafleet' ); ?></button>
                            <?php if ( $editing ) : ?>
                                <a href="<?php echo esc_url( RAF_Admin::admin_url( 'raf-pricing', array( 'tab' => 'insurance' ) ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'rentafleet' ); ?></a>
                            <?php endif; ?>
                        </p>
                    </form>
                </div>
            </div>
            <div class="raf-col-list">
                <table class="wp-list-table widefat fixed striped">
                    <thead><tr>
                        <th><?php esc_html_e( 'Name', 'rentafleet' ); ?></th>
                        <th><?php esc_html_e( 'Type', 'rentafleet' ); ?></th>
                        <th><?php esc_html_e( '/Day', 'rentafleet' ); ?></th>
                        <th><?php esc_html_e( '/Rental', 'rentafleet' ); ?></th>
                        <th><?php esc_html_e( 'Coverage', 'rentafleet' ); ?></th>
                        <th><?php esc_html_e( 'Deductible', 'rentafleet' ); ?></th>
                        <th><?php esc_html_e( 'Mandatory', 'rentafleet' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'rentafleet' ); ?></th>
                    </tr></thead>
                    <tbody>
                    <?php if ( empty( $items ) ) : ?>
                        <tr><td colspan="8"><?php esc_html_e( 'No insurance types.', 'rentafleet' ); ?></td></tr>
                    <?php else : foreach ( $items as $ins ) :
                        $eu = RAF_Admin::admin_url( 'raf-pricing', array( 'tab' => 'insurance', 'edit_id' => $ins->id ) );
                        $du = wp_nonce_url( RAF_Admin::admin_url( 'raf-pricing', array( 'tab' => 'insurance', 'raf_action' => 'delete_insurance', 'del_id' => $ins->id ) ), 'raf_delete_insurance_item_' . $ins->id );
                        ?>
                        <tr>
                            <td><strong><a href="<?php echo esc_url( $eu ); ?>"><?php echo esc_html( $ins->name ); ?></a></strong>
                                <div class="row-actions"><span class="edit"><a href="<?php echo esc_url( $eu ); ?>"><?php esc_html_e( 'Edit', 'rentafleet' ); ?></a> | </span><span class="trash"><a href="<?php echo esc_url( $du ); ?>" onclick="return confirm('Delete?');"><?php esc_html_e( 'Delete', 'rentafleet' ); ?></a></span></div></td>
                            <td><?php echo esc_html( strtoupper( $ins->type ) ); ?></td>
                            <td><?php echo RAF_Helpers::format_price( $ins->price_per_day ); ?></td>
                            <td><?php echo $ins->price_per_rental ? RAF_Helpers::format_price( $ins->price_per_rental ) : '—'; ?></td>
                            <td><?php echo $ins->coverage_amount ? RAF_Helpers::format_price( $ins->coverage_amount ) : '—'; ?></td>
                            <td><?php echo $ins->deductible ? RAF_Helpers::format_price( $ins->deductible ) : '—'; ?></td>
                            <td><?php echo $ins->is_mandatory ? '✓' : '—'; ?></td>
                            <td><?php echo RAF_Helpers::status_badge( $ins->status ); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /* ================================================================
     *  TAB 5: TAX RATES
     * ============================================================= */

    private static function render_tax() {
        global $wpdb;
        $t = RAF_Helpers::table( 'tax_rates' );

        $edit_id = isset( $_GET['edit_id'] ) ? absint( $_GET['edit_id'] ) : 0;
        $editing = $edit_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", $edit_id ) ) : null;

        $taxes = $wpdb->get_results( "SELECT * FROM $t ORDER BY priority DESC, country, state" );

        $d = $editing ? $editing : (object) array(
            'id' => 0, 'name' => '', 'rate' => '', 'type' => 'percentage',
            'country' => '', 'state' => '', 'city' => '', 'applies_to' => 'all',
            'priority' => 0, 'status' => 'active',
        );

        ?>
        <div class="raf-two-col-layout">
            <div class="raf-col-form">
                <div class="raf-panel">
                    <h2><?php echo $editing ? esc_html__( 'Edit Tax Rate', 'rentafleet' ) : esc_html__( 'Add Tax Rate', 'rentafleet' ); ?></h2>
                    <form method="post">
                        <input type="hidden" name="raf_action" value="save_tax">
                        <input type="hidden" name="page" value="raf-pricing">
                        <input type="hidden" name="item_id" value="<?php echo esc_attr( $d->id ); ?>">
                        <?php RAF_Admin::nonce_field( 'save_tax' ); ?>

                        <div class="raf-field"><label><?php esc_html_e( 'Name *', 'rentafleet' ); ?></label>
                            <input type="text" name="name" value="<?php echo esc_attr( $d->name ); ?>" class="widefat" required placeholder="<?php esc_attr_e( 'e.g. VAT, State Tax, City Tax', 'rentafleet' ); ?>"></div>
                        <div class="raf-field"><label><?php esc_html_e( 'Rate (%) *', 'rentafleet' ); ?></label>
                            <input type="number" name="rate" value="<?php echo esc_attr( $d->rate ); ?>" step="0.001" min="0" max="100" class="widefat" required></div>
                        <div class="raf-field"><label><?php esc_html_e( 'Country', 'rentafleet' ); ?></label>
                            <input type="text" name="country" value="<?php echo esc_attr( $d->country ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'Leave empty for all', 'rentafleet' ); ?>"></div>
                        <div class="raf-field"><label><?php esc_html_e( 'State / Province', 'rentafleet' ); ?></label>
                            <input type="text" name="state" value="<?php echo esc_attr( $d->state ); ?>" class="widefat"></div>
                        <div class="raf-field"><label><?php esc_html_e( 'City', 'rentafleet' ); ?></label>
                            <input type="text" name="city" value="<?php echo esc_attr( $d->city ); ?>" class="widefat"></div>
                        <div class="raf-field"><label><?php esc_html_e( 'Applies To', 'rentafleet' ); ?></label>
                            <select name="applies_to" class="widefat">
                                <option value="all" <?php selected( $d->applies_to, 'all' ); ?>><?php esc_html_e( 'Everything (rental + extras)', 'rentafleet' ); ?></option>
                                <option value="rental" <?php selected( $d->applies_to, 'rental' ); ?>><?php esc_html_e( 'Rental only', 'rentafleet' ); ?></option>
                                <option value="extras" <?php selected( $d->applies_to, 'extras' ); ?>><?php esc_html_e( 'Extras only', 'rentafleet' ); ?></option>
                            </select></div>
                        <div class="raf-field"><label><?php esc_html_e( 'Priority', 'rentafleet' ); ?></label>
                            <input type="number" name="priority" value="<?php echo esc_attr( $d->priority ); ?>" min="0" class="widefat">
                            <p class="description"><?php esc_html_e( 'Higher priority tax rules override lower ones for the same location.', 'rentafleet' ); ?></p></div>
                        <div class="raf-field"><label><?php esc_html_e( 'Status', 'rentafleet' ); ?></label>
                            <select name="status" class="widefat"><option value="active" <?php selected( $d->status, 'active' ); ?>><?php esc_html_e( 'Active', 'rentafleet' ); ?></option><option value="inactive" <?php selected( $d->status, 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'rentafleet' ); ?></option></select></div>
                        <p>
                            <button type="submit" class="button button-primary"><?php echo $editing ? esc_html__( 'Update', 'rentafleet' ) : esc_html__( 'Add Tax Rate', 'rentafleet' ); ?></button>
                            <?php if ( $editing ) : ?>
                                <a href="<?php echo esc_url( RAF_Admin::admin_url( 'raf-pricing', array( 'tab' => 'tax' ) ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'rentafleet' ); ?></a>
                            <?php endif; ?>
                        </p>
                    </form>
                </div>
                <?php /* Default tax note */ ?>
                <div class="raf-panel" style="padding:12px 16px;">
                    <p class="description"><?php printf( esc_html__( 'Default tax rate (when no location match): %s%%. Change in Settings → General.', 'rentafleet' ), esc_html( get_option( 'raf_default_tax_rate', 0 ) ) ); ?></p>
                </div>
            </div>
            <div class="raf-col-list">
                <table class="wp-list-table widefat fixed striped">
                    <thead><tr>
                        <th><?php esc_html_e( 'Name', 'rentafleet' ); ?></th>
                        <th><?php esc_html_e( 'Rate', 'rentafleet' ); ?></th>
                        <th><?php esc_html_e( 'Location', 'rentafleet' ); ?></th>
                        <th><?php esc_html_e( 'Applies To', 'rentafleet' ); ?></th>
                        <th><?php esc_html_e( 'Priority', 'rentafleet' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'rentafleet' ); ?></th>
                    </tr></thead>
                    <tbody>
                    <?php if ( empty( $taxes ) ) : ?>
                        <tr><td colspan="6"><?php esc_html_e( 'No tax rates. The default rate from Settings will be used.', 'rentafleet' ); ?></td></tr>
                    <?php else : foreach ( $taxes as $tx ) :
                        $eu = RAF_Admin::admin_url( 'raf-pricing', array( 'tab' => 'tax', 'edit_id' => $tx->id ) );
                        $du = wp_nonce_url( RAF_Admin::admin_url( 'raf-pricing', array( 'tab' => 'tax', 'raf_action' => 'delete_tax', 'del_id' => $tx->id ) ), 'raf_delete_tax_' . $tx->id );
                        $loc_parts = array_filter( array( $tx->city, $tx->state, $tx->country ) );
                        ?>
                        <tr>
                            <td><strong><a href="<?php echo esc_url( $eu ); ?>"><?php echo esc_html( $tx->name ); ?></a></strong>
                                <div class="row-actions"><span class="edit"><a href="<?php echo esc_url( $eu ); ?>"><?php esc_html_e( 'Edit', 'rentafleet' ); ?></a> | </span><span class="trash"><a href="<?php echo esc_url( $du ); ?>" onclick="return confirm('Delete?');"><?php esc_html_e( 'Delete', 'rentafleet' ); ?></a></span></div></td>
                            <td><strong><?php echo esc_html( $tx->rate ); ?>%</strong></td>
                            <td><?php echo $loc_parts ? esc_html( implode( ', ', $loc_parts ) ) : '<em>' . esc_html__( 'Global', 'rentafleet' ) . '</em>'; ?></td>
                            <td><?php echo esc_html( ucfirst( $tx->applies_to ) ); ?></td>
                            <td><?php echo esc_html( $tx->priority ); ?></td>
                            <td><?php echo RAF_Helpers::status_badge( $tx->status ); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /* ================================================================
     *  SAVE HANDLERS
     * ============================================================= */

    private static function handle_save_rate() {
        if ( ! RAF_Admin::verify_nonce( 'save_rate' ) ) { RAF_Admin::add_notice( __( 'Security check failed.', 'rentafleet' ), 'error' ); return; }
        $id = absint( $_POST['item_id'] );
        $assign = sanitize_text_field( $_POST['assign_type'] );

        $data = array(
            'name'          => sanitize_text_field( $_POST['name'] ),
            'vehicle_id'    => $assign === 'vehicle' ? absint( $_POST['vehicle_id'] ) : null,
            'category_id'   => $assign === 'category' ? absint( $_POST['category_id'] ) : null,
            'price'         => floatval( $_POST['price'] ),
            'weekend_price' => $_POST['weekend_price'] !== '' ? floatval( $_POST['weekend_price'] ) : null,
            'hourly_price'  => $_POST['hourly_price'] !== '' ? floatval( $_POST['hourly_price'] ) : null,
            'weekly_price'  => $_POST['weekly_price'] !== '' ? floatval( $_POST['weekly_price'] ) : null,
            'monthly_price' => $_POST['monthly_price'] !== '' ? floatval( $_POST['monthly_price'] ) : null,
            'min_days'      => max( 0, absint( $_POST['min_days'] ) ),
            'max_days'      => max( 1, absint( $_POST['max_days'] ) ),
            'status'        => sanitize_text_field( $_POST['status'] ),
        );

        if ( $id ) { RAF_Rate::update( $id, $data ); RAF_Admin::add_notice( __( 'Rate updated.', 'rentafleet' ) ); }
        else { RAF_Rate::create( $data ); RAF_Admin::add_notice( __( 'Rate created.', 'rentafleet' ) ); }
        RAF_Admin::redirect( 'raf-pricing', array( 'tab' => 'rates' ) );
    }

    private static function handle_save_seasonal() {
        if ( ! RAF_Admin::verify_nonce( 'save_seasonal' ) ) { RAF_Admin::add_notice( __( 'Security check failed.', 'rentafleet' ), 'error' ); return; }
        global $wpdb;
        $id = absint( $_POST['item_id'] );
        $t  = RAF_Helpers::table( 'seasonal_rates' );

        $data = array(
            'name'                 => sanitize_text_field( $_POST['name'] ),
            'vehicle_id'           => ! empty( $_POST['vehicle_id'] ) ? absint( $_POST['vehicle_id'] ) : null,
            'category_id'          => ! empty( $_POST['category_id'] ) ? absint( $_POST['category_id'] ) : null,
            'date_from'            => sanitize_text_field( $_POST['date_from'] ),
            'date_to'              => sanitize_text_field( $_POST['date_to'] ),
            'daily_price'          => $_POST['daily_price'] !== '' ? floatval( $_POST['daily_price'] ) : null,
            'weekend_price'        => $_POST['weekend_price'] !== '' ? floatval( $_POST['weekend_price'] ) : null,
            'price_modifier_type'  => sanitize_text_field( $_POST['price_modifier_type'] ),
            'price_modifier_value' => $_POST['price_modifier_value'] !== '' ? floatval( $_POST['price_modifier_value'] ) : null,
            'priority'             => absint( $_POST['priority'] ),
            'status'               => sanitize_text_field( $_POST['status'] ),
        );

        if ( $id ) { $wpdb->update( $t, $data, array( 'id' => $id ) ); RAF_Admin::add_notice( __( 'Seasonal rate updated.', 'rentafleet' ) ); }
        else { $data['created_at'] = current_time( 'mysql' ); $wpdb->insert( $t, $data ); RAF_Admin::add_notice( __( 'Seasonal rate created.', 'rentafleet' ) ); }
        RAF_Admin::redirect( 'raf-pricing', array( 'tab' => 'seasonal' ) );
    }

    private static function handle_save_extra() {
        if ( ! RAF_Admin::verify_nonce( 'save_extra' ) ) { RAF_Admin::add_notice( __( 'Security check failed.', 'rentafleet' ), 'error' ); return; }
        $id = absint( $_POST['item_id'] );

        $data = array(
            'name'          => sanitize_text_field( $_POST['name'] ),
            'description'   => sanitize_textarea_field( $_POST['description'] ),
            'price'         => floatval( $_POST['price'] ),
            'price_type'    => sanitize_text_field( $_POST['price_type'] ),
            'max_quantity'  => max( 1, absint( $_POST['max_quantity'] ) ),
            'stock_quantity'=> absint( $_POST['stock_quantity'] ?? 0 ),
            'is_mandatory'  => isset( $_POST['is_mandatory'] ) ? 1 : 0,
            'vehicle_ids'   => isset( $_POST['vehicle_ids'] ) ? array_map( 'absint', $_POST['vehicle_ids'] ) : array(),
            'category_ids'  => isset( $_POST['category_ids'] ) ? array_map( 'absint', $_POST['category_ids'] ) : array(),
            'location_ids'  => isset( $_POST['location_ids'] ) ? array_map( 'absint', $_POST['location_ids'] ) : array(),
            'sort_order'    => absint( $_POST['sort_order'] ),
            'status'        => sanitize_text_field( $_POST['status'] ),
        );

        if ( $id ) { RAF_Extra::update( $id, $data ); RAF_Admin::add_notice( __( 'Extra updated.', 'rentafleet' ) ); }
        else { RAF_Extra::create( $data ); RAF_Admin::add_notice( __( 'Extra created.', 'rentafleet' ) ); }
        RAF_Admin::redirect( 'raf-pricing', array( 'tab' => 'extras' ) );
    }

    private static function handle_save_insurance() {
        if ( ! RAF_Admin::verify_nonce( 'save_insurance' ) ) { RAF_Admin::add_notice( __( 'Security check failed.', 'rentafleet' ), 'error' ); return; }
        $id = absint( $_POST['item_id'] );

        $data = array(
            'name'              => sanitize_text_field( $_POST['name'] ),
            'slug'              => sanitize_title( $_POST['name'] ),
            'type'              => sanitize_text_field( $_POST['type'] ),
            'description'       => sanitize_textarea_field( $_POST['description'] ),
            'price_per_day'     => floatval( $_POST['price_per_day'] ),
            'price_per_rental'  => $_POST['price_per_rental'] !== '' ? floatval( $_POST['price_per_rental'] ) : null,
            'coverage_amount'   => floatval( $_POST['coverage_amount'] ),
            'deductible'        => floatval( $_POST['deductible'] ),
            'is_mandatory'      => isset( $_POST['is_mandatory'] ) ? 1 : 0,
            'sort_order'        => absint( $_POST['sort_order'] ),
            'status'            => sanitize_text_field( $_POST['status'] ),
        );

        if ( $id ) { RAF_Insurance::update( $id, $data ); RAF_Admin::add_notice( __( 'Insurance updated.', 'rentafleet' ) ); }
        else { RAF_Insurance::create( $data ); RAF_Admin::add_notice( __( 'Insurance created.', 'rentafleet' ) ); }
        RAF_Admin::redirect( 'raf-pricing', array( 'tab' => 'insurance' ) );
    }

    private static function handle_save_tax() {
        if ( ! RAF_Admin::verify_nonce( 'save_tax' ) ) { RAF_Admin::add_notice( __( 'Security check failed.', 'rentafleet' ), 'error' ); return; }
        global $wpdb;
        $id = absint( $_POST['item_id'] );
        $t  = RAF_Helpers::table( 'tax_rates' );

        $data = array(
            'name'       => sanitize_text_field( $_POST['name'] ),
            'rate'       => floatval( $_POST['rate'] ),
            'type'       => 'percentage',
            'country'    => sanitize_text_field( $_POST['country'] ),
            'state'      => sanitize_text_field( $_POST['state'] ),
            'city'       => sanitize_text_field( $_POST['city'] ),
            'applies_to' => sanitize_text_field( $_POST['applies_to'] ),
            'priority'   => absint( $_POST['priority'] ),
            'status'     => sanitize_text_field( $_POST['status'] ),
        );

        if ( $id ) { $wpdb->update( $t, $data, array( 'id' => $id ) ); RAF_Admin::add_notice( __( 'Tax rate updated.', 'rentafleet' ) ); }
        else { $data['created_at'] = current_time( 'mysql' ); $wpdb->insert( $t, $data ); RAF_Admin::add_notice( __( 'Tax rate created.', 'rentafleet' ) ); }
        RAF_Admin::redirect( 'raf-pricing', array( 'tab' => 'tax' ) );
    }

    /* ================================================================
     *  GENERIC DELETE HANDLER
     * ============================================================= */

    private static function handle_delete( $table_name, $nonce_slug ) {
        $id  = isset( $_GET['del_id'] ) ? absint( $_GET['del_id'] ) : 0;
        $tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'rates';

        if ( ! $id || ! wp_verify_nonce( $_GET['_wpnonce'], 'raf_delete_' . $nonce_slug . '_' . $id ) ) {
            RAF_Admin::add_notice( __( 'Invalid request.', 'rentafleet' ), 'error' );
            return;
        }

        global $wpdb;
        $wpdb->delete( RAF_Helpers::table( $table_name ), array( 'id' => $id ) );
        RAF_Admin::add_notice( __( 'Item deleted.', 'rentafleet' ) );
        RAF_Admin::redirect( 'raf-pricing', array( 'tab' => $tab ) );
    }
}
