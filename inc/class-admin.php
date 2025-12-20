<?php

class EdelBookingProAdmin {

    public function __construct() {
        // コンストラクタ (メニュー登録は class-admin-menu.php に委譲)
    }

    public function plugin_action_links($links) {
        $settings_link = '<a href="admin.php?page=edel-booking-settings">設定</a>';
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

        // --- 保存処理 (既存スタッフの更新) ---
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
            echo '<div class="notice notice-success is-dismissible"><p>スタッフ設定を保存しました。</p></div>';
        }

        // --- 新規追加処理 ---
        if (isset($_POST['add_new_staff']) && check_admin_referer('edel_add_staff_action')) {
            $new_user_id = intval($_POST['new_staff_user_id']);
            if ($new_user_id > 0) {
                update_user_meta($new_user_id, 'is_edel_staff', 1);
                echo '<div class="notice notice-success is-dismissible"><p>新しいスタッフを追加しました。右側のフォームで詳細を設定してください。</p></div>';
            }
        }

        // --- 削除処理 ---
        if (isset($_GET['action']) && $_GET['action'] == 'remove_staff' && isset($_GET['user_id'])) {
            $remove_id = intval($_GET['user_id']);
            delete_user_meta($remove_id, 'is_edel_staff');
            echo '<div class="notice notice-success is-dismissible"><p>スタッフリストから除外しました。（ユーザー自体は削除されません）</p></div>';
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
            <h1>スタッフ管理</h1>

            <div style="display:flex; gap:20px; align-items:flex-start;">
                <div style="flex:1; min-width:300px;">

                    <div style="background:#fff; padding:15px; border:1px solid #ccd0d4; margin-bottom:20px; border-left:4px solid #2271b1;">
                        <h3 style="margin-top:0;">スタッフを追加</h3>
                        <form method="post">
                            <?php wp_nonce_field('edel_add_staff_action'); ?>
                            <p style="margin-bottom:10px; font-size:0.9em;">
                                WordPressのユーザーを選択して追加してください。<br>
                                <a href="<?php echo admin_url('user-new.php'); ?>" target="_blank">ユーザーを新規作成する</a>
                            </p>
                            <div style="display:flex; gap:5px;">
                                <select name="new_staff_user_id" style="width:100%;">
                                    <option value="">-- ユーザーを選択 --</option>
                                    <?php foreach ($all_users as $candidate): ?>
                                        <option value="<?php echo $candidate->ID; ?>">
                                            <?php echo esc_html($candidate->display_name); ?> (<?php echo esc_html($candidate->user_email); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" name="add_new_staff" class="button button-primary">追加</button>
                            </div>
                        </form>
                    </div>

                    <table class="widefat fixed striped">
                        <thead>
                            <tr>
                                <th>名前 (メール)</th>
                                <th style="width:100px;">操作</th>
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
                                            <a href="?page=edel-booking-staff&edit_user=<?php echo $u->ID; ?>" class="button button-small">設定</a>
                                            <a href="?page=edel-booking-staff&action=remove_staff&user_id=<?php echo $u->ID; ?>" class="button button-small button-link-delete" onclick="return confirm('スタッフ一覧から外しますか？');" style="color:#a00;">除外</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2">スタッフが登録されていません。</td>
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
                        <h2 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">設定: <?php echo esc_html($target_user->display_name); ?></h2>
                        <form method="post">
                            <?php wp_nonce_field('edel_save_staff'); ?>
                            <input type="hidden" name="user_id" value="<?php echo $target_user->ID; ?>">

                            <h3>1. 週間シフト設定</h3>
                            <table class="widefat" style="margin-bottom:20px;">
                                <thead>
                                    <tr>
                                        <th>曜日</th>
                                        <th>開始</th>
                                        <th>終了</th>
                                        <th>休み</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $weekdays = ['mon' => '月', 'tue' => '火', 'wed' => '水', 'thu' => '木', 'fri' => '金', 'sat' => '土', 'sun' => '日'];
                                    foreach ($weekdays as $k => $label):
                                        $s = isset($schedule[$k]['start']) ? $schedule[$k]['start'] : '10:00';
                                        $e = isset($schedule[$k]['end']) ? $schedule[$k]['end'] : '19:00';
                                        $off = isset($schedule[$k]['off']) && $schedule[$k]['off'] ? 'checked' : '';
                                    ?>
                                        <tr>
                                            <td><?php echo $label; ?></td>
                                            <td><input type="time" name="schedule[<?php echo $k; ?>][start]" value="<?php echo $s; ?>"></td>
                                            <td><input type="time" name="schedule[<?php echo $k; ?>][end]" value="<?php echo $e; ?>"></td>
                                            <td><input type="checkbox" name="schedule[<?php echo $k; ?>][off]" value="1" <?php echo $off; ?>></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <h3>2. 担当メニューと指名料</h3>
                            <p class="description" style="margin-bottom:10px;">このスタッフが担当できるメニューにチェックを入れてください。</p>
                            <table class="widefat striped">
                                <thead>
                                    <tr>
                                        <th style="width:30px;"><input type="checkbox" disabled></th>
                                        <th>メニュー名</th>
                                        <th>カスタム料金 (空欄なら基本料金)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_services as $srv):
                                        $checked = in_array($srv->id, $my_services) ? 'checked' : '';
                                        $price_val = isset($my_custom_prices[$srv->id]) ? $my_custom_prices[$srv->id] : '';
                                    ?>
                                        <tr>
                                            <td><input type="checkbox" name="services[]" value="<?php echo $srv->id; ?>" <?php echo $checked; ?>></td>
                                            <td><strong><?php echo esc_html($srv->title); ?></strong> (基本: ¥<?php echo number_format($srv->price); ?>)</td>
                                            <td>¥ <input type="number" name="custom_price[<?php echo $srv->id; ?>]" value="<?php echo esc_attr($price_val); ?>" placeholder="<?php echo $srv->price; ?>" style="width:100px;"></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <p class="submit">
                                <input type="submit" name="save_staff" class="button button-primary button-large" value="設定を保存する">
                            </p>
                        </form>
                    <?php else: ?>
                        <div style="text-align:center; padding:40px; color:#666;">
                            <span class="dashicons dashicons-admin-users" style="font-size:40px; width:40px; height:40px; color:#ccc;"></span><br>
                            <p>左側のリストから「設定」ボタンを押して<br>スタッフの詳細設定を行ってください。</p>
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
                echo '<div class="notice notice-success is-dismissible"><p>更新しました。</p></div>';
            } else {
                $wpdb->insert($table, $data);
                echo '<div class="notice notice-success is-dismissible"><p>追加しました。</p></div>';
            }
        }

        if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
            $wpdb->delete($table, array('id' => intval($_GET['id'])));
            echo '<div class="notice notice-success is-dismissible"><p>削除しました。</p></div>';
        }

        $edit_data = null;
        if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
            $edit_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", intval($_GET['id'])));
        }
        $services = $wpdb->get_results("SELECT * FROM $table ORDER BY id ASC");
    ?>
        <div class="wrap">
            <h1>メニュー管理</h1>

            <div style="display:flex; gap:20px; align-items:flex-start;">
                <div style="flex:1; background:#fff; padding:20px; border:1px solid #ccc;">
                    <h2><?php echo $edit_data ? 'メニュー編集' : '新規追加'; ?></h2>
                    <form method="post" action="admin.php?page=edel-booking-services">
                        <?php wp_nonce_field('edel_save_service'); ?>
                        <?php if ($edit_data): ?><input type="hidden" name="service_id" value="<?php echo $edit_data->id; ?>"><?php endif; ?>

                        <p>
                            <label>メニュー名<br><input type="text" name="title" class="large-text" required value="<?php echo $edit_data ? esc_attr($edit_data->title) : ''; ?>"></label>
                        </p>
                        <div style="display:flex; gap:10px;">
                            <p style="flex:1;">
                                <label>所要時間 (分)<br><input type="number" name="duration" style="width:100%;" required value="<?php echo $edit_data ? $edit_data->duration : 60; ?>"></label>
                            </p>
                            <p style="flex:1;">
                                <label>料金 (円)<br><input type="number" name="price" style="width:100%;" required value="<?php echo $edit_data ? $edit_data->price : 0; ?>"></label>
                            </p>
                        </div>
                        <div style="display:flex; gap:10px;">
                            <p style="flex:1;">
                                <label>準備時間 (前) (分)<br><input type="number" name="buffer_before" style="width:100%;" value="<?php echo $edit_data ? $edit_data->buffer_before : 0; ?>"></label>
                            </p>
                            <p style="flex:1;">
                                <label>片付け時間 (後) (分)<br><input type="number" name="buffer_after" style="width:100%;" value="<?php echo $edit_data ? $edit_data->buffer_after : 0; ?>"></label>
                            </p>
                        </div>
                        <p>
                            <label>説明<br><textarea name="description" class="large-text" rows="3"><?php echo $edit_data ? esc_textarea($edit_data->description) : ''; ?></textarea></label>
                        </p>
                        <p>
                            <label><input type="checkbox" name="is_active" value="1" <?php checked($edit_data ? $edit_data->is_active : 1); ?>> 予約受付を有効にする</label>
                        </p>
                        <p class="submit">
                            <input type="submit" name="save_service" class="button button-primary" value="<?php echo $edit_data ? '更新' : '追加'; ?>">
                            <?php if ($edit_data): ?><a href="admin.php?page=edel-booking-services" class="button">キャンセル</a><?php endif; ?>
                        </p>
                    </form>
                </div>

                <div style="flex:2;">
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>メニュー名</th>
                                <th>時間</th>
                                <th>料金</th>
                                <th>前後バッファ</th>
                                <th>状態</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($services as $s): ?>
                                <tr>
                                    <td><?php echo $s->id; ?></td>
                                    <td><strong><?php echo esc_html($s->title); ?></strong></td>
                                    <td><?php echo $s->duration; ?>分</td>
                                    <td>¥<?php echo number_format($s->price); ?></td>
                                    <td><?php echo $s->buffer_before; ?> / <?php echo $s->buffer_after; ?></td>
                                    <td><?php echo $s->is_active ? '有効' : '<span style="color:red;">無効</span>'; ?></td>
                                    <td>
                                        <a href="admin.php?page=edel-booking-services&action=edit&id=<?php echo $s->id; ?>" class="button button-small">編集</a>
                                        <a href="admin.php?page=edel-booking-services&action=delete&id=<?php echo $s->id; ?>" class="button button-small button-link-delete" onclick="return confirm('削除しますか？');">削除</a>
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
     * ★修正: スタッフごとの絞り込み機能を追加
     * ==================================================
     */
    public function render_schedule_exceptions_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'edel_booking_schedule_exceptions';
        $staffs = get_users(array('meta_key' => 'is_edel_staff', 'meta_value' => 1));

        // 表示用フィルタリングパラメータ
        $view_staff_id = isset($_GET['view_staff_id']) ? intval($_GET['view_staff_id']) : 0;

        // 保存処理
        if (isset($_POST['save_exception']) && check_admin_referer('edel_save_exception')) {
            $wpdb->insert($table, array(
                'staff_id' => intval($_POST['staff_id']),
                'exception_date' => sanitize_text_field($_POST['date']),
                'is_day_off' => isset($_POST['is_day_off']) ? 1 : 0,
                'start_time' => sanitize_text_field($_POST['start_time']),
                'end_time' => sanitize_text_field($_POST['end_time']),
                'reason' => sanitize_text_field($_POST['reason'])
            ));
            echo '<div class="notice notice-success is-dismissible"><p>例外スケジュールを追加しました。</p></div>';
        }

        // 削除処理
        if (isset($_GET['delete_id'])) {
            $wpdb->delete($table, array('id' => intval($_GET['delete_id'])));
            echo '<div class="notice notice-success is-dismissible"><p>削除しました。</p></div>';
        }

        // 一覧取得 (絞り込み対応)
        $sql = "SELECT * FROM $table";
        if ($view_staff_id > 0) {
            $sql .= $wpdb->prepare(" WHERE staff_id = %d", $view_staff_id);
        }
        $sql .= " ORDER BY exception_date DESC LIMIT 50";

        $rows = $wpdb->get_results($sql);
    ?>
        <div class="wrap">
            <h1>スケジュール例外設定</h1>
            <p>特定の日だけ休みにしたり、営業時間を変更したりする場合に設定します。</p>

            <div style="display:flex; gap:20px; align-items:flex-start;">

                <div style="flex:1; background:#fff; padding:20px; border:1px solid #ccc;">
                    <h2>新規登録</h2>
                    <form method="post">
                        <?php wp_nonce_field('edel_save_exception'); ?>

                        <p>
                            <label>対象スタッフ<br>
                                <select name="staff_id" style="width:100%;">
                                    <?php foreach ($staffs as $st): ?>
                                        <option value="<?php echo $st->ID; ?>" <?php selected($view_staff_id, $st->ID); ?>><?php echo esc_html($st->display_name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </p>
                        <p>
                            <label>日付<br><input type="date" name="date" required style="width:100%;"></label>
                        </p>
                        <p>
                            <label><input type="checkbox" name="is_day_off" value="1" id="edel_is_off_check" onclick="toggleTimeInputs()"> この日は休みにする</label>
                        </p>
                        <div id="edel_time_inputs">
                            <div style="display:flex; gap:10px;">
                                <p style="flex:1;"><label>開始時間<br><input type="time" name="start_time" style="width:100%;"></label></p>
                                <p style="flex:1;"><label>終了時間<br><input type="time" name="end_time" style="width:100%;"></label></p>
                            </div>
                        </div>
                        <p>
                            <label>理由 (メモ)<br><input type="text" name="reason" class="large-text"></label>
                        </p>
                        <p class="submit"><input type="submit" name="save_exception" class="button button-primary" value="追加"></p>
                    </form>
                </div>

                <div style="flex:2;">

                    <div style="margin-bottom: 15px; text-align: right; background: #f9f9f9; padding: 10px; border: 1px solid #ddd;">
                        <form method="get">
                            <input type="hidden" name="page" value="edel-booking-exceptions">
                            <label style="font-weight:bold;">表示するスタッフ:
                                <select name="view_staff_id" onchange="this.form.submit()">
                                    <option value="0">全員を表示</option>
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
                                <th>日付</th>
                                <th>スタッフ</th>
                                <th>内容</th>
                                <th>理由</th>
                                <th>操作</th>
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
                                                <span style="color:red; font-weight:bold;">休み</span>
                                            <?php else: ?>
                                                <?php echo substr($r->start_time, 0, 5) . ' - ' . substr($r->end_time, 0, 5); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo esc_html($r->reason); ?></td>
                                        <td><a href="admin.php?page=edel-booking-exceptions&delete_id=<?php echo $r->id; ?>&view_staff_id=<?php echo $view_staff_id; ?>" class="button button-small" onclick="return confirm('削除しますか？')">削除</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5">例外スケジュールは見つかりませんでした。</td>
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
