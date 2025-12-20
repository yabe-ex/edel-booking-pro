<?php

class EdelBookingProAdminStaff {

    public function process_save() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

        // --- 例外スケジュールの削除処理 ---
        if (isset($_POST['delete_exception_id'])) {
            check_admin_referer('save_staff_action', 'staff_nonce');
            global $wpdb;
            $table_exceptions = $wpdb->prefix . 'edel_booking_schedule_exceptions';
            $wpdb->delete($table_exceptions, array('id' => intval($_POST['delete_exception_id'])));

            $redirect_url = remove_query_arg('msg', wp_get_referer());
            wp_redirect($redirect_url . '&msg=' . urlencode(__('Schedule exception deleted.', 'edel-booking')));
            exit;
        }

        // --- 通常の保存処理 ---
        if (!isset($_POST['staff_nonce'])) return;
        if (!wp_verify_nonce($_POST['staff_nonce'], 'save_staff_action')) {
            wp_die(__('Security check failed.', 'edel-booking'));
        }

        global $wpdb;
        $user_id = intval($_POST['user_id']);

        // 1. 担当サービスの保存
        $table_service_staff = $wpdb->prefix . 'edel_booking_service_staff';
        $wpdb->delete($table_service_staff, array('staff_id' => $user_id));

        if (isset($_POST['services']) && is_array($_POST['services'])) {
            $custom_prices = isset($_POST['custom_prices']) ? $_POST['custom_prices'] : array();

            foreach ($_POST['services'] as $service_id) {
                $sid = intval($service_id);
                $price = (isset($custom_prices[$sid]) && $custom_prices[$sid] !== '') ? intval($custom_prices[$sid]) : null;

                $wpdb->insert($table_service_staff, array(
                    'service_id' => $sid,
                    'staff_id'   => $user_id,
                    'custom_price' => $price
                ));
            }
        }

        // 2. 週間スケジュールの保存
        $schedule = array();
        $days = array('mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun');
        foreach ($days as $day) {
            $schedule[$day] = array(
                'start' => sanitize_text_field($_POST['schedule'][$day]['start']),
                'end'   => sanitize_text_field($_POST['schedule'][$day]['end']),
                'off'   => isset($_POST['schedule'][$day]['off']) ? 1 : 0
            );
        }

        update_user_meta($user_id, 'is_edel_staff', 1);
        update_user_meta($user_id, 'edel_weekly_schedule', $schedule);

