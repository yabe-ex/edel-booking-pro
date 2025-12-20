<?php

class EdelBookingProAdminSettings {

    private $option_name = 'edel_booking_settings';

    public function render() {
        // ---------------------------------------------------------
        // 1. 保存処理
        // ---------------------------------------------------------
        if (isset($_POST['edel_save_settings_btn'])) {
            if (check_admin_referer('edel_save_settings_action')) {
                $this->save_settings();
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved.', 'edel-booking') . '</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Security check failed. Please try again.', 'edel-booking') . '</p></div>';
            }
        }

        // ---------------------------------------------------------
        // 2. 設定値の読み込み
        // ---------------------------------------------------------
        $settings = get_option($this->option_name, array());

        // --- 一般設定 ---
        $shop_name    = isset($settings['shop_name']) ? $settings['shop_name'] : get_bloginfo('name');
        $mypage_id    = isset($settings['mypage_id']) ? intval($settings['mypage_id']) : 0;
        $closed_days  = isset($settings['closed_days']) ? $settings['closed_days'] : array();
        $cancel_limit = isset($settings['cancel_limit']) ? intval($settings['cancel_limit']) : 1;
        $delete_data  = isset($settings['delete_data_on_uninstall']) ? intval($settings['delete_data_on_uninstall']) : 0;

        // --- 表示設定 ---
        $show_price    = isset($settings['show_price']) ? intval($settings['show_price']) : 1;
        $calendar_mode = isset($settings['calendar_mode']) ? $settings['calendar_mode'] : 'bar';
        $label_service = isset($settings['label_service']) ? $settings['label_service'] : __('Menu', 'edel-booking');
        $label_staff   = isset($settings['label_staff']) ? $settings['label_staff'] : __('Staff', 'edel-booking');
        $hide_service  = isset($settings['hide_service']) ? intval($settings['hide_service']) : 0;
        $hide_staff    = isset($settings['hide_staff']) ? intval($settings['hide_staff']) : 0;
        $default_service = isset($settings['default_service']) ? intval($settings['default_service']) : 0;
        $default_staff   = isset($settings['default_staff']) ? intval($settings['default_staff']) : 0;

        // --- メール設定 ---
        $sender_name     = isset($settings['sender_name']) ? $settings['sender_name'] : get_bloginfo('name');
        $sender_email    = isset($settings['sender_email']) ? $settings['sender_email'] : get_option('admin_email');
        $admin_emails    = isset($settings['admin_emails']) ? $settings['admin_emails'] : get_option('admin_email');
        $reminder_timing = isset($settings['reminder_timing']) ? intval($settings['reminder_timing']) : 1;

        $val_book_sub    = isset($settings['email_book_sub']) ? $settings['email_book_sub'] : '';
        $val_book_body   = isset($settings['email_book_body']) ? $settings['email_book_body'] : '';
        $val_remind_sub  = isset($settings['email_remind_sub']) ? $settings['email_remind_sub'] : '';
        $val_remind_body = isset($settings['email_remind_body']) ? $settings['email_remind_body'] : '';

        // --- カスタムフィールド設定 ---
        $custom_fields = isset($settings['custom_fields']) ? $settings['custom_fields'] : array();

        // マスタデータ
        global $wpdb;
        $services = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}edel_booking_services WHERE is_active = 1");
        $staffs   = get_users(array('meta_key' => 'is_edel_staff', 'meta_value' => 1));
        $pages    = get_pages();

        $current_url = admin_url('admin.php?page=edel-booking-settings');
?>
        <div class="wrap">
            <h1><?php esc_html_e('Edel Booking Pro Settings', 'edel-booking'); ?></h1>

            <h2 class="nav-tab-wrapper">
                <a href="#tab-general" class="nav-tab nav-tab-active" onclick="switchTab(event, 'general')"><?php esc_html_e('General', 'edel-booking'); ?></a>
                <a href="#tab-display" class="nav-tab" onclick="switchTab(event, 'display')"><?php esc_html_e('Display', 'edel-booking'); ?></a>
                <a href="#tab-fields" class="nav-tab" onclick="switchTab(event, 'fields')"><?php esc_html_e('Custom Fields', 'edel-booking'); ?></a>
                <a href="#tab-mail" class="nav-tab" onclick="switchTab(event, 'mail')"><?php esc_html_e('Email Settings', 'edel-booking'); ?></a>
            </h2>

