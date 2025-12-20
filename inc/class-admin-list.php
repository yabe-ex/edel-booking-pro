<?php

class EdelBookingProAdminList {

    public function __construct() {
        add_action('admin_init', array($this, 'process_export'));
    }

    public function process_export() {
        if (!isset($_POST['edel_action']) || $_POST['edel_action'] !== 'export_csv') {
            return;
        }

        check_admin_referer('edel_export_csv_nonce');

        if (!current_user_can('manage_options')) {
            return;
        }

        $target_month = isset($_POST['target_month']) ? sanitize_text_field($_POST['target_month']) : date('Y-m');

        $appointments = $this->get_appointments_by_month($target_month);

        $filename = 'booking_list_' . $target_month . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $fp = fopen('php://output', 'w');
        fwrite($fp, "\xEF\xBB\xBF");

        // ヘッダー翻訳
        $header = array(
            __('Booking ID', 'edel-booking'),
            __('Date', 'edel-booking'),
            __('Time', 'edel-booking'),
            __('Service', 'edel-booking'),
            __('Price', 'edel-booking'),
            __('Staff', 'edel-booking'),
            __('Customer Name', 'edel-booking'),
            __('Email', 'edel-booking'),
            __('Phone', 'edel-booking'),
            __('Status', 'edel-booking'),
            __('Admin Note', 'edel-booking'),
            __('Booking Hash', 'edel-booking')
        );
        fputcsv($fp, $header);

        foreach ($appointments as $app) {
            $staff = get_userdata($app->staff_id);
            $price = $this->calculate_price($app->service_id, $app->staff_id);
            $status_label = $this->get_status_label($app->status);

            $row = array(
                $app->id,
                date('Y/m/d', strtotime($app->start_datetime)),
                date('H:i', strtotime($app->start_datetime)),
                $app->service_title,
                $price,
                $staff ? $staff->display_name : __('Unknown', 'edel-booking'),
                $app->customer_name,
                $app->customer_email,
                $app->customer_phone,
                $status_label,
                $app->note,
                $app->booking_hash
            );
            fputcsv($fp, $row);
        }

        fclose($fp);
        exit;
    }

    public function render() {
        $target_month = isset($_GET['month']) ? sanitize_text_field($_GET['month']) : date('Y-m');

        if (isset($_POST['target_month']) && !isset($_POST['edel_action'])) {
            $target_month = sanitize_text_field($_POST['target_month']);
        }

        $appointments = $this->get_appointments_by_month($target_month);
?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Booking List', 'edel-booking'); ?></h1>
            <hr class="wp-header-end">

            <div class="tablenav top">
                <form method="get" action="" style="float:left; margin-right: 10px;">
                    <input type="hidden" name="page" value="edel-booking-pro-list">
                    <div class="alignleft actions">
                        <input type="month" name="month" value="<?php echo esc_attr($target_month); ?>">
                        <input type="submit" class="button" value="<?php esc_attr_e('Show', 'edel-booking'); ?>">
                    </div>
                </form>

                <form method="post" action="" style="float:left;">
                    <?php wp_nonce_field('edel_export_csv_nonce'); ?>
                    <input type="hidden" name="edel_action" value="export_csv">
                    <input type="hidden" name="target_month" value="<?php echo esc_attr($target_month); ?>">
                    <input type="submit" class="button button-primary" value="<?php esc_attr_e('Download CSV', 'edel-booking'); ?>">
                </form>
                <div style="clear:both;"></div>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th><?php esc_html_e('Date/Time', 'edel-booking'); ?></th>
                        <th><?php esc_html_e('Service', 'edel-booking'); ?></th>
                        <th><?php esc_html_e('Price (Est.)', 'edel-booking'); ?></th>
                        <th><?php esc_html_e('Staff', 'edel-booking'); ?></th>
                        <th><?php esc_html_e('Customer Info', 'edel-booking'); ?></th>
                        <th><?php esc_html_e('Status', 'edel-booking'); ?></th>
                        <th><?php esc_html_e('Note', 'edel-booking'); ?></th>
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
                                <td><?php echo esc_html($staff ? $staff->display_name : __('Unknown', 'edel-booking')); ?></td>
                                <td>
                                    <?php echo esc_html($app->customer_name); ?><br>
                                    <span style="font-size:11px; color:#666;"><?php echo esc_html($app->customer_email); ?></span><br>
                                    <span style="font-size:11px; color:#666;"><?php echo esc_html($app->customer_phone); ?></span>
                                </td>
                                <td style="<?php echo $status_style; ?>"><?php echo esc_html($status_label); ?></td>
                                <td><?php echo nl2br(esc_html($app->note)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8"><?php esc_html_e('No bookings found.', 'edel-booking'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
<?php
    }

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

    private function calculate_price($service_id, $staff_id) {
        global $wpdb;

        $custom = $wpdb->get_var($wpdb->prepare(
            "SELECT custom_price FROM {$wpdb->prefix}edel_booking_service_staff WHERE service_id = %d AND staff_id = %d",
            $service_id,
            $staff_id
        ));

        if ($custom !== null) {
            return (int)$custom;
        }

        $base = $wpdb->get_var($wpdb->prepare(
            "SELECT price FROM {$wpdb->prefix}edel_booking_services WHERE id = %d",
            $service_id
        ));

        return (int)$base;
    }

    private function get_status_label($status) {
        switch ($status) {
            case 'confirmed':
                return __('Confirmed', 'edel-booking');
            case 'pending':
                return __('Pending', 'edel-booking');
            case 'cancelled':
                return __('Cancelled', 'edel-booking');
            case 'completed':
                return __('Completed', 'edel-booking');
            default:
                return $status;
        }
    }
}
