<?php

class EdelBookingProActivator {

    public static function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // テーブル名の定義
        $table_services    = $wpdb->prefix . 'edel_booking_services';
        $table_appointments = $wpdb->prefix . 'edel_booking_appointments';
        $table_exceptions  = $wpdb->prefix . 'edel_booking_schedule_exceptions';
        $table_service_staff = $wpdb->prefix . 'edel_booking_service_staff';
        $table_resources   = $wpdb->prefix . 'edel_booking_resources';
        $table_service_resources = $wpdb->prefix . 'edel_booking_service_resources';

        // 1. サービス（メニュー）テーブル
        $sql_services = "CREATE TABLE $table_services (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            duration int(11) NOT NULL,
            buffer_before int(11) DEFAULT 0,
            buffer_after int(11) DEFAULT 0,
            price decimal(10, 2) DEFAULT 0,
            description text,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // 2. 予約データテーブル
        // ★修正: reminder_sent カラムを追加しました
        $sql_appointments = "CREATE TABLE $table_appointments (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            booking_hash varchar(32) NOT NULL,
            customer_id bigint(20) UNSIGNED NULL,
            staff_id bigint(20) UNSIGNED NOT NULL,
            service_id bigint(20) UNSIGNED NOT NULL,
            start_datetime datetime NOT NULL,
            end_datetime datetime NOT NULL,
            occupied_start datetime NOT NULL,
            occupied_end datetime NOT NULL,
            customer_name varchar(255) NOT NULL,
            customer_email varchar(255) NOT NULL,
            customer_phone varchar(50),
            note text,
            status varchar(20) DEFAULT 'pending',
            payment_status varchar(20) DEFAULT 'unpaid',
            reminder_sent tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY staff_date_range (staff_id, start_datetime, end_datetime),
            KEY customer_email (customer_email),
            UNIQUE KEY hash (booking_hash)
        ) $charset_collate;";

        // 3. シフト例外テーブル
        $sql_exceptions = "CREATE TABLE $table_exceptions (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            staff_id bigint(20) UNSIGNED NOT NULL,
            exception_date date NOT NULL,
            start_time time NULL,
            end_time time NULL,
            is_day_off tinyint(1) DEFAULT 0,
            reason varchar(255),
            PRIMARY KEY  (id),
            KEY staff_date (staff_id, exception_date)
        ) $charset_collate;";

        // 4. スタッフとサービスの紐付け
        $sql_service_staff = "CREATE TABLE $table_service_staff (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            service_id bigint(20) UNSIGNED NOT NULL,
            staff_id bigint(20) UNSIGNED NOT NULL,
            custom_price decimal(10, 2) NULL,
            custom_duration int(11) NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY service_staff (service_id, staff_id)
        ) $charset_collate;";

        // 5. リソース（部屋・機材）テーブル
        $sql_resources = "CREATE TABLE $table_resources (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            quantity int(11) DEFAULT 1,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // 6. サービスとリソースの紐付け
        $sql_service_resources = "CREATE TABLE $table_service_resources (
            service_id bigint(20) UNSIGNED NOT NULL,
            resource_id bigint(20) UNSIGNED NOT NULL,
            PRIMARY KEY (service_id, resource_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        dbDelta($sql_services);
        dbDelta($sql_appointments);
        dbDelta($sql_exceptions);
        dbDelta($sql_service_staff);
        dbDelta($sql_resources);
        dbDelta($sql_service_resources);
    }
}
