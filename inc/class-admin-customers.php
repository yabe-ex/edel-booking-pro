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

        // WordPressユーザーかチェック
        $user = get_user_by('email', $email);
        if ($user) {
            update_user_meta($user->ID, 'edel_admin_note', $note);
        } else {
            // ゲストの場合はオプションテーブルに保存 (キー: edel_guest_note_{MD5ハッシュ})
            $key = 'edel_guest_note_' . md5($email);
            update_option($key, $note);
        }

        $redirect_url = admin_url('admin.php?page=edel-booking-pro-customers&action=view&email=' . urlencode($email) . '&msg=' . urlencode('メモを保存しました。'));
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

    /**
     * 顧客一覧表示
     */
    private function render_list() {
        global $wpdb;
        $table = $wpdb->prefix . 'edel_booking_appointments';

        // キャンセル以外のステータスをカウントする
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
            <h1 class="wp-heading-inline">顧客管理</h1>
            <hr class="wp-header-end">

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>氏名</th>
                        <th>メールアドレス</th>
                        <th>電話番号</th>
                        <th>来店回数</th>
                        <th>最終来店日</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($customers): ?>
                        <?php foreach ($customers as $c): ?>
                            <tr>
                                <td><strong><?php echo esc_html($c->display_name); ?></strong></td>
                                <td><?php echo esc_html($c->customer_email); ?></td>
                                <td><?php echo esc_html($c->phone); ?></td>
                                <td><?php echo esc_html($c->visit_count); ?>回</td>
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
                                    <a href="<?php echo admin_url('admin.php?page=edel-booking-pro-customers&action=view&email=' . urlencode($c->customer_email)); ?>" class="button button-small">詳細・メモ</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">データがありません。</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php
    }

    /**
     * 顧客詳細・履歴・メモ表示
     * ★修正: ヘッダーの件数表示を「有効な来店回数」に変更
     */
    private function render_detail($email) {
        global $wpdb;
        $table_appt = $wpdb->prefix . 'edel_booking_appointments';
        $table_service = $wpdb->prefix . 'edel_booking_services';

        // 最新の情報取得
        $latest = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_appt WHERE customer_email = %s ORDER BY start_datetime DESC LIMIT 1",
            $email
        ));

        if (!$latest) {
            echo '<div class="wrap"><p>データが見つかりません。</p></div>';
            return;
        }

        // 履歴取得
        $history = $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, s.title as service_name
             FROM $table_appt a
             LEFT JOIN $table_service s ON a.service_id = s.id
             WHERE a.customer_email = %s
             ORDER BY a.start_datetime DESC",
            $email
        ));

        // ★追加: キャンセルを除外した「有効来店回数」を計算
        $valid_visit_count = 0;
        foreach ($history as $h) {
            if ($h->status !== 'cancelled') {
                $valid_visit_count++;
            }
        }

        // メモの取得
        $admin_note = '';
        $user = get_user_by('email', $email);
        if ($user) {
            $admin_note = get_user_meta($user->ID, 'edel_admin_note', true);
            $user_type_label = '<span class="edel-badge-user">会員 (WordPressユーザー)</span>';
        } else {
            $key = 'edel_guest_note_' . md5($email);
            $admin_note = get_option($key, '');
            $user_type_label = '<span class="edel-badge-guest">ゲスト</span>';
        }
    ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">顧客詳細: <?php echo esc_html($latest->customer_name); ?> 様</h1>
            <a href="<?php echo admin_url('admin.php?page=edel-booking-pro-customers'); ?>" class="page-title-action">一覧に戻る</a>
            <hr class="wp-header-end">

            <div class="edel-customer-grid">
                <div class="edel-col-left">
                    <div class="edel-box">
                        <h3>基本情報</h3>
                        <table class="form-table" style="margin-top:0;">
                            <tr>
                                <th>区分</th>
                                <td><?php echo $user_type_label; ?></td>
                            </tr>
                            <tr>
                                <th>メール</th>
                                <td><?php echo esc_html($email); ?></td>
                            </tr>
                            <tr>
                                <th>電話番号</th>
                                <td><?php echo esc_html($latest->customer_phone); ?></td>
                            </tr>
                            <tr>
                                <th>初回来店</th>
                                <td>
                                    <?php
                                    // 履歴の最後（一番古いもの）を表示
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
                        <h3>管理者用メモ (内部共有)</h3>
                        <form method="post" action="">
                            <?php wp_nonce_field('edel_save_customer_note'); ?>
                            <input type="hidden" name="edel_customer_action" value="save_note">
                            <input type="hidden" name="customer_email" value="<?php echo esc_attr($email); ?>">

                            <textarea name="admin_note" rows="8" class="large-text" placeholder="お客様の好みや特記事項などを入力してください..."><?php echo esc_textarea($admin_note); ?></textarea>
                            <p class="description">※このメモはお客様には公開されません。</p>
                            <?php submit_button('メモを保存', 'primary', 'submit', true, array('style' => 'margin-top:10px;')); ?>
                        </form>
                    </div>
                </div>

                <div class="edel-col-right">
                    <div class="edel-box">
                        <h3>来店履歴 (有効来店数: <?php echo $valid_visit_count; ?>回)</h3>

                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th>日時</th>
                                    <th>メニュー</th>
                                    <th>担当スタッフ</th>
                                    <th>ステータス</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($history as $h):
                                    $staff = get_userdata($h->staff_id);
                                    $status_label = ($h->status == 'confirmed') ? '確定' : (($h->status == 'cancelled') ? 'キャンセル' : $h->status);

                                    // ステータスに応じたスタイル
                                    $status_style = '';
                                    if ($h->status == 'cancelled') $status_style = 'color:#a00; font-weight:bold;';
                                    if ($h->status == 'completed') $status_style = 'color:#27ae60; font-weight:bold;';

                                    // キャンセル行は薄く表示（背景色調整）
                                    $row_style = ($h->status == 'cancelled') ? 'background-color:#f9f9f9; color:#999;' : '';
                                ?>
                                    <tr style="<?php echo $row_style; ?>">
                                        <td><?php echo date('Y/m/d H:i', strtotime($h->start_datetime)); ?></td>
                                        <td><?php echo esc_html($h->service_name); ?></td>
                                        <td><?php echo esc_html($staff ? $staff->display_name : '不明'); ?></td>
                                        <td style="<?php echo $status_style; ?>"><?php echo $status_label; ?></td>
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
