<?php
/**
 * RentAFleet — Frontend Shortcodes
 *
 * Provides all customer-facing shortcodes for the booking flow.
 *
 * @package RentAFleet
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class RAF_Shortcodes {

    public function __construct() {
        add_shortcode( 'raf_search', array( $this, 'search_shortcode' ) );
        add_shortcode( 'raf_vehicles', array( $this, 'vehicles_shortcode' ) );
        add_shortcode( 'raf_booking', array( $this, 'booking_shortcode' ) );
        add_shortcode( 'raf_confirmation', array( $this, 'confirmation_shortcode' ) );
        add_shortcode( 'raf_my_bookings', array( $this, 'my_bookings_shortcode' ) );
    }

    /* ─────────────────────────────────────────────
     *  [raf_search] — Vehicle Search Form
     * ───────────────────────────────────────────── */
    public function search_shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'style'    => 'horizontal',
            'redirect' => '',
        ), $atts, 'raf_search' );

        $locations  = RAF_Location::get_all( array( 'type' => 'pickup' ) );
        $categories = $this->get_categories();
        $time_slots = RAF_Helpers::get_time_slots();
        $style_class = $atts['style'] === 'vertical' ? 'raf-search-vertical' : 'raf-search-horizontal';

        ob_start();
        ?>
        <div class="raf-search-form-wrap <?php echo esc_attr( $style_class ); ?>">
            <form class="raf-search-form" data-redirect="<?php echo esc_url( $atts['redirect'] ); ?>">
                <div class="raf-search-fields">
                    <div class="raf-field raf-field-location">
                        <label for="raf-pickup-location"><?php esc_html_e( 'Pick-up Location', 'rentafleet' ); ?></label>

                        <select id="raf-pickup-location" name="pickup_location_id" required>
                            <option value=""><?php esc_html_e( 'Select location...', 'rentafleet' ); ?></option>
                            <?php foreach ( $locations as $loc ) : ?>
                                <option value="<?php echo esc_attr( $loc->id ); ?>"><?php echo esc_html( $loc->name ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="raf-field raf-field-date">
                        <label for="raf-pickup-date"><?php esc_html_e( 'Pick-up Date', 'rentafleet' ); ?></label>
                        <input type="date" id="raf-pickup-date" name="pickup_date" required min="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>">
                    </div>

                    <div class="raf-field raf-field-time">
                        <label for="raf-pickup-time"><?php esc_html_e( 'Pick-up Time', 'rentafleet' ); ?></label>
                        <select id="raf-pickup-time" name="pickup_time">
                            <?php foreach ( $time_slots as $val => $label ) : ?>
                                <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $val, '10:00' ); ?>><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="raf-field raf-field-date">
                        <label for="raf-dropoff-date"><?php esc_html_e( 'Return Date', 'rentafleet' ); ?></label>
                        <input type="date" id="raf-dropoff-date" name="dropoff_date" required min="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>">
                    </div>

                    <div class="raf-field raf-field-time">
                        <label for="raf-dropoff-time"><?php esc_html_e( 'Return Time', 'rentafleet' ); ?></label>
                        <select id="raf-dropoff-time" name="dropoff_time">
                            <?php foreach ( $time_slots as $val => $label ) : ?>
                                <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $val, '10:00' ); ?>><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if ( ! empty( $categories ) ) : ?>
                    <div class="raf-field raf-field-category">
                        <label for="raf-category"><?php esc_html_e( 'Bike Type', 'rentafleet' ); ?></label>
                        <select id="raf-category" name="category_id">
                            <option value=""><?php esc_html_e( 'All Types', 'rentafleet' ); ?></option>
                            <?php foreach ( $categories as $cat ) : ?>
                                <option value="<?php echo esc_attr( $cat->id ); ?>"><?php echo esc_html( $cat->name ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div class="raf-field raf-field-different-dropoff">
                        <label>
                            <input type="checkbox" id="raf-different-dropoff" name="different_dropoff" value="1">
                            <?php esc_html_e( 'Return to different location', 'rentafleet' ); ?>
                        </label>
                    </div>

                    <div class="raf-field raf-field-dropoff-location" style="display:none;">
                        <label for="raf-dropoff-location"><?php esc_html_e( 'Return Location', 'rentafleet' ); ?></label>
                        <select id="raf-dropoff-location" name="dropoff_location_id">
                            <option value=""><?php esc_html_e( 'Same as pick-up', 'rentafleet' ); ?></option>
                            <?php
                            $dropoff_locations = RAF_Location::get_all( array( 'type' => 'dropoff' ) );
                            foreach ( $dropoff_locations as $loc ) : ?>
                                <option value="<?php echo esc_attr( $loc->id ); ?>"><?php echo esc_html( $loc->name ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="raf-field raf-field-submit">
                        <button type="submit" class="raf-btn raf-btn-primary raf-search-btn">
                            <?php esc_html_e( 'Search Available Vehicles', 'rentafleet' ); ?>
                        </button>
                    </div>
                </div>
            </form>

            <div class="raf-search-results" style="display:none;">
                <div class="raf-results-header">
                    <h3 class="raf-results-title"></h3>
                    <button type="button" class="raf-btn raf-btn-outline raf-modify-search"><?php esc_html_e( 'Modify Search', 'rentafleet' ); ?></button>
                </div>
                <div class="raf-results-grid"></div>
                <div class="raf-no-results" style="display:none;">
                    <p><?php esc_html_e( 'No vehicles available for the selected dates and location. Please try different dates or locations.', 'rentafleet' ); ?></p>
                </div>
            </div>

            <div class="raf-loading" style="display:none;">
                <div class="raf-spinner"></div>
                <p><?php esc_html_e( 'Searching available vehicles...', 'rentafleet' ); ?></p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /* ─────────────────────────────────────────────
     *  [raf_vehicles] — Vehicle Listing Page
     * ───────────────────────────────────────────── */
    public function vehicles_shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'category'    => '',
            'location'    => '',
            'limit'       => 12,
            'columns'     => 3,
            'show_filter' => 'yes',
        ), $atts, 'raf_vehicles' );

        $args = array( 'status' => 'active', 'limit' => intval( $atts['limit'] ) );
        if ( $atts['category'] ) {
            $args['category_id'] = intval( $atts['category'] );
        }

        $vehicles   = $atts['location'] ? RAF_Vehicle::get_by_location( intval( $atts['location'] ) ) : RAF_Vehicle::get_all( $args );
        $categories = $this->get_categories();
        $pricing    = new RAF_Pricing_Engine();

        ob_start();
        ?>
        <div class="raf-vehicles-wrap" data-columns="<?php echo esc_attr( $atts['columns'] ); ?>">

            <?php if ( $atts['show_filter'] === 'yes' && ! empty( $categories ) ) : ?>
            <div class="raf-vehicles-filter">
                <button type="button" class="raf-filter-btn active" data-category="all"><?php esc_html_e( 'All', 'rentafleet' ); ?></button>
                <?php foreach ( $categories as $cat ) : ?>
                    <button type="button" class="raf-filter-btn" data-category="<?php echo esc_attr( $cat->id ); ?>"><?php echo esc_html( $cat->name ); ?></button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="raf-vehicles-grid">
                <?php if ( empty( $vehicles ) ) : ?>
                    <p class="raf-no-vehicles"><?php esc_html_e( 'No vehicles available at this time.', 'rentafleet' ); ?></p>
                <?php else : ?>
                    <?php foreach ( $vehicles as $vehicle ) :
                        $daily_rate = $pricing->get_display_price( $vehicle->id );
                        $features   = RAF_Vehicle::get_features( $vehicle );
                        $rating     = RAF_Vehicle::get_average_rating( $vehicle->id );
                        $image_url  = $vehicle->featured_image_id ? wp_get_attachment_image_url( $vehicle->featured_image_id, 'medium_large' ) : '';
                        $bike_types = RAF_Helpers::get_bike_types();
                    ?>
                    <div class="raf-vehicle-card" data-category="<?php echo esc_attr( $vehicle->category_id ); ?>" data-vehicle-id="<?php echo esc_attr( $vehicle->id ); ?>">
                        <div class="raf-vehicle-image">
                            <?php if ( $image_url ) : ?>
                                <img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $vehicle->name ); ?>" loading="lazy">
                            <?php else : ?>
                                <div class="raf-vehicle-no-image"><span class="dashicons dashicons-motorcycle"></span></div>
                            <?php endif; ?>
                            <?php if ( $rating > 0 ) : ?>
                                <div class="raf-vehicle-rating">
                                    <span class="raf-stars"><?php echo esc_html( number_format( $rating, 1 ) ); ?></span>
                                    <span class="raf-star-icon">&#9733;</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="raf-vehicle-info">
                            <h3 class="raf-vehicle-name"><?php echo esc_html( $vehicle->name ); ?></h3>
                            <?php if ( $vehicle->short_description ) : ?>
                                <p class="raf-vehicle-desc"><?php echo esc_html( $vehicle->short_description ); ?></p>
                            <?php endif; ?>
                            <div class="raf-vehicle-specs">
                                <?php if ( $vehicle->bike_type && isset( $bike_types[ $vehicle->bike_type ] ) ) : ?>
                                    <span class="raf-spec"><span class="raf-spec-icon">&#127949;</span> <?php echo esc_html( $bike_types[ $vehicle->bike_type ] ); ?></span>
                                <?php endif; ?>
                                <?php if ( $vehicle->engine_cc ) : ?>
                                    <span class="raf-spec"><span class="raf-spec-icon">&#9881;</span> <?php echo esc_html( $vehicle->engine_cc ); ?>cc</span>
                                <?php endif; ?>
                            </div>
                            <?php if ( ! empty( $features ) ) : ?>
                            <div class="raf-vehicle-features">
                                <?php
                                $all_features = RAF_Helpers::get_vehicle_features();
                                $shown = 0;
                                foreach ( $features as $f ) :
                                    if ( $shown >= 4 ) break;
                                    if ( isset( $all_features[ $f ] ) ) :
                                        $shown++;
                                ?>
                                    <span class="raf-feature-tag"><?php echo esc_html( $all_features[ $f ] ); ?></span>
                                <?php endif; endforeach;
                                if ( count( $features ) > 4 ) : ?>
                                    <span class="raf-feature-more">+<?php echo count( $features ) - 4; ?></span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="raf-vehicle-price-action">
                            <?php if ( $daily_rate > 0 ) : ?>
                                <div class="raf-vehicle-price">
                                    <span class="raf-price-amount"><?php echo esc_html( RAF_Helpers::format_price( $daily_rate ) ); ?></span>
                                    <span class="raf-price-period">/<?php esc_html_e( 'day', 'rentafleet' ); ?></span>
                                </div>
                            <?php endif; ?>
                            <a href="<?php echo esc_url( add_query_arg( 'vehicle_id', $vehicle->id, get_permalink( get_option( 'raf_booking_page' ) ) ) ); ?>" class="raf-btn raf-btn-primary raf-book-btn">
                                <?php esc_html_e( 'Book Now', 'rentafleet' ); ?>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /* ─────────────────────────────────────────────
     *  [raf_booking] — Booking Form (multi-step)
     * ───────────────────────────────────────────── */
    public function booking_shortcode( $atts ) {
        $atts = shortcode_atts( array(), $atts, 'raf_booking' );

        $locations   = RAF_Location::get_all();
        $time_slots  = RAF_Helpers::get_time_slots();
        $extras      = RAF_Extra::get_all();
        $insurances  = RAF_Insurance::get_all();

        ob_start();
        ?>
        <div class="raf-booking-wrap">
            <!-- Progress Steps -->
            <div class="raf-booking-steps">
                <div class="raf-step active" data-step="1">
                    <span class="raf-step-number">1</span>
                    <span class="raf-step-label"><?php esc_html_e( 'Dates', 'rentafleet' ); ?></span>
                </div>
                <div class="raf-step-line"></div>
                <div class="raf-step" data-step="2">
                    <span class="raf-step-number">2</span>
                    <span class="raf-step-label"><?php esc_html_e( 'Choose Bike', 'rentafleet' ); ?></span>
                </div>
                <div class="raf-step-line"></div>
                <div class="raf-step" data-step="3">
                    <span class="raf-step-number">3</span>
                    <span class="raf-step-label"><?php esc_html_e( 'Add-ons', 'rentafleet' ); ?></span>
                </div>
                <div class="raf-step-line"></div>
                <div class="raf-step" data-step="4">
                    <span class="raf-step-number">4</span>
                    <span class="raf-step-label"><?php esc_html_e( 'Your Details', 'rentafleet' ); ?></span>
                </div>
                <div class="raf-step-line"></div>
                <div class="raf-step" data-step="5">
                    <span class="raf-step-number">5</span>
                    <span class="raf-step-label"><?php esc_html_e( 'Confirm', 'rentafleet' ); ?></span>
                </div>
            </div>

            <form id="raf-booking-form" class="raf-booking-form">
                <input type="hidden" name="vehicle_id" value="">

                <!-- Step 1: Dates & Locations -->
                <div class="raf-booking-step-content" data-step="1">
                    <h3><?php esc_html_e( 'When do you need a bike?', 'rentafleet' ); ?></h3>

                    <div class="raf-form-row">
                        <div class="raf-form-group raf-col-3">
                            <label for="raf-b-pickup-date"><?php esc_html_e( 'Pick-up Date', 'rentafleet' ); ?> *</label>
                            <input type="date" id="raf-b-pickup-date" name="pickup_date" required min="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>">
                        </div>
                        <div class="raf-form-group raf-col-3">
                            <label for="raf-b-pickup-time"><?php esc_html_e( 'Pick-up Time', 'rentafleet' ); ?></label>
                            <select id="raf-b-pickup-time" name="pickup_time">
                                <?php foreach ( $time_slots as $val => $label ) : ?>
                                    <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $val, '10:00' ); ?>><?php echo esc_html( $label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="raf-form-group raf-col-3">
                            <label for="raf-b-dropoff-date"><?php esc_html_e( 'Return Date', 'rentafleet' ); ?> *</label>
                            <input type="date" id="raf-b-dropoff-date" name="dropoff_date" required min="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>">
                        </div>
                        <div class="raf-form-group raf-col-3">
                            <label for="raf-b-dropoff-time"><?php esc_html_e( 'Return Time', 'rentafleet' ); ?></label>
                            <select id="raf-b-dropoff-time" name="dropoff_time">
                                <?php foreach ( $time_slots as $val => $label ) : ?>
                                    <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $val, '10:00' ); ?>><?php echo esc_html( $label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="raf-form-row">
                        <div class="raf-form-group raf-col-6">
                            <label for="raf-b-pickup-location"><?php esc_html_e( 'Pick-up Location', 'rentafleet' ); ?> *</label>
                            <select id="raf-b-pickup-location" name="pickup_location_id" required>
                                <option value=""><?php esc_html_e( 'Select location...', 'rentafleet' ); ?></option>
                                <?php foreach ( $locations as $loc ) :
                                    if ( ! $loc->is_pickup ) continue;
                                ?>
                                    <option value="<?php echo esc_attr( $loc->id ); ?>"><?php echo esc_html( $loc->name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="raf-form-group raf-col-6">
                            <label for="raf-b-dropoff-location"><?php esc_html_e( 'Return Location', 'rentafleet' ); ?> *</label>
                            <select id="raf-b-dropoff-location" name="dropoff_location_id" required>
                                <option value=""><?php esc_html_e( 'Select location...', 'rentafleet' ); ?></option>
                                <?php foreach ( $locations as $loc ) :
                                    if ( ! $loc->is_dropoff ) continue;
                                ?>
                                    <option value="<?php echo esc_attr( $loc->id ); ?>"><?php echo esc_html( $loc->name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Choose Bike (loaded via JS after searching) -->
                <div class="raf-booking-step-content" data-step="2" style="display:none;">
                    <h3><?php esc_html_e( 'Choose Your Bike', 'rentafleet' ); ?></h3>
                    <div class="raf-available-bikes-grid"></div>
                    <div class="raf-no-bikes" style="display:none;">
                        <p><?php esc_html_e( 'No bikes available for the selected dates and location. Please go back and try different dates.', 'rentafleet' ); ?></p>
                    </div>
                    <div class="raf-loading" style="display:none;">
                        <div class="raf-spinner"></div>
                        <p><?php esc_html_e( 'Searching available bikes...', 'rentafleet' ); ?></p>
                    </div>
                </div>

                <!-- Step 3: Extras & Insurance -->
                <div class="raf-booking-step-content" data-step="3" style="display:none;">
                    <h3><?php esc_html_e( 'Optional Add-ons & Insurance', 'rentafleet' ); ?></h3>

                    <?php if ( ! empty( $extras ) ) : ?>
                    <div class="raf-extras-section">
                        <h4><?php esc_html_e( 'Extra Services', 'rentafleet' ); ?></h4>
                        <div class="raf-extras-grid">
                            <?php foreach ( $extras as $extra ) : ?>
                            <div class="raf-extra-item" data-extra-id="<?php echo esc_attr( $extra->id ); ?>">
                                <label class="raf-extra-label">
                                    <input type="checkbox" name="extras[<?php echo esc_attr( $extra->id ); ?>][id]" value="<?php echo esc_attr( $extra->id ); ?>" class="raf-extra-checkbox">
                                    <div class="raf-extra-info">
                                        <span class="raf-extra-name"><?php echo esc_html( $extra->name ); ?></span>
                                        <?php if ( $extra->description ) : ?>
                                            <span class="raf-extra-desc"><?php echo esc_html( $extra->description ); ?></span>
                                        <?php endif; ?>
                                        <span class="raf-extra-price">
                                            <?php echo esc_html( RAF_Helpers::format_price( $extra->price ) ); ?>
                                            /<?php echo $extra->price_type === 'per_day' ? esc_html__( 'day', 'rentafleet' ) : esc_html__( 'rental', 'rentafleet' ); ?>
                                        </span>
                                    </div>
                                </label>
                                <?php if ( $extra->max_quantity > 1 ) : ?>
                                    <div class="raf-extra-qty" style="display:none;">
                                        <label><?php esc_html_e( 'Qty:', 'rentafleet' ); ?>
                                            <select name="extras[<?php echo esc_attr( $extra->id ); ?>][quantity]">
                                                <?php for ( $q = 1; $q <= $extra->max_quantity; $q++ ) : ?>
                                                    <option value="<?php echo $q; ?>"><?php echo $q; ?></option>
                                                <?php endfor; ?>
                                            </select>
                                        </label>
                                    </div>
                                <?php else : ?>
                                    <input type="hidden" name="extras[<?php echo esc_attr( $extra->id ); ?>][quantity]" value="1">
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ( ! empty( $insurances ) ) : ?>
                    <div class="raf-insurance-section">
                        <h4><?php esc_html_e( 'Insurance Options', 'rentafleet' ); ?></h4>
                        <div class="raf-insurance-grid">
                            <?php foreach ( $insurances as $ins ) : ?>
                            <div class="raf-insurance-item <?php echo $ins->is_mandatory ? 'raf-mandatory' : ''; ?>">
                                <label class="raf-insurance-label">
                                    <input type="checkbox" name="insurance[]" value="<?php echo esc_attr( $ins->id ); ?>"
                                        class="raf-insurance-checkbox"
                                        <?php echo $ins->is_mandatory ? 'checked disabled' : ''; ?>>
                                    <div class="raf-insurance-info">
                                        <span class="raf-insurance-name">
                                            <?php echo esc_html( $ins->name ); ?>
                                            <?php if ( $ins->is_mandatory ) : ?>
                                                <span class="raf-badge-mandatory"><?php esc_html_e( 'Required', 'rentafleet' ); ?></span>
                                            <?php endif; ?>
                                        </span>
                                        <?php if ( $ins->description ) : ?>
                                            <span class="raf-insurance-desc"><?php echo esc_html( $ins->description ); ?></span>
                                        <?php endif; ?>
                                        <span class="raf-insurance-price">
                                            <?php echo esc_html( RAF_Helpers::format_price( $ins->price_per_day ) ); ?>/<?php esc_html_e( 'day', 'rentafleet' ); ?>
                                            <?php if ( $ins->coverage_amount > 0 ) : ?>
                                                <span class="raf-coverage"><?php printf( esc_html__( 'Coverage: %s', 'rentafleet' ), esc_html( RAF_Helpers::format_price( $ins->coverage_amount ) ) ); ?></span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Step 4: Customer Details -->
                <div class="raf-booking-step-content" data-step="4" style="display:none;">
                    <h3><?php esc_html_e( 'Your Details', 'rentafleet' ); ?></h3>

                    <div class="raf-form-row">
                        <div class="raf-form-group raf-col-6">
                            <label for="raf-b-first-name"><?php esc_html_e( 'First Name', 'rentafleet' ); ?> *</label>
                            <input type="text" id="raf-b-first-name" name="first_name" required>
                        </div>
                        <div class="raf-form-group raf-col-6">
                            <label for="raf-b-last-name"><?php esc_html_e( 'Last Name', 'rentafleet' ); ?> *</label>
                            <input type="text" id="raf-b-last-name" name="last_name" required>
                        </div>
                    </div>

                    <div class="raf-form-row">
                        <div class="raf-form-group raf-col-6">
                            <label for="raf-b-email"><?php esc_html_e( 'Email', 'rentafleet' ); ?> *</label>
                            <input type="email" id="raf-b-email" name="email" required>
                        </div>
                        <div class="raf-form-group raf-col-6">
                            <label for="raf-b-phone"><?php esc_html_e( 'Phone Number', 'rentafleet' ); ?> *</label>
                            <input type="tel" id="raf-b-phone" name="phone" required>
                        </div>
                    </div>

                    <div class="raf-form-row">
                        <div class="raf-form-group raf-col-6">
                            <label for="raf-b-passport"><?php esc_html_e( 'Passport Number', 'rentafleet' ); ?> *</label>
                            <input type="text" id="raf-b-passport" name="passport_number" required>
                        </div>
                        <div class="raf-form-group raf-col-6">
                            <label for="raf-b-citizenship"><?php esc_html_e( 'Citizenship', 'rentafleet' ); ?> *</label>
                            <input type="text" id="raf-b-citizenship" name="citizenship" required>
                        </div>
                    </div>

                    <div class="raf-form-row">
                        <div class="raf-form-group raf-col-12">
                            <label for="raf-b-notes"><?php esc_html_e( 'Special Requests / Notes', 'rentafleet' ); ?></label>
                            <textarea id="raf-b-notes" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Step 5: Review, Deposit & Confirm -->
                <div class="raf-booking-step-content" data-step="5" style="display:none;">
                    <h3><?php esc_html_e( 'Review & Confirm Booking', 'rentafleet' ); ?></h3>

                    <div class="raf-review-sections">
                        <div class="raf-review-section">
                            <h4><?php esc_html_e( 'Rental Details', 'rentafleet' ); ?></h4>
                            <div class="raf-review-details" id="raf-review-rental"></div>
                        </div>

                        <div class="raf-review-section">
                            <h4><?php esc_html_e( 'Customer Details', 'rentafleet' ); ?></h4>
                            <div class="raf-review-details" id="raf-review-customer"></div>
                        </div>

                        <div class="raf-review-section">
                            <h4><?php esc_html_e( 'Price Breakdown', 'rentafleet' ); ?></h4>
                            <div class="raf-price-breakdown" id="raf-review-price"></div>
                        </div>

                        <div class="raf-deposit-section">
                            <div class="raf-notice raf-notice-info">
                                <strong><?php esc_html_e( 'Non-Refundable Deposit Required', 'rentafleet' ); ?></strong>
                                <p><?php esc_html_e( 'A non-refundable deposit is required to confirm your booking. The deposit amount will be shown in the price breakdown above. Payment will be collected offline at the time of pick-up.', 'rentafleet' ); ?></p>
                            </div>
                        </div>

                        <div class="raf-coupon-section">
                            <label for="raf-b-coupon"><?php esc_html_e( 'Coupon Code', 'rentafleet' ); ?></label>
                            <div class="raf-coupon-input">
                                <input type="text" id="raf-b-coupon" name="coupon_code" placeholder="<?php esc_attr_e( 'Enter coupon code', 'rentafleet' ); ?>">
                                <button type="button" class="raf-btn raf-btn-outline raf-apply-coupon"><?php esc_html_e( 'Apply', 'rentafleet' ); ?></button>
                            </div>
                            <div class="raf-coupon-message" style="display:none;"></div>
                        </div>

                        <div class="raf-terms-section">
                            <label>
                                <input type="checkbox" name="terms" required>
                                <?php printf(
                                    esc_html__( 'I agree to the %s and %s', 'rentafleet' ),
                                    '<a href="#" target="_blank">' . esc_html__( 'Terms & Conditions', 'rentafleet' ) . '</a>',
                                    '<a href="#" target="_blank">' . esc_html__( 'Rental Agreement', 'rentafleet' ) . '</a>'
                                ); ?>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Navigation -->
                <div class="raf-booking-nav">
                    <button type="button" class="raf-btn raf-btn-outline raf-prev-step" style="display:none;"><?php esc_html_e( 'Previous', 'rentafleet' ); ?></button>
                    <button type="button" class="raf-btn raf-btn-primary raf-next-step"><?php esc_html_e( 'Next Step', 'rentafleet' ); ?></button>
                    <button type="submit" class="raf-btn raf-btn-primary raf-confirm-booking" style="display:none;"><?php esc_html_e( 'Confirm Booking', 'rentafleet' ); ?></button>
                </div>
            </form>

            <div class="raf-booking-sidebar">
                <div class="raf-price-summary" id="raf-price-summary">
                    <h4><?php esc_html_e( 'Price Summary', 'rentafleet' ); ?></h4>
                    <div class="raf-summary-content">
                        <p class="raf-summary-placeholder"><?php esc_html_e( 'Select a vehicle and dates to see pricing.', 'rentafleet' ); ?></p>
                    </div>
                </div>
            </div>

            <div class="raf-loading" style="display:none;">
                <div class="raf-spinner"></div>
                <p><?php esc_html_e( 'Processing your booking...', 'rentafleet' ); ?></p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /* ─────────────────────────────────────────────
     *  [raf_confirmation] — Booking Confirmation
     * ───────────────────────────────────────────── */
    public function confirmation_shortcode( $atts ) {
        $booking_number = isset( $_GET['booking'] ) ? sanitize_text_field( $_GET['booking'] ) : '';

        if ( ! $booking_number ) {
            return '<div class="raf-notice raf-notice-warning"><p>' . esc_html__( 'No booking reference found.', 'rentafleet' ) . '</p></div>';
        }

        $booking = RAF_Booking_Model::get_by_number( $booking_number );
        if ( ! $booking ) {
            return '<div class="raf-notice raf-notice-error"><p>' . esc_html__( 'Booking not found. Please check your booking reference.', 'rentafleet' ) . '</p></div>';
        }

        $customer = RAF_Customer::get( $booking->customer_id );
        $vehicle  = RAF_Vehicle::get( $booking->vehicle_id );
        $pickup   = RAF_Location::get( $booking->pickup_location_id );
        $dropoff  = RAF_Location::get( $booking->dropoff_location_id );
        $extras   = RAF_Booking_Model::get_extras( $booking->id );
        $insurance = RAF_Booking_Model::get_insurance( $booking->id );

        ob_start();
        ?>
        <div class="raf-confirmation-wrap">
            <div class="raf-confirmation-header">
                <div class="raf-confirmation-icon">&#10003;</div>
                <h2><?php esc_html_e( 'Booking Confirmed!', 'rentafleet' ); ?></h2>
                <p class="raf-booking-ref">
                    <?php esc_html_e( 'Booking Reference:', 'rentafleet' ); ?>
                    <strong><?php echo esc_html( $booking->booking_number ); ?></strong>
                </p>
                <p class="raf-confirmation-status">
                    <?php esc_html_e( 'Status:', 'rentafleet' ); ?>
                    <?php echo wp_kses_post( RAF_Helpers::status_badge( $booking->status ) ); ?>
                </p>
            </div>

            <div class="raf-confirmation-details">
                <div class="raf-conf-section">
                    <h3><?php esc_html_e( 'Bike', 'rentafleet' ); ?></h3>
                    <?php if ( $vehicle ) : ?>
                        <div class="raf-conf-vehicle">
                            <?php if ( $vehicle->featured_image_id ) : ?>
                                <img src="<?php echo esc_url( wp_get_attachment_image_url( $vehicle->featured_image_id, 'medium' ) ); ?>" alt="">
                            <?php endif; ?>
                            <div>
                                <strong><?php echo esc_html( $vehicle->name ); ?></strong>
                                <p><?php echo esc_html( $vehicle->make . ' ' . $vehicle->model . ' ' . $vehicle->year ); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="raf-conf-section">
                    <h3><?php esc_html_e( 'Rental Period', 'rentafleet' ); ?></h3>
                    <table class="raf-conf-table">
                        <tr>
                            <td><?php esc_html_e( 'Pick-up:', 'rentafleet' ); ?></td>
                            <td>
                                <strong><?php echo esc_html( RAF_Helpers::format_datetime( $booking->pickup_date ) ); ?></strong>
                                <?php if ( $pickup ) : ?><br><?php echo esc_html( $pickup->name ); ?><?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e( 'Return:', 'rentafleet' ); ?></td>
                            <td>
                                <strong><?php echo esc_html( RAF_Helpers::format_datetime( $booking->dropoff_date ) ); ?></strong>
                                <?php if ( $dropoff ) : ?><br><?php echo esc_html( $dropoff->name ); ?><?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e( 'Duration:', 'rentafleet' ); ?></td>
                            <td><?php printf( esc_html__( '%d day(s)', 'rentafleet' ), $booking->rental_days ); ?></td>
                        </tr>
                    </table>
                </div>

                <?php if ( $customer ) : ?>
                <div class="raf-conf-section">
                    <h3><?php esc_html_e( 'Customer', 'rentafleet' ); ?></h3>
                    <p>
                        <strong><?php echo esc_html( RAF_Customer::get_full_name( $customer ) ); ?></strong><br>
                        <?php echo esc_html( $customer->email ); ?><br>
                        <?php echo esc_html( $customer->phone ); ?>
                    </p>
                </div>
                <?php endif; ?>

                <div class="raf-conf-section">
                    <h3><?php esc_html_e( 'Price Breakdown', 'rentafleet' ); ?></h3>
                    <table class="raf-conf-table raf-price-table">
                        <tr>
                            <td><?php esc_html_e( 'Base Rental:', 'rentafleet' ); ?></td>
                            <td><?php echo esc_html( RAF_Helpers::format_price( $booking->base_price ) ); ?></td>
                        </tr>
                        <?php if ( ! empty( $extras ) ) : foreach ( $extras as $ex ) : ?>
                        <tr>
                            <td><?php echo esc_html( $ex->name ); ?> (x<?php echo esc_html( $ex->quantity ); ?>):</td>
                            <td><?php echo esc_html( RAF_Helpers::format_price( $ex->total ) ); ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                        <?php if ( ! empty( $insurance ) ) : foreach ( $insurance as $ins ) : ?>
                        <tr>
                            <td><?php echo esc_html( $ins->name ); ?>:</td>
                            <td><?php echo esc_html( RAF_Helpers::format_price( $ins->total ) ); ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                        <?php if ( $booking->location_fees > 0 ) : ?>
                        <tr>
                            <td><?php esc_html_e( 'Location Fees:', 'rentafleet' ); ?></td>
                            <td><?php echo esc_html( RAF_Helpers::format_price( $booking->location_fees ) ); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ( $booking->discount_amount > 0 ) : ?>
                        <tr class="raf-discount-row">
                            <td><?php esc_html_e( 'Discount:', 'rentafleet' ); ?> <?php if ( $booking->coupon_code ) echo '(' . esc_html( $booking->coupon_code ) . ')'; ?></td>
                            <td>-<?php echo esc_html( RAF_Helpers::format_price( $booking->discount_amount ) ); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ( $booking->tax_amount > 0 ) : ?>
                        <tr>
                            <td><?php esc_html_e( 'Tax:', 'rentafleet' ); ?></td>
                            <td><?php echo esc_html( RAF_Helpers::format_price( $booking->tax_amount ) ); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr class="raf-total-row">
                            <td><strong><?php esc_html_e( 'Total:', 'rentafleet' ); ?></strong></td>
                            <td><strong><?php echo esc_html( RAF_Helpers::format_price( $booking->total_price ) ); ?></strong></td>
                        </tr>
                        <?php if ( $booking->deposit_amount > 0 ) : ?>
                        <tr>
                            <td><?php esc_html_e( 'Deposit Required:', 'rentafleet' ); ?></td>
                            <td><?php echo esc_html( RAF_Helpers::format_price( $booking->deposit_amount ) ); ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>

            <div class="raf-confirmation-actions">
                <p><?php esc_html_e( 'A confirmation email has been sent to your email address.', 'rentafleet' ); ?></p>
                <a href="<?php echo esc_url( get_permalink( get_option( 'raf_my_bookings_page' ) ) ); ?>" class="raf-btn raf-btn-primary"><?php esc_html_e( 'View My Bookings', 'rentafleet' ); ?></a>
                <a href="<?php echo esc_url( home_url() ); ?>" class="raf-btn raf-btn-outline"><?php esc_html_e( 'Back to Home', 'rentafleet' ); ?></a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /* ─────────────────────────────────────────────
     *  [raf_my_bookings] — Customer Booking History
     * ───────────────────────────────────────────── */
    public function my_bookings_shortcode( $atts ) {
        // Look up by email or booking number
        $lookup_email   = isset( $_GET['email'] ) ? sanitize_email( $_GET['email'] ) : '';
        $lookup_booking = isset( $_GET['booking'] ) ? sanitize_text_field( $_GET['booking'] ) : '';

        if ( ! $lookup_email && ! $lookup_booking ) {
            ob_start();
            ?>
            <div class="raf-my-bookings-wrap">
                <h2><?php esc_html_e( 'Find Your Bookings', 'rentafleet' ); ?></h2>
                <form method="get" class="raf-lookup-form">
                    <div class="raf-form-row">
                        <div class="raf-form-group raf-col-6">
                            <label for="raf-lookup-email"><?php esc_html_e( 'Email Address', 'rentafleet' ); ?></label>
                            <input type="email" id="raf-lookup-email" name="email" required placeholder="<?php esc_attr_e( 'Enter your email address', 'rentafleet' ); ?>">
                        </div>
                        <div class="raf-form-group raf-col-6" style="display:flex;align-items:flex-end;">
                            <button type="submit" class="raf-btn raf-btn-primary"><?php esc_html_e( 'Look Up Bookings', 'rentafleet' ); ?></button>
                        </div>
                    </div>
                </form>
            </div>
            <?php
            return ob_get_clean();
        }

        $customer = $lookup_email ? RAF_Customer::get_by_email( $lookup_email ) : null;
        if ( ! $customer ) {
            return '<div class="raf-notice raf-notice-info"><p>' . esc_html__( 'No bookings found for this email address.', 'rentafleet' ) . '</p></div>';
        }

        $bookings = RAF_Booking_Model::get_by_customer( $customer->id, 50 );

        ob_start();
        ?>
        <div class="raf-my-bookings-wrap">
            <h2><?php esc_html_e( 'My Bookings', 'rentafleet' ); ?></h2>

            <?php if ( empty( $bookings ) ) : ?>
                <div class="raf-notice raf-notice-info">
                    <p><?php esc_html_e( 'You have no bookings yet.', 'rentafleet' ); ?></p>
                    <a href="<?php echo esc_url( get_permalink( get_option( 'raf_search_page' ) ) ); ?>" class="raf-btn raf-btn-primary"><?php esc_html_e( 'Search Bikes', 'rentafleet' ); ?></a>
                </div>
            <?php else : ?>
                <div class="raf-bookings-list">
                    <?php foreach ( $bookings as $booking ) :
                        $vehicle = RAF_Vehicle::get( $booking->vehicle_id );
                        $pickup  = RAF_Location::get( $booking->pickup_location_id );
                    ?>
                    <div class="raf-booking-card">
                        <div class="raf-booking-card-header">
                            <span class="raf-booking-number">#<?php echo esc_html( $booking->booking_number ); ?></span>
                            <?php echo wp_kses_post( RAF_Helpers::status_badge( $booking->status ) ); ?>
                        </div>
                        <div class="raf-booking-card-body">
                            <div class="raf-booking-vehicle">
                                <?php if ( $vehicle ) : ?>
                                    <?php if ( $vehicle->featured_image_id ) : ?>
                                        <img src="<?php echo esc_url( wp_get_attachment_image_url( $vehicle->featured_image_id, 'thumbnail' ) ); ?>" alt="">
                                    <?php endif; ?>
                                    <strong><?php echo esc_html( $vehicle->name ); ?></strong>
                                <?php endif; ?>
                            </div>
                            <div class="raf-booking-dates">
                                <div>
                                    <span class="raf-label"><?php esc_html_e( 'Pick-up:', 'rentafleet' ); ?></span>
                                    <span><?php echo esc_html( RAF_Helpers::format_datetime( $booking->pickup_date ) ); ?></span>
                                    <?php if ( $pickup ) : ?><span class="raf-sub"><?php echo esc_html( $pickup->name ); ?></span><?php endif; ?>
                                </div>
                                <div>
                                    <span class="raf-label"><?php esc_html_e( 'Return:', 'rentafleet' ); ?></span>
                                    <span><?php echo esc_html( RAF_Helpers::format_datetime( $booking->dropoff_date ) ); ?></span>
                                </div>
                            </div>
                            <div class="raf-booking-total">
                                <span class="raf-label"><?php esc_html_e( 'Total:', 'rentafleet' ); ?></span>
                                <span class="raf-amount"><?php echo esc_html( RAF_Helpers::format_price( $booking->total_price ) ); ?></span>
                            </div>
                        </div>
                        <div class="raf-booking-card-footer">
                            <a href="<?php echo esc_url( add_query_arg( 'booking', $booking->booking_number, get_permalink( get_option( 'raf_confirmation_page' ) ) ) ); ?>" class="raf-btn raf-btn-sm raf-btn-outline"><?php esc_html_e( 'View Details', 'rentafleet' ); ?></a>
                            <?php if ( in_array( $booking->status, array( 'pending', 'confirmed' ), true ) ) : ?>
                                <button type="button" class="raf-btn raf-btn-sm raf-btn-danger raf-cancel-booking" data-booking="<?php echo esc_attr( $booking->id ); ?>"><?php esc_html_e( 'Cancel', 'rentafleet' ); ?></button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /* ─────────────────────────────────────────────
     *  HELPERS
     * ───────────────────────────────────────────── */
    private function get_categories() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM " . RAF_Helpers::table( 'vehicle_categories' ) .
            " WHERE status = 'active' ORDER BY sort_order ASC, name ASC"
        );
    }
}
