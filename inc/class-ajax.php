<?php

require_once EDEL_BOOKING_PRO_PATH . '/inc/class-availability.php';
// class-emails.php はメインファイルで読み込み済み

class EdelBookingProAjax {

    private $availability;
    private $emails;
    private $settings;

    public function __construct() {
        $this->availability = new EdelBookingProAvailability();
        $this->emails = new EdelBookingProEmails();
        $this->settings = get_option('edel_booking_settings', array());

        // 管理画面用
        add_action('wp_ajax_edel_fetch_events', array($this, 'fetch_events'));
        add_action('wp_ajax_edel_save_booking', array($this, 'save_booking'));

        // フロントエンド予約用
        add_action('wp_ajax_edel_get_available_slots', array($this, 'get_available_slots'));
        add_action('wp_ajax_nopriv_edel_get_available_slots', array($this, 'get_available_slots'));

        add_action('wp_ajax_edel_submit_booking_front', array($this, 'submit_booking_front'));
        add_action('wp_ajax_nopriv_edel_submit_booking_front', array($this, 'submit_booking_front'));

        add_action('wp_ajax_edel_cancel_booking', array($this, 'cancel_booking'));

        add_action('wp_ajax_edel_get_calendar_status', array($this, 'get_calendar_status'));
        add_action('wp_ajax_nopriv_edel_get_calendar_status', array($this, 'get_calendar_status'));

        // マイページ・ログイン関連
        add_action('wp_ajax_edel_mypage_login', array($this, 'mypage_login'));
        add_action('wp_ajax_nopriv_edel_mypage_login', array($this, 'mypage_login'));

        add_action('wp_ajax_edel_mypage_lost_password', array($this, 'mypage_lost_password'));
        add_action('wp_ajax_nopriv_edel_mypage_lost_password', array($this, 'mypage_lost_password'));

        add_action('wp_ajax_edel_mypage_change_password', array($this, 'mypage_change_password'));
    }

    private function is_store_closed($date) {
        $closed_days = isset($this->settings['closed_days']) ? $this->settings['closed_days'] : array();
        if (empty($closed_days)) return false;
        $dw = strtolower(date('D', strtotime($date)));
        return in_array($dw, $closed_days);
    }

    private function is_staff_off($date, $staff_id) {
        global $wpdb;
        $table_exceptions = $wpdb->prefix . 'edel_booking_schedule_exceptions';
        $exception = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_exceptions WHERE staff_id = %d AND exception_date = %s", $staff_id, $date));
        if ($exception) return (bool)$exception->is_day_off;
        $dw_map = ['Sun' => 'sun', 'Mon' => 'mon', 'Tue' => 'tue', 'Wed' => 'wed', 'Thu' => 'thu', 'Fri' => 'fri', 'Sat' => 'sat'];
        $dw = $dw_map[date('D', strtotime($date))];
        $schedule = get_user_meta($staff_id, 'edel_weekly_schedule', true);
        if (!$schedule || !isset($schedule[$dw]) || !empty($schedule[$dw]['off'])) return true;
        return false;
    }

