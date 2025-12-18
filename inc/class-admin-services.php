<?php

class EdelBookingProAdminServices {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'edel_booking_services';
    }

    /**
     * 保存処理 (admin_initで呼ばれる)
     * ★修正ポイント: HTML描画前に実行されるようになりました
     */
    public function process_save() {
        // POSTリクエストでなければ何もしない
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        // このページのアクションかチェック
        if (!isset($_POST['service_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['service_nonce'], 'save_service_action')) {
            wp_die('セキュリティチェックに失敗しました。');
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
            $message = '更新しました。';
        } else {
            $wpdb->insert($this->table_name, $data);
            $message = '作成しました。';
        }

        // 一覧画面へリダイレクト
        wp_redirect(admin_url('admin.php?page=edel-booking-pro-services&msg=' . urlencode($message)));
        exit;
    }

    /**
     * 画面表示
     */
    public function render() {
        $action = isset($_GET['action']) ? $_GET['action'] : 'list';
        $id     = isset($_GET['id']) ? intval($_GET['id']) : 0;

        // メッセージ表示
        if (isset($_GET['msg'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(urldecode($_GET['msg'])) . '</p></div>';
        }

        // ★削除処理 (GETリクエストでの削除)
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
            <h1 class="wp-heading-inline">サービス管理</h1>
            <a href="<?php echo admin_url('admin.php?page=edel-booking-pro-services&action=add'); ?>" class="page-title-action">新規追加</a>
            <hr class="wp-header-end">

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th width="20%">サービス名</th>
                        <th width="10%">時間</th>
                        <th width="10%">価格</th>
                        <th width="15%">余白</th>
                        <th>説明</th>
                        <th width="15%">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($services) : ?>
                        <?php foreach ($services as $service) : ?>
                            <tr>
                                <td><strong><?php echo esc_html($service->title); ?></strong></td>
                                <td><?php echo esc_html($service->duration); ?>分</td>
                                <td>¥<?php echo number_format($service->price); ?></td>
                                <td>前:<?php echo esc_html($service->buffer_before); ?> / 後:<?php echo esc_html($service->buffer_after); ?></td>
                                <td><?php echo esc_html(mb_strimwidth($service->description, 0, 50, '...')); ?></td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=edel-booking-pro-services&action=edit&id=' . $service->id); ?>" class="button button-small">編集</a>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=edel-booking-pro-services&action=delete&id=' . $service->id), 'delete_service_' . $service->id); ?>" class="button button-small button-link-delete" onclick="return confirm('削除しますか？');">削除</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="6">サービスがありません。</td>
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
        $title = '新規サービス追加';
        if ($id > 0) {
            $data = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id));
            if ($data) $title = '編集: ' . esc_html($data->title);
        }
    ?>
        <div class="wrap">
            <h1><?php echo $title; ?></h1>
            <form method="post" action="">
                <?php wp_nonce_field('save_service_action', 'service_nonce'); ?>
                <input type="hidden" name="id" value="<?php echo $id; ?>">

                <table class="form-table">
                    <tr>
                        <th>サービス名</th>
                        <td><input name="title" type="text" value="<?php echo $data ? esc_attr($data->title) : ''; ?>" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th>所要時間 (分)</th>
                        <td><input name="duration" type="number" value="<?php echo $data ? esc_attr($data->duration) : '60'; ?>" class="small-text" required> 分</td>
                    </tr>
                    <tr>
                        <th>価格 (円)</th>
                        <td><input name="price" type="number" value="<?php echo $data ? esc_attr($data->price) : '0'; ?>" class="regular-text"> 円</td>
                    </tr>
                    <tr>
                        <th>バッファ</th>
                        <td>
                            前: <input name="buffer_before" type="number" value="<?php echo $data ? esc_attr($data->buffer_before) : '0'; ?>" class="small-text"> 分<br>
                            後: <input name="buffer_after" type="number" value="<?php echo $data ? esc_attr($data->buffer_after) : '0'; ?>" class="small-text"> 分
                        </td>
                    </tr>
                    <tr>
                        <th>説明</th>
                        <td><textarea name="description" class="large-text" rows="3"><?php echo $data ? esc_textarea($data->description) : ''; ?></textarea></td>
                    </tr>
                </table>
                <?php submit_button('保存する'); ?>
                <a href="<?php echo admin_url('admin.php?page=edel-booking-pro-services'); ?>">一覧に戻る</a>
            </form>
        </div>
<?php
    }

    private function delete_service($id) {
        check_admin_referer('delete_service_' . $id);
        global $wpdb;
        $wpdb->delete($this->table_name, array('id' => $id));
        wp_redirect(admin_url('admin.php?page=edel-booking-pro-services&msg=' . urlencode('削除しました。')));
        exit;
    }
}
