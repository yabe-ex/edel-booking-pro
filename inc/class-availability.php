<?php

class EdelBookingProAvailability {

    private $settings;

    public function __construct() {
        // 設定をロード
        $this->settings = get_option('edel_booking_settings', array());
    }

    /**
     * 店舗定休日かチェックするヘルパー
     */
    private function is_store_closed($date) {
        $closed_days = isset($this->settings['closed_days']) ? $this->settings['closed_days'] : array();
        if (empty($closed_days)) return false;

        $dw = strtolower(date('D', strtotime($date))); // mon, tue...
        return in_array($dw, $closed_days);
    }

    public function get_available_slots($date, $staff_id, $service_id) {
        // ★定休日チェック
        if ($this->is_store_closed($date)) return array();

        global $wpdb;
        $service = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}edel_booking_services WHERE id = %d", $service_id));
        if (!$service) return array();

        // ... (以下、既存ロジック。変更なし) ...
        $duration = intval($service->duration);
        $buffer_before = intval($service->buffer_before);
        $buffer_after = intval($service->buffer_after);

        $work_data = $this->get_work_hours($date, $staff_id);
        if (!$work_data) return array();
        $work_start = $work_data['start'];
        $work_end = $work_data['end'];

        $appointments = $this->get_appointments($date, $staff_id);
        $slots = array();
        $current_ts = strtotime($date . ' ' . $work_start);
        $end_ts     = strtotime($date . ' ' . $work_end);
        $now_ts = current_time('timestamp');
        $min_start_ts = $now_ts + (30 * 60);

        while ($current_ts + ($duration * 60) <= $end_ts) {
            if ($current_ts < $min_start_ts) {
                $current_ts += (5 * 60);
                continue;
            }
            $proposed_start_ts = $current_ts - ($buffer_before * 60);
            $proposed_end_ts   = $current_ts + ($duration * 60) + ($buffer_after * 60);
            if (!$this->is_collision($proposed_start_ts, $proposed_end_ts, $appointments)) {
                $slots[] = date('H:i', $current_ts);
            }
            $current_ts += (15 * 60);
        }
        return $slots;
    }

    public function has_available_slot($date, $staff_id, $service_id) {
        // ★定休日チェック
        if ($this->is_store_closed($date)) return false;

        global $wpdb;
        $service = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}edel_booking_services WHERE id = %d", $service_id));
        if (!$service) return false;

        // ... (以下、既存ロジック。変更なし) ...
        $duration = intval($service->duration);
        $buffer_before = intval($service->buffer_before);
        $buffer_after = intval($service->buffer_after);

        $work_data = $this->get_work_hours($date, $staff_id);
        if (!$work_data) return false;
        $work_start = $work_data['start'];
        $work_end = $work_data['end'];

        $appointments = $this->get_appointments($date, $staff_id);
        $current_ts = strtotime($date . ' ' . $work_start);
        $end_ts     = strtotime($date . ' ' . $work_end);
        $now_ts = current_time('timestamp');
        $min_start_ts = $now_ts + (30 * 60);

        while ($current_ts + ($duration * 60) <= $end_ts) {
            if ($current_ts < $min_start_ts) {
                $current_ts += (5 * 60);
                continue;
            }
            $proposed_start_ts = $current_ts - ($buffer_before * 60);
            $proposed_end_ts   = $current_ts + ($duration * 60) + ($buffer_after * 60);
            if (!$this->is_collision($proposed_start_ts, $proposed_end_ts, $appointments)) {
                return true;
            }
            $current_ts += (15 * 60);
        }
        return false;
    }

    public function get_availability_timeline($date, $staff_id, $service_id) {
        // ★定休日チェック
        if ($this->is_store_closed($date)) return array();

        global $wpdb;
        $work_data = $this->get_work_hours($date, $staff_id);
        if (!$work_data) return array();

        // ... (以下、既存ロジック。変更なし) ...
        $work_start_ts = strtotime($date . ' ' . $work_data['start']);
        $work_end_ts   = strtotime($date . ' ' . $work_data['end']);

        $appointments = $this->get_appointments($date, $staff_id);
        $free_segments = array(array('start' => $work_start_ts, 'end' => $work_end_ts));
        foreach ($appointments as $app) {
            $busy_start = strtotime($app->occupied_start);
            $busy_end   = strtotime($app->occupied_end);
            $new_free_segments = array();
            foreach ($free_segments as $segment) {
                if ($busy_end <= $segment['start'] || $busy_start >= $segment['end']) {
                    $new_free_segments[] = $segment;
                    continue;
                }
                if ($busy_start > $segment['start']) {
                    $new_free_segments[] = array('start' => $segment['start'], 'end' => $busy_start);
                }
                if ($busy_end < $segment['end']) {
                    $new_free_segments[] = array('start' => $busy_end, 'end' => $segment['end']);
                }
            }
            $free_segments = $new_free_segments;
        }

        $now_ts = current_time('timestamp');
        $min_start_ts = $now_ts + (30 * 60);
        $final_segments = array();
        foreach ($free_segments as $seg) {
            if ($seg['end'] <= $min_start_ts) continue;
            $start = max($seg['start'], $min_start_ts);
            if ($start < $seg['end']) {
                $final_segments[] = array('start' => $start, 'end' => $seg['end']);
            }
        }

        $service = $wpdb->get_row($wpdb->prepare("SELECT duration FROM {$wpdb->prefix}edel_booking_services WHERE id = %d", $service_id));
        $min_duration = intval($service->duration) * 60;
        $valid_segments = array();
        foreach ($final_segments as $seg) {
            if (($seg['end'] - $seg['start']) >= $min_duration) {
                $valid_segments[] = $seg;
            }
        }
        return $valid_segments;
    }

    // --- Private Helpers (変更なし) ---
    private function get_work_hours($date, $staff_id) {
        global $wpdb;
        $table_exceptions = $wpdb->prefix . 'edel_booking_schedule_exceptions';
        $exception = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_exceptions WHERE staff_id = %d AND exception_date = %s",
            $staff_id,
            $date
        ));
        if ($exception) {
            if ($exception->is_day_off) return false;
            return array('start' => $exception->start_time, 'end' => $exception->end_time);
        }
        $day_of_week_map = ['Sun' => 'sun', 'Mon' => 'mon', 'Tue' => 'tue', 'Wed' => 'wed', 'Thu' => 'thu', 'Fri' => 'fri', 'Sat' => 'sat'];
        $dw = $day_of_week_map[date('D', strtotime($date))];
        $schedule = get_user_meta($staff_id, 'edel_weekly_schedule', true);
        if (!$schedule || !isset($schedule[$dw]) || !empty($schedule[$dw]['off'])) return false;
        return array('start' => $schedule[$dw]['start'], 'end' => $schedule[$dw]['end']);
    }

    private function get_appointments($date, $staff_id) {
        global $wpdb;
        $search_start = $date . ' 00:00:00';
        $search_end   = $date . ' 23:59:59';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT occupied_start, occupied_end FROM {$wpdb->prefix}edel_booking_appointments
             WHERE staff_id = %d
             AND status IN ('confirmed', 'pending')
             AND end_datetime > %s AND start_datetime < %s",
            $staff_id,
            $search_start,
            $search_end
        ));
    }

    private function is_collision($start_ts, $end_ts, $appointments) {
        foreach ($appointments as $app) {
            $app_start = strtotime($app->occupied_start);
            $app_end   = strtotime($app->occupied_end);
            if ($start_ts < $app_end && $end_ts > $app_start) {
                return true;
            }
        }
        return false;
    }
}
