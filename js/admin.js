jQuery(document).ready(function ($) {
    // --- カレンダー/リスト切り替えロジック ---
    $('.edel-switch-btn').on('click', function () {
        var view = $(this).data('view');

        // ボタンのアクティブ化
        $('.edel-switch-btn').removeClass('active');
        $(this).addClass('active');

        // コンテンツの切り替え (フェードイン)
        $('.edel-view-section').hide();
        $('#edel-view-' + view).fadeIn(200);

        // カレンダー表示時にサイズを再計算
        if (view === 'calendar' && calendar) {
            setTimeout(function () {
                calendar.updateSize();
            }, 200);
        }
    });

    // --- FullCalendar 初期化 ---
    var calendarEl = document.getElementById('edel-admin-calendar');
    var calendar;

    // 翻訳データの取得
    var l10n = edel_admin.l10n || {};

    if (calendarEl) {
        calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            locale: l10n.locale_code || 'ja',
            height: 'auto',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,listMonth'
            },
            buttonText: {
                today: l10n.button_today || '今日',
                month: l10n.button_month || '月',
                week: l10n.button_week || '週',
                day: l10n.button_day || '日',
                list: l10n.button_list || 'リスト'
            },
            navLinks: true,
            editable: false,

            // イベントデータの取得 (Ajax)
            events: {
                url: edel_admin.ajaxurl,
                method: 'GET',
                extraParams: {
                    action: 'edel_fetch_events',
                    nonce: edel_admin.nonce,
                    staff_id: edel_admin.staff_id
                },
                failure: function () {
                    alert(l10n.error_fetch || 'Failed to fetch bookings.');
                }
            },

            // イベントクリック時の簡易詳細表示
            eventClick: function (info) {
                // 背景イベント(空き時間表示)の場合はクリックしても何もしない
                if (info.event.display === 'background') {
                    return;
                }

                var props = info.event.extendedProps;
                var msg = (l10n.detail_title || '【Details】') + '\n\n';
                msg += (l10n.date || 'Date: ') + info.event.start.toLocaleString() + '\n';
                if (info.event.end) {
                    msg += (l10n.end || 'End: ') + info.event.end.toLocaleString() + '\n';
                }
                msg += (l10n.content || 'Content: ') + info.event.title + '\n';
                msg += (l10n.email || 'Email: ') + (props.email || '-') + '\n';
                msg += (l10n.phone || 'Phone: ') + (props.phone || '-') + '\n';
                msg += (l10n.status || 'Status: ') + (props.status || '-');

                alert(msg);
            }
        });

        calendar.render();
    }
});
