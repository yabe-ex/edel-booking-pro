<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * @package Edel_Booking_Pro
 */

// WordPressから呼び出されていない場合は終了
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// 1. 設定を確認 (削除フラグが立っているか)
$settings = get_option('edel_booking_settings');

// フラグがなければ何もせず終了 (安全策: デフォルトは削除しない)
if (empty($settings['delete_data_on_uninstall'])) {
    return;
}

// 2. データベーステーブルの削除
global $wpdb;

$table_services   = $wpdb->prefix . 'edel_booking_services';
$table_rel        = $wpdb->prefix . 'edel_booking_service_staff';
$table_appt       = $wpdb->prefix . 'edel_booking_appointments';
$table_exceptions = $wpdb->prefix . 'edel_booking_schedule_exceptions';

// 外部キー制約などでエラーにならないよう、念のためドロップ
$wpdb->query("DROP TABLE IF EXISTS $table_services");
$wpdb->query("DROP TABLE IF EXISTS $table_rel");
$wpdb->query("DROP TABLE IF EXISTS $table_appt");
$wpdb->query("DROP TABLE IF EXISTS $table_exceptions");

// 3. オプション設定の削除
delete_option('edel_booking_settings');

// 4. ユーザーメタデータの削除 (スタッフ設定、前回入力値など)
// メタキーが 'edel_' で始まるものを削除
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'edel_%'");
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key = 'is_edel_staff'");

// 5. その他 (一時的なキャッシュやCronなど)
// Cronは deactivate hook で解除されているはずだが、念のため
wp_clear_scheduled_hook('edel_booking_daily_reminder');
