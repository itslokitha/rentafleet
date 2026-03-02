<?php
if (!defined('ABSPATH')) exit;

class RAF_Damage_Manager {

    public static function create_report($data) {
        $required = ['booking_id', 'vehicle_id', 'report_type'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return new WP_Error('missing_field', sprintf(__('Missing required field: %s', 'rentafleet'), $field));
            }
        }

        $report_data = [
            'booking_id'  => (int) $data['booking_id'],
            'vehicle_id'  => (int) $data['vehicle_id'],
            'report_type' => sanitize_text_field($data['report_type']),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'severity'    => sanitize_text_field($data['severity'] ?? 'minor'),
            'location_on_vehicle' => sanitize_text_field($data['location_on_vehicle'] ?? ''),
            'repair_cost' => floatval($data['repair_cost'] ?? 0),
            'status'      => 'reported',
            'reported_by' => get_current_user_id(),
            'images'      => '',
        ];

        if (!empty($data['images']) && is_array($data['images'])) {
            $report_data['images'] = implode(',', array_map('intval', $data['images']));
        }

        $id = RAF_Damage_Report_Model::create($report_data);
        if (!$id) {
            return new WP_Error('create_failed', __('Failed to create damage report.', 'rentafleet'));
        }

        // Log to booking
        global $wpdb;
        $wpdb->insert(RAF_Helpers::table('booking_log'), [
            'booking_id' => $report_data['booking_id'],
            'action'     => 'damage_reported',
            'details'    => sprintf('Damage reported: %s - %s', $report_data['severity'], $report_data['description']),
            'user_id'    => get_current_user_id(),
            'created_at' => current_time('mysql'),
        ]);

        return $id;
    }

    public static function update_status($report_id, $status, $notes = '') {
        $report = RAF_Damage_Report_Model::get($report_id);
        if (!$report) return false;

        $update = ['status' => $status];
        if ($notes) {
            $update['description'] = $report->description . "\n\n[" . current_time('Y-m-d H:i') . "] " . $notes;
        }

        return RAF_Damage_Report_Model::update($report_id, $update);
    }

    public static function get_vehicle_damage_history($vehicle_id) {
        return RAF_Damage_Report_Model::get_by_vehicle($vehicle_id);
    }

    public static function get_booking_damages($booking_id) {
        return RAF_Damage_Report_Model::get_by_booking($booking_id);
    }

    public static function get_total_damage_costs($vehicle_id = null) {
        global $wpdb;
        $t = RAF_Helpers::table('damage_reports');

        if ($vehicle_id) {
            return (float) $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(repair_cost),0) FROM {$t} WHERE vehicle_id = %d",
                $vehicle_id
            ));
        }

        return (float) $wpdb->get_var("SELECT COALESCE(SUM(repair_cost),0) FROM {$t}");
    }

    public static function handle_image_upload($files) {
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
        }

        $image_ids = [];
        foreach ($files['name'] as $key => $val) {
            if ($files['error'][$key] !== UPLOAD_ERR_OK) continue;

            $file = [
                'name'     => $files['name'][$key],
                'type'     => $files['type'][$key],
                'tmp_name' => $files['tmp_name'][$key],
                'error'    => $files['error'][$key],
                'size'     => $files['size'][$key],
            ];

            $_FILES['damage_image'] = $file;
            $attachment_id = media_handle_upload('damage_image', 0);

            if (!is_wp_error($attachment_id)) {
                $image_ids[] = $attachment_id;
            }
        }

        return $image_ids;
    }
}
