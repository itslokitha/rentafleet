<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RAF_Pricing_Engine {

    public function __construct() {
        add_action( 'wp_ajax_raf_calculate_price', array( $this, 'ajax_calculate_price' ) );
        add_action( 'wp_ajax_nopriv_raf_calculate_price', array( $this, 'ajax_calculate_price' ) );
    }

    /**
     * Calculate total rental price for a vehicle
     */
    public function calculate_rental_price( $args ) {
        $defaults = array(
            'vehicle_id'   => 0,
            'pickup_date'  => '',
            'dropoff_date' => '',
            'extras'       => array(),
            'insurance'    => array(),
            'coupon_code'  => '',
            'pickup_location_id'  => 0,
            'dropoff_location_id' => 0,
        );
        $args = wp_parse_args( $args, $defaults );

        $vehicle = RAF_Vehicle::get( $args['vehicle_id'] );
        if ( ! $vehicle ) {
            return new WP_Error( 'invalid_vehicle', __( 'Vehicle not found.', 'rentafleet' ) );
        }

        $rental_days = RAF_Helpers::calculate_rental_days( $args['pickup_date'], $args['dropoff_date'] );

        // Calculate base price (day by day with seasonal & weekend rates)
        $base_price = $this->calculate_base_price( $vehicle, $args['pickup_date'], $args['dropoff_date'], $rental_days );

        // Calculate extras total
        $extras_total = 0;
        $extras_breakdown = array();
        if ( ! empty( $args['extras'] ) ) {
            foreach ( $args['extras'] as $extra_data ) {
                $extra = RAF_Extra::get( $extra_data['id'] );
                if ( $extra ) {
                    $qty   = isset( $extra_data['quantity'] ) ? max( 1, (int) $extra_data['quantity'] ) : 1;
                    $total = RAF_Extra::calculate_total( $extra, $rental_days ) * $qty;
                    $extras_total += $total;
                    $extras_breakdown[] = array(
                        'id'       => $extra->id,
                        'name'     => $extra->name,
                        'price'    => $extra->price,
                        'type'     => $extra->price_type,
                        'quantity' => $qty,
                        'total'    => $total,
                    );
                }
            }
        }

        // Calculate insurance total
        $insurance_total = 0;
        $insurance_breakdown = array();
        if ( ! empty( $args['insurance'] ) ) {
            foreach ( $args['insurance'] as $ins_id ) {
                $insurance = RAF_Insurance::get( $ins_id );
                if ( $insurance ) {
                    $total = RAF_Insurance::calculate_total( $insurance, $rental_days );
                    $insurance_total += $total;
                    $insurance_breakdown[] = array(
                        'id'    => $insurance->id,
                        'name'  => $insurance->name,
                        'price' => $insurance->price_per_day,
                        'total' => $total,
                    );
                }
            }
        }

        // Add mandatory insurance
        $mandatory_insurance = $this->get_mandatory_insurance();
        foreach ( $mandatory_insurance as $ins ) {
            // Check not already added
            $already = false;
            foreach ( $insurance_breakdown as $ib ) {
                if ( $ib['id'] == $ins->id ) { $already = true; break; }
            }
            if ( ! $already ) {
                $total = RAF_Insurance::calculate_total( $ins, $rental_days );
                $insurance_total += $total;
                $insurance_breakdown[] = array(
                    'id'    => $ins->id,
                    'name'  => $ins->name . ' (mandatory)',
                    'price' => $ins->price_per_day,
                    'total' => $total,
                );
            }
        }

        // Location fees
        $location_fees          = 0;
        $pickup_location_fee    = 0;
        $pickup_location_name   = '';
        $dropoff_location_fee   = 0;
        $dropoff_location_name  = '';

        if ( $args['pickup_location_id'] ) {
            $pickup_loc = RAF_Location::get( $args['pickup_location_id'] );
            if ( $pickup_loc ) {
                $pickup_location_fee  = (float) $pickup_loc->pickup_fee;
                $pickup_location_name = $pickup_loc->name;
                $location_fees       += $pickup_location_fee;
            }
        }
        if ( $args['dropoff_location_id'] ) {
            $dropoff_loc = RAF_Location::get( $args['dropoff_location_id'] );
            if ( $dropoff_loc ) {
                $dropoff_location_fee  = (float) $dropoff_loc->dropoff_fee;
                $dropoff_location_name = $dropoff_loc->name;
                $location_fees        += $dropoff_location_fee;
            }
        }

        // One-way fee (different pickup/dropoff locations)
        $one_way_fee = 0;
        if ( $args['pickup_location_id'] && $args['dropoff_location_id'] && $args['pickup_location_id'] != $args['dropoff_location_id'] ) {
            $one_way_fee = (float) get_option( 'raf_one_way_fee', 0 );
            $location_fees += $one_way_fee;
        }

        // Subtotal before tax & discount
        $subtotal = $base_price + $extras_total + $insurance_total + $location_fees;

        // Apply coupon
        $discount_amount = 0;
        $coupon_data = null;
        if ( ! empty( $args['coupon_code'] ) ) {
            $coupon = RAF_Coupon_Model::validate( $args['coupon_code'], array(
                'rental_days' => $rental_days,
                'total'       => $subtotal,
            ) );
            if ( ! is_wp_error( $coupon ) ) {
                $discount_amount = RAF_Coupon_Model::calculate_discount( $coupon, $base_price );
                $coupon_data = array(
                    'id'       => $coupon->id,
                    'code'     => $coupon->code,
                    'discount' => $discount_amount,
                );
            }
        }

        $subtotal -= $discount_amount;

        // Calculate tax
        $tax_rate   = $this->get_applicable_tax_rate( $args['pickup_location_id'] );
        $tax_amount = $subtotal * ( $tax_rate / 100 );

        // Total
        $total = $subtotal + $tax_amount;

        // Deposit
        $deposit = $this->calculate_deposit( $total, $vehicle );

        return array(
            'vehicle_id'          => $vehicle->id,
            'vehicle_name'        => $vehicle->name,
            'rental_days'         => $rental_days,
            'base_price'          => round( $base_price, 2 ),
            'daily_rate'          => round( $base_price / max( 1, $rental_days ), 2 ),
            'extras'              => $extras_breakdown,
            'extras_total'        => round( $extras_total, 2 ),
            'insurance'           => $insurance_breakdown,
            'insurance_total'     => round( $insurance_total, 2 ),
            'location_fees'        => round( $location_fees, 2 ),
            'pickup_location_name' => $pickup_location_name,
            'pickup_location_fee'  => round( $pickup_location_fee, 2 ),
            'dropoff_location_name'=> $dropoff_location_name,
            'dropoff_location_fee' => round( $dropoff_location_fee, 2 ),
            'one_way_fee'          => round( $one_way_fee, 2 ),
            'coupon'              => $coupon_data,
            'discount_amount'     => round( $discount_amount, 2 ),
            'subtotal'            => round( $subtotal, 2 ),
            'tax_rate'            => $tax_rate,
            'tax_amount'          => round( $tax_amount, 2 ),
            'total'               => round( $total, 2 ),
            'deposit_amount'      => round( $deposit, 2 ),
            'currency'            => get_option( 'raf_currency', 'USD' ),
        );
    }

    /**
     * Calculate base price day by day (with seasonal + weekend rates)
     */
    private function calculate_base_price( $vehicle, $pickup_date, $dropoff_date, $rental_days ) {
        // Get applicable rate tier
        $rates = RAF_Rate::get_for_vehicle( $vehicle->id );
        if ( empty( $rates ) ) {
            $rates = RAF_Rate::get_for_category( $vehicle->category_id );
        }

        // Find the matching rate tier
        $rate = null;
        foreach ( $rates as $r ) {
            if ( $rental_days >= $r->min_days && $rental_days <= $r->max_days ) {
                $rate = $r;
                break;
            }
        }

        // Fallback to first rate
        if ( ! $rate && ! empty( $rates ) ) {
            $rate = $rates[0];
        }

        if ( ! $rate ) {
            return 0;
        }

        // Check for weekly/monthly pricing
        if ( $rental_days >= 30 && $rate->monthly_price > 0 ) {
            $months = floor( $rental_days / 30 );
            $remaining = $rental_days % 30;
            return ( $months * $rate->monthly_price ) + ( $remaining * $rate->price );
        }

        if ( $rental_days >= 7 && $rate->weekly_price > 0 ) {
            $weeks = floor( $rental_days / 7 );
            $remaining = $rental_days % 7;
            return ( $weeks * $rate->weekly_price ) + ( $remaining * $rate->price );
        }

        // Calculate day by day for seasonal and weekend rates
        $total = 0;
        $current = new DateTime( $pickup_date );
        $end     = new DateTime( $dropoff_date );

        for ( $i = 0; $i < $rental_days; $i++ ) {
            $date_str = $current->format( 'Y-m-d' );

            // Check seasonal rate
            $seasonal = RAF_Rate::get_seasonal( $vehicle->id, $vehicle->category_id, $date_str );
            if ( ! empty( $seasonal ) ) {
                $sr = $seasonal[0]; // highest priority
                if ( $sr->daily_price > 0 ) {
                    $day_price = $sr->daily_price;
                } elseif ( $sr->price_modifier_type === 'percentage' ) {
                    $day_price = $rate->price * ( 1 + $sr->price_modifier_value / 100 );
                } elseif ( $sr->price_modifier_type === 'fixed' ) {
                    $day_price = $rate->price + $sr->price_modifier_value;
                } else {
                    $day_price = $rate->price;
                }

                // Weekend override in seasonal
                if ( RAF_Helpers::is_weekend( $date_str ) && $sr->weekend_price > 0 ) {
                    $day_price = $sr->weekend_price;
                }
            } else {
                // Standard rate
                if ( RAF_Helpers::is_weekend( $date_str ) && $rate->weekend_price > 0 ) {
                    $day_price = $rate->weekend_price;
                } else {
                    $day_price = $rate->price;
                }
            }

            $total += $day_price;
            $current->modify( '+1 day' );
        }

        return $total;
    }

    /**
     * Get mandatory insurance types
     */
    private function get_mandatory_insurance() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM " . RAF_Helpers::table( 'insurance' ) .
            " WHERE is_mandatory = 1 AND status = 'active' ORDER BY sort_order ASC"
        );
    }

    /**
     * Get applicable tax rate
     */
    private function get_applicable_tax_rate( $location_id = 0 ) {
        $default_rate = (float) get_option( 'raf_default_tax_rate', 0 );

        if ( $location_id ) {
            global $wpdb;
            $location = RAF_Location::get( $location_id );
            if ( $location ) {
                // Check for location-specific tax
                $tax = $wpdb->get_var( $wpdb->prepare(
                    "SELECT rate FROM " . RAF_Helpers::table( 'tax_rates' ) .
                    " WHERE status = 'active' AND (country = %s OR state = %s OR city = %s)
                      ORDER BY priority DESC LIMIT 1",
                    $location->country, $location->state, $location->city
                ) );
                if ( $tax !== null ) return (float) $tax;
            }
        }

        return $default_rate;
    }

    /**
     * Calculate deposit amount
     */
    private function calculate_deposit( $total, $vehicle ) {
        if ( ! get_option( 'raf_require_deposit', 0 ) ) return 0;

        // Vehicle-specific deposit
        if ( $vehicle->deposit_amount > 0 ) {
            return $vehicle->deposit_amount;
        }

        $type  = get_option( 'raf_deposit_type', 'percentage' );
        $value = (float) get_option( 'raf_deposit_value', 20 );

        if ( $type === 'percentage' ) {
            return $total * ( $value / 100 );
        }
        return $value;
    }

    /**
     * Get display price (starting from) for a vehicle
     */
    public function get_display_price( $vehicle_id ) {
        $rates = RAF_Rate::get_for_vehicle( $vehicle_id );
        if ( empty( $rates ) ) {
            $vehicle = RAF_Vehicle::get( $vehicle_id );
            if ( $vehicle ) {
                $rates = RAF_Rate::get_for_category( $vehicle->category_id );
            }
        }
        if ( empty( $rates ) ) return 0;
        return (float) $rates[0]->price;
    }

    /**
     * AJAX handler for price calculation
     */
    public function ajax_calculate_price() {
        check_ajax_referer( 'raf_public_nonce', 'nonce' );

        $args = array(
            'vehicle_id'          => intval( $_POST['vehicle_id'] ?? 0 ),
            'pickup_date'         => sanitize_text_field( $_POST['pickup_date'] ?? '' ),
            'dropoff_date'        => sanitize_text_field( $_POST['dropoff_date'] ?? '' ),
            'extras'              => isset( $_POST['extras'] ) ? (array) $_POST['extras'] : array(),
            'insurance'           => isset( $_POST['insurance'] ) ? array_map( 'intval', (array) $_POST['insurance'] ) : array(),
            'coupon_code'         => sanitize_text_field( $_POST['coupon_code'] ?? '' ),
            'pickup_location_id'  => intval( $_POST['pickup_location_id'] ?? 0 ),
            'dropoff_location_id' => intval( $_POST['dropoff_location_id'] ?? 0 ),
        );

        $result = $this->calculate_rental_price( $args );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success( $result );
    }
}