            <form method="post" action="<?php echo esc_url($current_url); ?>">
                <?php wp_nonce_field('edel_save_settings_action'); ?>

                <div id="tab-general" class="edel-tab-content active">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label><?php esc_html_e('Shop Name / Signature', 'edel-booking'); ?></label></th>
                            <td><input type="text" name="settings[shop_name]" value="<?php echo esc_attr($shop_name); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label><?php esc_html_e('Closed Days', 'edel-booking'); ?></label></th>
                            <td>
                                <?php
                                $weekdays = array(
                                    'mon' => __('Mon', 'edel-booking'),
                                    'tue' => __('Tue', 'edel-booking'),
                                    'wed' => __('Wed', 'edel-booking'),
                                    'thu' => __('Thu', 'edel-booking'),
                                    'fri' => __('Fri', 'edel-booking'),
                                    'sat' => __('Sat', 'edel-booking'),
                                    'sun' => __('Sun', 'edel-booking')
                                );
                                foreach ($weekdays as $key => $label):
                                ?>
                                    <label style="margin-right:10px;"><input type="checkbox" name="settings[closed_days][]" value="<?php echo $key; ?>" <?php checked(in_array($key, $closed_days)); ?>> <?php echo esc_html($label); ?></label>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label><?php esc_html_e('Cancellation Deadline', 'edel-booking'); ?></label></th>
                            <td>
                                <?php printf(
                                    esc_html__('Up to %s hours before booking', 'edel-booking'),
                                    '<input type="number" name="settings[cancel_limit]" value="' . esc_attr($cancel_limit) . '" min="0" class="small-text">'
                                ); ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label><?php esc_html_e('My Page (Fixed Page)', 'edel-booking'); ?></label></th>
                            <td>
                                <select name="settings[mypage_id]">
                                    <option value="0"><?php esc_html_e('-- Select Page --', 'edel-booking'); ?></option>
                                    <?php foreach ($pages as $page): ?>
                                        <option value="<?php echo $page->ID; ?>" <?php selected($mypage_id, $page->ID); ?>>
                                            <?php echo esc_html($page->post_title); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">※ <code>[edel_mypage]</code> <?php esc_html_e('Select the page where the shortcode is placed.', 'edel-booking'); ?></p>
                            </td>
                        </tr>

