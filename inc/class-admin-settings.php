<?php

class EdelBookingProAdminSettings {

    private $option_name = 'edel_booking_settings';

    public function render() {
        if (isset($_POST['edel_save_settings']) && check_admin_referer('edel_save_settings_action')) {
            $this->save_settings();
            echo '<div class="notice notice-success is-dismissible"><p>設定を保存しました。</p></div>';
        }

        $settings = get_option($this->option_name, array());

        $shop_name = isset($settings['shop_name']) ? $settings['shop_name'] : get_bloginfo('name');
        $reminder_timing = isset($settings['reminder_timing']) ? intval($settings['reminder_timing']) : 1;
        $show_price = isset($settings['show_price']) ? intval($settings['show_price']) : 1;
        $cancel_limit = isset($settings['cancel_limit']) ? intval($settings['cancel_limit']) : 1;
        $closed_days = isset($settings['closed_days']) ? $settings['closed_days'] : array();

        $mypage_id = isset($settings['mypage_id']) ? intval($settings['mypage_id']) : 0;

        $sender_name  = isset($settings['sender_name']) ? $settings['sender_name'] : get_bloginfo('name');
        $sender_email = isset($settings['sender_email']) ? $settings['sender_email'] : get_option('admin_email');
        $admin_emails = isset($settings['admin_emails']) ? $settings['admin_emails'] : get_option('admin_email');

        $val_book_sub  = isset($settings['email_book_sub']) ? $settings['email_book_sub'] : '';
        $val_book_body = isset($settings['email_book_body']) ? $settings['email_book_body'] : '';
        $val_remind_sub  = isset($settings['email_remind_sub']) ? $settings['email_remind_sub'] : '';
        $val_remind_body = isset($settings['email_remind_body']) ? $settings['email_remind_body'] : '';

        // フロント表示設定
        $calendar_mode = isset($settings['calendar_mode']) ? $settings['calendar_mode'] : 'bar'; // ★新規: デフォルトはバー

        $label_service = isset($settings['label_service']) ? $settings['label_service'] : 'メニュー';
        $label_staff   = isset($settings['label_staff']) ? $settings['label_staff'] : '担当スタッフ';
        $hide_service = isset($settings['hide_service']) ? intval($settings['hide_service']) : 0;
        $hide_staff   = isset($settings['hide_staff']) ? intval($settings['hide_staff']) : 0;
        $default_service = isset($settings['default_service']) ? intval($settings['default_service']) : 0;
        $default_staff   = isset($settings['default_staff']) ? intval($settings['default_staff']) : 0;

        global $wpdb;
        $services = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}edel_booking_services WHERE is_active = 1");
        $staffs   = get_users(array('meta_key' => 'is_edel_staff', 'meta_value' => 1));
        $pages = get_pages();

?>
        <div class="wrap">
            <h1>Edel Booking Pro 設定</h1>
            <form method="post" action="">
                <?php wp_nonce_field('edel_save_settings_action'); ?>

