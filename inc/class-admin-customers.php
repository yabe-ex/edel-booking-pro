<?php

class EdelBookingProAdminCustomers {

    public function process_save() {
        if (!isset($_POST['edel_customer_action']) || $_POST['edel_customer_action'] !== 'save_note') {
            return;
        }

        check_admin_referer('edel_save_customer_note');

        $email = sanitize_email($_POST['customer_email']);
        $note  = sanitize_textarea_field($_POST['admin_note']);

        if (!$email) return;

        $user = get_user_by('email', $email);
        if ($user) {
            update_user_meta($user->ID, 'edel_admin_note', $note);
        } else {
            $key = 'edel_guest_note_' . md5($email);
            update_option($key, $note);
        }

        $redirect_url = admin_url('admin.php?page=edel-booking-pro-customers&action=view&email=' . urlencode($email) . '&msg=' . urlencode(__('Note saved.', 'edel-booking')));
        wp_redirect($redirect_url);
        exit;
    }

    public function render() {
        $action = isset($_GET['action']) ? $_GET['action'] : 'list';
        $email  = isset($_GET['email']) ? sanitize_email($_GET['email']) : '';

        if (isset($_GET['msg'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(urldecode($_GET['msg'])) . '</p></div>';
        }

        if ($action === 'view' && $email) {
            $this->render_detail($email);
        } else {
            $this->render_list();
        }
    }

    private function render_list() {
        global $wpdb;
        $table = $wpdb->prefix . 'edel_booking_appointments';

        $sql = "SELECT
                    customer_email,
                    MAX(customer_name) as display_name,
                    MAX(customer_phone) as phone,
                    SUM(CASE WHEN status != 'cancelled' THEN 1 ELSE 0 END) as visit_count,
                    MAX(CASE WHEN status != 'cancelled' THEN start_datetime ELSE NULL END) as last_visit
                FROM $table
                GROUP BY customer_email
                ORDER BY last_visit DESC";

        $customers = $wpdb->get_results($sql);
?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Customer Management', 'edel-booking'); ?></h1>
            <hr class="wp-header-end">

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Name', 'edel-booking'); ?></th>
                        <th><?php esc_html_e('Email', 'edel-booking'); ?></th>
                        <th><?php esc_html_e('Phone', 'edel-booking'); ?></th>
                        <th><?php esc_html_e('Visit Count', 'edel-booking'); ?></th>
                        <th><?php esc_html_e('Last Visit', 'edel-booking'); ?></th>
                        <th><?php esc_html_e('Actions', 'edel-booking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($customers): ?>
                        <?php foreach ($customers as $c): ?>
                            <tr>
                                <td><strong><?php echo esc_html($c->display_name); ?></strong></td>
                                <td><?php echo esc_html($c->customer_email); ?></td>
                                <td><?php echo esc_html($c->phone); ?></td>
                                <td><?php echo esc_html($c->visit_count); ?><?php esc_html_e(' times', 'edel-booking'); ?></td>
                                <td>
                                    <?php
                                    if ($c->last_visit) {
                                        echo date('Y/m/d', strtotime($c->last_visit));
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=edel-booking-pro-customers&action=view&email=' . urlencode($c->customer_email)); ?>" class="button button-small"><?php esc_html_e('Details / Note', 'edel-booking'); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6"><?php esc_html_e('No data found.', 'edel-booking'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php
    }

    private function render_detail($email) {
        global $wpdb;
        $table_appt = $wpdb->prefix . 'edel_booking_appointments';
        $table_service = $wpdb->prefix . 'edel_booking_services';

        $latest = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_appt WHERE customer_email = %s ORDER BY start_datetime DESC LIMIT 1",
            $email
        ));

        if (!$latest) {
            echo '<div class="wrap"><p>' . esc_html__('No data found.', 'edel-booking') . '</p></div>';
            return;
        }

        $history = $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, s.title as service_name
             FROM $table_appt a
             LEFT JOIN $table_service s ON a.service_id = s.id
             WHERE a.customer_email = %s
             ORDER BY a.start_datetime DESC",
            $email
        ));

        $valid_visit_count = 0;
        foreach ($history as $h) {
            if ($h->status !== 'cancelled') {
                $valid_visit_count++;
            }
        }

        $admin_note = '';
        $user = get_user_by('email', $email);
        if ($user) {
            $admin_note = get_user_meta($user->ID, 'edel_admin_note', true);
            $user_type_label = '<span class="edel-badge-user">' . __('Member (WP User)', 'edel-booking') . '</span>';
        } else {
            $key = 'edel_guest_note_' . md5($email);
            $admin_note = get_option($key, '');
            $user_type_label = '<span class="edel-badge-guest">' . __('Guest', 'edel-booking') . '</span>';
        }
    ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php printf(esc_html__('Customer Details: %s', 'edel-booking'), esc_html($latest->customer_name)); ?></h1>
            <a href="<?php echo admin_url('admin.php?page=edel-booking-pro-customers'); ?>" class="page-title-action"><?php esc_html_e('Back to List', 'edel-booking'); ?></a>
            <hr class="wp-header-end">

            <div class="edel-customer-grid">
                <div class="edel-col-left">
                    <div class="edel-box">
                        <h3><?php esc_html_e('Basic Info', 'edel-booking'); ?></h3>
                        <table class="form-table" style="margin-top:0;">
                            <tr>
                                <th><?php esc_html_e('Type', 'edel-booking'); ?></th>
                                <td><?php echo $user_type_label; ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Email', 'edel-booking'); ?></th>
                                <td><?php echo esc_html($email); ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Phone', 'edel-booking'); ?></th>
                                <td><?php echo esc_html($latest->customer_phone); ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('First Visit', 'edel-booking'); ?></th>
                                <td>
                                    <?php
                                    if (!empty($history)) {
                                        echo date('Y/m/d', strtotime($history[count($history) - 1]->start_datetime));
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div class="edel-box">
                        <h3><?php esc_html_e('Admin Note (Internal)', 'edel-booking'); ?></h3>
                        <form method="post" action="">
                            <?php wp_nonce_field('edel_save_customer_note'); ?>
                            <input type="hidden" name="edel_customer_action" value="save_note">
                            <input type="hidden" name="customer_email" value="<?php echo esc_attr($email); ?>">

                            <textarea name="admin_note" rows="8" class="large-text" placeholder="<?php esc_attr_e('Enter preferences or special notes...', 'edel-booking'); ?>"><?php echo esc_textarea($admin_note); ?></textarea>
                            <p class="description"><?php esc_html_e('This note is not visible to the customer.', 'edel-booking'); ?></p>
                            <?php submit_button(__('Save Note', 'edel-booking'), 'primary', 'submit', true, array('style' => 'margin-top:10px;')); ?>
                        </form>
                    </div>
                </div>

                <div class="edel-col-right">
                    <div class="edel-box">
                        <h3><?php printf(esc_html__('Visit History (Valid: %d)', 'edel-booking'), $valid_visit_count); ?></h3>

                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Date/Time', 'edel-booking'); ?></th>
                                    <th><?php esc_html_e('Menu', 'edel-booking'); ?></th>
                                    <th><?php esc_html_e('Staff', 'edel-booking'); ?></th>
                                    <th><?php esc_html_e('Status', 'edel-booking'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($history as $h):
                                    $staff = get_userdata($h->staff_id);

                                    $status_label = $h->status;
                                    if ($h->status == 'confirmed') $status_label = __('Confirmed', 'edel-booking');
                                    if ($h->status == 'pending') $status_label = __('Pending', 'edel-booking');
                                    if ($h->status == 'cancelled') $status_label = __('Cancelled', 'edel-booking');
                                    if ($h->status == 'completed') $status_label = __('Completed', 'edel-booking');

                                    $status_style = '';
                                    if ($h->status == 'cancelled') $status_style = 'color:#a00; font-weight:bold;';
                                    if ($h->status == 'completed') $status_style = 'color:#27ae60; font-weight:bold;';

                                    $row_style = ($h->status == 'cancelled') ? 'background-color:#f9f9f9; color:#999;' : '';
                                ?>
                                    <tr style="<?php echo $row_style; ?>">
                                        <td><?php echo date('Y/m/d H:i', strtotime($h->start_datetime)); ?></td>
                                        <td><?php echo esc_html($h->service_name); ?></td>
                                        <td><?php echo esc_html($staff ? $staff->display_name : __('Unknown', 'edel-booking')); ?></td>
                                        <td style="<?php echo $status_style; ?>"><?php echo esc_html($status_label); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <style>
            .edel-customer-grid {
                display: flex;
                gap: 20px;
                flex-wrap: wrap;
                margin-top: 20px;
            }

            .edel-col-left {
                flex: 1;
                min-width: 300px;
            }

            .edel-col-right {
                flex: 2;
                min-width: 400px;
            }

            .edel-box {
                background: #fff;
                border: 1px solid #ccd0d4;
                padding: 20px;
                margin-bottom: 20px;
                box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
            }

            .edel-box h3 {
                margin-top: 0;
                padding-bottom: 10px;
                border-bottom: 1px solid #eee;
            }

            .edel-badge-user {
                background: #2271b1;
                color: #fff;
                padding: 3px 8px;
                border-radius: 4px;
                font-size: 11px;
            }

            .edel-badge-guest {
                background: #646970;
                color: #fff;
                padding: 3px 8px;
                border-radius: 4px;
                font-size: 11px;
            }
        </style>
<?php
    }
}