    // fetch_events, get_calendar_status, get_available_slots 等は変更なしのため省略せず記述
    public function fetch_events() {
        if (!current_user_can('edit_posts')) wp_send_json_error('Forbidden');
        global $wpdb;
        $table_appt = $wpdb->prefix . 'edel_booking_appointments';
        $start_req = isset($_GET['start']) ? sanitize_text_field($_GET['start']) : '';
        $end_req   = isset($_GET['end']) ? sanitize_text_field($_GET['end']) : '';
        $staff_id  = isset($_GET['staff_id']) ? intval($_GET['staff_id']) : 0;
        $events = array();
        $sql = "SELECT * FROM $table_appt WHERE start_datetime >= %s AND end_datetime <= %s";
        $params = array($start_req, $end_req);
        if ($staff_id > 0) {
            $sql .= " AND staff_id = %d";
            $params[] = $staff_id;
        }
        $appointments = $wpdb->get_results($wpdb->prepare($sql, $params));
        foreach ($appointments as $row) {
            $staff = get_userdata($row->staff_id);
            $staff_name = $staff ? $staff->display_name : '不明';
            $color = '#3788d8';
            if ($row->status === 'pending') $color = '#f39c12';
            if ($row->status === 'cancelled') $color = '#c0392b';
            if ($row->status === 'completed') $color = '#27ae60';
            $events[] = array('id' => $row->id, 'title' => $row->customer_name . ' (' . $staff_name . ')', 'start' => $row->start_datetime, 'end' => $row->end_datetime, 'color' => $color, 'extendedProps' => array('email' => $row->customer_email, 'phone' => $row->customer_phone, 'status' => $row->status));
        }
        if ($staff_id > 0) {
            $current_date = new DateTime($start_req);
            $end_date     = new DateTime($end_req);
            $table_exceptions = $wpdb->prefix . 'edel_booking_schedule_exceptions';
            $exceptions = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_exceptions WHERE staff_id = %d AND exception_date >= %s AND exception_date <= %s", $staff_id, $start_req, $end_req));
            $exceptions_map = array();
            foreach ($exceptions as $ex) {
                $exceptions_map[$ex->exception_date] = $ex;
            }
            $weekly_schedule = get_user_meta($staff_id, 'edel_weekly_schedule', true);
            $day_map = ['Sun' => 'sun', 'Mon' => 'mon', 'Tue' => 'tue', 'Wed' => 'wed', 'Thu' => 'thu', 'Fri' => 'fri', 'Sat' => 'sat'];
            while ($current_date < $end_date) {
                $ymd = $current_date->format('Y-m-d');
                $dw  = $day_map[$current_date->format('D')];
                $start_time = '';
                $end_time = '';
                $is_off = false;
                if (isset($exceptions_map[$ymd])) {
                    $ex = $exceptions_map[$ymd];
                    if ($ex->is_day_off) {
                        $is_off = true;
                    } else {
                        $start_time = $ex->start_time;
                        $end_time = $ex->end_time;
                    }
                } else {
                    if (isset($weekly_schedule[$dw])) {
                        if (!empty($weekly_schedule[$dw]['off'])) {
                            $is_off = true;
                        } else {
                            $start_time = $weekly_schedule[$dw]['start'];
                            $end_time = $weekly_schedule[$dw]['end'];
                        }
                    } else {
                        $is_off = true;
                    }
                }
                if (!$is_off && $start_time && $end_time) {
                    $events[] = array('start' => $ymd . 'T' . $start_time, 'end' => $ymd . 'T' . $end_time, 'display' => 'background', 'color' => '#d4edda');
                }
                $current_date->modify('+1 day');
            }
        }
        echo json_encode($events);
        wp_die();
    }

    public function get_calendar_status() {
        check_ajax_referer(EDEL_BOOKING_PRO_SLUG, 'nonce');
        $staff_id = intval($_GET['staff_id']);
        $service_id = intval($_GET['service_id']);
        $start_date = sanitize_text_field($_GET['start']);
        $end_date   = sanitize_text_field($_GET['end']);
        if (!$staff_id || !$service_id) {
            wp_send_json_success(array());
        }
        global $wpdb;
        $service = $wpdb->get_row($wpdb->prepare("SELECT duration FROM {$wpdb->prefix}edel_booking_services WHERE id = %d", $service_id));
        $duration = $service ? intval($service->duration) : 60;
        $events = array();
        $current = new DateTime($start_date);
        $end     = new DateTime($end_date);
        while ($current < $end) {
            $ymd = $current->format('Y-m-d');
            if ($this->is_store_closed($ymd) || $this->is_staff_off($ymd, $staff_id)) {
                $events[] = array('start' => $ymd, 'display' => 'background', 'classNames' => ['edel-day-closed'], 'extendedProps' => array('is_closed' => true));
            } else {
                $segments = $this->availability->get_availability_timeline($ymd, $staff_id, $service_id);
                $has_free = $this->availability->has_available_slot($ymd, $staff_id, $service_id);
                $am_html = $this->generate_bar_html($ymd, $segments, 0, 12);
                $pm_html = $this->generate_bar_html($ymd, $segments, 12, 24);
                $total_free_mins = 0;
                $total_work_mins = 0;
                $work_data = $this->get_staff_work_hours($ymd, $staff_id);
                if ($work_data) {
                    $start_ts = strtotime($ymd . ' ' . $work_data['start']);
                    $end_ts   = strtotime($ymd . ' ' . $work_data['end']);
                    $total_work_mins = ($end_ts - $start_ts) / 60;
                }
                foreach ($segments as $seg) {
                    $total_free_mins += ($seg['end'] - $seg['start']) / 60;
                }
                $symbol = '×';
                if ($has_free && $total_work_mins > 0) {
                    $ratio = $total_free_mins / $total_work_mins;
                    if ($ratio >= 0.5) $symbol = '◎';
                    elseif ($ratio >= 0.2) $symbol = '○';
                    else $symbol = '△';
                } elseif ($has_free) {
                    $symbol = '△';
                }
                $events[] = array('start' => $ymd, 'display' => 'background', 'extendedProps' => array('am_bar' => $am_html, 'pm_bar' => $pm_html, 'is_open' => $has_free, 'symbol' => $symbol));
            }
            $current->modify('+1 day');
        }
        wp_send_json_success($events);
    }

