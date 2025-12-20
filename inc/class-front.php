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

        wp_enqueue_script(
            'edel-fullcalendar',
            EDEL_BOOKING_PRO_URL . '/assets/js/lib/fullcalendar/index.global.min.js',
            array(),
            '6.1.8',
            true
        );

        wp_enqueue_script(
            'edel-fullcalendar-locales',
            EDEL_BOOKING_PRO_URL . '/assets/js/lib/fullcalendar/locales-all.global.min.js',
            array('edel-fullcalendar'),
            '6.1.8',
            true
        );
        wp_register_style(EDEL_BOOKING_PRO_SLUG . '-front',  EDEL_BOOKING_PRO_URL . '/css/front.css', array(), $version);
        wp_register_script(EDEL_BOOKING_PRO_SLUG . '-front', EDEL_BOOKING_PRO_URL . '/js/front.js', array('jquery', 'edel-fullcalendar'), $version, true);

        $settings = get_option('edel_booking_settings', array());
        $show_price = isset($settings['show_price']) ? intval($settings['show_price']) : 1;
        $hide_service = isset($settings['hide_service']) ? 1 : 0;
        $hide_staff   = isset($settings['hide_staff']) ? 1 : 0;
        $calendar_mode = isset($settings['calendar_mode']) ? $settings['calendar_mode'] : 'bar';

        $mypage_id = isset($settings['mypage_id']) ? intval($settings['mypage_id']) : 0;
        $mypage_url = ($mypage_id > 0) ? get_permalink($mypage_id) : home_url();

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

        // JS用翻訳データ
        $l10n = array(
            'select_condition' => __('Please select conditions.', 'edel-booking'),
            'loading_slots'    => __('Loading available slots...', 'edel-booking'),
            'no_slots'         => __('Sorry, no slots available on this date.', 'edel-booking'),
            'error_fetch'      => __('Communication Error.', 'edel-booking'),
            'confirm_booking'  => __('Are you sure you want to confirm this booking?', 'edel-booking'),
            'processing'       => __('Processing...', 'edel-booking'),
            'btn_confirm'      => __('Confirm Booking', 'edel-booking'),
            'full_day'         => __('Sorry, fully booked.', 'edel-booking'),
            'locale_code'      => substr(get_locale(), 0, 2)
        );

        $front_vars = array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce(EDEL_BOOKING_PRO_SLUG),
            'show_price' => $show_price,
            'hide_service' => $hide_service,
            'hide_staff'   => $hide_staff,
            'calendar_mode' => $calendar_mode,
            'mypage_url'   => $mypage_url,
            'base_prices' => $service_prices,
            'durations'   => $service_durations,
            'custom_prices' => $staff_custom_prices,
            'relations' => array('service_to_staff' => $map_service_to_staff, 'staff_to_service' => $map_staff_to_service),
            'l10n' => $l10n
        );

        wp_enqueue_style(EDEL_BOOKING_PRO_SLUG . '-front');
        wp_enqueue_script(EDEL_BOOKING_PRO_SLUG . '-front');
        wp_localize_script(EDEL_BOOKING_PRO_SLUG . '-front', 'edel_front', $front_vars);
    }

    public function render_booking_form($atts) {
        global $wpdb;
        $services = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}edel_booking_services WHERE is_active = 1");
        $staffs   = get_users(array('meta_key' => 'is_edel_staff', 'meta_value' => 1));

        $current_user_name = '';
        $current_user_email = '';
        $is_logged_in = is_user_logged_in();
        $current_user_id = 0;

        if ($is_logged_in) {
            $u = wp_get_current_user();
            $current_user_id = $u->ID;
            $current_user_name = $u->display_name;
            $current_user_email = $u->user_email;
        }

        $settings = get_option('edel_booking_settings', array());
        $label_service = !empty($settings['label_service']) ? $settings['label_service'] : __('Menu', 'edel-booking');
        $label_staff   = !empty($settings['label_staff']) ? $settings['label_staff'] : __('Staff', 'edel-booking');
        $hide_service = !empty($settings['hide_service']) ? true : false;
        $hide_staff   = !empty($settings['hide_staff']) ? true : false;
        $def_service = !empty($settings['default_service']) ? intval($settings['default_service']) : '';
        $def_staff   = !empty($settings['default_staff']) ? intval($settings['default_staff']) : '';
        $show_price = isset($settings['show_price']) ? intval($settings['show_price']) : 1;
        $display_style = ($show_price === 1) ? '' : 'display:none;';
        $style_service_container = $hide_service ? 'display:none;' : '';
        $style_staff_container   = $hide_staff ? 'display:none;' : '';

        // カスタムフィールド設定
        $custom_fields = isset($settings['custom_fields']) ? $settings['custom_fields'] : array();

        $mypage_id = isset($settings['mypage_id']) ? intval($settings['mypage_id']) : 0;
        $mypage_url = ($mypage_id > 0) ? get_permalink($mypage_id) : home_url();

        ob_start();
