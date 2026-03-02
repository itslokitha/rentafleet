<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RAF_Deactivator {
    public static function deactivate() {
        wp_clear_scheduled_hook( 'raf_daily_cron' );
        wp_clear_scheduled_hook( 'raf_hourly_cron' );
        flush_rewrite_rules();
    }
}
