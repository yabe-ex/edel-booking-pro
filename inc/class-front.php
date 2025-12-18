<?php

class EdelBookingProFront {

    public function __construct() {
        add_shortcode('edel_booking', array($this, 'render_booking_form'));

        // 一般ユーザー（購読者）の管理バー非表示とダッシュボード禁止
        add_filter('show_admin_bar', array($this, 'filter_admin_bar'));
        add_action('admin_init', array($this, 'block_dashboard_access'));
    }

    public function filter_admin_bar($show) {
        if (!current_user_can('edit_posts')) {
            return false;
        }
        return $show;
    }

    public function block_dashboard_access() {
        if (is_admin() && !current_user_can('edit_posts') && !defined('DOING_AJAX')) {
            wp_redirect(home_url());
            exit;
        }
    }

    function front_enqueue() {
        $version  = (defined('EDEL_BOOKING_PRO_DEVELOP') && true === EDEL_BOOKING_PRO_DEVELOP) ? time() : EDEL_BOOKING_PRO_VERSION;

        wp_enqueue_script('edel-fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js', array(), '6.1.8', true);

        wp_register_style(EDEL_BOOKING_PRO_SLUG . '-front',  EDEL_BOOKING_PRO_URL . '/css/front.css', array(), $version);
        wp_register_script(EDEL_BOOKING_PRO_SLUG . '-front', EDEL_BOOKING_PRO_URL . '/js/front.js', array('jquery', 'edel-fullcalendar'), $version, true);

        $settings = get_option('edel_booking_settings', array());
        $show_price = isset($settings['show_price']) ? intval($settings['show_price']) : 1;
        $hide_service = isset($settings['hide_service']) ? 1 : 0;
        $hide_staff   = isset($settings['hide_staff']) ? 1 : 0;

        $mypage_id = isset($settings['mypage_id']) ? intval($settings['mypage_id']) : 0;
        $mypage_url = ($mypage_id > 0) ? get_permalink($mypage_id) : home_url();

        // ★新規: カレンダーモード
        $calendar_mode = isset($settings['calendar_mode']) ? $settings['calendar_mode'] : 'bar';

        global $wpdb;
        $services = $wpdb->get_results("SELECT id, price, duration FROM {$wpdb->prefix}edel_booking_services");
        $service_prices = array();
        $service_durations = array();
        foreach ($services as $s) {
            $service_prices[$s->id] = (int)$s->price;
            $service_durations[$s->id] = (int)$s->duration;
        }

        $staff_custom_prices = array();
        $custom_rows = $wpdb->get_results("SELECT staff_id, service_id, custom_price FROM {$wpdb->prefix}edel_booking_service_staff WHERE custom_price IS NOT NULL");
        foreach ($custom_rows as $row) {
            if (!isset($staff_custom_prices[$row->staff_id])) {
                $staff_custom_prices[$row->staff_id] = array();
            }
            $staff_custom_prices[$row->staff_id][$row->service_id] = (int)$row->custom_price;
        }

        $relations = $wpdb->get_results("SELECT service_id, staff_id FROM {$wpdb->prefix}edel_booking_service_staff");
        $map_service_to_staff = array();
        $map_staff_to_service = array();
        foreach ($relations as $r) {
            $sid = $r->service_id;
            $uid = $r->staff_id;
            if (!isset($map_service_to_staff[$sid])) $map_service_to_staff[$sid] = array();
            $map_service_to_staff[$sid][] = $uid;
            if (!isset($map_staff_to_service[$uid])) $map_staff_to_service[$uid] = array();
            $map_staff_to_service[$uid][] = $sid;
        }

        $front_vars = array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce(EDEL_BOOKING_PRO_SLUG),
            'show_price' => $show_price,
            'hide_service' => $hide_service,
            'hide_staff'   => $hide_staff,
            'mypage_url'   => $mypage_url,
            'calendar_mode' => $calendar_mode, // JSへ渡す
            'base_prices' => $service_prices,
            'durations'   => $service_durations,
            'custom_prices' => $staff_custom_prices,
            'relations' => array('service_to_staff' => $map_service_to_staff, 'staff_to_service' => $map_staff_to_service)
        );

