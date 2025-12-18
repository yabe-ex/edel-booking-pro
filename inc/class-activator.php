<?php

class EdelBookingProActivator {

    public static function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // 1. サービス (メニュー) テーブル
        $table_services = $wpdb->prefix . 'edel_booking_services';
        $sql_services = "CREATE TABLE $table_services (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            duration smallint(5) DEFAULT 60 NOT NULL,
            price mediumint(9) DEFAULT 0 NOT NULL,
            buffer_before smallint(5) DEFAULT 0 NOT NULL,
            buffer_after smallint(5) DEFAULT 0 NOT NULL,
            description text,
            is_active tinyint(1) DEFAULT 1 NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // 2. サービスとスタッフの中間テーブル (担当・指名料)
        $table_rel = $wpdb->prefix . 'edel_booking_service_staff';
        $sql_rel = "CREATE TABLE $table_rel (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            service_id mediumint(9) NOT NULL,
            staff_id bigint(20) unsigned NOT NULL,
            custom_price mediumint(9) DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY service_id (service_id),
            KEY staff_id (staff_id)
        ) $charset_collate;";

        // 3. 予約データテーブル (★修正: custom_data 追加)
        $table_appt = $wpdb->prefix . 'edel_booking_appointments';
        $sql_appt = "CREATE TABLE $table_appt (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            booking_hash varchar(64) NOT NULL,
            customer_id bigint(20) unsigned DEFAULT NULL,
            staff_id bigint(20) unsigned NOT NULL,
            service_id mediumint(9) NOT NULL,
            start_datetime datetime NOT NULL,
            end_datetime datetime NOT NULL,
            occupied_start datetime NOT NULL,
            occupied_end datetime NOT NULL,
            customer_name varchar(255) NOT NULL,
            customer_email varchar(255) NOT NULL,
            customer_phone varchar(50) DEFAULT '',
            note text,
            custom_data longtext DEFAULT NULL,
            status varchar(20) DEFAULT 'confirmed',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY staff_date (staff_id, start_datetime),
            KEY customer_email (customer_email)
        ) $charset_collate;";

        // 4. スケジュール例外テーブル
        $table_exceptions = $wpdb->prefix . 'edel_booking_schedule_exceptions';
        $sql_exceptions = "CREATE TABLE $table_exceptions (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            staff_id bigint(20) unsigned NOT NULL,
            exception_date date NOT NULL,
            is_day_off tinyint(1) DEFAULT 0,
            start_time time DEFAULT NULL,
            end_time time DEFAULT NULL,
            reason varchar(255) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY staff_date (staff_id, exception_date)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_services);
        dbDelta($sql_rel);
        dbDelta($sql_appt);
        dbDelta($sql_exceptions);

        // オプション初期値
        add_option('edel_booking_settings', array());
    }
}
