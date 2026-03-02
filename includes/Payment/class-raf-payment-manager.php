<?php
if (!defined('ABSPATH')) exit;

class RAF_Payment_Manager {

    private static $gateways = [];

    public static function init() {
        // Register built-in gateways
        self::register_gateway('offline', [
            'name'        => __('Pay at Pickup', 'rentafleet'),
            'description' => __('Pay when you pick up the vehicle.', 'rentafleet'),
            'class'       => 'RAF_Gateway_Offline',
        ]);

        // Allow third-party gateways
        self::$gateways = apply_filters('raf_payment_gateways', self::$gateways);
    }

    public static function register_gateway($id, $config) {
        self::$gateways[$id] = $config;
    }

    public static function get_active_gateways() {
        $active = [];
        $enabled = get_option('raf_payment_gateways', ['offline']);
        if (is_string($enabled)) $enabled = [$enabled];

        foreach ($enabled as $id) {
            if (isset(self::$gateways[$id])) {
                $active[$id] = self::$gateways[$id];
            }
        }

        return $active;
    }

    public static function get_gateway($id) {
        return self::$gateways[$id] ?? null;
    }

    public static function process_payment($booking_id, $gateway_id, $data = []) {
        $gateway = self::get_gateway($gateway_id);
        if (!$gateway) {
            return new WP_Error('invalid_gateway', __('Invalid payment gateway.', 'rentafleet'));
        }

        $booking = RAF_Booking_Model::get($booking_id);
        if (!$booking) {
            return new WP_Error('invalid_booking', __('Booking not found.', 'rentafleet'));
        }

        // Record payment attempt
        $payment_id = self::record_payment($booking_id, [
            'amount'          => $booking->total_price,
            'gateway'         => $gateway_id,
            'status'          => 'pending',
            'transaction_id'  => '',
        ]);

        if ($gateway_id === 'offline') {
            self::update_payment($payment_id, ['status' => 'pending']);
            return ['success' => true, 'payment_id' => $payment_id, 'redirect' => false];
        }

        // For online gateways, delegate to gateway class
        if (isset($gateway['class']) && class_exists($gateway['class'])) {
            $processor = new $gateway['class']();
            $result = $processor->process($booking, $data);

            if (is_wp_error($result)) {
                self::update_payment($payment_id, ['status' => 'failed']);
                return $result;
            }

            self::update_payment($payment_id, [
                'status'         => $result['status'] ?? 'completed',
                'transaction_id' => $result['transaction_id'] ?? '',
            ]);

            return array_merge($result, ['payment_id' => $payment_id]);
        }

        return new WP_Error('gateway_error', __('Payment gateway not properly configured.', 'rentafleet'));
    }

    public static function record_payment($booking_id, $data) {
        global $wpdb;
        $wpdb->insert(RAF_Helpers::table('payments'), [
            'booking_id'     => $booking_id,
            'amount'         => $data['amount'],
            'currency'       => get_option('raf_currency', 'USD'),
            'gateway'        => $data['gateway'],
            'transaction_id' => $data['transaction_id'] ?? '',
            'status'         => $data['status'] ?? 'pending',
            'created_at'     => current_time('mysql'),
        ]);
        return $wpdb->insert_id;
    }

    public static function update_payment($payment_id, $data) {
        global $wpdb;
        return $wpdb->update(
            RAF_Helpers::table('payments'),
            $data,
            ['id' => $payment_id]
        );
    }

    public static function get_booking_payments($booking_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . RAF_Helpers::table('payments') . " WHERE booking_id = %d ORDER BY created_at DESC",
            $booking_id
        ));
    }

    public static function get_total_paid($booking_id) {
        global $wpdb;
        return (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount),0) FROM " . RAF_Helpers::table('payments') . " WHERE booking_id = %d AND status = 'completed'",
            $booking_id
        ));
    }

    public static function refund($payment_id, $amount = null) {
        global $wpdb;
        $payment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . RAF_Helpers::table('payments') . " WHERE id = %d",
            $payment_id
        ));

        if (!$payment || $payment->status !== 'completed') {
            return new WP_Error('invalid_payment', __('Payment not found or not completed.', 'rentafleet'));
        }

        $refund_amount = $amount ?? $payment->amount;
        $gateway = self::get_gateway($payment->gateway);

        if ($gateway && isset($gateway['class']) && class_exists($gateway['class'])) {
            $processor = new $gateway['class']();
            if (method_exists($processor, 'refund')) {
                $result = $processor->refund($payment, $refund_amount);
                if (is_wp_error($result)) return $result;
            }
        }

        self::update_payment($payment_id, ['status' => 'refunded']);

        self::record_payment($payment->booking_id, [
            'amount'         => -$refund_amount,
            'gateway'        => $payment->gateway,
            'transaction_id' => 'refund_' . $payment->transaction_id,
            'status'         => 'completed',
        ]);

        return true;
    }
}

// Offline gateway
class RAF_Gateway_Offline {
    public function process($booking, $data = []) {
        return [
            'success'        => true,
            'status'         => 'pending',
            'transaction_id' => 'offline_' . $booking->id . '_' . time(),
            'redirect'       => false,
        ];
    }
}