        wp_enqueue_style(EDEL_BOOKING_PRO_SLUG . '-front');
        wp_enqueue_script(EDEL_BOOKING_PRO_SLUG . '-front');
        wp_localize_script(EDEL_BOOKING_PRO_SLUG . '-front', 'edel_front', $front_vars);
    }

    public function render_booking_form($atts) {
        // ... (前回の完全版コードと同じなので省略。そのままお使いください) ...
        // ※このメソッドは前回から変更ありません。
        global $wpdb;
        $services = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}edel_booking_services WHERE is_active = 1");
        $staffs   = get_users(array('meta_key' => 'is_edel_staff', 'meta_value' => 1));

        $current_user_name = '';
        $current_user_email = '';
        $is_logged_in = is_user_logged_in();

        if ($is_logged_in) {
            $u = wp_get_current_user();
            $current_user_name = $u->display_name;
            $current_user_email = $u->user_email;
        }

        $settings = get_option('edel_booking_settings', array());
        $label_service = !empty($settings['label_service']) ? $settings['label_service'] : 'メニュー';
        $label_staff   = !empty($settings['label_staff']) ? $settings['label_staff'] : '担当スタッフ';
        $hide_service = !empty($settings['hide_service']) ? true : false;
        $hide_staff   = !empty($settings['hide_staff']) ? true : false;
        $def_service = !empty($settings['default_service']) ? intval($settings['default_service']) : '';
        $def_staff   = !empty($settings['default_staff']) ? intval($settings['default_staff']) : '';
        $show_price = isset($settings['show_price']) ? intval($settings['show_price']) : 1;
        $display_style = ($show_price === 1) ? '' : 'display:none;';
        $style_service_container = $hide_service ? 'display:none;' : '';
        $style_staff_container   = $hide_staff ? 'display:none;' : '';

        $mypage_id = isset($settings['mypage_id']) ? intval($settings['mypage_id']) : 0;
        $mypage_url = ($mypage_id > 0) ? get_permalink($mypage_id) : home_url();

        ob_start();