?>
        <div id="edel-booking-app">
            <div class="edel-step" id="edel-step-1">
                <h3><?php esc_html_e('1. Select Conditions', 'edel-booking'); ?></h3>
                <div class="edel-form-group" style="<?php echo $style_service_container; ?>">
                    <label><?php echo esc_html($label_service); ?></label>
                    <select id="edel-front-service" class="edel-select">
                        <option value=""><?php esc_html_e('Please Select', 'edel-booking'); ?></option>
                        <?php foreach ($services as $s): ?>
                            <option value="<?php echo $s->id; ?>" <?php selected($def_service, $s->id); ?>><?php echo esc_html($s->title); ?> (<?php echo $s->duration; ?><?php esc_html_e('min', 'edel-booking'); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="edel-form-group" style="<?php echo $style_staff_container; ?>">
                    <label><?php echo esc_html($label_staff); ?></label>
                    <select id="edel-front-staff" class="edel-select">
                        <option value=""><?php esc_html_e('Please Select', 'edel-booking'); ?></option>
                        <?php foreach ($staffs as $st): ?>
                            <option value="<?php echo $st->ID; ?>" <?php selected($def_staff, $st->ID); ?>><?php echo esc_html($st->display_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="edel-form-group">
                    <label><?php esc_html_e('Select Date', 'edel-booking'); ?></label>
                    <div id="edel-front-calendar-wrapper">
                        <div id="edel-front-calendar"></div>
                        <div id="edel-calendar-overlay"><?php esc_html_e('Please select conditions first', 'edel-booking'); ?></div>
                    </div>
                    <input type="hidden" id="edel-front-date">
                </div>
            </div>

            <div class="edel-step" id="edel-step-2" style="display:none;">
                <h3><?php esc_html_e('2. Select Time', 'edel-booking'); ?> (<span id="edel-display-date"></span>)</h3>
                <div id="edel-slots-container"></div>
                <button class="edel-btn edel-btn-back" onclick="jQuery('#edel-step-2').hide(); jQuery('#edel-step-1').fadeIn();"><?php esc_html_e('Back to Calendar', 'edel-booking'); ?></button>
            </div>

            <div class="edel-step" id="edel-step-3" style="display:none;">
                <h3><?php esc_html_e('3. Enter Information', 'edel-booking'); ?></h3>
                <div class="edel-confirm-box">
                    <p><strong><?php esc_html_e('Date/Time:', 'edel-booking'); ?></strong> <span id="edel-summary-date"></span> <span id="edel-summary-time"></span></p>
                    <p id="edel-row-service"><strong><?php echo esc_html($label_service); ?>:</strong> <span id="edel-summary-service"></span></p>
                    <p id="edel-row-staff"><strong><?php echo esc_html($label_staff); ?>:</strong> <span id="edel-summary-staff"></span></p>
                    <p id="edel-summary-price-row" style="<?php echo $display_style; ?>"><strong><?php esc_html_e('Price:', 'edel-booking'); ?></strong> <span id="edel-summary-price"></span></p>
                </div>
                <form id="edel-front-booking-form">
                    <input type="hidden" name="service_id" id="hidden-service-id">
                    <input type="hidden" name="staff_id" id="hidden-staff-id">
                    <input type="hidden" name="date" id="hidden-date">
                    <input type="hidden" name="time" id="hidden-time">
                    <div class="edel-form-group">
                        <label><?php esc_html_e('Name', 'edel-booking'); ?> <span class="required">*</span></label>
                        <input type="text" name="customer_name" class="edel-input" required value="<?php echo esc_attr($current_user_name); ?>" placeholder="<?php esc_attr_e('Ex: Taro Yamada', 'edel-booking'); ?>">
                    </div>
                    <div class="edel-form-group">
                        <label><?php esc_html_e('Email', 'edel-booking'); ?> <span class="required">*</span></label>
                        <input type="email" name="customer_email" class="edel-input" required value="<?php echo esc_attr($current_user_email); ?>" placeholder="example@email.com">
                    </div>
                    <div class="edel-form-group">
                        <label><?php esc_html_e('Phone', 'edel-booking'); ?></label>
                        <input type="tel" name="customer_phone" class="edel-input" placeholder="090-1234-5678">
                    </div>

                    <?php if (!empty($custom_fields)): ?>
                        <?php foreach ($custom_fields as $index => $field):
                            $label = esc_html($field['label']);
                            $type  = $field['type'];
                            $req   = !empty($field['required']) ? 'required' : '';
                            $req_mark = !empty($field['required']) ? '<span class="required">*</span>' : '';
                            $options = array();
                            if (!empty($field['options'])) {
                                $options = array_map('trim', explode(',', $field['options']));
                            }

                            $default_val = '';
                            if ($is_logged_in && !empty($field['save_default'])) {
                                $default_val = get_user_meta($current_user_id, 'edel_cf_last_' . $index, true);
                            }
                        ?>
                            <div class="edel-form-group">
                                <label><?php echo $label . $req_mark; ?></label>

                                <?php if ($type === 'text'): ?>
                                    <input type="text" name="edel_custom_fields[<?php echo $index; ?>]" class="edel-input" <?php echo $req; ?> value="<?php echo esc_attr($default_val); ?>">

                                <?php elseif ($type === 'textarea'): ?>
                                    <textarea name="edel_custom_fields[<?php echo $index; ?>]" class="edel-input" rows="3" <?php echo $req; ?>><?php echo esc_textarea($default_val); ?></textarea>

                                <?php elseif ($type === 'select'): ?>
                                    <select name="edel_custom_fields[<?php echo $index; ?>]" class="edel-select" <?php echo $req; ?>>
                                        <option value=""><?php esc_html_e('Please Select', 'edel-booking'); ?></option>
                                        <?php foreach ($options as $opt): ?>
                                            <option value="<?php echo esc_attr($opt); ?>" <?php selected($default_val, $opt); ?>><?php echo esc_html($opt); ?></option>
                                        <?php endforeach; ?>
                                    </select>

                                <?php elseif ($type === 'radio'): ?>
                                    <div style="margin-top:5px;">
                                        <?php foreach ($options as $opt_idx => $opt): ?>
                                            <label style="display:inline-block; margin-right:15px; font-weight:normal;">
                                                <input type="radio" name="edel_custom_fields[<?php echo $index; ?>]" value="<?php echo esc_attr($opt); ?>" <?php echo ($req && $opt_idx === 0 && empty($default_val)) ? 'required' : ''; ?> <?php checked($default_val, $opt); ?>>
                                                <?php echo esc_html($opt); ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <div class="edel-form-group">
                        <label><?php esc_html_e('Note', 'edel-booking'); ?></label>
                        <textarea name="note" class="edel-input" rows="3"></textarea>
                    </div>

                    <?php if (!$is_logged_in): ?>
                        <div class="edel-form-group edel-register-check">
                            <label style="font-weight:normal;">
                                <input type="checkbox" name="create_account" value="1" checked>
                                <strong><?php esc_html_e('Create an account and book (Auto Login)', 'edel-booking'); ?></strong>
                            </label>
                            <p class="description" style="margin-top:5px; font-size:0.9em; color:#666;">
                                <?php esc_html_e('If you register, you can check your booking or cancel from My Page. Password will be sent via email.', 'edel-booking'); ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <button type="submit" id="edel-btn-submit" class="edel-btn edel-btn-primary"><?php esc_html_e('Confirm Booking', 'edel-booking'); ?></button>
                </form>
                <button class="edel-btn edel-btn-back" onclick="jQuery('#edel-step-3').hide(); jQuery('#edel-step-2').fadeIn();"><?php esc_html_e('Back to Time Selection', 'edel-booking'); ?></button>
            </div>

            <div class="edel-step" id="edel-step-4" style="display:none; text-align:center;">
                <h3 style="color:#27ae60;"><?php esc_html_e('Booking Confirmed', 'edel-booking'); ?></h3>
                <p><?php esc_html_e('Thank you for your booking. We have sent you a confirmation email.', 'edel-booking'); ?></p>
                <br>
                <div class="edel-btn-group">
                    <a href="" class="edel-btn edel-btn-secondary"><?php esc_html_e('Back to Booking Form', 'edel-booking'); ?></a>

                    <?php if ($is_logged_in): ?>
                        <a href="<?php echo esc_url($mypage_url); ?>" class="edel-btn edel-btn-primary"><?php esc_html_e('Go to My Page', 'edel-booking'); ?></a>
                    <?php else: ?>
                        <a href="<?php echo home_url(); ?>" id="edel-btn-home" class="edel-btn edel-btn-primary"><?php esc_html_e('Go to Top Page', 'edel-booking'); ?></a>
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
