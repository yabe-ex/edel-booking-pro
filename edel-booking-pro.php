<?php

/**
 * Plugin Name: Edel Booking Pro
 * Plugin URI:
 * Description: Pro version of reservation management system for stores.
 * Version: 1.0.0
 * Author: Edel Hearts
 * License: GPLv2 or later
 * Text Domain: edel-booking
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) exit();

$info = get_file_data(__FILE__, array('plugin_name' => 'Plugin Name', 'version' => 'Version'));

define('EDEL_BOOKING_PRO_URL', plugins_url('', __FILE__));
define('EDEL_BOOKING_PRO_PATH', dirname(__FILE__));
define('EDEL_BOOKING_PRO_NAME', $info['plugin_name']);
define('EDEL_BOOKING_PRO_SLUG', 'edel-booking-pro');
define('EDEL_BOOKING_PRO_PREFIX', 'edel_booking_pro_');
define('EDEL_BOOKING_PRO_VERSION', $info['version']);
define('EDEL_BOOKING_PRO_DEVELOP', true);

// 必要なクラスファイルの読み込み (順序重要)
require_once EDEL_BOOKING_PRO_PATH . '/inc/class-cron.php';
require_once EDEL_BOOKING_PRO_PATH . '/inc/class-emails.php';
require_once EDEL_BOOKING_PRO_PATH . '/inc/class-availability.php';

function activate_edel_booking_pro() {
    require_once EDEL_BOOKING_PRO_PATH . '/inc/class-activator.php';
    EdelBookingProActivator::activate();
    EdelBookingProCron::activate();
}
register_activation_hook(__FILE__, 'activate_edel_booking_pro');

function deactivate_edel_booking_pro() {
    EdelBookingProCron::deactivate();
}
register_deactivation_hook(__FILE__, 'deactivate_edel_booking_pro');

class EdelBookingPro {
    public function init() {

        // 1. Ajax & ロジック関連
        require_once EDEL_BOOKING_PRO_PATH . '/inc/class-ajax.php';
        new EdelBookingProAjax();

        // 2. 管理画面 (メニュー & 設定)
        require_once EDEL_BOOKING_PRO_PATH . '/inc/class-admin.php';
        $admin = new EdelBookingProAdmin();
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($admin, 'plugin_action_links'));
        add_action('admin_enqueue_scripts', array($admin, 'admin_enqueue'));

        require_once EDEL_BOOKING_PRO_PATH . '/inc/class-admin-settings.php';
        require_once EDEL_BOOKING_PRO_PATH . '/inc/class-admin-menu.php';
        new EdelBookingProAdminMenu();

        // 3. フロントエンド
        require_once EDEL_BOOKING_PRO_PATH . '/inc/class-front.php';
        $front = new EdelBookingProFront();
        add_action('wp_enqueue_scripts', array($front, 'front_enqueue'));

        // 4. マイページ
        require_once EDEL_BOOKING_PRO_PATH . '/inc/class-mypage.php';
        new EdelBookingProMyPage();

        // 5. Cron
        $cron = new EdelBookingProCron();
        $cron->init();
    }
}

$instance = new EdelBookingPro();
$instance->init();
