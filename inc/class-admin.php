<?php

class EdelBookingProAdmin {

    public function __construct() {
        // コンストラクタ (メニュー登録は class-admin-menu.php に委譲)
    }

    public function plugin_action_links($links) {
        $settings_link = '<a href="admin.php?page=edel-booking-settings">' . __('Settings', 'edel-booking') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public function admin_enqueue() {
        // 個別のCSSが必要な場合はここに記述
    }

    /**
     * ==================================================
     * 1. スタッフ管理ページ
     * ==================================================
     */
    public function render_staff_page() {
        global $wpdb;
        $table_services = $wpdb->prefix . 'edel_booking_services';
        $table_rel      = $wpdb->prefix . 'edel_booking_service_staff';

        // --- 保存処理 ---
        if (isset($_POST['save_staff']) && check_admin_referer('edel_save_staff')) {
            $user_id = intval($_POST['user_id']);

            // 週間シフト
            $schedule = array();
            $weekdays = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
            foreach ($weekdays as $day) {
                $schedule[$day] = array(
                    'start' => sanitize_text_field($_POST['schedule'][$day]['start']),
                    'end'   => sanitize_text_field($_POST['schedule'][$day]['end']),
                    'off'   => isset($_POST['schedule'][$day]['off']) ? 1 : 0
                );
            }
            update_user_meta($user_id, 'edel_weekly_schedule', $schedule);

            // 担当サービス
            $assigned_services = isset($_POST['services']) ? $_POST['services'] : array();
            $wpdb->delete($table_rel, array('staff_id' => $user_id));
            foreach ($assigned_services as $service_id) {
                $custom_price = isset($_POST['custom_price'][$service_id]) && $_POST['custom_price'][$service_id] !== '' ? intval($_POST['custom_price'][$service_id]) : NULL;
                $wpdb->insert($table_rel, array(
                    'service_id' => intval($service_id),
                    'staff_id'   => $user_id,
                    'custom_price' => $custom_price
                ));
            }
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Staff settings saved.', 'edel-booking') . '</p></div>';
        }

        // --- 新規追加処理 ---
        if (isset($_POST['add_new_staff']) && check_admin_referer('edel_add_staff_action')) {
            $new_user_id = intval($_POST['new_staff_user_id']);
            if ($new_user_id > 0) {
                update_user_meta($new_user_id, 'is_edel_staff', 1);
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('New staff added. Please configure details in the form.', 'edel-booking') . '</p></div>';
            }
        }

        // --- 削除処理 ---
        if (isset($_GET['action']) && $_GET['action'] == 'remove_staff' && isset($_GET['user_id'])) {
            $remove_id = intval($_GET['user_id']);
            delete_user_meta($remove_id, 'is_edel_staff');
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Removed from staff list. (User account is not deleted)', 'edel-booking') . '</p></div>';
        }

        // --- データ取得 ---
        $staff_users = get_users(array('meta_key' => 'is_edel_staff', 'meta_value' => 1));
        $staff_ids = wp_list_pluck($staff_users, 'ID');
        $all_users = get_users(array('exclude' => $staff_ids));
        $edit_user_id = isset($_GET['edit_user']) ? intval($_GET['edit_user']) : 0;
        $target_user = $edit_user_id ? get_userdata($edit_user_id) : null;
        $all_services = $wpdb->get_results("SELECT * FROM $table_services WHERE is_active = 1");

?>
        <div class="wrap">
            <h1><?php esc_html_e('Staff Management', 'edel-booking'); ?></h1>

            <div style="display:flex; gap:20px; align-items:flex-start;">
                <div style="flex:1; min-width:300px;">

                    <div style="background:#fff; padding:15px; border:1px solid #ccd0d4; margin-bottom:20px; border-left:4px solid #2271b1;">
                        <h3 style="margin-top:0;"><?php esc_html_e('Add Staff', 'edel-booking'); ?></h3>
                        <form method="post">
                            <?php wp_nonce_field('edel_add_staff_action'); ?>
                            <p style="margin-bottom:10px; font-size:0.9em;">
                                <?php esc_html_e('Select a WordPress user to add as staff.', 'edel-booking'); ?><br>
                                <a href="<?php echo admin_url('user-new.php'); ?>" target="_blank"><?php esc_html_e('Create new user', 'edel-booking'); ?></a>
                            </p>
                            <div style="display:flex; gap:5px;">
                                <select name="new_staff_user_id" style="width:100%;">
                                    <option value=""><?php esc_html_e('-- Select User --', 'edel-booking'); ?></option>
                                    <?php foreach ($all_users as $candidate): ?>
                                        <option value="<?php echo $candidate->ID; ?>">
                                            <?php echo esc_html($candidate->display_name); ?> (<?php echo esc_html($candidate->user_email); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" name="add_new_staff" class="button button-primary"><?php esc_html_e('Add', 'edel-booking'); ?></button>
                            </div>
                        </form>
                    </div>

                    <table class="widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Name (Email)', 'edel-booking'); ?></th>
                                <th style="width:100px;"><?php esc_html_e('Actions', 'edel-booking'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($staff_users): ?>
                                <?php foreach ($staff_users as $u):
                                    $row_style = ($target_user && $target_user->ID == $u->ID) ? 'background-color:#e6f6ff;' : '';
                                ?>
                                    <tr style="<?php echo $row_style; ?>">
                                        <td>
                                            <strong><?php echo esc_html($u->display_name); ?></strong><br>
                                            <small style="color:#666;"><?php echo esc_html($u->user_email); ?></small>
                                        </td>
                                        <td>
                                            <a href="?page=edel-booking-staff&edit_user=<?php echo $u->ID; ?>" class="button button-small"><?php esc_html_e('Settings', 'edel-booking'); ?></a>
                                            <a href="?page=edel-booking-staff&action=remove_staff&user_id=<?php echo $u->ID; ?>" class="button button-small button-link-delete" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to remove this user from staff?', 'edel-booking')); ?>');" style="color:#a00;"><?php esc_html_e('Remove', 'edel-booking'); ?></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2"><?php esc_html_e('No staff registered.', 'edel-booking'); ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div style="flex:2; background:#fff; padding:20px; border:1px solid #ccd0d4; box-shadow:0 1px 1px rgba(0,0,0,0.04);">
                    <?php if ($target_user):
                        $schedule = get_user_meta($target_user->ID, 'edel_weekly_schedule', true);
                        $rel_rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_rel WHERE staff_id = %d", $target_user->ID));
                        $my_services = array();
                        $my_custom_prices = array();
                        foreach ($rel_rows as $row) {
                            $my_services[] = $row->service_id;
                            $my_custom_prices[$row->service_id] = $row->custom_price;
                        }
                    ?>
                        <h2 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">
                            <?php printf(esc_html__('Settings: %s', 'edel-booking'), esc_html($target_user->display_name)); ?>
                        </h2>
                        <form method="post">
                            <?php wp_nonce_field('edel_save_staff'); ?>
                            <input type="hidden" name="user_id" value="<?php echo $target_user->ID; ?>">

                            <h3><?php esc_html_e('1. Weekly Shift Settings', 'edel-booking'); ?></h3>
                            <table class="widefat" style="margin-bottom:20px;">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Day', 'edel-booking'); ?></th>
                                        <th><?php esc_html_e('Start', 'edel-booking'); ?></th>
                                        <th><?php esc_html_e('End', 'edel-booking'); ?></th>
                                        <th><?php esc_html_e('Off', 'edel-booking'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $weekdays = [
                                        'mon' => __('Mon', 'edel-booking'),
                                        'tue' => __('Tue', 'edel-booking'),
                                        'wed' => __('Wed', 'edel-booking'),
                                        'thu' => __('Thu', 'edel-booking'),
                                        'fri' => __('Fri', 'edel-booking'),
                                        'sat' => __('Sat', 'edel-booking'),
                                        'sun' => __('Sun', 'edel-booking')
                                    ];
                                    $keys = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
                                    foreach ($keys as $k):
                                        $label = $weekdays[$k];
                                        $s = isset($schedule[$k]['start']) ? $schedule[$k]['start'] : '10:00';
                                        $e = isset($schedule[$k]['end']) ? $schedule[$k]['end'] : '19:00';
                                        $off = isset($schedule[$k]['off']) && $schedule[$k]['off'] ? 'checked' : '';
                                    ?>
                                        <tr>
                                            <td><?php echo esc_html($label); ?></td>
                                            <td><input type="time" name="schedule[<?php echo $k; ?>][start]" value="<?php echo $s; ?>"></td>
                                            <td><input type="time" name="schedule[<?php echo $k; ?>][end]" value="<?php echo $e; ?>"></td>
                                            <td><input type="checkbox" name="schedule[<?php echo $k; ?>][off]" value="1" <?php echo $off; ?>></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <h3><?php esc_html_e('2. Assigned Services & Custom Fees', 'edel-booking'); ?></h3>
                            <p class="description" style="margin-bottom:10px;"><?php esc_html_e('Check the services this staff can handle.', 'edel-booking'); ?></p>
                            <table class="widefat striped">
                                <thead>
                                    <tr>
                                        <th style="width:30px;"><input type="checkbox" disabled></th>
                                        <th><?php esc_html_e('Service Name', 'edel-booking'); ?></th>
                                        <th><?php esc_html_e('Custom Fee (Blank for base price)', 'edel-booking'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_services as $srv):
                                        $checked = in_array($srv->id, $my_services) ? 'checked' : '';
                                        $price_val = isset($my_custom_prices[$srv->id]) ? $my_custom_prices[$srv->id] : '';
                                    ?>
                                        <tr>
                                            <td><input type="checkbox" name="services[]" value="<?php echo $srv->id; ?>" <?php echo $checked; ?>></td>
                                            <td><strong><?php echo esc_html($srv->title); ?></strong> (<?php printf(esc_html__('Base: ¥%s', 'edel-booking'), number_format($srv->price)); ?>)</td>
                                            <td>¥ <input type="number" name="custom_price[<?php echo $srv->id; ?>]" value="<?php echo esc_attr($price_val); ?>" placeholder="<?php echo $srv->price; ?>" style="width:100px;"></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <p class="submit">
                                <input type="submit" name="save_staff" class="button button-primary button-large" value="<?php esc_attr_e('Save Settings', 'edel-booking'); ?>">
                            </p>
                        </form>
                    <?php else: ?>
                        <div style="text-align:center; padding:40px; color:#666;">
                            <span class="dashicons dashicons-admin-users" style="font-size:40px; width:40px; height:40px; color:#ccc;"></span><br>
                            <p><?php esc_html_e('Select a staff from the list on the left to configure details.', 'edel-booking'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php
    }

    /**
     * ==================================================
     * 2. メニュー (サービス) 管理ページ
     * ==================================================
     */
    public function render_services_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'edel_booking_services';

        if (isset($_POST['save_service']) && check_admin_referer('edel_save_service')) {
            $data = array(
                'title' => sanitize_text_field($_POST['title']),
                'duration' => intval($_POST['duration']),
                'price' => intval($_POST['price']),
                'buffer_before' => intval($_POST['buffer_before']),
                'buffer_after' => intval($_POST['buffer_after']),
                'description' => sanitize_textarea_field($_POST['description']),
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            );

            if (!empty($_POST['service_id'])) {
                $wpdb->update($table, $data, array('id' => intval($_POST['service_id'])));
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Updated.', 'edel-booking') . '</p></div>';
            } else {
                $wpdb->insert($table, $data);
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Added.', 'edel-booking') . '</p></div>';
            }
        }

        if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
            $wpdb->delete($table, array('id' => intval($_GET['id'])));
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Deleted.', 'edel-booking') . '</p></div>';
        }

        $edit_data = null;
        if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
            $edit_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", intval($_GET['id'])));
        }
        $services = $wpdb->get_results("SELECT * FROM $table ORDER BY id ASC");
    ?>
        <div class="wrap">
            <h1><?php esc_html_e('Service Management', 'edel-booking'); ?></h1>

            <div style="display:flex; gap:20px; align-items:flex-start;">
                <div style="flex:1; background:#fff; padding:20px; border:1px solid #ccc;">
                    <h2><?php echo $edit_data ? esc_html__('Edit Service', 'edel-booking') : esc_html__('Add New', 'edel-booking'); ?></h2>
                    <form method="post" action="admin.php?page=edel-booking-services">
                        <?php wp_nonce_field('edel_save_service'); ?>
                        <?php if ($edit_data): ?><input type="hidden" name="service_id" value="<?php echo $edit_data->id; ?>"><?php endif; ?>

                        <p>
                            <label><?php esc_html_e('Service Name', 'edel-booking'); ?><br><input type="text" name="title" class="large-text" required value="<?php echo $edit_data ? esc_attr($edit_data->title) : ''; ?>"></label>
                        </p>
                        <div style="display:flex; gap:10px;">
                            <p style="flex:1;">
                                <label><?php esc_html_e('Duration (min)', 'edel-booking'); ?><br><input type="number" name="duration" style="width:100%;" required value="<?php echo $edit_data ? $edit_data->duration : 60; ?>"></label>
                            </p>
                            <p style="flex:1;">
                                <label><?php esc_html_e('Price (Yen)', 'edel-booking'); ?><br><input type="number" name="price" style="width:100%;" required value="<?php echo $edit_data ? $edit_data->price : 0; ?>"></label>
                            </p>
                        </div>
                        <div style="display:flex; gap:10px;">
                            <p style="flex:1;">
                                <label><?php esc_html_e('Buffer Before (min)', 'edel-booking'); ?><br><input type="number" name="buffer_before" style="width:100%;" value="<?php echo $edit_data ? $edit_data->buffer_before : 0; ?>"></label>
                            </p>
                            <p style="flex:1;">
                                <label><?php esc_html_e('Buffer After (min)', 'edel-booking'); ?><br><input type="number" name="buffer_after" style="width:100%;" value="<?php echo $edit_data ? $edit_data->buffer_after : 0; ?>"></label>
                            </p>
                        </div>
                        <p>
                            <label><?php esc_html_e('Description', 'edel-booking'); ?><br><textarea name="description" class="large-text" rows="3"><?php echo $edit_data ? esc_textarea($edit_data->description) : ''; ?></textarea></label>
                        </p>
                        <p>
                            <label><input type="checkbox" name="is_active" value="1" <?php checked($edit_data ? $edit_data->is_active : 1); ?>> <?php esc_html_e('Enable booking', 'edel-booking'); ?></label>
                        </p>
                        <p class="submit">
                            <input type="submit" name="save_service" class="button button-primary" value="<?php echo $edit_data ? esc_attr__('Update', 'edel-booking') : esc_attr__('Add', 'edel-booking'); ?>">
                            <?php if ($edit_data): ?><a href="admin.php?page=edel-booking-services" class="button"><?php esc_html_e('Cancel', 'edel-booking'); ?></a><?php endif; ?>
                        </p>
                    </form>
                </div>

                <div style="flex:2;">
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th><?php esc_html_e('Service Name', 'edel-booking'); ?></th>
                                <th><?php esc_html_e('Time', 'edel-booking'); ?></th>
                                <th><?php esc_html_e('Price', 'edel-booking'); ?></th>
                                <th><?php esc_html_e('Buffer', 'edel-booking'); ?></th>
                                <th><?php esc_html_e('Status', 'edel-booking'); ?></th>
                                <th><?php esc_html_e('Actions', 'edel-booking'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($services as $s): ?>
                                <tr>
                                    <td><?php echo $s->id; ?></td>
                                    <td><strong><?php echo esc_html($s->title); ?></strong></td>
                                    <td><?php echo $s->duration; ?></td>
                                    <td>¥<?php echo number_format($s->price); ?></td>
                                    <td><?php echo $s->buffer_before; ?> / <?php echo $s->buffer_after; ?></td>
                                    <td><?php echo $s->is_active ? esc_html__('Active', 'edel-booking') : '<span style="color:red;">' . esc_html__('Inactive', 'edel-booking') . '</span>'; ?></td>
                                    <td>
                                        <a href="admin.php?page=edel-booking-services&action=edit&id=<?php echo $s->id; ?>" class="button button-small"><?php esc_html_e('Edit', 'edel-booking'); ?></a>
                                        <a href="admin.php?page=edel-booking-services&action=delete&id=<?php echo $s->id; ?>" class="button button-small button-link-delete" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to delete?', 'edel-booking')); ?>');"><?php esc_html_e('Delete', 'edel-booking'); ?></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php
    }

    /**
     * ==================================================
     * 3. スケジュール例外 (休日・時間変更) 管理ページ
     * ==================================================
     */
    public function render_schedule_exceptions_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'edel_booking_schedule_exceptions';
        $staffs = get_users(array('meta_key' => 'is_edel_staff', 'meta_value' => 1));

        $view_staff_id = isset($_GET['view_staff_id']) ? intval($_GET['view_staff_id']) : 0;

        if (isset($_POST['save_exception']) && check_admin_referer('edel_save_exception')) {
            $wpdb->insert($table, array(
                'staff_id' => intval($_POST['staff_id']),
                'exception_date' => sanitize_text_field($_POST['date']),
                'is_day_off' => isset($_POST['is_day_off']) ? 1 : 0,
                'start_time' => sanitize_text_field($_POST['start_time']),
                'end_time' => sanitize_text_field($_POST['end_time']),
                'reason' => sanitize_text_field($_POST['reason'])
            ));
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Exception added.', 'edel-booking') . '</p></div>';
        }

        if (isset($_GET['delete_id'])) {
            $wpdb->delete($table, array('id' => intval($_GET['delete_id'])));
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Deleted.', 'edel-booking') . '</p></div>';
        }

        $sql = "SELECT * FROM $table";
        if ($view_staff_id > 0) {
            $sql .= $wpdb->prepare(" WHERE staff_id = %d", $view_staff_id);
        }
        $sql .= " ORDER BY exception_date DESC LIMIT 50";

        $rows = $wpdb->get_results($sql);
    ?>
        <div class="wrap">
            <h1><?php esc_html_e('Schedule Exceptions', 'edel-booking'); ?></h1>
            <p><?php esc_html_e('Configure holidays or special hours for specific dates.', 'edel-booking'); ?></p>

            <div style="display:flex; gap:20px; align-items:flex-start;">

                <div style="flex:1; background:#fff; padding:20px; border:1px solid #ccc;">
                    <h2><?php esc_html_e('Add New', 'edel-booking'); ?></h2>
                    <form method="post">
                        <?php wp_nonce_field('edel_save_exception'); ?>

                        <p>
                            <label><?php esc_html_e('Target Staff', 'edel-booking'); ?><br>
                                <select name="staff_id" style="width:100%;">
                                    <?php foreach ($staffs as $st): ?>
                                        <option value="<?php echo $st->ID; ?>" <?php selected($view_staff_id, $st->ID); ?>><?php echo esc_html($st->display_name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </p>
                        <p>
                            <label><?php esc_html_e('Date', 'edel-booking'); ?><br><input type="date" name="date" required style="width:100%;"></label>
                        </p>
                        <p>
                            <label><input type="checkbox" name="is_day_off" value="1" id="edel_is_off_check" onclick="toggleTimeInputs()"> <?php esc_html_e('Set as Day Off', 'edel-booking'); ?></label>
                        </p>
                        <div id="edel_time_inputs">
                            <div style="display:flex; gap:10px;">
                                <p style="flex:1;"><label><?php esc_html_e('Start Time', 'edel-booking'); ?><br><input type="time" name="start_time" style="width:100%;"></label></p>
                                <p style="flex:1;"><label><?php esc_html_e('End Time', 'edel-booking'); ?><br><input type="time" name="end_time" style="width:100%;"></label></p>
                            </div>
                        </div>
                        <p>
                            <label><?php esc_html_e('Reason (Note)', 'edel-booking'); ?><br><input type="text" name="reason" class="large-text"></label>
                        </p>
                        <p class="submit"><input type="submit" name="save_exception" class="button button-primary" value="<?php esc_attr_e('Add', 'edel-booking'); ?>"></p>
                    </form>
                </div>

                <div style="flex:2;">

                    <div style="margin-bottom: 15px; text-align: right; background: #f9f9f9; padding: 10px; border: 1px solid #ddd;">
                        <form method="get">
                            <input type="hidden" name="page" value="edel-booking-exceptions">
                            <label style="font-weight:bold;"><?php esc_html_e('Filter by Staff:', 'edel-booking'); ?>
                                <select name="view_staff_id" onchange="this.form.submit()">
                                    <option value="0"><?php esc_html_e('Show All', 'edel-booking'); ?></option>
                                    <?php foreach ($staffs as $st): ?>
                                        <option value="<?php echo $st->ID; ?>" <?php selected($view_staff_id, $st->ID); ?>>
                                            <?php echo esc_html($st->display_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </form>
                    </div>

                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Date', 'edel-booking'); ?></th>
                                <th><?php esc_html_e('Staff', 'edel-booking'); ?></th>
                                <th><?php esc_html_e('Content', 'edel-booking'); ?></th>
                                <th><?php esc_html_e('Reason', 'edel-booking'); ?></th>
                                <th><?php esc_html_e('Actions', 'edel-booking'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($rows): ?>
                                <?php foreach ($rows as $r):
                                    $st = get_userdata($r->staff_id);
                                ?>
                                    <tr>
                                        <td><?php echo $r->exception_date; ?></td>
                                        <td><?php echo $st ? esc_html($st->display_name) : '不明'; ?></td>
                                        <td>
                                            <?php if ($r->is_day_off): ?>
                                                <span style="color:red; font-weight:bold;"><?php esc_html_e('Off', 'edel-booking'); ?></span>
                                            <?php else: ?>
                                                <?php echo substr($r->start_time, 0, 5) . ' - ' . substr($r->end_time, 0, 5); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo esc_html($r->reason); ?></td>
                                        <td><a href="admin.php?page=edel-booking-exceptions&delete_id=<?php echo $r->id; ?>&view_staff_id=<?php echo $view_staff_id; ?>" class="button button-small" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to delete?', 'edel-booking')); ?>')"><?php esc_html_e('Delete', 'edel-booking'); ?></a></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5"><?php esc_html_e('No schedule exceptions found.', 'edel-booking'); ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <script>
                function toggleTimeInputs() {
                    var isOff = document.getElementById('edel_is_off_check').checked;
                    document.getElementById('edel_time_inputs').style.display = isOff ? 'none' : 'block';
                }
            </script>
        </div>
<?php
    }
}
