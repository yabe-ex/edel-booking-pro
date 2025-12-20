<?php

class EdelBookingProAdminMenu {

    private $admin_logic;

    public function __construct() {
        if (class_exists('EdelBookingProAdmin')) {
            $this->admin_logic = new EdelBookingProAdmin();
        }
        add_action('admin_menu', array($this, 'add_menu_pages'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public function add_menu_pages() {
        add_menu_page(
            __('Booking Management', 'edel-booking'),
            __('Booking Management', 'edel-booking'),
            'edit_posts',
            EDEL_BOOKING_PRO_SLUG,
            array($this, 'render_booking_list_page'),
            'dashicons-calendar-alt',
            26
        );
        add_submenu_page(
            EDEL_BOOKING_PRO_SLUG,
            __('Booking List', 'edel-booking'),
            __('Booking List', 'edel-booking'),
            'edit_posts',
            EDEL_BOOKING_PRO_SLUG,
            array($this, 'render_booking_list_page')
        );

        if ($this->admin_logic) {
            add_submenu_page(
                EDEL_BOOKING_PRO_SLUG,
                __('Staff Management', 'edel-booking'),
                __('Staff Management', 'edel-booking'),
                'manage_options',
                'edel-booking-staff',
                array($this->admin_logic, 'render_staff_page')
            );
            add_submenu_page(
                EDEL_BOOKING_PRO_SLUG,
                __('Services', 'edel-booking'),
                __('Services', 'edel-booking'),
                'manage_options',
                'edel-booking-services',
                array($this->admin_logic, 'render_services_page')
            );
            $method = method_exists($this->admin_logic, 'render_schedule_exceptions_page') ? 'render_schedule_exceptions_page' : 'render_exceptions_page';
            add_submenu_page(
                EDEL_BOOKING_PRO_SLUG,
                __('Schedule Exceptions', 'edel-booking'),
                __('Schedule Exceptions', 'edel-booking'),
                'manage_options',
                'edel-booking-exceptions',
                array($this->admin_logic, $method)
            );
        }
        if (class_exists('EdelBookingProAdminSettings')) {
            $settings = new EdelBookingProAdminSettings();
            add_submenu_page(
                EDEL_BOOKING_PRO_SLUG,
                __('Settings', 'edel-booking'),
                __('Settings', 'edel-booking'),
                'manage_options',
                'edel-booking-settings',
                array($settings, 'render')
            );
        }
    }

    public function enqueue_scripts($hook) {
        if (strpos($hook, 'edel-booking-') === false) return;

        $version = time(); // キャッシュクリア用

        // 現在選択されているスタッフIDを取得してJSに渡す
        $staff_id = isset($_GET['staff_id']) ? intval($_GET['staff_id']) : 0;

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

        wp_enqueue_style(EDEL_BOOKING_PRO_SLUG . '-admin', EDEL_BOOKING_PRO_URL . '/css/admin.css', array(), $version);
        wp_enqueue_script(EDEL_BOOKING_PRO_SLUG . '-admin', EDEL_BOOKING_PRO_URL . '/js/admin.js', array('jquery', 'edel-fullcalendar'), $version, true);

        // JS用翻訳データ
        $l10n = array(
            'error_fetch' => __('Failed to fetch booking data.', 'edel-booking'),
            'detail_title' => __('【Booking Details】', 'edel-booking'),
            'date' => __('Date: ', 'edel-booking'),
            'end' => __('End: ', 'edel-booking'),
            'content' => __('Content: ', 'edel-booking'),
            'email' => __('Email: ', 'edel-booking'),
            'phone' => __('Phone: ', 'edel-booking'),
            'status' => __('Status: ', 'edel-booking'),
            'locale_code' => substr(get_locale(), 0, 2), // 'ja', 'en' etc.
            'button_today' => __('Today', 'edel-booking'),
            'button_month' => __('Month', 'edel-booking'),
            'button_week' => __('Week', 'edel-booking'),
            'button_day' => __('Day', 'edel-booking'),
            'button_list' => __('List', 'edel-booking'),
        );

        wp_localize_script(EDEL_BOOKING_PRO_SLUG . '-admin', 'edel_admin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce(EDEL_BOOKING_PRO_SLUG),
            'staff_id' => $staff_id,
            'l10n'    => $l10n
        ));
    }

    public function render_booking_list_page() {
        global $wpdb;

        // フィルタリングパラメータ
        $filter_month = isset($_GET['filter_month']) ? $_GET['filter_month'] : date('Y-m');
        $selected_staff_id = isset($_GET['staff_id']) ? intval($_GET['staff_id']) : 0;

        // スタッフ一覧取得
        $staff_users = get_users(array('meta_key' => 'is_edel_staff', 'meta_value' => 1));

        // CSV出力処理
        if (isset($_POST['export_csv']) && check_admin_referer('edel_export_csv_action')) {
            $this->export_csv($filter_month, $selected_staff_id);
        }
?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Booking List', 'edel-booking'); ?></h1>

            <form method="get" id="edel-filter-form" style="background:#fff; padding:10px 15px; margin:15px 0; border:1px solid #ccd0d4; display:flex; align-items:center; gap:10px;">
                <input type="hidden" name="page" value="<?php echo EDEL_BOOKING_PRO_SLUG; ?>">

                <label style="font-weight:bold;"><?php _e('Target Month:', 'edel-booking'); ?></label>
                <input type="month" name="filter_month" value="<?php echo esc_attr($filter_month); ?>">

                <label style="font-weight:bold; margin-left:10px;"><?php _e('Staff:', 'edel-booking'); ?></label>
                <select name="staff_id">
                    <option value="0"><?php _e('Show All Bookings', 'edel-booking'); ?></option>
                    <?php foreach ($staff_users as $st): ?>
                        <option value="<?php echo $st->ID; ?>" <?php selected($selected_staff_id, $st->ID); ?>>
                            <?php echo esc_html($st->display_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <input type="submit" class="button" value="<?php _e('Filter', 'edel-booking'); ?>">

                <span style="flex:1;"></span>
            </form>

            <div style="text-align:right; margin-bottom:10px;">
                <form method="post" style="display:inline-block;">
                    <?php wp_nonce_field('edel_export_csv_action'); ?>
                    <input type="hidden" name="filter_month" value="<?php echo esc_attr($filter_month); ?>">
                    <input type="hidden" name="staff_id" value="<?php echo $selected_staff_id; ?>">
                    <input type="submit" name="export_csv" class="button" value="<?php _e('Export CSV', 'edel-booking'); ?>">
                </form>
            </div>

            <div class="edel-view-switcher">
                <button type="button" class="edel-switch-btn active" data-view="calendar"><span class="dashicons dashicons-calendar-alt"></span> <?php _e('Calendar', 'edel-booking'); ?></button>
                <button type="button" class="edel-switch-btn" data-view="list"><span class="dashicons dashicons-list-view"></span> <?php _e('List', 'edel-booking'); ?></button>
            </div>

            <div id="edel-view-calendar" class="edel-view-section">
                <?php if ($selected_staff_id > 0): ?>
                    <p class="description" style="margin-bottom:10px;">
                        <span style="display:inline-block; width:12px; height:12px; background:#d4edda; border:1px solid #c3e6cb;"></span>
                        <?php _e('Green background indicates the available shifts for the selected staff.', 'edel-booking'); ?>
                    </p>
                <?php endif; ?>
                <div id="edel-admin-calendar"></div>
            </div>

            <div id="edel-view-list" class="edel-view-section" style="display:none;">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th><?php _e('Date/Time', 'edel-booking'); ?></th>
                            <th><?php _e('Customer', 'edel-booking'); ?></th>
                            <th><?php _e('Service', 'edel-booking'); ?></th>
                            <th><?php _e('Staff', 'edel-booking'); ?></th>
                            <th><?php _e('Status', 'edel-booking'); ?></th>
                            <th><?php _e('Details (Note)', 'edel-booking'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $start_date = $filter_month . '-01 00:00:00';
                        $end_date   = date('Y-m-t 23:59:59', strtotime($start_date));

                        $sql = "SELECT * FROM {$wpdb->prefix}edel_booking_appointments WHERE start_datetime BETWEEN %s AND %s";
                        $params = array($start_date, $end_date);

                        if ($selected_staff_id > 0) {
                            $sql .= " AND staff_id = %d";
                            $params[] = $selected_staff_id;
                        }

                        $sql .= " ORDER BY start_datetime ASC";

                        $results = $wpdb->get_results($wpdb->prepare($sql, $params));

                        if ($results):
                            foreach ($results as $row):
                                $service = $wpdb->get_row($wpdb->prepare("SELECT title FROM {$wpdb->prefix}edel_booking_services WHERE id = %d", $row->service_id));
                                $staff   = get_userdata($row->staff_id);

                                $status_label = __('Confirmed', 'edel-booking');
                                $status_class = 'edel-status-confirmed';
                                if ($row->status === 'cancelled') {
                                    $status_label = __('Cancelled', 'edel-booking');
                                    $status_class = 'edel-status-cancelled';
                                } elseif ($row->status === 'pending') {
                                    $status_label = __('Pending', 'edel-booking');
                                    $status_class = 'edel-status-pending';
                                }

                                $date_display = date('Y-m-d H:i', strtotime($row->start_datetime));
                                if ($row->end_datetime) {
                                    $date_display .= ' - ' . date('H:i', strtotime($row->end_datetime));
                                }

                                $details = "";
                                if (!empty($row->custom_data)) {
                                    $arr = json_decode($row->custom_data, true);
                                    if (is_array($arr)) {
                                        foreach ($arr as $f) {
                                            $val = is_array($f['value']) ? implode(', ', $f['value']) : $f['value'];
                                            $details .= "<strong>" . esc_html($f['label']) . ":</strong> " . esc_html($val) . "<br>";
                                        }
                                    }
                                }
                                if (!empty($row->note)) {
                                    if ($details) $details .= "<hr style='margin:5px 0; border:0; border-top:1px dashed #ccc;'>";
                                    $details .= "<strong>" . __('Note:', 'edel-booking') . "</strong> " . nl2br(esc_html($row->note));
                                }
                        ?>
                                <tr>
                                    <td><?php echo $row->id; ?></td>
                                    <td><?php echo esc_html($date_display); ?></td>
                                    <td><?php echo esc_html($row->customer_name); ?><br><small><?php echo esc_html($row->customer_email); ?></small></td>
                                    <td><?php echo $service ? esc_html($service->title) : '-'; ?></td>
                                    <td><?php echo $staff ? esc_html($staff->display_name) : '-'; ?></td>
                                    <td><span class="<?php echo $status_class; ?>"><?php echo esc_html($status_label); ?></span></td>
                                    <td><span style="font-size:0.9em; color:#555;"><?php echo $details; ?></span></td>
                                </tr>
                            <?php endforeach;
                        else: ?>
                            <tr>
                                <td colspan="7"><?php _e('No bookings found for this criteria.', 'edel-booking'); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
<?php
    }

    private function export_csv($filter_month, $staff_id = 0) {
        global $wpdb;
        $start_date = $filter_month . '-01 00:00:00';
        $end_date   = date('Y-m-t 23:59:59', strtotime($start_date));

        $sql = "SELECT * FROM {$wpdb->prefix}edel_booking_appointments WHERE start_datetime BETWEEN %s AND %s";
        $params = array($start_date, $end_date);

        if ($staff_id > 0) {
            $sql .= " AND staff_id = %d";
            $params[] = $staff_id;
        }

        $sql .= " ORDER BY start_datetime ASC";
        $results = $wpdb->get_results($wpdb->prepare($sql, $params));

        $filename = 'booking_list_' . $filter_month . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        $output = fopen('php://output', 'w');
        fputs($output, "\xEF\xBB\xBF");

        // CSVヘッダー翻訳
        $header = array(
            __('ID', 'edel-booking'),
            __('Start Date/Time', 'edel-booking'),
            __('End Date/Time', 'edel-booking'),
            __('Customer Name', 'edel-booking'),
            __('Email', 'edel-booking'),
            __('Phone', 'edel-booking'),
            __('Service', 'edel-booking'),
            __('Staff', 'edel-booking'),
            __('Status', 'edel-booking'),
            __('Details(Custom)', 'edel-booking'),
            __('Note', 'edel-booking')
        );
        fputcsv($output, $header);

        foreach ($results as $row) {
            $service = $wpdb->get_row($wpdb->prepare("SELECT title FROM {$wpdb->prefix}edel_booking_services WHERE id = %d", $row->service_id));
            $staff   = get_userdata($row->staff_id);
            $custom_str = "";
            if (!empty($row->custom_data)) {
                $arr = json_decode($row->custom_data, true);
                if (is_array($arr)) {
                    foreach ($arr as $f) {
                        $val = is_array($f['value']) ? implode(', ', $f['value']) : $f['value'];
                        $custom_str .= $f['label'] . ":" . $val . " / ";
                    }
                }
            }
            fputcsv($output, array(
                $row->id,
                $row->start_datetime,
                $row->end_datetime,
                $row->customer_name,
                $row->customer_email,
                $row->customer_phone,
                $service ? $service->title : '',
                $staff ? $staff->display_name : '',
                $row->status,
                $custom_str,
                $row->note
            ));
        }
        fclose($output);
        exit;
    }
}