    private function get_staff_work_hours($date, $staff_id) {
        global $wpdb;
        $table_exceptions = $wpdb->prefix . 'edel_booking_schedule_exceptions';
        $exception = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_exceptions WHERE staff_id = %d AND exception_date = %s", $staff_id, $date));
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

    private function generate_bar_html($ymd, $segments, $start_hour, $end_hour) {
        $base_start = strtotime($ymd . ' ' . sprintf('%02d:00:00', $start_hour));
        $base_end   = strtotime($ymd . ' ' . sprintf('%02d:00:00', $end_hour));
        $total_sec  = $base_end - $base_start;
        $html = '';
        foreach ($segments as $seg) {
            $s = max($seg['start'], $base_start);
            $e = min($seg['end'], $base_end);
            if ($s < $e) {
                $left_percent = (($s - $base_start) / $total_sec) * 100;
                $width_percent = (($e - $s) / $total_sec) * 100;
                $html .= '<div class="edel-bar-segment" style="left:' . $left_percent . '%; width:' . $width_percent . '%;"></div>';
            }
        }
        return $html;
    }

    public function get_available_slots() {
        check_ajax_referer(EDEL_BOOKING_PRO_SLUG, 'nonce');
        $date = sanitize_text_field($_POST['date']);
        $staff_id = intval($_POST['staff_id']);
        $service_id = intval($_POST['service_id']);
        if (!$date || !$staff_id || !$service_id) wp_send_json_error('パラメータが不足しています');
        $slots = $this->availability->get_available_slots($date, $staff_id, $service_id);
        wp_send_json_success($slots);
    }

    public function save_booking() {
        check_ajax_referer(EDEL_BOOKING_PRO_SLUG, '_ajax_nonce');
        if (!current_user_can('edit_posts')) wp_send_json_error('権限がありません。');
        global $wpdb;
        $staff_id = intval($_POST['staff_id']);
        $service_id = intval($_POST['service_id']);
        $date = sanitize_text_field($_POST['date']);
        $time = sanitize_text_field($_POST['time']);
        $customer_name = sanitize_text_field($_POST['customer_name']);
        $customer_email = sanitize_email($_POST['customer_email']);
        $customer_phone = sanitize_text_field($_POST['customer_phone']);
        $note = sanitize_textarea_field($_POST['note']);
        $service = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}edel_booking_services WHERE id = %d", $service_id));
        if (!$service) wp_send_json_error('選択されたサービスが無効です。');
        $start_datetime = $date . ' ' . $time . ':00';
        $start_ts = strtotime($start_datetime);
        $end_ts = $start_ts + ($service->duration * 60);
        $end_datetime = date('Y-m-d H:i:s', $end_ts);
        $occupied_start_ts = $start_ts - ($service->buffer_before * 60);
        $occupied_end_ts = $end_ts + ($service->buffer_after * 60);
        $occupied_start = date('Y-m-d H:i:s', $occupied_start_ts);
        $occupied_end = date('Y-m-d H:i:s', $occupied_end_ts);
        $result = $wpdb->insert($wpdb->prefix . 'edel_booking_appointments', array('booking_hash' => wp_generate_password(32, false), 'staff_id' => $staff_id, 'service_id' => $service_id, 'start_datetime' => $start_datetime, 'end_datetime' => $end_datetime, 'occupied_start' => $occupied_start, 'occupied_end' => $occupied_end, 'customer_name' => $customer_name, 'customer_email' => $customer_email, 'customer_phone' => $customer_phone, 'note' => $note, 'status' => 'confirmed',), array('%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'));
        if ($result) {
            $staff = get_userdata($staff_id);
            $booking_data = array('customer_name' => $customer_name, 'customer_email' => $customer_email, 'staff_id' => $staff_id, 'date' => $date, 'time' => $time, 'end_time' => date('H:i', $end_ts), 'note' => $note);
            $this->emails->send_booking_confirmation($booking_data, $service->title, $staff->display_name);
            wp_send_json_success('予約を保存しました。');
        } else {
            wp_send_json_error('データベース保存エラー: ' . $wpdb->last_error);
        }
    }