                <div class="edel-settings-box">
                    <h2>一般設定</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label>店舗名 / 署名</label></th>
                            <td><input type="text" name="settings[shop_name]" value="<?php echo esc_attr($shop_name); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label>店舗定休日</label></th>
                            <td>
                                <?php
                                $weekdays = array('mon' => '月', 'tue' => '火', 'wed' => '水', 'thu' => '木', 'fri' => '金', 'sat' => '土', 'sun' => '日');
                                foreach ($weekdays as $key => $label):
                                ?>
                                    <label style="margin-right:10px;"><input type="checkbox" name="settings[closed_days][]" value="<?php echo $key; ?>" <?php checked(in_array($key, $closed_days)); ?>> <?php echo $label; ?></label>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label>キャンセル期限</label></th>
                            <td>予約の <input type="number" name="settings[cancel_limit]" value="<?php echo esc_attr($cancel_limit); ?>" min="0" class="small-text"> 時間前まで</td>
                        </tr>
                        <tr>
                            <th scope="row"><label>マイページ (固定ページ)</label></th>
                            <td>
                                <select name="settings[mypage_id]">
                                    <option value="0">-- 選択してください --</option>
                                    <?php foreach ($pages as $page): ?>
                                        <option value="<?php echo $page->ID; ?>" <?php selected($mypage_id, $page->ID); ?>>
                                            <?php echo esc_html($page->post_title); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">※選択したページにはショートコード <code>[edel_mypage]</code> を設置してください。</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="edel-settings-box">
                    <h2>予約フォーム表示設定</h2>
                    <table class="form-table">
                        <tr>
                            <th>カレンダー表示モード</th>
                            <td>
                                <label style="margin-right:15px;"><input type="radio" name="settings[calendar_mode]" value="bar" <?php checked($calendar_mode, 'bar'); ?>> AM/PM バー表示 (デフォルト)</label>
                                <label><input type="radio" name="settings[calendar_mode]" value="symbol" <?php checked($calendar_mode, 'symbol'); ?>> 記号表示 (◎ ○ △ ×)</label>
                                <p class="description">記号表示の場合、休業日はグレーアウトされます。</p>
                            </td>
                        </tr>
                        <tr>
                            <th>料金表示</th>
                            <td>
                                <label><input type="checkbox" name="settings[show_price]" value="1" <?php checked($show_price, 1); ?>> 料金を表示する</label>
                            </td>
                        </tr>
                        <tr>
                            <th>「メニュー」項目</th>
                            <td>
                                <div style="margin-bottom:10px;">
                                    <label>ラベル名: <input type="text" name="settings[label_service]" value="<?php echo esc_attr($label_service); ?>" class="regular-text"></label>
                                </div>
                                <div style="margin-bottom:10px;">
                                    <label>デフォルト選択:
                                        <select name="settings[default_service]">
                                            <option value="0">なし</option>
                                            <?php foreach ($services as $s): ?>
                                                <option value="<?php echo $s->id; ?>" <?php selected($default_service, $s->id); ?>><?php echo esc_html($s->title); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                </div>
                                <div>
                                    <label><input type="checkbox" name="settings[hide_service]" value="1" <?php checked($hide_service, 1); ?>> 選択肢を隠す</label>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th>「スタッフ」項目</th>
                            <td>
                                <div style="margin-bottom:10px;">
                                    <label>ラベル名: <input type="text" name="settings[label_staff]" value="<?php echo esc_attr($label_staff); ?>" class="regular-text"></label>
                                </div>
                                <div style="margin-bottom:10px;">
                                    <label>デフォルト選択:
                                        <select name="settings[default_staff]">
                                            <option value="0">なし</option>
                                            <?php foreach ($staffs as $st): ?>
                                                <option value="<?php echo $st->ID; ?>" <?php selected($default_staff, $st->ID); ?>><?php echo esc_html($st->display_name); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                </div>
                                <div>
                                    <label><input type="checkbox" name="settings[hide_staff]" value="1" <?php checked($hide_staff, 1); ?>> 選択肢を隠す</label>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="edel-settings-box">
                    <h2>メール設定</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">差出人名</th>
                            <td><input type="text" name="settings[sender_name]" value="<?php echo esc_attr($sender_name); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row">差出人メールアドレス</th>
                            <td><input type="email" name="settings[sender_email]" value="<?php echo esc_attr($sender_email); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row">管理者通知先メール</th>
                            <td><textarea name="settings[admin_emails]" rows="2" class="large-text"><?php echo esc_textarea($admin_emails); ?></textarea></td>
                        </tr>
                        <tr>
                            <th scope="row">リマインド送信</th>
                            <td>
                                <select name="settings[reminder_timing]">
                                    <option value="0" <?php selected($reminder_timing, 0); ?>>当日朝</option>
                                    <option value="1" <?php selected($reminder_timing, 1); ?>>前日朝</option>
                                    <option value="2" <?php selected($reminder_timing, 2); ?>>2日前朝</option>
                                    <option value="3" <?php selected($reminder_timing, 3); ?>>3日前朝</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>予約確定 件名</th>
                            <td><input type="text" name="settings[email_book_sub]" value="<?php echo esc_attr($val_book_sub); ?>" class="large-text"></td>
                        </tr>
                        <tr>
                            <th>予約確定 本文</th>
                            <td><textarea name="settings[email_book_body]" rows="5" class="large-text"><?php echo esc_textarea($val_book_body); ?></textarea></td>
                        </tr>
                        <tr>
                            <th>リマインド 件名</th>
                            <td><input type="text" name="settings[email_remind_sub]" value="<?php echo esc_attr($val_remind_sub); ?>" class="large-text"></td>
                        </tr>
                        <tr>
                            <th>リマインド 本文</th>
                            <td><textarea name="settings[email_remind_body]" rows="5" class="large-text"><?php echo esc_textarea($val_remind_body); ?></textarea></td>
                        </tr>
                    </table>
                </div>

                <?php submit_button('設定を保存'); ?>
                <input type="hidden" name="edel_save_settings" value="1">
            </form>
        </div>

        <style>
            .edel-settings-box {
                background: #fff;
                padding: 20px;
                border: 1px solid #ccd0d4;
                margin-bottom: 20px;
                max-width: 800px;
            }

            .edel-settings-box h2 {
                margin-top: 0;
                padding-bottom: 10px;
                border-bottom: 1px solid #eee;
                font-size: 1.2em;
            }
        </style>
<?php
    }

    private function save_settings() {
        $input = $_POST['settings'];
        $clean = array();

        $clean['shop_name'] = sanitize_text_field($input['shop_name']);
        $clean['show_price'] = isset($input['show_price']) ? 1 : 0;
        $clean['cancel_limit'] = intval($input['cancel_limit']);
        $clean['closed_days'] = isset($input['closed_days']) ? $input['closed_days'] : array();
        $clean['mypage_id'] = intval($input['mypage_id']);

        $clean['sender_name']  = sanitize_text_field($input['sender_name']);
        $clean['sender_email'] = sanitize_email($input['sender_email']);
        $clean['admin_emails'] = sanitize_textarea_field($input['admin_emails']);

        // ★新規: 保存
        $clean['calendar_mode'] = isset($input['calendar_mode']) ? $input['calendar_mode'] : 'bar';

        $clean['label_service'] = sanitize_text_field($input['label_service']);
        $clean['label_staff']   = sanitize_text_field($input['label_staff']);
        $clean['hide_service']  = isset($input['hide_service']) ? 1 : 0;
        $clean['hide_staff']    = isset($input['hide_staff']) ? 1 : 0;
        $clean['default_service'] = intval($input['default_service']);
        $clean['default_staff']   = intval($input['default_staff']);

        $clean['reminder_timing'] = intval($input['reminder_timing']);
        $clean['email_book_sub'] = sanitize_text_field($input['email_book_sub']);
        $clean['email_book_body'] = sanitize_textarea_field($input['email_book_body']);
        $clean['email_remind_sub'] = sanitize_text_field($input['email_remind_sub']);
        $clean['email_remind_body'] = sanitize_textarea_field($input['email_remind_body']);

        update_option($this->option_name, $clean);
    }
}
