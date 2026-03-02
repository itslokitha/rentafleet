<?php
if (!defined('ABSPATH')) exit;

class RAF_Coupon_Manager {

    public static function validate_coupon($code, $booking_data = []) {
        $coupon = RAF_Coupon_Model::get_by_code($code);
        if (!$coupon) {
            return ['valid' => false, 'message' => __('Invalid coupon code.', 'rentafleet')];
        }

        if ($coupon->status !== 'active') {
            return ['valid' => false, 'message' => __('This coupon is no longer active.', 'rentafleet')];
        }

        $now = current_time('mysql');
        if ($coupon->valid_from && $now < $coupon->valid_from) {
            return ['valid' => false, 'message' => __('This coupon is not yet valid.', 'rentafleet')];
        }
        if ($coupon->valid_until && $now > $coupon->valid_until) {
            return ['valid' => false, 'message' => __('This coupon has expired.', 'rentafleet')];
        }

        if ($coupon->max_uses > 0 && $coupon->used_count >= $coupon->max_uses) {
            return ['valid' => false, 'message' => __('This coupon has reached its usage limit.', 'rentafleet')];
        }

        if (!empty($booking_data['total']) && $coupon->min_rental_amount > 0) {
            if ($booking_data['total'] < $coupon->min_rental_amount) {
                return ['valid' => false, 'message' => sprintf(__('Minimum rental amount of %s required.', 'rentafleet'), RAF_Helpers::format_price($coupon->min_rental_amount))];
            }
        }

        if (!empty($booking_data['days']) && $coupon->min_rental_days > 0) {
            if ($booking_data['days'] < $coupon->min_rental_days) {
                return ['valid' => false, 'message' => sprintf(__('Minimum rental of %d days required.', 'rentafleet'), $coupon->min_rental_days)];
            }
        }

        if (!empty($coupon->vehicle_ids) && !empty($booking_data['vehicle_id'])) {
            $allowed = array_map('intval', explode(',', $coupon->vehicle_ids));
            if (!in_array((int)$booking_data['vehicle_id'], $allowed)) {
                return ['valid' => false, 'message' => __('This coupon is not valid for the selected vehicle.', 'rentafleet')];
            }
        }

        if (!empty($coupon->category_ids) && !empty($booking_data['category_id'])) {
            $allowed = array_map('intval', explode(',', $coupon->category_ids));
            if (!in_array((int)$booking_data['category_id'], $allowed)) {
                return ['valid' => false, 'message' => __('This coupon is not valid for this vehicle category.', 'rentafleet')];
            }
        }

        if (!empty($coupon->location_ids) && !empty($booking_data['location_id'])) {
            $allowed = array_map('intval', explode(',', $coupon->location_ids));
            if (!in_array((int)$booking_data['location_id'], $allowed)) {
                return ['valid' => false, 'message' => __('This coupon is not valid at this location.', 'rentafleet')];
            }
        }

        return ['valid' => true, 'coupon' => $coupon, 'message' => __('Coupon applied successfully!', 'rentafleet')];
    }

    public static function calculate_discount($coupon, $subtotal) {
        if (is_string($coupon)) {
            $coupon = RAF_Coupon_Model::get_by_code($coupon);
        }
        if (!$coupon) return 0;

        if ($coupon->discount_type === 'percentage') {
            $discount = $subtotal * ($coupon->discount_value / 100);
            if ($coupon->max_discount > 0) {
                $discount = min($discount, $coupon->max_discount);
            }
        } else {
            $discount = min($coupon->discount_value, $subtotal);
        }

        return round($discount, 2);
    }

    public static function increment_usage($coupon_id) {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "UPDATE " . RAF_Helpers::table('coupons') . " SET used_count = used_count + 1 WHERE id = %d",
            $coupon_id
        ));
    }
}
