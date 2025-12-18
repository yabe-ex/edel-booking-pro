<?php

class EdelBookingProEmails {

    private $settings;

    public function __construct() {
        $this->settings = get_option('edel_booking_settings', array());
    }

    private function get_sender_headers() {
        $name  = isset($this->settings['sender_name']) && $this->settings['sender_name'] ? $this->settings['sender_name'] : get_bloginfo('name');
        $email = isset($this->settings['sender_email']) && $this->settings['sender_email'] ? $this->settings['sender_email'] : get_option('admin_email');
        return array("From: $name <$email>", "Content-Type: text/plain; charset=UTF-8");
    }

    private function get_admin_recipients() {
        $raw = isset($this->settings['admin_emails']) ? $this->settings['admin_emails'] : get_option('admin_email');
        $arr = explode(',', $raw);
        $arr = array_map('trim', $arr);
        $arr = array_filter($arr);
        if (empty($arr)) {
            return array(get_option('admin_email'));
        }
        return $arr;
    }

    public function start_mail_filters() {
        add_filter('wp_mail_from', array($this, 'custom_wp_mail_from'));
        add_filter('wp_mail_from_name', array($this, 'custom_wp_mail_from_name'));
    }
    public function stop_mail_filters() {
        remove_filter('wp_mail_from', array($this, 'custom_wp_mail_from'));
        remove_filter('wp_mail_from_name', array($this, 'custom_wp_mail_from_name'));
    }
    public function custom_wp_mail_from($original_email) {
        if (isset($this->settings['sender_email']) && $this->settings['sender_email']) {
            return $this->settings['sender_email'];
        }
        return $original_email;
    }
    public function custom_wp_mail_from_name($original_name) {
        if (isset($this->settings['sender_name']) && $this->settings['sender_name']) {
            return $this->settings['sender_name'];
        }
        return $original_name;
    }

    public function send_booking_confirmation($booking_data, $service_title, $staff_name) {
        $shop_name = isset($this->settings['shop_name']) ? $this->settings['shop_name'] : get_bloginfo('name');
        $subject = isset($this->settings['email_book_sub']) && $this->settings['email_book_sub'] ? $this->settings['email_book_sub'] : "【{shop_name}】ご予約ありがとうございます";
        $body    = isset($this->settings['email_book_body']) && $this->settings['email_book_body'] ? $this->settings['email_book_body'] : "{name} 様\n\nご予約ありがとうございます。\n日時: {date} {time}\nメニュー: {service}\n担当: {staff}\n\n{shop_name}";

        $time_display = $booking_data['time'];
        if (!empty($booking_data['end_time'])) {
            $time_display .= ' - ' . $booking_data['end_time'];
        }

        $replacements = array(
            '{shop_name}' => $shop_name,
            '{name}'      => $booking_data['customer_name'],
            '{date}'      => $booking_data['date'],
            '{time}'      => $time_display,
            '{service}'   => $service_title,
            '{staff}'     => $staff_name,
            '{note}'      => isset($booking_data['note']) ? $booking_data['note'] : ''
        );

        // ★カスタムフィールドの挿入
        if (!empty($booking_data['custom_data'])) {
            $custom_fields_str = "\n";
            $custom_arr = json_decode($booking_data['custom_data'], true);
            if (is_array($custom_arr)) {
                foreach ($custom_arr as $field) {
                    $custom_fields_str .= $field['label'] . ": " . $field['value'] . "\n";
                }
            }
            // 本文に {custom_fields} タグがあれば置換、なければ備考の前あたりに挿入
            if (strpos($body, '{custom_fields}') !== false) {
                $replacements['{custom_fields}'] = $custom_fields_str;
            } else {
                $body .= "\n\n[その他情報]" . $custom_fields_str;
            }
        } else {
            $replacements['{custom_fields}'] = '';
        }

        $body = $this->process_hidden_fields($body, $replacements);
        $subject = $this->replace_tags($subject, $replacements);

        if (!empty($booking_data['new_password'])) {
            $mypage_id = isset($this->settings['mypage_id']) ? intval($this->settings['mypage_id']) : 0;
            $mypage_url = ($mypage_id > 0) ? get_permalink($mypage_id) : home_url();
            $account_info = "\n\n-----------------------------\n■会員登録のお知らせ\n同時に会員登録を受け付けました。\n以下の情報でマイページにログインし、予約確認や次回予約を行えます。\n\nログインID (メール): " . $booking_data['customer_email'] . "\nパスワード: " . $booking_data['new_password'] . "\n\nマイページURL: " . $mypage_url . "\n-----------------------------";
            $body .= $account_info;
        }

        $headers = $this->get_sender_headers();
        wp_mail($booking_data['customer_email'], $subject, $body, $headers);

        $staff_user = get_user_by('ID', $booking_data['staff_id']);
        $subject_admin = "【予約通知】{$booking_data['customer_name']}様より新規予約";
        $body_admin = "新しい予約が入りました。\n\n顧客名: {$booking_data['customer_name']}\n日時: {$booking_data['date']} {$time_display}\nメニュー: {$service_title}\n担当: {$staff_name}\n備考: {$booking_data['note']}\n";

        // 管理者メールにもカスタムフィールド追加
        if (!empty($booking_data['custom_data'])) {
            $body_admin .= "\n[カスタムフィールド]\n";
            $custom_arr = json_decode($booking_data['custom_data'], true);
            if (is_array($custom_arr)) {
                foreach ($custom_arr as $field) {
                    $body_admin .= $field['label'] . ": " . $field['value'] . "\n";
                }
            }
        }

        $body_admin .= "\n\n管理画面を確認してください。";

        $recipients = $this->get_admin_recipients();
        if ($staff_user) {
            $recipients[] = $staff_user->user_email;
        }
        $recipients = array_unique($recipients);

        wp_mail($recipients, $subject_admin, $body_admin, $headers);
    }