?>
        <div id="edel-booking-app">
            <div class="edel-step" id="edel-step-1">
                <h3>1. 予約条件を選択</h3>
                <div class="edel-form-group" style="<?php echo $style_service_container; ?>">
                    <label><?php echo esc_html($label_service); ?></label>
                    <select id="edel-front-service" class="edel-select">
                        <option value="">選択してください</option>
                        <?php foreach ($services as $s): ?>
                            <option value="<?php echo $s->id; ?>" <?php selected($def_service, $s->id); ?>><?php echo esc_html($s->title); ?> (<?php echo $s->duration; ?>分)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="edel-form-group" style="<?php echo $style_staff_container; ?>">
                    <label><?php echo esc_html($label_staff); ?></label>
                    <select id="edel-front-staff" class="edel-select">
                        <option value="">選択してください</option>
                        <?php foreach ($staffs as $st): ?>
                            <option value="<?php echo $st->ID; ?>" <?php selected($def_staff, $st->ID); ?>><?php echo esc_html($st->display_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="edel-form-group">
                    <label>来店日を選択</label>
                    <div id="edel-front-calendar-wrapper">
                        <div id="edel-front-calendar"></div>
                        <div id="edel-calendar-overlay">条件を選択してください</div>
                    </div>
                    <input type="hidden" id="edel-front-date">
                </div>
            </div>

            <div class="edel-step" id="edel-step-2" style="display:none;">
                <h3>2. 時間を選択 (<span id="edel-display-date"></span>)</h3>
                <div id="edel-slots-container"></div>
                <button class="edel-btn edel-btn-back" onclick="jQuery('#edel-step-2').hide(); jQuery('#edel-step-1').fadeIn();">カレンダーに戻る</button>
            </div>

            <div class="edel-step" id="edel-step-3" style="display:none;">
                <h3>3. お客様情報を入力</h3>
                <div class="edel-confirm-box">
                    <p><strong>日時:</strong> <span id="edel-summary-date"></span> <span id="edel-summary-time"></span></p>
                    <p id="edel-row-service"><strong><?php echo esc_html($label_service); ?>:</strong> <span id="edel-summary-service"></span></p>
                    <p id="edel-row-staff"><strong><?php echo esc_html($label_staff); ?>:</strong> <span id="edel-summary-staff"></span></p>
                    <p id="edel-summary-price-row" style="<?php echo $display_style; ?>"><strong>料金:</strong> <span id="edel-summary-price"></span></p>
                </div>
                <form id="edel-front-booking-form">
                    <input type="hidden" name="service_id" id="hidden-service-id">
                    <input type="hidden" name="staff_id" id="hidden-staff-id">
                    <input type="hidden" name="date" id="hidden-date">
                    <input type="hidden" name="time" id="hidden-time">
                    <div class="edel-form-group">
                        <label>お名前 <span class="required">*</span></label>
                        <input type="text" name="customer_name" class="edel-input" required value="<?php echo esc_attr($current_user_name); ?>" placeholder="例: 山田 太郎">
                    </div>
                    <div class="edel-form-group">
                        <label>メールアドレス <span class="required">*</span></label>
                        <input type="email" name="customer_email" class="edel-input" required value="<?php echo esc_attr($current_user_email); ?>" placeholder="example@email.com">
                    </div>
                    <div class="edel-form-group">
                        <label>電話番号</label>
                        <input type="tel" name="customer_phone" class="edel-input" placeholder="090-1234-5678">
                    </div>
                    <div class="edel-form-group">
                        <label>備考</label>
                        <textarea name="note" class="edel-input" rows="3"></textarea>
                    </div>

                    <?php if (!$is_logged_in): ?>
                        <div class="edel-form-group edel-register-check">
                            <label style="font-weight:normal;">
                                <input type="checkbox" name="create_account" value="1" checked>
                                <strong>アカウントを作成して予約する（自動ログイン）</strong>
                            </label>
                            <p class="description" style="margin-top:5px; font-size:0.9em; color:#666;">
                                ※登録すると、予約の確認やキャンセルがマイページから行えるようになります。
                                パスワードはメールでお知らせします。
                            </p>
                        </div>
                    <?php endif; ?>

                    <button type="submit" id="edel-btn-submit" class="edel-btn edel-btn-primary">予約を確定する</button>
                </form>
                <button class="edel-btn edel-btn-back" onclick="jQuery('#edel-step-3').hide(); jQuery('#edel-step-2').fadeIn();">時間を選び直す</button>
            </div>

            <div class="edel-step" id="edel-step-4" style="display:none; text-align:center;">
                <h3 style="color:#27ae60;">予約が確定しました</h3>
                <p>ご予約ありがとうございます。<br>確認メールをお送りしましたのでご確認ください。</p>
                <br>
                <div class="edel-btn-group">
                    <a href="" class="edel-btn edel-btn-secondary">予約画面に戻る</a>

                    <?php if ($is_logged_in): ?>
                        <a href="<?php echo esc_url($mypage_url); ?>" class="edel-btn edel-btn-primary">マイページへ</a>
                    <?php else: ?>
                        <a href="<?php echo home_url(); ?>" id="edel-btn-home" class="edel-btn edel-btn-primary">トップページへ</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <style>
            .edel-confirm-box {
                background: #f9f9f9;
                padding: 15px;
                margin-bottom: 20px;
                border-left: 4px solid #333;
            }

            .required {
                color: red;
            }

            .edel-register-check {
                background: #f0f8ff;
                padding: 15px;
                border-radius: 4px;
                border: 1px solid #d0e8ff;
            }

            #edel-front-calendar-wrapper {
                position: relative;
                min-height: 400px;
            }

            #edel-calendar-overlay {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(255, 255, 255, 0.85);
                z-index: 20;
                display: flex;
                justify-content: center;
                align-items: center;
                font-weight: bold;
                color: #555;
                backdrop-filter: blur(2px);
            }
        </style>
<?php
        return ob_get_clean();
    }
}
