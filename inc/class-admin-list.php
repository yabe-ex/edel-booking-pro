<?php

class EdelBookingProAdminList {

    public function __construct() {
        // CSV出力はヘッダー送信が必要なため、admin_init で処理をフックします
        add_action('admin_init', array($this, 'process_export'));
    }

    /**
     * CSVエクスポート処理
     */
    public function process_export() {
        if (!isset($_POST['edel_action']) || $_POST['edel_action'] !== 'export_csv') {
            return;
        }

        check_admin_referer('edel_export_csv_nonce');

        if (!current_user_can('manage_options')) {
            return;
        }

        $target_month = isset($_POST['target_month']) ? sanitize_text_field($_POST['target_month']) : date('Y-m');

        // データ取得
        $appointments = $this->get_appointments_by_month($target_month);

        // ファイル名
        $filename = 'booking_list_' . $target_month . '.csv';

        // ヘッダー設定
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $fp = fopen('php://output', 'w');

        // BOM (Excelでの文字化け防止)
        fwrite($fp, "\xEF\xBB\xBF");

        // CSVヘッダー
        $header = array('予約ID', '来店日', '来店時間', 'メニュー', '料金', '担当スタッフ', '顧客名', 'メールアドレス', '電話番号', 'ステータス', '管理メモ', '予約ハッシュ');
        fputcsv($fp, $header);

        foreach ($appointments as $app) {
            $staff = get_userdata($app->staff_id);

            // 料金計算
            $price = $this->calculate_price($app->service_id, $app->staff_id);

            // ステータス表記
            $status_label = $this->get_status_label($app->status);

            $row = array(
                $app->id,
                date('Y/m/d', strtotime($app->start_datetime)),
                date('H:i', strtotime($app->start_datetime)),
                $app->service_title,
                $price,
                $staff ? $staff->display_name : '不明',
                $app->customer_name,
                $app->customer_email,
                $app->customer_phone,
                $status_label,
                $app->note, // お客様備考
                $app->booking_hash
            );
            fputcsv($fp, $row);
        }

        fclose($fp);
        exit;
    }

    /**
     * 管理画面描画
     */
    public function render() {
        $target_month = isset($_GET['month']) ? sanitize_text_field($_GET['month']) : date('Y-m');

        // フォーム送信後のリロード対応
        if (isset($_POST['target_month']) && !isset($_POST['edel_action'])) {
            $target_month = sanitize_text_field($_POST['target_month']);
        }

        $appointments = $this->get_appointments_by_month($target_month);
?>
        <div class="wrap">
            <h1 class="wp-heading-inline">予約リスト</h1>
            <hr class="wp-header-end">

            <div class="tablenav top">
                <form method="get" action="" style="float:left; margin-right: 10px;">
                    <input type="hidden" name="page" value="edel-booking-pro-list">
                    <div class="alignleft actions">
                        <input type="month" name="month" value="<?php echo esc_attr($target_month); ?>">
                        <input type="submit" class="button" value="表示">
                    </div>
                </form>

                <form method="post" action="" style="float:left;">
                    <?php wp_nonce_field('edel_export_csv_nonce'); ?>
                    <input type="hidden" name="edel_action" value="export_csv">
                    <input type="hidden" name="target_month" value="<?php echo esc_attr($target_month); ?>">
                    <input type="submit" class="button button-primary" value="CSVダウンロード">
                </form>
                <div style="clear:both;"></div>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>日時</th>
                        <th>メニュー</th>
                        <th>料金 (概算)</th>
                        <th>担当スタッフ</th>
                        <th>顧客情報</th>
                        <th>ステータス</th>
                        <th>備考</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($appointments): ?>
                        <?php foreach ($appointments as $app):
                            $staff = get_userdata($app->staff_id);
                            $price = $this->calculate_price($app->service_id, $app->staff_id);
                            $status_label = $this->get_status_label($app->status);

                            $row_style = ($app->status == 'cancelled') ? 'opacity: 0.6; background:#f9f9f9;' : '';
                            $status_style = ($app->status == 'cancelled') ? 'color:#a00; font-weight:bold;' : (($app->status == 'confirmed') ? 'color:#27ae60; font-weight:bold;' : '');
                        ?>
                            <tr style="<?php echo $row_style; ?>">
                                <td><?php echo $app->id; ?></td>
                                <td>
                                    <strong><?php echo date('Y/m/d', strtotime($app->start_datetime)); ?></strong><br>
                                    <?php echo date('H:i', strtotime($app->start_datetime)); ?> 〜 <?php echo date('H:i', strtotime($app->end_datetime)); ?>
                                </td>
                                <td><?php echo esc_html($app->service_title); ?></td>
                                <td>¥<?php echo number_format($price); ?></td>
                                <td><?php echo esc_html($staff ? $staff->display_name : '不明'); ?></td>
                                <td>
                                    <?php echo esc_html($app->customer_name); ?><br>
                                    <span style="font-size:11px; color:#666;"><?php echo esc_html($app->customer_email); ?></span><br>
                                    <span style="font-size:11px; color:#666;"><?php echo esc_html($app->customer_phone); ?></span>
                                </td>
                                <td style="<?php echo $status_style; ?>"><?php echo $status_label; ?></td>
                                <td><?php echo nl2br(esc_html($app->note)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8">予約データがありません。</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
<?php
    }

    /**
     * データ取得ヘルパー
     */
    private function get_appointments_by_month($month_str) {
        global $wpdb;
        $table_appt = $wpdb->prefix . 'edel_booking_appointments';
        $table_service = $wpdb->prefix . 'edel_booking_services';

        $start_date = $month_str . '-01 00:00:00';
        $end_date   = date('Y-m-t 23:59:59', strtotime($start_date));

        return $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, s.title as service_title, s.price as base_price
             FROM $table_appt a
             LEFT JOIN $table_service s ON a.service_id = s.id
             WHERE a.start_datetime >= %s AND a.start_datetime <= %s
             ORDER BY a.start_datetime ASC",
            $start_date,
            $end_date
        ));
    }

    /**
     * 料金計算ヘルパー (フロントと同じロジック)
     */
    private function calculate_price($service_id, $staff_id) {
        global $wpdb;

        // カスタム料金チェック
        $custom = $wpdb->get_var($wpdb->prepare(
            "SELECT custom_price FROM {$wpdb->prefix}edel_booking_service_staff WHERE service_id = %d AND staff_id = %d",
            $service_id,
            $staff_id
        ));

        if ($custom !== null) {
            return (int)$custom;
        }

        // 基本料金
        $base = $wpdb->get_var($wpdb->prepare(
            "SELECT price FROM {$wpdb->prefix}edel_booking_services WHERE id = %d",
            $service_id
        ));

        return (int)$base;
    }

    private function get_status_label($status) {
        switch ($status) {
            case 'confirmed':
                return '確定';
            case 'pending':
                return '仮予約';
            case 'cancelled':
                return 'キャンセル';
            case 'completed':
                return '来店済';
            default:
                return $status;
        }
    }
}