    public function send_reminder($app, $service_title, $staff_name) {
        // (省略なし)
        $shop_name = isset($this->settings['shop_name']) ? $this->settings['shop_name'] : get_bloginfo('name');
        $subject = isset($this->settings['email_remind_sub']) && $this->settings['email_remind_sub'] ? $this->settings['email_remind_sub'] : "【{shop_name}】ご予約の確認";
        $body    = isset($this->settings['email_remind_body']) && $this->settings['email_remind_body'] ? $this->settings['email_remind_body'] : "{name} 様\n\n明日ご予約の日時です。\n日時: {date} {time}\nメニュー: {service}\n担当: {staff}\n\n{shop_name}";
        $time_display = date('H:i', strtotime($app->start_datetime));
        if (!empty($app->end_datetime)) {
            $time_display .= ' - ' . date('H:i', strtotime($app->end_datetime));
        }
        $replacements = array('{shop_name}' => $shop_name, '{name}' => $app->customer_name, '{date}' => date('Y年m月d日', strtotime($app->start_datetime)), '{time}' => $time_display, '{service}' => $service_title, '{staff}' => $staff_name);
        $body = $this->process_hidden_fields($body, $replacements);
        $subject = $this->replace_tags($subject, $replacements);
        $headers = $this->get_sender_headers();
        return wp_mail($app->customer_email, $subject, $body, $headers);
    }

    public function send_cancellation($booking, $staff_name) {
        // (省略なし)
        $shop_name = isset($this->settings['shop_name']) ? $this->settings['shop_name'] : get_bloginfo('name');
        $subject_user = "【{$shop_name}】予約キャンセルの完了";
        $date_display = date('Y-m-d H:i', strtotime($booking->start_datetime));
        if (!empty($booking->end_datetime)) {
            $date_display .= ' - ' . date('H:i', strtotime($booking->end_datetime));
        }
        $body_user = "予約のキャンセルを承りました。\n\n日時: {$date_display}\nまたのご利用をお待ちしております。\n\n{$shop_name}";
        $headers = $this->get_sender_headers();
        wp_mail($booking->customer_email, $subject_user, $body_user, $headers);
        $staff_user = get_userdata($booking->staff_id);
        $subject_admin = "【キャンセル通知】予約ID:{$booking->id} がキャンセルされました";
        $body_admin = "以下の予約がユーザーによりキャンセルされました。\n\n顧客名: {$booking->customer_name}\n日時: {$date_display}\n\n管理画面で確認してください。";
        $recipients = $this->get_admin_recipients();
        if ($staff_user) $recipients[] = $staff_user->user_email;
        $recipients = array_unique($recipients);
        wp_mail($recipients, $subject_admin, $body_admin, $headers);
    }

    private function process_hidden_fields($text, $replacements) {
        $hide_service = isset($this->settings['hide_service']) ? (bool)$this->settings['hide_service'] : false;
        $hide_staff   = isset($this->settings['hide_staff']) ? (bool)$this->settings['hide_staff'] : false;
        if ($hide_service) {
            $text = preg_replace('/^.*\{service\}.*$[\r\n]*/m', '', $text);
        }
        if ($hide_staff) {
            $text = preg_replace('/^.*\{staff\}.*$[\r\n]*/m', '', $text);
        }
        return $this->replace_tags($text, $replacements);
    }

    private function replace_tags($text, $replacements) {
        foreach ($replacements as $tag => $value) {
            $text = str_replace($tag, $value, $text);
        }
        return $text;
    }
}
