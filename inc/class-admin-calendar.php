<?php

class EdelBookingProAdminCalendar {

    public function render() {
        global $wpdb;

        // スタッフ一覧取得
        $staff_users = get_users(array('meta_key' => 'is_edel_staff', 'meta_value' => 1));

        // サービス一覧取得
        $services = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}edel_booking_services WHERE is_active = 1");

?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Booking Calendar', 'edel-booking'); ?></h1>
            <button id="edel-add-booking-btn" class="page-title-action"><?php esc_html_e('Add New Booking', 'edel-booking'); ?></button>
            <hr class="wp-header-end">

            <div class="edel-calendar-controls">
                <select id="edel-calendar-staff-filter">
                    <option value="0"><?php esc_html_e('All Staff', 'edel-booking'); ?></option>
                    <?php foreach ($staff_users as $staff) : ?>
                        <option value="<?php echo $staff->ID; ?>"><?php echo esc_html($staff->display_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <br>
            <div id="edel-booking-calendar" style="background: #fff; padding: 20px; max-width: 100%;"></div>
        </div>

        <div id="edel-booking-modal" class="edel-modal" style="display:none;">
            <div class="edel-modal-content">
                <span class="edel-close">&times;</span>
                <h2 id="edel-modal-title"><?php esc_html_e('New Booking Registration', 'edel-booking'); ?></h2>

                <form id="edel-booking-form">
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e('Staff', 'edel-booking'); ?></th>
                            <td>
                                <select name="staff_id" required>
                                    <?php foreach ($staff_users as $staff) : ?>
                                        <option value="<?php echo $staff->ID; ?>"><?php echo esc_html($staff->display_name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Service', 'edel-booking'); ?></th>
                            <td>
                                <select name="service_id" required>
                                    <?php foreach ($services as $service) : ?>
                                        <option value="<?php echo $service->id; ?>">
                                            <?php echo esc_html($service->title); ?> (<?php echo $service->duration; ?><?php esc_html_e('min', 'edel-booking'); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Date/Time', 'edel-booking'); ?></th>
                            <td>
                                <input type="date" name="date" required value="<?php echo date('Y-m-d'); ?>">
                                <input type="time" name="time" required value="10:00">
                                <p class="description">※<?php esc_html_e('End time is automatically calculated from service duration.', 'edel-booking'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Customer Name', 'edel-booking'); ?></th>
                            <td><input type="text" name="customer_name" class="regular-text" required placeholder="<?php esc_attr_e('Ex: Taro Yamada', 'edel-booking'); ?>"></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Email', 'edel-booking'); ?></th>
                            <td><input type="email" name="customer_email" class="regular-text" placeholder="sample@example.com"></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Phone', 'edel-booking'); ?></th>
                            <td><input type="text" name="customer_phone" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Note', 'edel-booking'); ?></th>
                            <td><textarea name="note" class="large-text" rows="2"></textarea></td>
                        </tr>
                    </table>
                    <br>
                    <button type="submit" class="button button-primary"><?php esc_html_e('Save Booking', 'edel-booking'); ?></button>
                </form>
            </div>
        </div>
<?php
    }
}