    /**
     * フロントからの予約送信 (★修正: カスタムフィールド保存対応)
     */
    public function submit_booking_front() {
        check_ajax_referer(EDEL_BOOKING_PRO_SLUG, 'nonce');
        global $wpdb;
        $staff_id = intval($_POST['staff_id']);
        $service_id = intval($_POST['service_id']);
        $date = sanitize_text_field($_POST['date']);
        $time = sanitize_text_field($_POST['time']);
        $name = sanitize_text_field($_POST['customer_name']);
        $email = sanitize_email($_POST['customer_email']);
        $phone = sanitize_text_field($_POST['customer_phone']);
        $note = sanitize_textarea_field($_POST['note']);

        // ★カスタムフィールド処理
        $custom_data_json = NULL;
        if (isset($_POST['edel_custom_fields']) && is_array($_POST['edel_custom_fields'])) {
            $custom_fields_config = isset($this->settings['custom_fields']) ? $this->settings['custom_fields'] : array();
            $saved_custom_data = array();

            foreach ($custom_fields_config as $idx => $conf) {
                $val = isset($_POST['edel_custom_fields'][$idx]) ? sanitize_text_field($_POST['edel_custom_fields'][$idx]) : '';
                // ラベルと値をペアで保存 (設定変更に強くするため)
                $saved_custom_data[] = array(
                    'label' => $conf['label'],
                    'value' => $val
                );
            }
            if (!empty($saved_custom_data)) {
                $custom_data_json = json_encode($saved_custom_data, JSON_UNESCAPED_UNICODE);
            }
        }

        $create_account = isset($_POST['create_account']) && $_POST['create_account'] == '1';

        if (!$staff_id || !$service_id || !$date || !$time || !$name || !$email) wp_send_json_error('入力内容に不備があります。');

        $customer_id = NULL;
        $new_password = NULL;
        $account_created = false;
        if (is_user_logged_in()) {
            $customer_id = get_current_user_id();
        } else if ($create_account) {
            if (email_exists($email)) {
                $user = get_user_by('email', $email);
                $customer_id = $user->ID;
            } else {
                $new_password = wp_generate_password(12, false);
                $user_id = wp_create_user($email, $new_password, $email);
                if (!is_wp_error($user_id)) {
                    $customer_id = $user_id;
                    wp_update_user(array('ID' => $user_id, 'display_name' => $name));
                    wp_set_current_user($customer_id);
                    wp_set_auth_cookie($customer_id);
                    $account_created = true;
                }
            }
        }

        $service = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}edel_booking_services WHERE id = %d", $service_id));
        if (!$service) wp_send_json_error('サービスが無効です。');
        $start_datetime = $date . ' ' . $time . ':00';
        $start_ts = strtotime($start_datetime);
        $end_ts = $start_ts + ($service->duration * 60);
        $end_datetime = date('Y-m-d H:i:s', $end_ts);
        $occupied_start_ts = $start_ts - ($service->buffer_before * 60);
        $occupied_end_ts = $end_ts + ($service->buffer_after * 60);
        $occupied_start = date('Y-m-d H:i:s', $occupied_start_ts);
        $occupied_end = date('Y-m-d H:i:s', $occupied_end_ts);
        $collision = $wpdb->get_var($wpdb->prepare("SELECT count(*) FROM {$wpdb->prefix}edel_booking_appointments WHERE staff_id = %d AND status IN ('confirmed', 'pending') AND occupied_start < %s AND occupied_end > %s", $staff_id, $occupied_end, $occupied_start));
        if ($collision > 0) wp_send_json_error('申し訳ありません。タッチの差でその時間は埋まってしまいました。');

