<?php
if (!defined('ABSPATH')) exit;

class RAF_Statistics {

    public static function get_dashboard_stats() {
        global $wpdb;
        $t = RAF_Helpers::table('bookings');

        $today = current_time('Y-m-d');
        $month_start = current_time('Y-m-01');
        $year_start = current_time('Y-01-01');

        return [
            'today_bookings'    => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t} WHERE DATE(created_at) = %s", $today)),
            'month_bookings'    => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t} WHERE created_at >= %s", $month_start)),
            'year_bookings'     => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t} WHERE created_at >= %s", $year_start)),
            'total_bookings'    => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$t}"),
            'active_bookings'   => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t} WHERE status = %s", 'active')),
            'pending_bookings'  => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t} WHERE status = %s", 'pending')),
            'month_revenue'     => (float) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(total_price),0) FROM {$t} WHERE status IN ('confirmed','active','completed') AND created_at >= %s", $month_start)),
            'year_revenue'      => (float) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(total_price),0) FROM {$t} WHERE status IN ('confirmed','active','completed') AND created_at >= %s", $year_start)),
            'total_revenue'     => (float) $wpdb->get_var("SELECT COALESCE(SUM(total_price),0) FROM {$t} WHERE status IN ('confirmed','active','completed')"),
            'total_customers'   => (int) $wpdb->get_var("SELECT COUNT(*) FROM " . RAF_Helpers::table('customers')),
            'total_vehicles'    => (int) $wpdb->get_var("SELECT COUNT(*) FROM " . RAF_Helpers::table('vehicles') . " WHERE status = 'active'"),
            'avg_booking_value' => (float) $wpdb->get_var("SELECT COALESCE(AVG(total_price),0) FROM {$t} WHERE status IN ('confirmed','active','completed')"),
        ];
    }

    public static function get_revenue_chart($period = 'month', $months = 12) {
        global $wpdb;
        $t = RAF_Helpers::table('bookings');
        $data = [];

        if ($period === 'month') {
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT DATE_FORMAT(created_at, '%%Y-%%m') as period, 
                        COUNT(*) as bookings, 
                        COALESCE(SUM(total_price),0) as revenue
                 FROM {$t} 
                 WHERE status IN ('confirmed','active','completed') 
                   AND created_at >= DATE_SUB(NOW(), INTERVAL %d MONTH)
                 GROUP BY period ORDER BY period",
                $months
            ));
        } else {
            $results = $wpdb->get_results(
                "SELECT DATE_FORMAT(created_at, '%Y') as period, 
                        COUNT(*) as bookings, 
                        COALESCE(SUM(total_price),0) as revenue
                 FROM {$t} 
                 WHERE status IN ('confirmed','active','completed')
                 GROUP BY period ORDER BY period"
            );
        }

        foreach ($results as $row) {
            $data[] = [
                'period'   => $row->period,
                'bookings' => (int) $row->bookings,
                'revenue'  => (float) $row->revenue,
            ];
        }

        return $data;
    }

    public static function get_popular_vehicles($limit = 10) {
        global $wpdb;
        $bt = RAF_Helpers::table('bookings');
        $vt = RAF_Helpers::table('vehicles');

        return $wpdb->get_results($wpdb->prepare(
            "SELECT v.id, v.make, v.model, v.year, 
                    COUNT(b.id) as total_bookings,
                    COALESCE(SUM(b.total_price),0) as total_revenue
             FROM {$vt} v
             LEFT JOIN {$bt} b ON v.id = b.vehicle_id AND b.status IN ('confirmed','active','completed')
             GROUP BY v.id ORDER BY total_bookings DESC LIMIT %d",
            $limit
        ));
    }

    public static function get_revenue_by_location($limit = 10) {
        global $wpdb;
        $bt = RAF_Helpers::table('bookings');
        $lt = RAF_Helpers::table('locations');

        return $wpdb->get_results($wpdb->prepare(
            "SELECT l.id, l.name, 
                    COUNT(b.id) as total_bookings,
                    COALESCE(SUM(b.total_price),0) as total_revenue
             FROM {$lt} l
             LEFT JOIN {$bt} b ON l.id = b.pickup_location_id AND b.status IN ('confirmed','active','completed')
             GROUP BY l.id ORDER BY total_revenue DESC LIMIT %d",
            $limit
        ));
    }

    public static function get_booking_status_breakdown() {
        global $wpdb;
        $t = RAF_Helpers::table('bookings');

        $results = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$t} GROUP BY status"
        );

        $breakdown = [];
        foreach ($results as $row) {
            $breakdown[$row->status] = (int) $row->count;
        }
        return $breakdown;
    }

    public static function get_recent_bookings($limit = 10) {
        global $wpdb;
        $bt = RAF_Helpers::table('bookings');
        $ct = RAF_Helpers::table('customers');
        $vt = RAF_Helpers::table('vehicles');

        return $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, c.first_name, c.last_name, c.email,
                    v.make, v.model, v.year
             FROM {$bt} b
             LEFT JOIN {$ct} c ON b.customer_id = c.id
             LEFT JOIN {$vt} v ON b.vehicle_id = v.id
             ORDER BY b.created_at DESC LIMIT %d",
            $limit
        ));
    }

    public static function get_upcoming_pickups($days = 3) {
        global $wpdb;
        $bt = RAF_Helpers::table('bookings');
        $ct = RAF_Helpers::table('customers');
        $vt = RAF_Helpers::table('vehicles');

        return $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, c.first_name, c.last_name, c.phone,
                    v.make, v.model, v.year, v.plate_number
             FROM {$bt} b
             LEFT JOIN {$ct} c ON b.customer_id = c.id
             LEFT JOIN {$vt} v ON b.vehicle_id = v.id
             WHERE b.status = 'confirmed' 
               AND DATE(b.pickup_date) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL %d DAY)
             ORDER BY b.pickup_date ASC",
            $days
        ));
    }

    public static function get_upcoming_returns($days = 3) {
        global $wpdb;
        $bt = RAF_Helpers::table('bookings');
        $ct = RAF_Helpers::table('customers');
        $vt = RAF_Helpers::table('vehicles');

        return $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, c.first_name, c.last_name, c.phone,
                    v.make, v.model, v.year, v.plate_number
             FROM {$bt} b
             LEFT JOIN {$ct} c ON b.customer_id = c.id
             LEFT JOIN {$vt} v ON b.vehicle_id = v.id
             WHERE b.status = 'active' 
               AND DATE(b.dropoff_date) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL %d DAY)
             ORDER BY b.dropoff_date ASC",
            $days
        ));
    }

    public static function get_occupancy_rate($start_date = null, $end_date = null) {
        global $wpdb;
        $vt = RAF_Helpers::table('vehicles');
        $bt = RAF_Helpers::table('bookings');

        if (!$start_date) $start_date = current_time('Y-m-01');
        if (!$end_date) $end_date = current_time('Y-m-d');

        $total_units = (int) $wpdb->get_var("SELECT COALESCE(SUM(units),0) FROM {$vt} WHERE status = 'active'");
        if ($total_units === 0) return 0;

        $days = max(1, (strtotime($end_date) - strtotime($start_date)) / 86400);
        $total_capacity = $total_units * $days;

        $booked_days = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(DATEDIFF(LEAST(dropoff_date, %s), GREATEST(pickup_date, %s)) + 1), 0)
             FROM {$bt}
             WHERE status IN ('confirmed','active','completed')
               AND pickup_date <= %s AND dropoff_date >= %s",
            $end_date, $start_date, $end_date, $start_date
        ));

        return round(($booked_days / $total_capacity) * 100, 1);
    }
}
