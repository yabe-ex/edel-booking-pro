<?php

class EdelBookingProMyPage {

    public function __construct() {
        add_shortcode('edel_mypage', array($this, 'render_mypage'));
    }

    public function render_mypage() {
        if (!is_user_logged_in()) {
            return $this->render_login_form();
        }

        // --- ログイン後のマイページ ---
        $user_id = get_current_user_id();
        $user = wp_get_current_user();
        $email = $user->user_email;

        global $wpdb;
        $table_appt = $wpdb->prefix . 'edel_booking_appointments';
        $table_service = $wpdb->prefix . 'edel_booking_services';

        $settings = get_option('edel_booking_settings', array());
        $cancel_limit = isset($settings['cancel_limit']) ? intval($settings['cancel_limit']) : 1;

        $hide_service = isset($settings['hide_service']) ? (bool)$settings['hide_service'] : false;
        $hide_staff   = isset($settings['hide_staff']) ? (bool)$settings['hide_staff'] : false;
        $label_service = !empty($settings['label_service']) ? $settings['label_service'] : __('Menu', 'edel-booking');
        $label_staff   = !empty($settings['label_staff']) ? $settings['label_staff'] : __('Staff', 'edel-booking');

        $appointments = $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, s.title as service_name
             FROM $table_appt a
             LEFT JOIN $table_service s ON a.service_id = s.id
             WHERE (a.customer_email = %s OR a.customer_id = %d)
             ORDER BY a.start_datetime DESC",
            $email,
            $user_id
        ));

        $upcoming = array();
        $past = array();
        $now = current_time('timestamp');

        foreach ($appointments as $app) {
            $app_ts = strtotime($app->start_datetime);
            if ($app->status == 'cancelled' || $app_ts < $now) {
                $past[] = $app;
            } else {
                $upcoming[] = $app;
            }
        }

        usort($upcoming, function ($a, $b) {
            return strtotime($a->start_datetime) - strtotime($b->start_datetime);
        });

        ob_start();