        $insert = $wpdb->insert(
            $wpdb->prefix . 'edel_booking_appointments',
            array(
                'booking_hash' => wp_generate_password(32, false),
                'customer_id' => $customer_id,
                'staff_id' => $staff_id,
                'service_id' => $service_id,
                'start_datetime' => $start_datetime,
                'end_datetime' => $end_datetime,
                'occupied_start' => $occupied_start,
                'occupied_end' => $occupied_end,
                'customer_name' => $name,
                'customer_email' => $email,
                'customer_phone' => $phone,
                'note' => $note,
                'custom_data' => $custom_data_json, // ★保存
                'status' => 'confirmed'
            ),
            array('%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        if (!$insert) wp_send_json_error('予約の保存に失敗しました。');

        $staff = get_userdata($staff_id);
        $booking_data = array(
            'customer_name' => $name,
            'customer_email' => $email,
            'staff_id' => $staff_id,
            'date' => $date,
            'time' => $time,
            'end_time' => date('H:i', $end_ts),
            'note' => $note,
            'custom_data' => $custom_data_json, // ★メール用
            'new_password' => $new_password
        );
        $this->emails->send_booking_confirmation($booking_data, $service->title, $staff->display_name);
        wp_send_json_success(array('message' => '予約完了', 'created_account' => $account_created));
    }

    // cancel_booking, mypage_login, mypage_lost_password, mypage_change_password は変更なしのため省略
    public function cancel_booking() {
        check_ajax_referer(EDEL_BOOKING_PRO_SLUG, 'nonce');
        if (!is_user_logged_in()) wp_send_json_error('ログインが必要です。');
        $booking_id = intval($_POST['booking_id']);
        $current_user = wp_get_current_user();
        global $wpdb;
        $table = $wpdb->prefix . 'edel_booking_appointments';
        $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d AND (customer_email = %s OR customer_id = %d)", $booking_id, $current_user->user_email, $current_user->ID));
        if (!$booking) wp_send_json_error('予約が見つからないか、権限がありません。');
        if ($booking->status === 'cancelled') wp_send_json_error('既にキャンセル済みです。');
        $settings = get_option('edel_booking_settings', array());
        $cancel_limit_hours = isset($settings['cancel_limit']) ? intval($settings['cancel_limit']) : 1;
        $limit_time = strtotime($booking->start_datetime) - ($cancel_limit_hours * 3600);
        if (time() > $limit_time) {
            wp_send_json_error("キャンセル期限（予約の{$cancel_limit_hours}時間前）を過ぎています。");
        }
        $updated = $wpdb->update($table, array('status' => 'cancelled'), array('id' => $booking_id));
        if ($updated === false) wp_send_json_error('データベースエラー');
        $staff = get_userdata($booking->staff_id);
        $this->emails->send_cancellation($booking, $staff->display_name);
        wp_send_json_success('キャンセルしました。');
    }
    public function mypage_login() {
        check_ajax_referer('edel-booking-pro', 'nonce');
        $email = sanitize_email($_POST['email']);
        $password = $_POST['password'];
        if (!$email || !$password) {
            wp_send_json_error('メールアドレスとパスワードを入力してください。');
        }
        $user = get_user_by('email', $email);
        if (!$user) {
            wp_send_json_error('ユーザーが見つかりません。');
        }
        $creds = array('user_login' => $user->user_login, 'user_password' => $password, 'remember' => true);
        $signon = wp_signon($creds, false);
        if (is_wp_error($signon)) {
            wp_send_json_error('パスワードが間違っています。');
        }
        wp_send_json_success('ログインしました。リダイレクトします...');
    }
    public function mypage_lost_password() {
        check_ajax_referer('edel-booking-pro', 'nonce');
        $email = sanitize_email($_POST['email']);
        if (!$email) wp_send_json_error('メールアドレスを入力してください。');
        $user = get_user_by('email', $email);
        if (!$user) {
            wp_send_json_error('このメールアドレスは登録されていません。');
        }
        $this->emails->start_mail_filters();
        $status = retrieve_password($user->user_login);
        $this->emails->stop_mail_filters();
        if (is_wp_error($status)) {
            wp_send_json_error('メール送信に失敗しました: ' . $status->get_error_message());
        }
        wp_send_json_success('パスワードリセット用のメールを送信しました。メール内のリンクから再設定を行ってください。');
    }
    public function mypage_change_password() {
        check_ajax_referer('edel-booking-pro', 'nonce');
        if (!is_user_logged_in()) wp_send_json_error('ログインが必要です。');
        $new_pass = $_POST['new_pass'];
        $confirm_pass = $_POST['confirm_pass'];
        if (!$new_pass) wp_send_json_error('新しいパスワードを入力してください。');
        if ($new_pass !== $confirm_pass) wp_send_json_error('新しいパスワードが一致しません。');
        if (strlen($new_pass) < 6) wp_send_json_error('パスワードは6文字以上にしてください。');
        $user = wp_get_current_user();
        wp_set_password($new_pass, $user->ID);
        $creds = array('user_login' => $user->user_login, 'user_password' => $new_pass, 'remember' => true);
        wp_signon($creds, false);
        wp_send_json_success('パスワードを変更しました。');
    }
}
