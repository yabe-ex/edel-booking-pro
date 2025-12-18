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
            <h1 class="wp-heading-inline">予約カレンダー</h1>
            <button id="edel-add-booking-btn" class="page-title-action">新規予約作成</button>
            <hr class="wp-header-end">

            <div class="edel-calendar-controls">
                <select id="edel-calendar-staff-filter">
                    <option value="0">すべてのスタッフ</option>
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
                <h2 id="edel-modal-title">新規予約登録</h2>

                <form id="edel-booking-form">
                    <table class="form-table">
                        <tr>
                            <th>スタッフ</th>
                            <td>
                                <select name="staff_id" required>
                                    <?php foreach ($staff_users as $staff) : ?>
                                        <option value="<?php echo $staff->ID; ?>"><?php echo esc_html($staff->display_name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>メニュー</th>
                            <td>
                                <select name="service_id" required>
                                    <?php foreach ($services as $service) : ?>
                                        <option value="<?php echo $service->id; ?>">
                                            <?php echo esc_html($service->title); ?> (<?php echo $service->duration; ?>分)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>日時</th>
                            <td>
                                <input type="date" name="date" required value="<?php echo date('Y-m-d'); ?>">
                                <input type="time" name="time" required value="10:00">
                                <p class="description">※終了時間はメニューの所要時間から自動計算されます。</p>
                            </td>
                        </tr>
                        <tr>
                            <th>お客様名</th>
                            <td><input type="text" name="customer_name" class="regular-text" required placeholder="例: 山田 太郎"></td>
                        </tr>
                        <tr>
                            <th>メールアドレス</th>
                            <td><input type="email" name="customer_email" class="regular-text" placeholder="sample@example.com"></td>
                        </tr>
                        <tr>
                            <th>電話番号</th>
                            <td><input type="text" name="customer_phone" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th>備考</th>
                            <td><textarea name="note" class="large-text" rows="2"></textarea></td>
                        </tr>
                    </table>
                    <br>
                    <button type="submit" class="button button-primary">予約を保存</button>
                </form>
            </div>
        </div>
<?php
    }
}
