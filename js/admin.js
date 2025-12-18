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

        // カレンダー表示時にサイズを再計算 (FullCalendarの表示崩れ防止)
        if (view === 'calendar' && calendar) {
            setTimeout(function () {
                calendar.updateSize();
            }, 200);
        }
    });

    // --- FullCalendar 初期化 ---
    var calendarEl = document.getElementById('edel-admin-calendar');
    var calendar;

    if (calendarEl) {
        calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            locale: 'ja',
            height: 'auto',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,listMonth'
            },
            buttonText: {
                today: '今日',
                month: '月',
                week: '週',
                day: '日',
                list: 'リスト'
            },
            navLinks: true,
            editable: false, // 管理画面でもドラッグ移動は誤操作の元なので無効化

            // イベントデータの取得 (Ajax)
            events: {
                url: edel_admin.ajaxurl,
                method: 'GET',
                extraParams: {
                    action: 'edel_fetch_events',
                    nonce: edel_admin.nonce
                },
                failure: function () {
                    alert('予約データの取得に失敗しました。');
                }
            },

            // イベントクリック時の簡易詳細表示
            eventClick: function (info) {
                var props = info.event.extendedProps;
                var msg = '【予約詳細】\n\n';
                msg += '日時: ' + info.event.start.toLocaleString() + '\n';
                if (info.event.end) {
                    msg += '終了: ' + info.event.end.toLocaleString() + '\n';
                }
                msg += '内容: ' + info.event.title + '\n';
                msg += 'メール: ' + (props.email || '-') + '\n';
                msg += '電話: ' + (props.phone || '-') + '\n';
                msg += 'ステータス: ' + (props.status || '-');

                alert(msg);
            }
        });

        calendar.render();
    }
});