                        <tr style="border-top: 1px solid #ddd;">
                            <th scope="row"><label style="color:#d63638;"><?php esc_html_e('Uninstall Settings', 'edel-booking'); ?></label></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="settings[delete_data_on_uninstall]" value="1" <?php checked($delete_data, 1); ?>>
                                    <?php esc_html_e('Delete all data when uninstalling plugin', 'edel-booking'); ?>
                                </label>
                                <p class="description" style="color:#d63638; margin-top:5px;">
                                    <strong><?php esc_html_e('Warning:', 'edel-booking'); ?></strong>
                                    <?php esc_html_e('If you enable this setting and delete the plugin, all data including reservations, customer history, and settings will be permanently deleted and cannot be restored.', 'edel-booking'); ?><br>
                                    <?php esc_html_e('Do not check this if you are upgrading to the Pro version or temporarily disabling the plugin.', 'edel-booking'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div id="tab-display" class="edel-tab-content" style="display:none;">
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e('Calendar Display Mode', 'edel-booking'); ?></th>
                            <td>
                                <div class="edel-radio-group">
                                    <label><input type="radio" name="settings[calendar_mode]" value="bar" <?php checked($calendar_mode, 'bar'); ?>> <?php esc_html_e('AM/PM Bar', 'edel-booking'); ?></label>
                                    <label><input type="radio" name="settings[calendar_mode]" value="symbol" <?php checked($calendar_mode, 'symbol'); ?>> <?php esc_html_e('Symbols (◎ ○ △ ×)', 'edel-booking'); ?></label>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Price Display', 'edel-booking'); ?></th>
                            <td>
                                <label class="edel-toggle-label">
                                    <input type="checkbox" name="settings[show_price]" value="1" <?php checked($show_price, 1); ?>>
                                    <?php esc_html_e('Show price', 'edel-booking'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e("'Service' Field", 'edel-booking'); ?></th>
                            <td>
                                <div class="edel-display-setting-box">
                                    <div class="edel-display-row">
                                        <span class="edel-display-label"><?php esc_html_e('Label', 'edel-booking'); ?></span>
                                        <input type="text" name="settings[label_service]" value="<?php echo esc_attr($label_service); ?>" class="regular-text">
                                    </div>
                                    <div class="edel-display-row">
                                        <span class="edel-display-label"><?php esc_html_e('Default', 'edel-booking'); ?></span>
                                        <select name="settings[default_service]">
                                            <option value="0"><?php esc_html_e('None', 'edel-booking'); ?></option>
                                            <?php foreach ($services as $s): ?>
                                                <option value="<?php echo $s->id; ?>" <?php selected($default_service, $s->id); ?>><?php echo esc_html($s->title); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="edel-display-row footer">
                                        <label class="edel-toggle-label">
                                            <input type="checkbox" name="settings[hide_service]" value="1" <?php checked($hide_service, 1); ?>>
                                            <?php esc_html_e('Hide options (Force default)', 'edel-booking'); ?>
                                        </label>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e("'Staff' Field", 'edel-booking'); ?></th>
                            <td>
                                <div class="edel-display-setting-box">
                                    <div class="edel-display-row">
                                        <span class="edel-display-label"><?php esc_html_e('Label', 'edel-booking'); ?></span>
                                        <input type="text" name="settings[label_staff]" value="<?php echo esc_attr($label_staff); ?>" class="regular-text">
                                    </div>
                                    <div class="edel-display-row">
                                        <span class="edel-display-label"><?php esc_html_e('Default', 'edel-booking'); ?></span>
                                        <select name="settings[default_staff]">
                                            <option value="0"><?php esc_html_e('None', 'edel-booking'); ?></option>
                                            <?php foreach ($staffs as $st): ?>
                                                <option value="<?php echo $st->ID; ?>" <?php selected($default_staff, $st->ID); ?>><?php echo esc_html($st->display_name); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="edel-display-row footer">
                                        <label class="edel-toggle-label">
                                            <input type="checkbox" name="settings[hide_staff]" value="1" <?php checked($hide_staff, 1); ?>>
                                            <?php esc_html_e('Hide options (Force default)', 'edel-booking'); ?>
                                        </label>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>

                <div id="tab-fields" class="edel-tab-content" style="display:none;">
                    <h3><?php esc_html_e('Add Booking Form Fields', 'edel-booking'); ?></h3>
                    <p class="description" style="margin-bottom:20px;">
                        <?php esc_html_e('Add items if you want to ask for information other than Name, Email, Phone, and Note.', 'edel-booking'); ?><br>
                        <?php esc_html_e("If 'Keep Last Value' is ON, the previously entered value will be automatically set when a member logs in.", 'edel-booking'); ?>
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
                            <span class="dashicons dashicons-plus-alt2" style="vertical-align: text-bottom;"></span> <?php esc_html_e('Add Field', 'edel-booking'); ?>
                        </button>
                    </div>
                </div>

                <div id="tab-mail" class="edel-tab-content" style="display:none;">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Sender Name', 'edel-booking'); ?></th>
                            <td><input type="text" name="settings[sender_name]" value="<?php echo esc_attr($sender_name); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Sender Email', 'edel-booking'); ?></th>
                            <td><input type="email" name="settings[sender_email]" value="<?php echo esc_attr($sender_email); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Admin Notification Email', 'edel-booking'); ?></th>
                            <td>
                                <textarea name="settings[admin_emails]" rows="2" class="large-text"><?php echo esc_textarea($admin_emails); ?></textarea>
                                <p class="description"><?php esc_html_e('Comma separated for multiple emails.', 'edel-booking'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Reminder Email', 'edel-booking'); ?></th>
                            <td>
                                <select name="settings[reminder_timing]">
                                    <option value="0" <?php selected($reminder_timing, 0); ?>><?php esc_html_e('Morning of the day', 'edel-booking'); ?></option>
                                    <option value="1" <?php selected($reminder_timing, 1); ?>><?php esc_html_e('Morning of the day before', 'edel-booking'); ?></option>
                                    <option value="2" <?php selected($reminder_timing, 2); ?>><?php esc_html_e('Morning of 2 days before', 'edel-booking'); ?></option>
                                    <option value="3" <?php selected($reminder_timing, 3); ?>><?php esc_html_e('Morning of 3 days before', 'edel-booking'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Booking Confirmed Subject', 'edel-booking'); ?></th>
                            <td><input type="text" name="settings[email_book_sub]" value="<?php echo esc_attr($val_book_sub); ?>" class="large-text"></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Booking Confirmed Body', 'edel-booking'); ?></th>
                            <td>
                                <textarea name="settings[email_book_body]" rows="5" class="large-text"><?php echo esc_textarea($val_book_body); ?></textarea>
                                <p class="description"><?php esc_html_e('Available tags:', 'edel-booking'); ?> <code>{name}</code> <code>{date}</code> <code>{time}</code> <code>{service}</code> <code>{staff}</code> <code>{shop_name}</code> <code>{note}</code> <code>{custom_fields}</code></p>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Reminder Subject', 'edel-booking'); ?></th>
                            <td><input type="text" name="settings[email_remind_sub]" value="<?php echo esc_attr($val_remind_sub); ?>" class="large-text"></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Reminder Body', 'edel-booking'); ?></th>
                            <td><textarea name="settings[email_remind_body]" rows="5" class="large-text"><?php echo esc_textarea($val_remind_body); ?></textarea></td>
                        </tr>
                    </table>
                </div>

                <hr style="margin-top:30px;">
                <input type="submit" name="edel_save_settings_btn" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'edel-booking'); ?>">
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
                if (confirm('<?php echo esc_js(__('Are you sure you want to delete this field?', 'edel-booking')); ?>')) {
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
                    <label><?php esc_html_e('Field Name', 'edel-booking'); ?> <span class="required">*</span></label>
                    <input type="text" name="settings[custom_fields][<?php echo $index; ?>][label]" value="<?php echo esc_attr($label); ?>" placeholder="<?php esc_attr_e('Ex: Gender', 'edel-booking'); ?>" required>
                </div>
                <div class="edel-field-col col-type">
                    <label><?php esc_html_e('Type', 'edel-booking'); ?></label>
                    <select name="settings[custom_fields][<?php echo $index; ?>][type]">
                        <option value="text" <?php selected($type, 'text'); ?>><?php esc_html_e('Single line text', 'edel-booking'); ?></option>
                        <option value="textarea" <?php selected($type, 'textarea'); ?>><?php esc_html_e('Multi-line text', 'edel-booking'); ?></option>
                        <option value="radio" <?php selected($type, 'radio'); ?>><?php esc_html_e('Radio button', 'edel-booking'); ?></option>
                        <option value="select" <?php selected($type, 'select'); ?>><?php esc_html_e('Select box', 'edel-booking'); ?></option>
                    </select>
                </div>
                <div class="edel-field-col col-default">
                    <label><?php esc_html_e('Keep Last Value', 'edel-booking'); ?></label>
                    <label class="switch">
                        <input type="checkbox" name="settings[custom_fields][<?php echo $index; ?>][save_default]" value="1" <?php checked($save_default, 1); ?>>
                        <span class="slider round"></span>
                    </label>
                </div>
                <div class="edel-field-col col-req">
                    <label><?php esc_html_e('Required', 'edel-booking'); ?></label>
                    <label class="switch">
                        <input type="checkbox" name="settings[custom_fields][<?php echo $index; ?>][required]" value="1" <?php checked($req, 1); ?>>
                        <span class="slider round"></span>
                    </label>
                </div>
            </div>

            <div class="edel-field-body">
                <div class="edel-field-col col-options">
                    <label><?php esc_html_e('Options (for Radio/Select)', 'edel-booking'); ?></label>
                    <input type="text" name="settings[custom_fields][<?php echo $index; ?>][options]" value="<?php echo esc_attr($opts); ?>" placeholder="<?php esc_attr_e('Comma separated (Ex: Male, Female)', 'edel-booking'); ?>">
                    <p class="description"><?php esc_html_e('Only valid if type is Radio button or Select box.', 'edel-booking'); ?></p>
                </div>
            </div>

            <div class="edel-field-footer">
                <button type="button" class="button edel-remove-field-text" onclick="removeField(this)"><?php esc_html_e('Delete', 'edel-booking'); ?></button>
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
