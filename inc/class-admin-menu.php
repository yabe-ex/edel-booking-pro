<?php

class EdelBookingProAdminMenu {

    private $admin_logic;

    public function __construct() {
        // 既存の管理機能（スタッフ管理など）のロジックを利用するためインスタンス化
        // class-admin.php はメインファイルですでに require されている前提
        if (class_exists('EdelBookingProAdmin')) {
            $this->admin_logic = new EdelBookingProAdmin();
        }

        add_action('admin_menu', array($this, 'add_menu_pages'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public function add_menu_pages() {
        // 1. メインメニュー: 予約管理 (予約リストを表示)
        add_menu_page(
            '予約管理',
            '予約管理',
            'edit_posts',
            EDEL_BOOKING_PRO_SLUG,
            array($this, 'render_booking_list_page'),
            'dashicons-calendar-alt',
            26
        );

        // 2. サブメニュー: 予約リスト
        add_submenu_page(
            EDEL_BOOKING_PRO_SLUG,
            '予約リスト',
            '予約リスト',
            'edit_posts',
            EDEL_BOOKING_PRO_SLUG,
            array($this, 'render_booking_list_page')
        );

        // 3. サブメニュー: スタッフ管理 (復旧)
        if ($this->admin_logic) {
            add_submenu_page(
                EDEL_BOOKING_PRO_SLUG,
                'スタッフ管理',
                'スタッフ管理',
                'manage_options',
                'edel-booking-staff',
                array($this->admin_logic, 'render_staff_page')
            );
        }

        // 4. サブメニュー: メニュー管理 (復旧)
        if ($this->admin_logic) {
            add_submenu_page(
                EDEL_BOOKING_PRO_SLUG,
                'メニュー管理',
                'メニュー管理',
                'manage_options',
                'edel-booking-services',
                array($this->admin_logic, 'render_services_page')
            );
        }

        // 5. サブメニュー: スケジュール例外 (復旧)
        if ($this->admin_logic) {
            // メソッド名が環境によって異なる可能性があるためチェック
            $method = method_exists($this->admin_logic, 'render_schedule_exceptions_page') ? 'render_schedule_exceptions_page' : 'render_exceptions_page';

            add_submenu_page(
                EDEL_BOOKING_PRO_SLUG,
                'スケジュール例外',
                'スケジュール例外',
                'manage_options',
                'edel-booking-exceptions',
                array($this->admin_logic, $method)
            );
        }

        // 6. サブメニュー: 設定 (class-admin-settings.php)
        if (class_exists('EdelBookingProAdminSettings')) {
            $settings = new EdelBookingProAdminSettings();
            add_submenu_page(
                EDEL_BOOKING_PRO_SLUG,
                '設定',
                '設定',
                'manage_options',
                'edel-booking-settings',
                array($settings, 'render')
            );
        }
    }

    public function enqueue_scripts($hook) {
        // 予約管理ページでのみ読み込む
        if (strpos($hook, EDEL_BOOKING_PRO_SLUG) === false) {
            return;
        }

        $version = (defined('EDEL_BOOKING_PRO_DEVELOP') && true === EDEL_BOOKING_PRO_DEVELOP) ? time() : EDEL_BOOKING_PRO_VERSION;

        // FullCalendar (CDN)
        wp_enqueue_script('edel-fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js', array(), '6.1.8', true);

        // 管理画面用 JS & CSS
        wp_enqueue_style(EDEL_BOOKING_PRO_SLUG . '-admin', EDEL_BOOKING_PRO_URL . '/css/admin.css', array(), $version);
        wp_enqueue_script(EDEL_BOOKING_PRO_SLUG . '-admin', EDEL_BOOKING_PRO_URL . '/js/admin.js', array('jquery', 'edel-fullcalendar'), $version, true);

        // JSへデータを渡す
        wp_localize_script(EDEL_BOOKING_PRO_SLUG . '-admin', 'edel_admin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce(EDEL_BOOKING_PRO_SLUG)
        ));
    }

    /**
     * 予約リストページ描画 (カレンダー & リスト)
     */
    public function render_booking_list_page() {
        global $wpdb;

        // 絞り込み条件 (リスト表示用)
        $filter_month = isset($_GET['filter_month']) ? $_GET['filter_month'] : date('Y-m');

        // CSV出力処理
        if (isset($_POST['export_csv']) && check_admin_referer('edel_export_csv_action')) {
            $this->export_csv($filter_month);
        }

?>
        <div class="wrap">
            <h1 class="wp-heading-inline">予約リスト</h1>

            <form method="post" style="display:inline-block; margin-left:10px;">
                <?php wp_nonce_field('edel_export_csv_action'); ?>
                <input type="hidden" name="filter_month" value="<?php echo esc_attr($filter_month); ?>">
                <input type="submit" name="export_csv" class="button" value="CSV出力">
            </form>
            <hr class="wp-header-end">

            <div class="edel-view-switcher">
                <button type="button" class="edel-switch-btn active" data-view="calendar">
                    <span class="dashicons dashicons-calendar-alt"></span> カレンダー
                </button>
                <button type="button" class="edel-switch-btn" data-view="list">
                    <span class="dashicons dashicons-list-view"></span> リスト
                </button>
            </div>

            <div id="edel-view-calendar" class="edel-view-section">
                <div id="edel-admin-calendar"></div>
            </div>

            <div id="edel-view-list" class="edel-view-section" style="display:none;">

                <div class="tablenav top">
                    <div class="alignleft actions">
                        <form method="get">
                            <input type="hidden" name="page" value="<?php echo EDEL_BOOKING_PRO_SLUG; ?>">
                            <input type="month" name="filter_month" value="<?php echo esc_attr($filter_month); ?>">
                            <input type="submit" class="button" value="絞り込み">
                        </form>
                    </div>
                </div>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>日時</th>
                            <th>顧客名</th>
                            <th>メニュー</th>
                            <th>担当</th>
                            <th>ステータス</th>
                            <th>備考</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $start_date = $filter_month . '-01 00:00:00';
                        $end_date   = date('Y-m-t 23:59:59', strtotime($start_date));

                        $sql = "SELECT * FROM {$wpdb->prefix}edel_booking_appointments
                                WHERE start_datetime BETWEEN %s AND %s
                                ORDER BY start_datetime ASC";
                        $results = $wpdb->get_results($wpdb->prepare($sql, $start_date, $end_date));

                        if ($results):
                            foreach ($results as $row):
                                $service = $wpdb->get_row($wpdb->prepare("SELECT title FROM {$wpdb->prefix}edel_booking_services WHERE id = %d", $row->service_id));
                                $staff   = get_userdata($row->staff_id);

                                // ステータス表示
                                $status_label = '確定';
                                $status_class = 'edel-status-confirmed';
                                if ($row->status === 'cancelled') {
                                    $status_label = 'キャンセル';
                                    $status_class = 'edel-status-cancelled';
                                } elseif ($row->status === 'pending') {
                                    $status_label = '仮予約';
                                    $status_class = 'edel-status-pending';
                                }

                                // 日時フォーマット
                                $date_display = date('Y-m-d H:i', strtotime($row->start_datetime));
                                if ($row->end_datetime) {
                                    $date_display .= ' - ' . date('H:i', strtotime($row->end_datetime));
                                }
                        ?>
                                <tr>
                                    <td><?php echo $row->id; ?></td>
                                    <td><?php echo esc_html($date_display); ?></td>
                                    <td>
                                        <?php echo esc_html($row->customer_name); ?><br>
                                        <small><?php echo esc_html($row->customer_email); ?></small><br>
                                        <small><?php echo esc_html($row->customer_phone); ?></small>
                                    </td>
                                    <td><?php echo $service ? esc_html($service->title) : '-'; ?></td>
                                    <td><?php echo $staff ? esc_html($staff->display_name) : '-'; ?></td>
                                    <td><span class="<?php echo $status_class; ?>"><?php echo $status_label; ?></span></td>
                                    <td><?php echo nl2br(esc_html($row->note)); ?></td>
                                </tr>
                            <?php endforeach;
                        else: ?>
                            <tr>
                                <td colspan="7">この月の予約はありません。</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
<?php
    }

    /**
     * CSV出力処理
     */
    private function export_csv($filter_month) {
        global $wpdb;
        $start_date = $filter_month . '-01 00:00:00';
        $end_date   = date('Y-m-t 23:59:59', strtotime($start_date));

        $sql = "SELECT * FROM {$wpdb->prefix}edel_booking_appointments
                WHERE start_datetime BETWEEN %s AND %s
                ORDER BY start_datetime ASC";
        $results = $wpdb->get_results($wpdb->prepare($sql, $start_date, $end_date));

        $filename = 'booking_list_' . $filter_month . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $output = fopen('php://output', 'w');
        fputs($output, "\xEF\xBB\xBF"); // BOM (Excelでの文字化け防止)

        fputcsv($output, array('ID', '開始日時', '終了日時', '顧客名', 'メール', '電話番号', 'メニュー', '担当スタッフ', 'ステータス', '備考'));

        foreach ($results as $row) {
            $service = $wpdb->get_row($wpdb->prepare("SELECT title FROM {$wpdb->prefix}edel_booking_services WHERE id = %d", $row->service_id));
            $staff   = get_userdata($row->staff_id);

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
                $row->note
            ));
        }
        fclose($output);
        exit;
    }
}