?>
        <div class="edel-mypage-container">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h2 style="margin:0;"><?php esc_html_e('My Page', 'edel-booking'); ?></h2>
                <a href="<?php echo wp_logout_url(get_permalink()); ?>" class="edel-btn-small"><?php esc_html_e('Logout', 'edel-booking'); ?></a>
            </div>

            <div class="edel-my-tabs">
                <div class="edel-my-tab active" onclick="switchTab('upcoming', this)"><?php esc_html_e('Upcoming Bookings', 'edel-booking'); ?></div>
                <div class="edel-my-tab" onclick="switchTab('past', this)"><?php esc_html_e('History / Cancelled', 'edel-booking'); ?></div>
                <div class="edel-my-tab" onclick="switchTab('account', this)"><?php esc_html_e('Account Settings', 'edel-booking'); ?></div>
            </div>

            <div id="edel-tab-upcoming" class="edel-tab-content">
                <?php if ($upcoming): ?>
                    <?php foreach ($upcoming as $app):
                        $staff = get_userdata($app->staff_id);
                        $cancel_deadline = strtotime($app->start_datetime) - ($cancel_limit * 3600);
                        $can_cancel = ($now <= $cancel_deadline);

                        $date_str = date('Y-m-d H:i', strtotime($app->start_datetime));
                        if ($app->end_datetime) $date_str .= ' - ' . date('H:i', strtotime($app->end_datetime));
                    ?>
                        <div class="edel-booking-card">
                            <div class="edel-card-header">
                                <div class="edel-card-date"><?php echo esc_html($date_str); ?></div>
                                <span class="edel-card-status"><?php esc_html_e('Confirmed', 'edel-booking'); ?></span>
                            </div>
                            <div class="edel-card-body">
                                <?php if (!$hide_service): ?><p><strong><?php echo esc_html($label_service); ?>:</strong> <?php echo esc_html($app->service_name); ?></p><?php endif; ?>
                                <?php if (!$hide_staff): ?><p><strong><?php echo esc_html($label_staff); ?>:</strong> <?php echo esc_html($staff ? $staff->display_name : __('Unknown', 'edel-booking')); ?></p><?php endif; ?>
                                <?php if (!empty($app->note)): ?><p><strong><?php esc_html_e('Note:', 'edel-booking'); ?></strong> <?php echo nl2br(esc_html($app->note)); ?></p><?php endif; ?>
                            </div>
                            <div class="edel-card-actions">
                                <?php if ($can_cancel): ?>
                                    <button class="edel-cancel-btn" onclick="cancelBooking(<?php echo $app->id; ?>)"><?php esc_html_e('Cancel Booking', 'edel-booking'); ?></button>
                                <?php else: ?>
                                    <span style="font-size:0.9em; color:#999;"><?php esc_html_e('Cancellation deadline passed', 'edel-booking'); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="edel-no-data"><?php esc_html_e('No upcoming bookings.', 'edel-booking'); ?></p>
                <?php endif; ?>
            </div>

            <div id="edel-tab-past" class="edel-tab-content" style="display:none;">
                <?php if ($past): ?>
                    <?php foreach ($past as $app):
                        $staff = get_userdata($app->staff_id);
                        $is_cancelled = ($app->status == 'cancelled');
                        $status_label = $is_cancelled ? __('Cancelled', 'edel-booking') : __('Completed', 'edel-booking');
                        $class_mod = $is_cancelled ? 'cancelled' : 'past';

                        $date_str = date('Y-m-d H:i', strtotime($app->start_datetime));
                        if ($app->end_datetime) $date_str .= ' - ' . date('H:i', strtotime($app->end_datetime));
                    ?>
                        <div class="edel-booking-card <?php echo $class_mod; ?>">
                            <div class="edel-card-header">
                                <div class="edel-card-date"><?php echo esc_html($date_str); ?></div>
                                <span class="edel-card-status"><?php echo esc_html($status_label); ?></span>
                            </div>
                            <div class="edel-card-body">
                                <?php if (!$hide_service): ?><p><strong><?php echo esc_html($label_service); ?>:</strong> <?php echo esc_html($app->service_name); ?></p><?php endif; ?>
                                <?php if (!$hide_staff): ?><p><strong><?php echo esc_html($label_staff); ?>:</strong> <?php echo esc_html($staff ? $staff->display_name : __('Unknown', 'edel-booking')); ?></p><?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="edel-no-data"><?php esc_html_e('No history found.', 'edel-booking'); ?></p>
                <?php endif; ?>
            </div>

            <div id="edel-tab-account" class="edel-tab-content" style="display:none;">
                <div class="edel-form-box">
                    <h3><?php esc_html_e('Change Password', 'edel-booking'); ?></h3>
                    <p style="font-size:0.9em; margin-bottom:15px; color:#666;"><?php esc_html_e('Enter your new password.', 'edel-booking'); ?></p>
                    <form id="edel-change-password-form">
                        <div class="edel-form-group">
                            <label><?php esc_html_e('New Password', 'edel-booking'); ?></label>
                            <input type="password" name="new_pass" class="edel-input" required>
                        </div>
                        <div class="edel-form-group">
                            <label><?php esc_html_e('New Password (Confirm)', 'edel-booking'); ?></label>
                            <input type="password" name="confirm_pass" class="edel-input" required>
                        </div>
                        <button type="submit" class="edel-btn edel-btn-primary"><?php esc_html_e('Update Password', 'edel-booking'); ?></button>
                    </form>
                </div>
            </div>
        </div>

        <script>
            function switchTab(tabName, element) {
                var contents = document.getElementsByClassName('edel-tab-content');
                for (var i = 0; i < contents.length; i++) {
                    contents[i].style.display = 'none';
                }

                var tabs = document.getElementsByClassName('edel-my-tab');
                for (var i = 0; i < tabs.length; i++) {
                    tabs[i].classList.remove('active');
                }

                document.getElementById('edel-tab-' + tabName).style.display = 'block';
                element.classList.add('active');
            }

            function cancelBooking(bookingId) {
                if (!confirm('<?php echo esc_js(__('Are you sure you want to cancel this booking?', 'edel-booking')); ?>')) return;
                jQuery.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'edel_cancel_booking',
                        nonce: '<?php echo wp_create_nonce('edel-booking-pro'); ?>',
                        booking_id: bookingId
                    },
                    success: function(res) {
                        if (res.success) {
                            alert(res.data);
                            location.reload();
                        } else {
                            alert(res.data);
                        }
                    }
                });
            }

            jQuery('#edel-change-password-form').on('submit', function(e) {
                e.preventDefault();
                var btn = jQuery(this).find('button');
                btn.prop('disabled', true).text('<?php echo esc_js(__('Updating...', 'edel-booking')); ?>');

                jQuery.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: jQuery(this).serialize() + '&action=edel_mypage_change_password&nonce=<?php echo wp_create_nonce('edel-booking-pro'); ?>',
                    success: function(res) {
                        if (res.success) {
                            alert(res.data);
                            jQuery('#edel-change-password-form')[0].reset();
                        } else {
                            alert(res.data);
                        }
                        btn.prop('disabled', false).text('<?php echo esc_js(__('Update Password', 'edel-booking')); ?>');
                    },
                    error: function() {
                        alert('<?php echo esc_js(__('Communication Error', 'edel-booking')); ?>');
                        btn.prop('disabled', false).text('<?php echo esc_js(__('Update Password', 'edel-booking')); ?>');
                    }
                });
            });
        </script>
    <?php
        return ob_get_clean();
    }

    private function render_login_form() {
        ob_start();
    ?>
        <div class="edel-mypage-container edel-login-container">
            <h2 style="text-align:center; margin-bottom:30px;"><?php esc_html_e('Login', 'edel-booking'); ?></h2>

            <div id="edel-login-view">
                <form id="edel-login-form">
                    <div class="edel-form-group">
                        <label><?php esc_html_e('Email', 'edel-booking'); ?></label>
                        <input type="email" name="email" class="edel-input" required>
                    </div>
                    <div class="edel-form-group">
                        <label><?php esc_html_e('Password', 'edel-booking'); ?></label>
                        <input type="password" name="password" class="edel-input" required>
                    </div>
                    <button type="submit" class="edel-btn edel-btn-primary"><?php esc_html_e('Login', 'edel-booking'); ?></button>

                    <div style="margin-top:20px; text-align:center;">
                        <a href="#" onclick="showLostPassword(event)" style="color:#666; font-size:0.9em;"><?php esc_html_e('Forgot Password?', 'edel-booking'); ?></a>
                    </div>
                </form>
            </div>

            <div id="edel-lost-password-view" style="display:none;">
                <p style="font-size:0.95em; color:#555;"><?php esc_html_e('Enter your email address. We will send you a link to reset your password.', 'edel-booking'); ?></p>
                <form id="edel-lost-password-form">
                    <div class="edel-form-group">
                        <label><?php esc_html_e('Email', 'edel-booking'); ?></label>
                        <input type="email" name="email" class="edel-input" required>
                    </div>
                    <button type="submit" class="edel-btn edel-btn-secondary"><?php esc_html_e('Send Reset Link', 'edel-booking'); ?></button>

                    <div style="margin-top:20px; text-align:center;">
                        <a href="#" onclick="showLogin(event)" style="color:#666; font-size:0.9em;"><?php esc_html_e('Back to Login', 'edel-booking'); ?></a>
                    </div>
                </form>
            </div>
        </div>

        <script>
            function showLostPassword(e) {
                e.preventDefault();
                jQuery('#edel-login-view').fadeOut(200, function() {
                    jQuery('#edel-lost-password-view').fadeIn(200);
                });
            }

            function showLogin(e) {
                e.preventDefault();
                jQuery('#edel-lost-password-view').fadeOut(200, function() {
                    jQuery('#edel-login-view').fadeIn(200);
                });
            }

            jQuery('#edel-login-form').on('submit', function(e) {
                e.preventDefault();
                var btn = jQuery(this).find('button');
                btn.prop('disabled', true).text('<?php echo esc_js(__('Authenticating...', 'edel-booking')); ?>');

                jQuery.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: jQuery(this).serialize() + '&action=edel_mypage_login&nonce=<?php echo wp_create_nonce('edel-booking-pro'); ?>',
                    success: function(res) {
                        if (res.success) {
                            location.reload();
                        } else {
                            alert(res.data);
                            btn.prop('disabled', false).text('<?php echo esc_js(__('Login', 'edel-booking')); ?>');
                        }
                    },
                    error: function() {
                        alert('<?php echo esc_js(__('Communication Error', 'edel-booking')); ?>');
                        btn.prop('disabled', false).text('<?php echo esc_js(__('Login', 'edel-booking')); ?>');
                    }
                });
            });

            jQuery('#edel-lost-password-form').on('submit', function(e) {
                e.preventDefault();
                var btn = jQuery(this).find('button');
                btn.prop('disabled', true).text('<?php echo esc_js(__('Sending...', 'edel-booking')); ?>');

                jQuery.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: jQuery(this).serialize() + '&action=edel_mypage_lost_password&nonce=<?php echo wp_create_nonce('edel-booking-pro'); ?>',
                    success: function(res) {
                        alert(res.data);
                        if (res.success) {
                            jQuery('#edel-lost-password-form')[0].reset();
                            showLogin(e);
                        }
                        btn.prop('disabled', false).text('<?php echo esc_js(__('Send Reset Link', 'edel-booking')); ?>');
                    }
                });
            });
        </script>
<?php
        return ob_get_clean();
    }
}
