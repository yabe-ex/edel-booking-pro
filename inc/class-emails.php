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

        $default_subject = sprintf(__('【%s】Booking Confirmation', 'edel-booking'), '{shop_name}');
        $default_body = __("{name}\n\nThank you for your booking.\nDate: {date} {time}\nMenu: {service}\nStaff: {staff}\n\n{shop_name}", 'edel-booking');

        $subject = isset($this->settings['email_book_sub']) && $this->settings['email_book_sub'] ? $this->settings['email_book_sub'] : $default_subject;
        $body    = isset($this->settings['email_book_body']) && $this->settings['email_book_body'] ? $this->settings['email_book_body'] : $default_body;

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

        if (!empty($booking_data['custom_data'])) {
            $custom_fields_str = "\n";
            $custom_arr = json_decode($booking_data['custom_data'], true);
            if (is_array($custom_arr)) {
                foreach ($custom_arr as $field) {
                    $custom_fields_str .= $field['label'] . ": " . $field['value'] . "\n";
                }
            }
            if (strpos($body, '{custom_fields}') !== false) {
                $replacements['{custom_fields}'] = $custom_fields_str;
            } else {
                $body .= "\n\n" . __('[Other Information]', 'edel-booking') . $custom_fields_str;
            }
        } else {
            $replacements['{custom_fields}'] = '';
        }

        $body = $this->process_hidden_fields($body, $replacements);
        $subject = $this->replace_tags($subject, $replacements);

        if (!empty($booking_data['new_password'])) {
            $mypage_id = isset($this->settings['mypage_id']) ? intval($this->settings['mypage_id']) : 0;
            $mypage_url = ($mypage_id > 0) ? get_permalink($mypage_id) : home_url();

            $account_info = "\n\n-----------------------------\n" . __('■ Account Registration', 'edel-booking') . "\n";
            $account_info .= __('You have been registered as a member.', 'edel-booking') . "\n";
            $account_info .= __('Login ID (Email): ', 'edel-booking') . $booking_data['customer_email'] . "\n";
            $account_info .= __('Password: ', 'edel-booking') . $booking_data['new_password'] . "\n\n";
            $account_info .= __('My Page URL: ', 'edel-booking') . $mypage_url . "\n-----------------------------";

            $body .= $account_info;
        }

        $headers = $this->get_sender_headers();
        wp_mail($booking_data['customer_email'], $subject, $body, $headers);

        $staff_user = get_user_by('ID', $booking_data['staff_id']);
        $subject_admin = sprintf(__('【Booking Notification】New booking from %s', 'edel-booking'), $booking_data['customer_name']);
        $body_admin = __("New booking received.\n\nCustomer: %s\nDate: %s %s\nMenu: %s\nStaff: %s\nNote: %s\n", 'edel-booking');
        $body_admin = sprintf($body_admin, $booking_data['customer_name'], $booking_data['date'], $time_display, $service_title, $staff_name, $booking_data['note']);

        if (!empty($booking_data['custom_data'])) {
            $body_admin .= "\n" . __('[Custom Fields]', 'edel-booking') . "\n";
            $custom_arr = json_decode($booking_data['custom_data'], true);
            if (is_array($custom_arr)) {
                foreach ($custom_arr as $field) {
                    $body_admin .= $field['label'] . ": " . $field['value'] . "\n";
                }
            }
        }

        $body_admin .= "\n\n" . __('Please check the admin panel.', 'edel-booking');

        $recipients = $this->get_admin_recipients();
        if ($staff_user) {
            $recipients[] = $staff_user->user_email;
        }
        $recipients = array_unique($recipients);

        wp_mail($recipients, $subject_admin, $body_admin, $headers);
    }

    public function send_reminder($app, $service_title, $staff_name) {
        $shop_name = isset($this->settings['shop_name']) ? $this->settings['shop_name'] : get_bloginfo('name');

        $default_subject = sprintf(__('【%s】Reservation Reminder', 'edel-booking'), '{shop_name}');
        $default_body = __("{name}\n\nYour appointment is tomorrow.\nDate: {date} {time}\nMenu: {service}\nStaff: {staff}\n\n{shop_name}", 'edel-booking');

        $subject = isset($this->settings['email_remind_sub']) && $this->settings['email_remind_sub'] ? $this->settings['email_remind_sub'] : $default_subject;
        $body    = isset($this->settings['email_remind_body']) && $this->settings['email_remind_body'] ? $this->settings['email_remind_body'] : $default_body;

        $time_display = date('H:i', strtotime($app->start_datetime));
        if (!empty($app->end_datetime)) {
            $time_display .= ' - ' . date('H:i', strtotime($app->end_datetime));
        }
        $replacements = array(
            '{shop_name}' => $shop_name,
            '{name}' => $app->customer_name,
            '{date}' => date('Y-m-d', strtotime($app->start_datetime)),
            '{time}' => $time_display,
            '{service}' => $service_title,
            '{staff}' => $staff_name
        );
        $body = $this->process_hidden_fields($body, $replacements);
        $subject = $this->replace_tags($subject, $replacements);
        $headers = $this->get_sender_headers();
        return wp_mail($app->customer_email, $subject, $body, $headers);
    }

    public function send_cancellation($booking, $staff_name) {
        $shop_name = isset($this->settings['shop_name']) ? $this->settings['shop_name'] : get_bloginfo('name');

        $subject_user = sprintf(__('【%s】Cancellation Completed', 'edel-booking'), $shop_name);
        $date_display = date('Y-m-d H:i', strtotime($booking->start_datetime));
        if (!empty($booking->end_datetime)) {
            $date_display .= ' - ' . date('H:i', strtotime($booking->end_datetime));
        }
        $body_user = __("Your cancellation has been processed.\n\nDate: %s\nWe look forward to seeing you again.\n\n%s", 'edel-booking');
        $body_user = sprintf($body_user, $date_display, $shop_name);

        $headers = $this->get_sender_headers();
        wp_mail($booking->customer_email, $subject_user, $body_user, $headers);

        $staff_user = get_userdata($booking->staff_id);
        $subject_admin = sprintf(__('【Cancellation】Booking ID: %s has been cancelled', 'edel-booking'), $booking->id);
        $body_admin = __("The following booking was cancelled by user.\n\nCustomer: %s\nDate: %s\n\nPlease check admin panel.", 'edel-booking');
        $body_admin = sprintf($body_admin, $booking->customer_name, $date_display);

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
