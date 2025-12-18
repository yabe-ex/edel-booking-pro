<?php

class EdelBookingProCron {

    private $emails;

    public function init() {
        add_action('edel_booking_daily_reminder', array($this, 'send_reminders'));
    }

    public static function activate() {
        if (!wp_next_scheduled('edel_booking_daily_reminder')) {
            $time = strtotime('tomorrow 09:00:00');
            wp_schedule_event($time, 'daily', 'edel_booking_daily_reminder');
        }
    }

    public static function deactivate() {
        $timestamp = wp_next_scheduled('edel_booking_daily_reminder');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'edel_booking_daily_reminder');
        }
    }

    public function send_reminders() {
        global $wpdb;
        $table = $wpdb->prefix . 'edel_booking_appointments';

        // 設定の読み込み
        $settings = get_option('edel_booking_settings', array());
        $days_before = isset($settings['reminder_timing']) ? intval($settings['reminder_timing']) : 1;

        $target_ts = strtotime("+{$days_before} day", current_time('timestamp'));
        $target_date = date('Y-m-d', $target_ts);

        $start_range = $target_date . ' 00:00:00';
        $end_range   = $target_date . ' 23:59:59';

        $appointments = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table
             WHERE status = 'confirmed'
             AND reminder_sent = 0
             AND start_datetime >= %s
             AND start_datetime <= %s",
            $start_range,
            $end_range
        ));

        if (empty($appointments)) {
            return;
        }

        // ★修正: Emailsクラスの初期化
        if (!isset($this->emails)) {
            $this->emails = new EdelBookingProEmails();
        }

        foreach ($appointments as $app) {
            $staff = get_userdata($app->staff_id);
            $service = $wpdb->get_row($wpdb->prepare("SELECT title FROM {$wpdb->prefix}edel_booking_services WHERE id = %d", $app->service_id));

            // ★修正: クラスメソッドで送信
            $sent = $this->emails->send_reminder($app, $service ? $service->title : '不明', $staff->display_name);

            if ($sent) {
                $wpdb->update(
                    $table,
                    array('reminder_sent' => 1),
                    array('id' => $app->id)
                );
            }
        }
    }
}
