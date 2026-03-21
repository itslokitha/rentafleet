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

        // Aliases — pages created by the activator use the `rentafleet_*` prefix.
        add_shortcode( 'rentafleet_search', array( $this, 'search_shortcode' ) );
        add_shortcode( 'rentafleet_vehicles', array( $this, 'vehicles_shortcode' ) );
        add_shortcode( 'rentafleet_booking', array( $this, 'booking_shortcode' ) );
        add_shortcode( 'rentafleet_confirmation', array( $this, 'confirmation_shortcode' ) );
        add_shortcode( 'rentafleet_my_bookings', array( $this, 'my_bookings_shortcode' ) );
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
                                <option value="<?php echo esc_attr( $loc->id ); ?>"
                                    data-pickup-fee="<?php echo esc_attr( (float) $loc->pickup_fee ); ?>"
                                    data-dropoff-fee="<?php echo esc_attr( (float) $loc->dropoff_fee ); ?>"
                                ><?php echo esc_html( $loc->name ); ?></option>
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
                                <option value="<?php echo esc_attr( $loc->id ); ?>"
                                    data-pickup-fee="<?php echo esc_attr( (float) $loc->pickup_fee ); ?>"
                                    data-dropoff-fee="<?php echo esc_attr( (float) $loc->dropoff_fee ); ?>"
                                ><?php echo esc_html( $loc->name ); ?></option>
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

            <?php /* Booking Modal — hidden inline to prevent FOUC; opened via JS on "Book Now" click */ ?>
            <div id="raf-booking-modal" class="raf-modal-overlay" style="display:none;">
                <div class="raf-modal-dialog">
                    <button type="button" class="raf-modal-close" aria-label="<?php esc_attr_e( 'Close', 'rentafleet' ); ?>">&times;</button>

                    <div class="raf-modal-form-body">
                        <div class="raf-modal-header">
                            <h3><?php esc_html_e( 'Complete Your Booking', 'rentafleet' ); ?></h3>
                        </div>

                        <?php /* Vehicle summary — visible in both steps */ ?>
                        <div class="raf-modal-vehicle-summary">
                            <img class="raf-modal-vehicle-img" src="" alt="" style="display:none;">
                            <div class="raf-modal-vehicle-details">
                                <strong class="raf-modal-vehicle-name"></strong>
                                <span class="raf-modal-vehicle-price"></span>
                                <span class="raf-modal-vehicle-dates"></span>
                                <span class="raf-modal-vehicle-location"></span>
                            </div>
                        </div>

                        <?php /* Step indicator */ ?>
                        <div class="raf-modal-steps">
                            <div class="raf-modal-step raf-modal-step--active" data-step="1">
                                <span class="raf-modal-step-num">1</span>
                                <span class="raf-modal-step-label"><?php esc_html_e( 'Add-ons', 'rentafleet' ); ?></span>
                            </div>
                            <div class="raf-modal-step-divider"></div>
                            <div class="raf-modal-step" data-step="2">
                                <span class="raf-modal-step-num">2</span>
                                <span class="raf-modal-step-label"><?php esc_html_e( 'Your Details', 'rentafleet' ); ?></span>
                            </div>
                            <div class="raf-modal-step-divider"></div>
                            <div class="raf-modal-step" data-step="3">
                                <span class="raf-modal-step-num">3</span>
                                <span class="raf-modal-step-label"><?php esc_html_e( 'Terms', 'rentafleet' ); ?></span>
                            </div>
                        </div>

                        <?php /* Step 1: Add-ons */ ?>
                        <div class="raf-modal-step-content" data-step="1">
                            <div class="raf-addons-loading" style="display:none;">
                                <span class="raf-price-spinner"></span>
                                <?php esc_html_e( 'Loading add-ons...', 'rentafleet' ); ?>
                            </div>
                            <div class="raf-addons-list"></div>
                            <div class="raf-addons-none" style="display:none;">
                                <p><?php esc_html_e( 'No add-ons available for this rental.', 'rentafleet' ); ?></p>
                            </div>

                            <div class="raf-modal-step-actions">
                                <button type="button" class="raf-btn raf-btn-primary raf-modal-continue-btn" style="width:100%;">
                                    <?php esc_html_e( 'Continue', 'rentafleet' ); ?> <span>&#8594;</span>
                                </button>
                            </div>
                        </div>

                        <?php /* Step 2: Your Details */ ?>
                        <div class="raf-modal-step-content" data-step="2" style="display:none;">
                            <?php /* Full price summary shown in step 2 */ ?>
                            <div class="raf-modal-price-summary" style="display:none;">
                                <div class="raf-modal-price-summary-header">
                                    <span class="raf-modal-price-summary-icon">&#128179;</span>
                                    <span><?php esc_html_e( 'Price Summary', 'rentafleet' ); ?></span>
                                    <span class="raf-modal-price-summary-dates"></span>
                                </div>
                                <div class="raf-modal-price-loading">
                                    <span class="raf-price-spinner"></span>
                                    <?php esc_html_e( 'Calculating price...', 'rentafleet' ); ?>
                                </div>
                                <div class="raf-modal-price-breakdown"></div>
                            </div>

                            <div class="raf-modal-error raf-notice raf-notice-error" style="display:none;"></div>

                            <form class="raf-booking-modal-form">
                                <div class="raf-field">
                                    <label for="raf-modal-full-name"><?php esc_html_e( 'Full Name', 'rentafleet' ); ?> <span class="raf-required">*</span></label>
                                    <input type="text" id="raf-modal-full-name" name="full_name" required placeholder="<?php esc_attr_e( 'e.g. John Smith', 'rentafleet' ); ?>">
                                </div>
                                <div class="raf-field">
                                    <label for="raf-modal-passport"><?php esc_html_e( 'Passport Number', 'rentafleet' ); ?> <span class="raf-required">*</span></label>
                                    <input type="text" id="raf-modal-passport" name="passport_number" required placeholder="<?php esc_attr_e( 'e.g. AB1234567', 'rentafleet' ); ?>">
                                </div>
                                <div class="raf-field">
                                    <label for="raf-modal-phone"><?php esc_html_e( 'Contact Number', 'rentafleet' ); ?> <span class="raf-required">*</span></label>
                                    <input type="tel" id="raf-modal-phone" name="phone" required placeholder="<?php esc_attr_e( '+1 234 567 890', 'rentafleet' ); ?>">
                                </div>
                                <div class="raf-field">
                                    <label for="raf-modal-email"><?php esc_html_e( 'Email', 'rentafleet' ); ?> <span class="raf-required">*</span></label>
                                    <input type="email" id="raf-modal-email" name="email" required placeholder="<?php esc_attr_e( 'you@example.com', 'rentafleet' ); ?>">
                                </div>
                                <button type="button" class="raf-btn raf-btn-primary raf-modal-continue-step3-btn" style="width:100%;">
                                    <?php esc_html_e( 'Continue', 'rentafleet' ); ?> <span>&#8594;</span>
                                </button>
                            </form>
                        </div>

                        <?php /* Step 3: Terms & Conditions */ ?>
                        <div class="raf-modal-step-content" data-step="3" style="display:none;">
                            <h4 class="raf-terms-heading"><?php esc_html_e( 'Terms & Conditions', 'rentafleet' ); ?></h4>
                            <div class="raf-terms-box"></div>

                            <div class="raf-terms-agree">
                                <label class="raf-terms-agree-label">
                                    <input type="checkbox" id="raf-agree-terms" name="agree_terms" value="1">
                                    <?php esc_html_e( 'I agree to the Terms & Conditions', 'rentafleet' ); ?>
                                </label>
                            </div>

                            <div class="raf-terms-date-row">
                                <label for="raf-agree-date"><?php esc_html_e( 'Date', 'rentafleet' ); ?></label>
                                <input type="date" id="raf-agree-date" name="agreed_date" value="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>">
                            </div>

                            <div class="raf-modal-error raf-modal-terms-error raf-notice raf-notice-error" style="display:none;"></div>

                            <button type="button" class="raf-btn raf-btn-primary raf-modal-confirm-btn" style="width:100%;">
                                <?php esc_html_e( 'Confirm Booking', 'rentafleet' ); ?>
                            </button>
                        </div>
                    </div>

                    <?php /* Success state */ ?>
                    <div class="raf-modal-success" style="display:none;">
                        <div class="raf-modal-success-icon">&#10003;</div>
                        <h3><?php esc_html_e( 'Booking Confirmed!', 'rentafleet' ); ?></h3>
                        <p><?php esc_html_e( 'Your booking has been created successfully.', 'rentafleet' ); ?></p>
                        <p class="raf-modal-booking-ref">
                            <?php esc_html_e( 'Booking Reference:', 'rentafleet' ); ?>
                            <strong class="raf-modal-booking-number"></strong>
                        </p>
                        <p class="raf-modal-success-note"><?php esc_html_e( 'A confirmation email will be sent to the address you provided. Please save your booking reference number.', 'rentafleet' ); ?></p>
                    </div>
                </div>
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
                    <?php
                        $vehicle_data = array(
                            'id'          => (int) $vehicle->id,
                            'name'        => $vehicle->name,
                            'description' => $vehicle->description ?? '',
                            'short_desc'  => $vehicle->short_description ?? '',
                            'image'       => $image_url,
                            'type'        => $vehicle->bike_type ?? '',
                            'type_label'  => ( $vehicle->bike_type && isset( $bike_types[ $vehicle->bike_type ] ) ) ? $bike_types[ $vehicle->bike_type ] : '',
                            'engine_cc'   => (int) ( $vehicle->engine_cc ?? 0 ),
                            'year'        => (int) ( $vehicle->year ?? 0 ),
                            'make'        => $vehicle->make ?? '',
                            'model'       => $vehicle->model ?? '',
                            'color'       => $vehicle->color ?? '',
                            'daily_rate'  => (float) $daily_rate,
                            'features'    => $features,
                            'deposit'     => (float) ( $vehicle->deposit_amount ?? 0 ),
                            'min_age'     => (int) ( $vehicle->min_driver_age ?? 21 ),
                        );
                    ?>
                    <div class="raf-vehicle-card raf-vehicle-card--clickable" data-category="<?php echo esc_attr( $vehicle->category_id ); ?>" data-vehicle-id="<?php echo esc_attr( $vehicle->id ); ?>" data-vehicle="<?php echo esc_attr( wp_json_encode( $vehicle_data ) ); ?>">
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
                                $all_features = RAF_Helpers::get_bike_features();
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
                        <?php if ( $daily_rate > 0 ) : ?>
                        <div class="raf-vehicle-price-action">
                                <div class="raf-vehicle-price">
                                    <span class="raf-price-amount"><?php echo esc_html( RAF_Helpers::format_price( $daily_rate ) ); ?></span>
                                    <span class="raf-price-period">/<?php esc_html_e( 'day', 'rentafleet' ); ?></span>
                                </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php /* Vehicle Detail Modal — populated via JS on card click */ ?>
            <?php
                $rent_page_id  = get_option( 'raf_search_page' );
                $rent_page_url = $rent_page_id ? get_permalink( $rent_page_id ) : '#';
            ?>
            <div id="raf-vehicle-detail-modal" class="raf-vd-overlay" style="display:none;" role="dialog" aria-modal="true">
                <div class="raf-vd-modal">
                    <button class="raf-vd-close" aria-label="<?php esc_attr_e( 'Close', 'rentafleet' ); ?>">&times;</button>
                    <div class="raf-vd-content">
                        <div class="raf-vd-image-col">
                            <img class="raf-vd-image" src="" alt="">
                        </div>
                        <div class="raf-vd-info-col">
                            <h2 class="raf-vd-name"></h2>
                            <div class="raf-vd-badges"></div>
                            <p class="raf-vd-description"></p>
                            <div class="raf-vd-specs"></div>
                            <div class="raf-vd-features"></div>
                            <div class="raf-vd-price-row">
                                <span class="raf-vd-price"></span>
                                <a class="raf-btn raf-btn-primary raf-vd-rent-btn" href="<?php echo esc_url( $rent_page_url ); ?>"><?php esc_html_e( 'Rent This Bike', 'rentafleet' ); ?></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
        <?php
        return ob_get_clean();
    }

    /* ─────────────────────────────────────────────
     *  [raf_booking] — Booking Form (multi-step)
     *
     *  Disabled: the booking flow now uses the inline modal
     *  within the [raf_search] shortcode. This standalone page
     *  shortcode is kept for backwards compatibility but returns
     *  nothing. The page itself is NOT deleted so the admin can
     *  re-enable it if needed.
     * ───────────────────────────────────────────── */
    public function booking_shortcode( $atts ) {
        return '';
    }

    /* ─────────────────────────────────────────────
     *  [raf_confirmation] — Booking Confirmation
     *
     *  Disabled: booking confirmation is now shown in the inline
     *  modal within [raf_search]. The page is kept in the database
     *  for admin reference but renders nothing on the frontend.
     * ───────────────────────────────────────────── */
    public function confirmation_shortcode( $atts ) {
        return '';
    }

    /* ─────────────────────────────────────────────
     *  [raf_my_bookings] — Customer Booking History
     *
     *  Disabled: this standalone page is not part of the current
     *  frontend flow. The page is kept in the database for admin
     *  reference but renders nothing on the frontend.
     * ───────────────────────────────────────────── */
    public function my_bookings_shortcode( $atts ) {
        return '';
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
