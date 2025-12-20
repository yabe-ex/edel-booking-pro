<?php

class EdelBookingProAdminSettings {

    private $option_name = 'edel_booking_settings';

    public function render() {
        // --- 保存処理 ---
        if (isset($_POST['edel_save_settings_btn'])) {
            if (check_admin_referer('edel_save_settings_action')) {
                $this->save_settings();
                echo '<div class="notice notice-success is-dismissible"><p>設定を保存しました。</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>セキュリティチェックに失敗しました。</p></div>';
            }
        }

        // --- 設定値の読み込み ---
        $settings = get_option($this->option_name, array());

        // 一般設定
        $shop_name    = isset($settings['shop_name']) ? $settings['shop_name'] : get_bloginfo('name');
        $mypage_id    = isset($settings['mypage_id']) ? intval($settings['mypage_id']) : 0;
        $closed_days  = isset($settings['closed_days']) ? $settings['closed_days'] : array();
        $cancel_limit = isset($settings['cancel_limit']) ? intval($settings['cancel_limit']) : 1;
        // ★追加: アンインストール設定
        $delete_data  = isset($settings['delete_data_on_uninstall']) ? intval($settings['delete_data_on_uninstall']) : 0;

        // 表示設定
        $show_price    = isset($settings['show_price']) ? intval($settings['show_price']) : 1;
        $calendar_mode = isset($settings['calendar_mode']) ? $settings['calendar_mode'] : 'bar';
        $label_service = isset($settings['label_service']) ? $settings['label_service'] : 'メニュー';
        $label_staff   = isset($settings['label_staff']) ? $settings['label_staff'] : '担当スタッフ';
        $hide_service  = isset($settings['hide_service']) ? intval($settings['hide_service']) : 0;
        $hide_staff    = isset($settings['hide_staff']) ? intval($settings['hide_staff']) : 0;
        $default_service = isset($settings['default_service']) ? intval($settings['default_service']) : 0;
        $default_staff   = isset($settings['default_staff']) ? intval($settings['default_staff']) : 0;

        // メール設定
        $sender_name     = isset($settings['sender_name']) ? $settings['sender_name'] : get_bloginfo('name');
        $sender_email    = isset($settings['sender_email']) ? $settings['sender_email'] : get_option('admin_email');
        $admin_emails    = isset($settings['admin_emails']) ? $settings['admin_emails'] : get_option('admin_email');
        $reminder_timing = isset($settings['reminder_timing']) ? intval($settings['reminder_timing']) : 1;

        $val_book_sub    = isset($settings['email_book_sub']) ? $settings['email_book_sub'] : '';
        $val_book_body   = isset($settings['email_book_body']) ? $settings['email_book_body'] : '';
        $val_remind_sub  = isset($settings['email_remind_sub']) ? $settings['email_remind_sub'] : '';
        $val_remind_body = isset($settings['email_remind_body']) ? $settings['email_remind_body'] : '';

        // カスタムフィールド
        $custom_fields = isset($settings['custom_fields']) ? $settings['custom_fields'] : array();

        global $wpdb;
        $services = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}edel_booking_services WHERE is_active = 1");
        $staffs   = get_users(array('meta_key' => 'is_edel_staff', 'meta_value' => 1));
        $pages    = get_pages();

        $current_url = admin_url('admin.php?page=edel-booking-settings');
?>
        <div class="wrap">
            <h1>Edel Booking Pro 設定</h1>

            <h2 class="nav-tab-wrapper">
                <a href="#tab-general" class="nav-tab nav-tab-active" onclick="switchTab(event, 'general')">一般設定</a>
                <a href="#tab-display" class="nav-tab" onclick="switchTab(event, 'display')">表示設定</a>
                <a href="#tab-fields" class="nav-tab" onclick="switchTab(event, 'fields')">カスタムフィールド</a>
                <a href="#tab-mail" class="nav-tab" onclick="switchTab(event, 'mail')">メール設定</a>
            </h2>

            <form method="post" action="<?php echo esc_url($current_url); ?>">
                <?php wp_nonce_field('edel_save_settings_action'); ?>

                <div id="tab-general" class="edel-tab-content active">
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
                            </td>
                        </tr>

