<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RAF_Calendar {

    public function __construct() {
        add_action( 'wp_ajax_raf_get_calendar_data', array( $this, 'ajax_get_calendar_data' ) );
        add_action( 'wp_ajax_raf_get_availability', array( $this, 'ajax_get_availability' ) );
        add_action( 'wp_ajax_nopriv_raf_get_availability', array( $this, 'ajax_get_availability' ) );
    }

    public function get_calendar_data( $start_date, $end_date, $vehicle_id = 0 ) {
        $bookings = RAF_Booking_Model::get_for_calendar( $start_date, $end_date, $vehicle_id );
        $events = array();
        $colors = array(
            'pending' => '#f0ad4e', 'confirmed' => '#5bc0de',
            'active' => '#5cb85c', 'completed' => '#337ab7',
        );

        foreach ( $bookings as $b ) {
            $events[] = array(
                'id'             => $b->id,
                'title'          => sprintf( '%s - %s %s', $b->vehicle_name, $b->first_name, $b->last_name ),
                'start'          => date( 'Y-m-d', strtotime( $b->pickup_date ) ),
                'end'            => date( 'Y-m-d', strtotime( $b->dropoff_date . ' +1 day' ) ),
                'color'          => $colors[ $b->status ] ?? '#777',
                'status'         => $b->status,
                'vehicle'        => $b->vehicle_name,
                'customer'       => trim( $b->first_name . ' ' . $b->last_name ),
                'booking_number' => $b->booking_number,
                'pickup_date'    => RAF_Helpers::format_datetime( $b->pickup_date ),
                'dropoff_date'   => RAF_Helpers::format_datetime( $b->dropoff_date ),
                'total_price'    => RAF_Helpers::format_price( $b->total_price ),
            );
        }

        if ( $vehicle_id ) {
            $blocked = RAF_Availability_Checker::get_blocked_dates( $vehicle_id, $start_date, $end_date );
            foreach ( $blocked as $bd ) {
                $events[] = array(
                    'id'      => 'blocked_' . $bd->id,
                    'title'   => 'BLOCKED: ' . ( $bd->reason ?: 'Maintenance' ),
                    'start'   => date( 'Y-m-d', strtotime( $bd->date_from ) ),
                    'end'     => date( 'Y-m-d', strtotime( $bd->date_to . ' +1 day' ) ),
                    'color'   => '#d9534f',
                    'status'  => 'blocked',
                    'blocked' => true,
                );
            }
        }
        return $events;
    }

    public function render_admin_calendar() {
        $vehicles = RAF_Vehicle::get_all( array( 'status' => 'active', 'limit' => 0 ) );
        ob_start();
        ?>
        <div class="raf-calendar-wrapper">
            <div class="raf-calendar-controls">
                <select id="raf-calendar-vehicle">
                    <option value="0"><?php _e( 'All Bikes', 'rentafleet' ); ?></option>
                    <?php foreach ( $vehicles as $v ) : ?>
                        <option value="<?php echo esc_attr( $v->id ); ?>"><?php echo esc_html( $v->name ); ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="raf-calendar-nav">
                    <button type="button" class="button" id="raf-cal-prev">&laquo; <?php _e( 'Prev', 'rentafleet' ); ?></button>
                    <span id="raf-cal-title" class="raf-cal-title"></span>
                    <button type="button" class="button" id="raf-cal-next"><?php _e( 'Next', 'rentafleet' ); ?> &raquo;</button>
                    <button type="button" class="button" id="raf-cal-today"><?php _e( 'Today', 'rentafleet' ); ?></button>
                </div>
                <div class="raf-calendar-legend">
                    <span class="legend-item"><span class="legend-color" style="background:#f0ad4e"></span> Pending</span>
                    <span class="legend-item"><span class="legend-color" style="background:#5bc0de"></span> Confirmed</span>
                    <span class="legend-item"><span class="legend-color" style="background:#5cb85c"></span> Active</span>
                    <span class="legend-item"><span class="legend-color" style="background:#337ab7"></span> Completed</span>
                    <span class="legend-item"><span class="legend-color" style="background:#d9534f"></span> Blocked</span>
                </div>
            </div>
            <div id="raf-calendar-grid" class="raf-calendar-grid"></div>
        </div>
        <div id="raf-calendar-modal" class="raf-modal" style="display:none;">
            <div class="raf-modal-content">
                <span class="raf-modal-close">&times;</span>
                <div id="raf-modal-body"></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function ajax_get_calendar_data() {
        check_ajax_referer( 'raf_admin_nonce', 'nonce' );
        $start   = sanitize_text_field( $_POST['start'] ?? date( 'Y-m-01' ) );
        $end     = sanitize_text_field( $_POST['end'] ?? date( 'Y-m-t' ) );
        $vehicle = intval( $_POST['vehicle_id'] ?? 0 );
        wp_send_json_success( $this->get_calendar_data( $start, $end, $vehicle ) );
    }

    public function ajax_get_availability() {
        check_ajax_referer( 'raf_public_nonce', 'nonce' );
        $vehicle_id = intval( $_POST['vehicle_id'] ?? 0 );
        $month = intval( $_POST['month'] ?? date( 'n' ) );
        $year  = intval( $_POST['year'] ?? date( 'Y' ) );
        wp_send_json_success( RAF_Availability_Checker::get_availability_calendar( $vehicle_id, $month, $year ) );
    }
}
