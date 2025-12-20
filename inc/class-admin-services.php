<?php

class EdelBookingProAdminServices {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'edel_booking_services';
    }

    public function process_save() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        if (!isset($_POST['service_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['service_nonce'], 'save_service_action')) {
            wp_die(__('Security check failed.', 'edel-booking'));
        }

        global $wpdb;
        $id = intval($_POST['id']);

        $data = array(
            'title'         => sanitize_text_field($_POST['title']),
            'duration'      => intval($_POST['duration']),
            'price'         => floatval($_POST['price']),
            'buffer_before' => intval($_POST['buffer_before']),
            'buffer_after'  => intval($_POST['buffer_after']),
            'description'   => sanitize_textarea_field($_POST['description']),
        );

        if ($id > 0) {
            $wpdb->update($this->table_name, $data, array('id' => $id));
            $message = __('Updated.', 'edel-booking');
        } else {
            $wpdb->insert($this->table_name, $data);
            $message = __('Created.', 'edel-booking');
        }

        wp_redirect(admin_url('admin.php?page=edel-booking-pro-services&msg=' . urlencode($message)));
        exit;
    }

    public function render() {
        $action = isset($_GET['action']) ? $_GET['action'] : 'list';
        $id     = isset($_GET['id']) ? intval($_GET['id']) : 0;

        if (isset($_GET['msg'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(urldecode($_GET['msg'])) . '</p></div>';
        }

        if ($action === 'delete' && $id > 0) {
            $this->delete_service($id);
        }

        if ($action === 'edit' || $action === 'add') {
            $this->render_form($id);
        } else {
            $this->render_list();
        }
    }

    private function render_list() {
        global $wpdb;
        $services = $wpdb->get_results("SELECT * FROM {$this->table_name} ORDER BY id DESC");
?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Service Management', 'edel-booking'); ?></h1>
            <a href="<?php echo admin_url('admin.php?page=edel-booking-pro-services&action=add'); ?>" class="page-title-action"><?php esc_html_e('Add New', 'edel-booking'); ?></a>
            <hr class="wp-header-end">

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th width="20%"><?php esc_html_e('Service Name', 'edel-booking'); ?></th>
                        <th width="10%"><?php esc_html_e('Duration', 'edel-booking'); ?></th>
                        <th width="10%"><?php esc_html_e('Price', 'edel-booking'); ?></th>
                        <th width="15%"><?php esc_html_e('Buffer', 'edel-booking'); ?></th>
                        <th><?php esc_html_e('Description', 'edel-booking'); ?></th>
                        <th width="15%"><?php esc_html_e('Actions', 'edel-booking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($services) : ?>
                        <?php foreach ($services as $service) : ?>
                            <tr>
                                <td><strong><?php echo esc_html($service->title); ?></strong></td>
                                <td><?php echo esc_html($service->duration); ?><?php esc_html_e('min', 'edel-booking'); ?></td>
                                <td>¥<?php echo number_format($service->price); ?></td>
                                <td><?php printf(esc_html__('Pre:%s / Post:%s', 'edel-booking'), $service->buffer_before, $service->buffer_after); ?></td>
                                <td><?php echo esc_html(mb_strimwidth($service->description, 0, 50, '...')); ?></td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=edel-booking-pro-services&action=edit&id=' . $service->id); ?>" class="button button-small"><?php esc_html_e('Edit', 'edel-booking'); ?></a>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=edel-booking-pro-services&action=delete&id=' . $service->id), 'delete_service_' . $service->id); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to delete?', 'edel-booking')); ?>');"><?php esc_html_e('Delete', 'edel-booking'); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="6"><?php esc_html_e('No services found.', 'edel-booking'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php
    }

    private function render_form($id = 0) {
        global $wpdb;
        $data = null;
        $title = __('Add New Service', 'edel-booking');
        if ($id > 0) {
            $data = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id));
            if ($data) $title = sprintf(__('Edit: %s', 'edel-booking'), esc_html($data->title));
        }
    ?>
        <div class="wrap">
            <h1><?php echo esc_html($title); ?></h1>
            <form method="post" action="">
                <?php wp_nonce_field('save_service_action', 'service_nonce'); ?>
                <input type="hidden" name="id" value="<?php echo $id; ?>">

                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e('Service Name', 'edel-booking'); ?></th>
                        <td><input name="title" type="text" value="<?php echo $data ? esc_attr($data->title) : ''; ?>" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Duration (min)', 'edel-booking'); ?></th>
                        <td><input name="duration" type="number" value="<?php echo $data ? esc_attr($data->duration) : '60'; ?>" class="small-text" required> <?php esc_html_e('min', 'edel-booking'); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Price (Yen)', 'edel-booking'); ?></th>
                        <td><input name="price" type="number" value="<?php echo $data ? esc_attr($data->price) : '0'; ?>" class="regular-text"> <?php esc_html_e('Yen', 'edel-booking'); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Buffer', 'edel-booking'); ?></th>
                        <td>
                            <?php esc_html_e('Before:', 'edel-booking'); ?> <input name="buffer_before" type="number" value="<?php echo $data ? esc_attr($data->buffer_before) : '0'; ?>" class="small-text"> <?php esc_html_e('min', 'edel-booking'); ?><br>
                            <?php esc_html_e('After:', 'edel-booking'); ?> <input name="buffer_after" type="number" value="<?php echo $data ? esc_attr($data->buffer_after) : '0'; ?>" class="small-text"> <?php esc_html_e('min', 'edel-booking'); ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Description', 'edel-booking'); ?></th>
                        <td><textarea name="description" class="large-text" rows="3"><?php echo $data ? esc_textarea($data->description) : ''; ?></textarea></td>
                    </tr>
                </table>
                <?php submit_button(__('Save', 'edel-booking')); ?>
                <a href="<?php echo admin_url('admin.php?page=edel-booking-pro-services'); ?>"><?php esc_html_e('Back to List', 'edel-booking'); ?></a>
            </form>
        </div>
<?php
    }

    private function delete_service($id) {
        check_admin_referer('delete_service_' . $id);
        global $wpdb;
        $wpdb->delete($this->table_name, array('id' => $id));
        wp_redirect(admin_url('admin.php?page=edel-booking-pro-services&msg=' . urlencode(__('Deleted.', 'edel-booking'))));
        exit;
    }
}