                        <tr style="border-top: 1px solid #ddd;">
                            <th scope="row"><label style="color:#d63638;">アンインストール設定</label></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="settings[delete_data_on_uninstall]" value="1" <?php checked($delete_data, 1); ?>>
                                    プラグイン削除時にすべてのデータを消去する
                                </label>
                                <p class="description" style="color:#d63638; margin-top:5px;">
                                    <strong>注意:</strong> この設定をONにしてプラグインを削除すると、予約データ、顧客履歴、設定など<strong>すべてのデータが完全に削除され、復元できなくなります。</strong><br>
                                    通常版からPro版へ移行する場合や、一時的に停止する場合は<strong>チェックを入れないでください</strong>。
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div id="tab-display" class="edel-tab-content" style="display:none;">
                    <table class="form-table">
                        <tr>
                            <th>カレンダー表示モード</th>
                            <td>
                                <div class="edel-radio-group">
                                    <label><input type="radio" name="settings[calendar_mode]" value="bar" <?php checked($calendar_mode, 'bar'); ?>> AM/PM バー表示</label>
                                    <label><input type="radio" name="settings[calendar_mode]" value="symbol" <?php checked($calendar_mode, 'symbol'); ?>> 記号表示 (◎ ○ △ ×)</label>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th>料金表示</th>
                            <td>
                                <label class="edel-toggle-label">
                                    <input type="checkbox" name="settings[show_price]" value="1" <?php checked($show_price, 1); ?>>
                                    料金を表示する
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th>「メニュー」項目</th>
                            <td>
                                <div class="edel-display-setting-box">
                                    <div class="edel-display-row">
                                        <span class="edel-display-label">ラベル名</span>
                                        <input type="text" name="settings[label_service]" value="<?php echo esc_attr($label_service); ?>" class="regular-text">
                                    </div>
                                    <div class="edel-display-row">
                                        <span class="edel-display-label">デフォルト</span>
                                        <select name="settings[default_service]">
                                            <option value="0">なし</option>
                                            <?php foreach ($services as $s): ?>
                                                <option value="<?php echo $s->id; ?>" <?php selected($default_service, $s->id); ?>><?php echo esc_html($s->title); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="edel-display-row footer">
                                        <label class="edel-toggle-label">
                                            <input type="checkbox" name="settings[hide_service]" value="1" <?php checked($hide_service, 1); ?>>
                                            選択肢を隠す (デフォルト値を強制)
                                        </label>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th>「スタッフ」項目</th>
                            <td>
                                <div class="edel-display-setting-box">
                                    <div class="edel-display-row">
                                        <span class="edel-display-label">ラベル名</span>
                                        <input type="text" name="settings[label_staff]" value="<?php echo esc_attr($label_staff); ?>" class="regular-text">
                                    </div>
                                    <div class="edel-display-row">
                                        <span class="edel-display-label">デフォルト</span>
                                        <select name="settings[default_staff]">
                                            <option value="0">なし</option>
                                            <?php foreach ($staffs as $st): ?>
                                                <option value="<?php echo $st->ID; ?>" <?php selected($default_staff, $st->ID); ?>><?php echo esc_html($st->display_name); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="edel-display-row footer">
                                        <label class="edel-toggle-label">
                                            <input type="checkbox" name="settings[hide_staff]" value="1" <?php checked($hide_staff, 1); ?>>
                                            選択肢を隠す (デフォルト値を強制)
                                        </label>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>

                <div id="tab-fields" class="edel-tab-content" style="display:none;">
                    <h3>予約フォーム入力項目の追加</h3>
                    <p class="description" style="margin-bottom:20px;">
                        「お名前」「メール」「電話番号」「備考」以外に聞きたい項目がある場合に追加してください。<br>
                        「前回値を保持」をONにすると、会員ログイン時に前回の入力内容が自動でセットされます。
                    </p>

                    <div id="edel-fields-wrapper">
                        <?php
                        if (!empty($custom_fields)) {
                            foreach ($custom_fields as $index => $field) {
                                $this->render_field_row($index, $field);
                            }
                        }
                        ?>
                    </div>

                    <div style="margin-top: 20px;">
                        <button type="button" class="button button-primary button-large edel-add-field-btn" onclick="addCustomField()">
                            <span class="dashicons dashicons-plus-alt2" style="vertical-align: text-bottom;"></span> フィールドを追加
                        </button>
                    </div>
                </div>

                <div id="tab-mail" class="edel-tab-content" style="display:none;">
                    <table class="form-table">
                        <tr>
                            <th scope="row">差出人名</th>
                            <td><input type="text" name="settings[sender_name]" value="<?php echo esc_attr($sender_name); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row">差出人メール</th>
                            <td><input type="email" name="settings[sender_email]" value="<?php echo esc_attr($sender_email); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row">管理者通知先</th>
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

                <hr style="margin-top:30px;">
                <input type="submit" name="edel_save_settings_btn" class="button button-primary" value="設定を保存">
            </form>

            <div id="edel-field-template" style="display:none;">
                <?php $this->render_field_row('INDEX', array()); ?>
            </div>

        </div>

        <script>
            function switchTab(e, tabId) {
                e.preventDefault();
                var tabs = document.querySelectorAll('.nav-tab');
                var contents = document.querySelectorAll('.edel-tab-content');

                tabs.forEach(function(el) {
                    el.classList.remove('nav-tab-active');
                });
                contents.forEach(function(el) {
                    el.style.display = 'none';
                });

                e.target.classList.add('nav-tab-active');
                document.getElementById('tab-' + tabId).style.display = 'block';
            }

            function addCustomField() {
                var wrapper = document.getElementById('edel-fields-wrapper');
                var template = document.getElementById('edel-field-template').innerHTML;
                var index = new Date().getTime();
                var html = template.replace(/INDEX/g, index);

                var div = document.createElement('div');
                div.innerHTML = html;
                wrapper.appendChild(div.firstElementChild);
            }

