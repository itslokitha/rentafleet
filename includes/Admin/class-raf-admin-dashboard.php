<?php
if (!defined('ABSPATH')) exit;

class RAF_Admin_Dashboard {

    public static function render() {
        $stats = RAF_Statistics::get_dashboard_stats();
        $recent = RAF_Statistics::get_recent_bookings(5);
        $pickups = RAF_Statistics::get_upcoming_pickups(3);
        $returns = RAF_Statistics::get_upcoming_returns(3);
        $occupancy = RAF_Statistics::get_occupancy_rate();
        $currency = get_option('raf_currency', 'USD');
        ?>
        <div class="wrap raf-dashboard">
            <h1><?php _e('RentAFleet Dashboard', 'rentafleet'); ?></h1>

            <div class="raf-stats-grid">
                <div class="raf-stat-card">
                    <h3><?php _e('Today\'s Bookings', 'rentafleet'); ?></h3>
                    <span class="raf-stat-number"><?php echo $stats['today_bookings']; ?></span>
                </div>
                <div class="raf-stat-card">
                    <h3><?php _e('This Month', 'rentafleet'); ?></h3>
                    <span class="raf-stat-number"><?php echo $stats['month_bookings']; ?></span>
                </div>
                <div class="raf-stat-card raf-stat-revenue">
                    <h3><?php _e('Monthly Revenue', 'rentafleet'); ?></h3>
                    <span class="raf-stat-number"><?php echo RAF_Helpers::format_price($stats['month_revenue']); ?></span>
                </div>
                <div class="raf-stat-card raf-stat-revenue">
                    <h3><?php _e('Yearly Revenue', 'rentafleet'); ?></h3>
                    <span class="raf-stat-number"><?php echo RAF_Helpers::format_price($stats['year_revenue']); ?></span>
                </div>
                <div class="raf-stat-card">
                    <h3><?php _e('Active Rentals', 'rentafleet'); ?></h3>
                    <span class="raf-stat-number"><?php echo $stats['active_bookings']; ?></span>
                </div>
                <div class="raf-stat-card">
                    <h3><?php _e('Pending', 'rentafleet'); ?></h3>
                    <span class="raf-stat-number"><?php echo $stats['pending_bookings']; ?></span>
                </div>
                <div class="raf-stat-card">
                    <h3><?php _e('Fleet Size', 'rentafleet'); ?></h3>
                    <span class="raf-stat-number"><?php echo $stats['total_vehicles']; ?></span>
                </div>
                <div class="raf-stat-card">
                    <h3><?php _e('Occupancy Rate', 'rentafleet'); ?></h3>
                    <span class="raf-stat-number"><?php echo $occupancy; ?>%</span>
                </div>
            </div>

            <div class="raf-dashboard-columns">
                <div class="raf-dashboard-col">
                    <div class="raf-panel">
                        <h2><?php _e('Upcoming Pickups', 'rentafleet'); ?></h2>
                        <?php if ($pickups): ?>
                        <table class="widefat striped">
                            <thead><tr>
                                <th><?php _e('Booking', 'rentafleet'); ?></th>
                                <th><?php _e('Customer', 'rentafleet'); ?></th>
                                <th><?php _e('Vehicle', 'rentafleet'); ?></th>
                                <th><?php _e('Pickup', 'rentafleet'); ?></th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ($pickups as $b): ?>
                            <tr>
                                <td><a href="<?php echo admin_url('admin.php?page=raf-bookings&action=view&id=' . $b->id); ?>">#<?php echo esc_html($b->booking_number); ?></a></td>
                                <td><?php echo esc_html($b->first_name . ' ' . $b->last_name); ?></td>
                                <td><?php echo esc_html($b->make . ' ' . $b->model); ?></td>
                                <td><?php echo RAF_Helpers::format_datetime($b->pickup_date); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <p><?php _e('No upcoming pickups.', 'rentafleet'); ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="raf-panel">
                        <h2><?php _e('Upcoming Returns', 'rentafleet'); ?></h2>
                        <?php if ($returns): ?>
                        <table class="widefat striped">
                            <thead><tr>
                                <th><?php _e('Booking', 'rentafleet'); ?></th>
                                <th><?php _e('Customer', 'rentafleet'); ?></th>
                                <th><?php _e('Vehicle', 'rentafleet'); ?></th>
                                <th><?php _e('Return', 'rentafleet'); ?></th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ($returns as $b): ?>
                            <tr>
                                <td><a href="<?php echo admin_url('admin.php?page=raf-bookings&action=view&id=' . $b->id); ?>">#<?php echo esc_html($b->booking_number); ?></a></td>
                                <td><?php echo esc_html($b->first_name . ' ' . $b->last_name); ?></td>
                                <td><?php echo esc_html($b->make . ' ' . $b->model); ?></td>
                                <td><?php echo RAF_Helpers::format_datetime($b->dropoff_date); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <p><?php _e('No upcoming returns.', 'rentafleet'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="raf-dashboard-col">
                    <div class="raf-panel">
                        <h2><?php _e('Recent Bookings', 'rentafleet'); ?></h2>
                        <?php if ($recent): ?>
                        <table class="widefat striped">
                            <thead><tr>
                                <th><?php _e('Booking', 'rentafleet'); ?></th>
                                <th><?php _e('Customer', 'rentafleet'); ?></th>
                                <th><?php _e('Total', 'rentafleet'); ?></th>
                                <th><?php _e('Status', 'rentafleet'); ?></th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ($recent as $b): ?>
                            <tr>
                                <td><a href="<?php echo admin_url('admin.php?page=raf-bookings&action=view&id=' . $b->id); ?>">#<?php echo esc_html($b->booking_number); ?></a></td>
                                <td><?php echo esc_html($b->first_name . ' ' . $b->last_name); ?></td>
                                <td><?php echo RAF_Helpers::format_price($b->total_price); ?></td>
                                <td><?php echo RAF_Helpers::status_badge($b->status); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <p><?php _e('No bookings yet.', 'rentafleet'); ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="raf-panel">
                        <h2><?php _e('Quick Stats', 'rentafleet'); ?></h2>
                        <ul class="raf-quick-stats">
                            <li><strong><?php _e('Total Bookings:', 'rentafleet'); ?></strong> <?php echo $stats['total_bookings']; ?></li>
                            <li><strong><?php _e('Total Revenue:', 'rentafleet'); ?></strong> <?php echo RAF_Helpers::format_price($stats['total_revenue']); ?></li>
                            <li><strong><?php _e('Avg. Booking Value:', 'rentafleet'); ?></strong> <?php echo RAF_Helpers::format_price($stats['avg_booking_value']); ?></li>
                            <li><strong><?php _e('Total Customers:', 'rentafleet'); ?></strong> <?php echo $stats['total_customers']; ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