        // 3. 例外スケジュールの新規追加
        if (!empty($_POST['new_exception_date'])) {
            $ex_date = sanitize_text_field($_POST['new_exception_date']);
            $ex_start = sanitize_text_field($_POST['new_exception_start']);
            $ex_end = sanitize_text_field($_POST['new_exception_end']);
            $ex_off = isset($_POST['new_exception_off']) ? 1 : 0;
            $ex_reason = sanitize_text_field($_POST['new_exception_reason']);

            $table_exceptions = $wpdb->prefix . 'edel_booking_schedule_exceptions';

            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_exceptions WHERE staff_id = %d AND exception_date = %s",
                $user_id,
                $ex_date
            ));

            if ($exists) {
                $wpdb->update($table_exceptions, array(
                    'start_time' => $ex_start,
                    'end_time'   => $ex_end,
                    'is_day_off' => $ex_off,
                    'reason'     => $ex_reason
                ), array('id' => $exists));
            } else {
                $wpdb->insert($table_exceptions, array(
                    'staff_id'       => $user_id,
                    'exception_date' => $ex_date,
                    'start_time'     => $ex_start,
                    'end_time'       => $ex_end,
                    'is_day_off'     => $ex_off,
                    'reason'         => $ex_reason
                ));
            }
        }

        wp_redirect(admin_url('admin.php?page=edel-booking-pro-staff&action=edit&user_id=' . $user_id . '&msg=' . urlencode(__('Settings saved.', 'edel-booking'))));
        exit;
    }

    public function render() {
        $action = isset($_GET['action']) ? $_GET['action'] : 'list';
        $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

        if (isset($_GET['msg'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(urldecode($_GET['msg'])) . '</p></div>';
        }

        if ($action === 'remove' && $user_id > 0) {
            check_admin_referer('remove_staff_' . $user_id);
            delete_user_meta($user_id, 'is_edel_staff');
            wp_redirect(admin_url('admin.php?page=edel-booking-pro-staff&msg=' . urlencode(__('Removed from staff list.', 'edel-booking'))));
            exit;
        }

        if ($action === 'edit' || $action === 'add') {
            $this->render_form($user_id);
        } else {
            $this->render_list();
        }
    }

    private function render_list() {
        $staff_users = get_users(array('meta_key' => 'is_edel_staff', 'meta_value' => 1));
?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Staff Management', 'edel-booking'); ?></h1>
            <a href="<?php echo admin_url('admin.php?page=edel-booking-pro-staff&action=add'); ?>" class="page-title-action"><?php esc_html_e('Add/Configure Staff', 'edel-booking'); ?></a>
            <hr class="wp-header-end">
            <p><?php esc_html_e('Register WordPress users as staff and configure shifts and assigned menus.', 'edel-booking'); ?></p>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th width="50px"><?php esc_html_e('ID', 'edel-booking'); ?></th>
                        <th><?php esc_html_e('Display Name', 'edel-booking'); ?></th>
                        <th><?php esc_html_e('Email Address', 'edel-booking'); ?></th>
                        <th><?php esc_html_e('Actions', 'edel-booking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($staff_users) : ?>
                        <?php foreach ($staff_users as $user) : ?>
                            <tr>
                                <td><?php echo $user->ID; ?></td>
                                <td><strong><?php echo esc_html($user->display_name); ?></strong></td>
                                <td><?php echo esc_html($user->user_email); ?></td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=edel-booking-pro-staff&action=edit&user_id=' . $user->ID); ?>" class="button button-small"><?php esc_html_e('Edit Settings', 'edel-booking'); ?></a>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=edel-booking-pro-staff&action=remove&user_id=' . $user->ID), 'remove_staff_' . $user->ID); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to remove this user from staff?', 'edel-booking')); ?>');"><?php esc_html_e('Remove', 'edel-booking'); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="4"><?php esc_html_e('No staff registered yet.', 'edel-booking'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php
    }

    private function render_form($user_id = 0) {
        $all_users = get_users();
        $current_user_data = ($user_id > 0) ? get_userdata($user_id) : null;

        global $wpdb;
        $services = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}edel_booking_services ORDER BY id DESC");

        $assigned_map = array();
        if ($user_id > 0) {
            $results = $wpdb->get_results($wpdb->prepare("SELECT service_id, custom_price FROM {$wpdb->prefix}edel_booking_service_staff WHERE staff_id = %d", $user_id));
            foreach ($results as $r) {
                $assigned_map[$r->service_id] = $r->custom_price;
            }
        }

        $schedule = get_user_meta($user_id, 'edel_weekly_schedule', true);
        $days_label = array(
            'mon' => __('Mon', 'edel-booking'),
            'tue' => __('Tue', 'edel-booking'),
            'wed' => __('Wed', 'edel-booking'),
            'thu' => __('Thu', 'edel-booking'),
            'fri' => __('Fri', 'edel-booking'),
            'sat' => __('Sat', 'edel-booking'),
            'sun' => __('Sun', 'edel-booking')
        );

        $exceptions = array();
        if ($user_id > 0) {
            $exceptions = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}edel_booking_schedule_exceptions WHERE staff_id = %d ORDER BY exception_date ASC",
                $user_id
            ));
        }
    ?>
        <div class="wrap edel-booking-container">
            <h1><?php esc_html_e('Staff Settings', 'edel-booking'); ?></h1>
            <form method="post" action="">
                <?php wp_nonce_field('save_staff_action', 'staff_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e('Target User', 'edel-booking'); ?></th>
                        <td>
                            <?php if ($user_id > 0 && isset($_GET['action']) && $_GET['action'] == 'edit') : ?>
                                <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                                <strong><?php echo esc_html($current_user_data->display_name); ?></strong> (<?php echo esc_html($current_user_data->user_email); ?>)
                            <?php else : ?>
                                <select name="user_id" required>
                                    <option value=""><?php esc_html_e('Select a user', 'edel-booking'); ?></option>
                                    <?php foreach ($all_users as $u) : ?>
                                        <option value="<?php echo $u->ID; ?>"><?php echo esc_html($u->display_name); ?> (<?php echo esc_html($u->user_email); ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <tr>
                        <th><?php esc_html_e('Assigned Menus & Prices', 'edel-booking'); ?></th>
                        <td>
                            <?php if ($services) : ?>
                                <table class="widefat" style="max-width:600px; border:1px solid #ddd;">
                                    <thead>
                                        <tr>
                                            <th style="width:30px;"><input type="checkbox" disabled></th>
                                            <th><?php esc_html_e('Menu Name', 'edel-booking'); ?></th>
                                            <th><?php esc_html_e('Base Price', 'edel-booking'); ?></th>
                                            <th><?php esc_html_e('Staff Price (Override)', 'edel-booking'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($services as $service) :
                                            $is_checked = array_key_exists($service->id, $assigned_map);
                                            $custom_price = $is_checked ? $assigned_map[$service->id] : '';
                                        ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" name="services[]" value="<?php echo $service->id; ?>" <?php checked($is_checked); ?>>
                                                </td>
                                                <td><?php echo esc_html($service->title); ?></td>
                                                <td>¥<?php echo number_format($service->price); ?></td>
                                                <td>
                                                    ¥ <input type="number" name="custom_prices[<?php echo $service->id; ?>]" value="<?php echo esc_attr($custom_price); ?>" placeholder="<?php esc_attr_e('Base', 'edel-booking'); ?>" style="width:100px;">
                                                    <span class="description" style="font-size:11px;">※<?php esc_html_e('Empty uses base price', 'edel-booking'); ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else : ?>
                                <p><?php esc_html_e('No services registered.', 'edel-booking'); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <tr>
                        <th><?php esc_html_e('Weekly Schedule (Base Shift)', 'edel-booking'); ?></th>
                        <td>
                            <table class="widefat edel-weekly-table">
                                <?php foreach ($days_label as $key => $label) :
                                    $start = isset($schedule[$key]['start']) ? $schedule[$key]['start'] : '10:00';
                                    $end   = isset($schedule[$key]['end']) ? $schedule[$key]['end'] : '19:00';
                                    $is_off = (isset($schedule[$key]['off']) && $schedule[$key]['off']) ? true : false;
                                ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($label); ?></strong></td>
                                        <td>
                                            <input type="time" name="schedule[<?php echo $key; ?>][start]" value="<?php echo esc_attr($start); ?>">
                                            〜
                                            <input type="time" name="schedule[<?php echo $key; ?>][end]" value="<?php echo esc_attr($end); ?>">
                                        </td>
                                        <td>
                                            <label><input type="checkbox" name="schedule[<?php echo $key; ?>][off]" value="1" <?php checked($is_off); ?>> <?php esc_html_e('Off', 'edel-booking'); ?></label>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        </td>
                    </tr>

                    <?php if ($user_id > 0): ?>
                        <tr>
                            <th><?php esc_html_e('Schedule Exceptions', 'edel-booking'); ?></th>
                            <td>
                                <div class="edel-exception-box">
                                    <h4 class="edel-box-title"><?php esc_html_e('Add Exception', 'edel-booking'); ?></h4>
                                    <div class="edel-exception-row">
                                        <div class="edel-form-group">
                                            <label><?php esc_html_e('Date', 'edel-booking'); ?></label>
                                            <input type="date" name="new_exception_date">
                                        </div>
                                        <div class="edel-form-group">
                                            <label><?php esc_html_e('Business Hours', 'edel-booking'); ?></label>
                                            <div class="edel-time-range">
                                                <input type="time" name="new_exception_start" value="10:00">
                                                <span>〜</span>
                                                <input type="time" name="new_exception_end" value="19:00">
                                            </div>
                                        </div>
                                        <div class="edel-form-group edel-checkbox-group">
                                            <label>
                                                <input type="checkbox" name="new_exception_off" value="1">
                                                <span class="edel-badge-off"><?php esc_html_e('Closed All Day', 'edel-booking'); ?></span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="edel-exception-row">
                                        <div class="edel-form-group full-width">
                                            <label><?php esc_html_e('Memo (Reason)', 'edel-booking'); ?></label>
                                            <input type="text" name="new_exception_reason" placeholder="<?php esc_attr_e('Ex: Holiday, Training, etc.', 'edel-booking'); ?>" class="regular-text">
                                        </div>
                                    </div>
                                    <p class="description">※<?php esc_html_e("Set the date and click 'Save' to add to the list below.", 'edel-booking'); ?></p>
                                </div>

                                <br>

                                <?php if ($exceptions): ?>
                                    <table class="widefat striped edel-exception-table">
                                        <thead>
                                            <tr>
                                                <th><?php esc_html_e('Date', 'edel-booking'); ?></th>
                                                <th><?php esc_html_e('Settings', 'edel-booking'); ?></th>
                                                <th><?php esc_html_e('Reason', 'edel-booking'); ?></th>
                                                <th><?php esc_html_e('Actions', 'edel-booking'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($exceptions as $ex): ?>
                                                <tr>
                                                    <td><strong><?php echo date('Y/m/d', strtotime($ex->exception_date)); ?></strong></td>
                                                    <td>
                                                        <?php if ($ex->is_day_off): ?>
                                                            <span class="edel-status-off"><?php esc_html_e('Off', 'edel-booking'); ?></span>
                                                        <?php else: ?>
                                                            <?php echo substr($ex->start_time, 0, 5); ?> 〜 <?php echo substr($ex->end_time, 0, 5); ?>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo esc_html($ex->reason); ?></td>
                                                    <td>
                                                        <button type="submit" name="delete_exception_id" value="<?php echo $ex->id; ?>" class="button button-small button-link-delete" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to delete?', 'edel-booking')); ?>');"><?php esc_html_e('Delete', 'edel-booking'); ?></button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>

                </table>

                <?php submit_button(__('Save Settings', 'edel-booking')); ?>
                <a href="<?php echo admin_url('admin.php?page=edel-booking-pro-staff'); ?>"><?php esc_html_e('Back to List', 'edel-booking'); ?></a>
            </form>
        </div>
<?php
    }
}