            function removeField(btn) {
                if (confirm('このフィールドを削除しますか？')) {
                    btn.closest('.edel-field-row').remove();
                }
            }
        </script>
    <?php
    }

    private function render_field_row($index, $data) {
        $label = isset($data['label']) ? $data['label'] : '';
        $type  = isset($data['type']) ? $data['type'] : 'text';
        $req   = isset($data['required']) ? $data['required'] : 0;
        $opts  = isset($data['options']) ? $data['options'] : '';
        $save_default = isset($data['save_default']) ? $data['save_default'] : 0;
    ?>
        <div class="edel-field-row card">
            <div class="edel-field-header">
                <div class="edel-field-col col-label">
                    <label>項目名 <span class="required">*</span></label>
                    <input type="text" name="settings[custom_fields][<?php echo $index; ?>][label]" value="<?php echo esc_attr($label); ?>" placeholder="例: 性別" required>
                </div>
                <div class="edel-field-col col-type">
                    <label>タイプ</label>
                    <select name="settings[custom_fields][<?php echo $index; ?>][type]">
                        <option value="text" <?php selected($type, 'text'); ?>>1行テキスト</option>
                        <option value="textarea" <?php selected($type, 'textarea'); ?>>複数行テキスト</option>
                        <option value="radio" <?php selected($type, 'radio'); ?>>ラジオボタン</option>
                        <option value="select" <?php selected($type, 'select'); ?>>セレクトボックス</option>
                    </select>
                </div>
                <div class="edel-field-col col-default">
                    <label>前回値を保持</label>
                    <label class="switch">
                        <input type="checkbox" name="settings[custom_fields][<?php echo $index; ?>][save_default]" value="1" <?php checked($save_default, 1); ?>>
                        <span class="slider round"></span>
                    </label>
                </div>
                <div class="edel-field-col col-req">
                    <label>必須</label>
                    <label class="switch">
                        <input type="checkbox" name="settings[custom_fields][<?php echo $index; ?>][required]" value="1" <?php checked($req, 1); ?>>
                        <span class="slider round"></span>
                    </label>
                </div>
            </div>

            <div class="edel-field-body">
                <div class="edel-field-col col-options">
                    <label>選択肢 (ラジオ/セレクト用)</label>
                    <input type="text" name="settings[custom_fields][<?php echo $index; ?>][options]" value="<?php echo esc_attr($opts); ?>" placeholder="カンマ(,)区切りで入力 (例: 男性, 女性)">
                    <p class="description">※タイプがラジオボタンかセレクトボックスの場合のみ有効です。</p>
                </div>
            </div>

            <div class="edel-field-footer">
                <button type="button" class="button edel-remove-field-text" onclick="removeField(this)">削除</button>
            </div>
        </div>
<?php
    }

    private function save_settings() {
        if (!isset($_POST['settings'])) return;
        $input = $_POST['settings'];
        $clean = array();

        $clean['shop_name'] = sanitize_text_field($input['shop_name']);
        $clean['mypage_id'] = intval($input['mypage_id']);
        $clean['closed_days'] = isset($input['closed_days']) ? $input['closed_days'] : array();
        $clean['cancel_limit'] = intval($input['cancel_limit']);
        // ★追加: アンインストール設定の保存
        $clean['delete_data_on_uninstall'] = isset($input['delete_data_on_uninstall']) ? 1 : 0;

        $clean['calendar_mode'] = isset($input['calendar_mode']) ? $input['calendar_mode'] : 'bar';
        $clean['show_price'] = isset($input['show_price']) ? 1 : 0;
        $clean['label_service'] = sanitize_text_field($input['label_service']);
        $clean['label_staff']   = sanitize_text_field($input['label_staff']);
        $clean['hide_service']  = isset($input['hide_service']) ? 1 : 0;
        $clean['hide_staff']    = isset($input['hide_staff']) ? 1 : 0;
        $clean['default_service'] = intval($input['default_service']);
        $clean['default_staff']   = intval($input['default_staff']);

        $clean['sender_name']  = sanitize_text_field($input['sender_name']);
        $clean['sender_email'] = sanitize_email($input['sender_email']);
        $clean['admin_emails'] = sanitize_textarea_field($input['admin_emails']);
        $clean['reminder_timing'] = intval($input['reminder_timing']);
        $clean['email_book_sub'] = sanitize_text_field($input['email_book_sub']);
        $clean['email_book_body'] = sanitize_textarea_field($input['email_book_body']);
        $clean['email_remind_sub'] = sanitize_text_field($input['email_remind_sub']);
        $clean['email_remind_body'] = sanitize_textarea_field($input['email_remind_body']);

        $clean_fields = array();
        if (isset($input['custom_fields']) && is_array($input['custom_fields'])) {
            foreach ($input['custom_fields'] as $key => $field) {
                if (empty($field['label'])) continue;
                $clean_fields[$key] = array(
                    'label' => sanitize_text_field($field['label']),
                    'type'  => sanitize_text_field($field['type']),
                    'required' => isset($field['required']) ? 1 : 0,
                    'save_default' => isset($field['save_default']) ? 1 : 0,
                    'options' => sanitize_text_field($field['options'])
                );
            }
        }
        $clean['custom_fields'] = $clean_fields;

        update_option($this->option_name, $clean);
    }
}
