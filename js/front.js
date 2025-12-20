jQuery(document).ready(function ($) {
    var calendarEl = document.getElementById('edel-front-calendar');
    var calendar;

    // 翻訳データの取得
    var l10n = edel_front.l10n || {};

    if (calendarEl) {
        calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            locale: l10n.locale_code || 'ja',
            headerToolbar: { left: 'prev', center: 'title', right: 'next' },
            height: 'auto',
            selectable: false,

            events: function (info, successCallback, failureCallback) {
                var service = $('#edel-front-service').val();
                var staff = $('#edel-front-staff').val();
                if (!service || !staff) {
                    successCallback([]);
                    return;
                }

                $.ajax({
                    url: edel_front.ajaxurl,
                    type: 'GET',
                    data: {
                        action: 'edel_get_calendar_status',
                        nonce: edel_front.nonce,
                        service_id: service,
                        staff_id: staff,
                        start: info.startStr,
                        end: info.endStr
                    },
                    success: function (response) {
                        if (response.success) successCallback(response.data);
                        else failureCallback();
                    }
                });
            },

            eventContent: function (arg) {
                var props = arg.event.extendedProps;

                if (props.is_closed) {
                    return { domNodes: [] };
                }

                var container = document.createElement('div');

                if (edel_front.calendar_mode === 'symbol') {
                    container.className = 'edel-symbol-container';
                    var symbolClass = '';
                    var symbolChar = props.symbol || '×';

                    if (symbolChar === '◎') symbolClass = 'circle-double';
                    else if (symbolChar === '○') symbolClass = 'circle';
                    else if (symbolChar === '△') symbolClass = 'triangle';
                    else if (symbolChar === '×') symbolClass = 'cross';

                    container.innerHTML = '<span class="edel-mark ' + symbolClass + '">' + symbolChar + '</span>';
                } else {
                    container.className = 'edel-day-bars';
                    var amBar = document.createElement('div');
                    amBar.className = 'edel-bar edel-bar-am';
                    amBar.innerHTML = '<span class="edel-bar-label">AM</span>' + (props.am_bar || '');
                    var pmBar = document.createElement('div');
                    pmBar.className = 'edel-bar edel-bar-pm';
                    pmBar.innerHTML = '<span class="edel-bar-label">PM</span>' + (props.pm_bar || '');
                    container.appendChild(amBar);
                    container.appendChild(pmBar);
                }

                return { domNodes: [container] };
            },

            dateClick: function (info) {
                var events = calendar.getEvents();
                var clickedDate = info.dateStr;
                var isOpen = false;
                var isClosedDay = false;

                for (var i = 0; i < events.length; i++) {
                    if (events[i].startStr === clickedDate) {
                        if (events[i].extendedProps.is_closed) {
                            isClosedDay = true;
                        } else if (events[i].extendedProps.is_open) {
                            isOpen = true;
                        }
                        break;
                    }
                }

                if (isClosedDay) {
                    return;
                }

                if (isOpen) {
                    $('#edel-front-date').val(clickedDate);
                    goToStep2(clickedDate);
                } else {
                    alert(l10n.full_day || 'Sorry, fully booked.');
                }
            }
        });
        calendar.render();
    }

    var relations = edel_front.relations || {};
    var serviceToStaff = relations.service_to_staff || {};
    var staffToService = relations.staff_to_service || {};

    $('#edel-front-service').on('change', function () {
        var serviceId = $(this).val();
        var currentStaff = $('#edel-front-staff').val();
        if (serviceId && serviceToStaff[serviceId]) {
            var validStaffIds = serviceToStaff[serviceId];
            $('#edel-front-staff option').each(function () {
                var val = $(this).val();
                if (val === '') return;
                if (validStaffIds.includes(val)) {
                    $(this).prop('disabled', false).show();
                } else {
                    $(this).prop('disabled', true).hide();
                    if (val == currentStaff) $('#edel-front-staff').val('');
                }
            });
        } else if (serviceId === '') {
            $('#edel-front-staff option').prop('disabled', false).show();
        }
        checkSelectionAndRefresh();
    });

    $('#edel-front-staff').on('change', function () {
        var staffId = $(this).val();
        var currentService = $('#edel-front-service').val();
        if (staffId && staffToService[staffId]) {
            var validServiceIds = staffToService[staffId];
            $('#edel-front-service option').each(function () {
                var val = $(this).val();
                if (val === '') return;
                if (validServiceIds.includes(val)) {
                    $(this).prop('disabled', false).show();
                } else {
                    $(this).prop('disabled', true).hide();
                    if (val == currentService) $('#edel-front-service').val('');
                }
            });
        } else if (staffId === '') {
            $('#edel-front-service option').prop('disabled', false).show();
        }
        checkSelectionAndRefresh();
    });

    function checkSelectionAndRefresh() {
        var service = $('#edel-front-service').val();
        var staff = $('#edel-front-staff').val();
        if (service && staff) {
            $('#edel-calendar-overlay').fadeOut();
            calendar.refetchEvents();
        } else {
            $('#edel-calendar-overlay').fadeIn();
        }
    }

    if ($('#edel-front-service').val()) $('#edel-front-service').trigger('change');
    if ($('#edel-front-staff').val()) $('#edel-front-staff').trigger('change');

    function goToStep2(date) {
        var service = $('#edel-front-service').val();
        var staff = $('#edel-front-staff').val();
        $('#edel-step-1').hide();
        $('#edel-step-2').fadeIn();
        $('#edel-display-date').text(date);
        $('#edel-slots-container').html('<p class="edel-loading">' + (l10n.loading_slots || 'Loading...') + '</p>');
        var targetOffset = $('#edel-booking-app').offset().top - 50;
        $('html, body').animate({ scrollTop: targetOffset }, 400);

        $.ajax({
            url: edel_front.ajaxurl,
            type: 'POST',
            data: { action: 'edel_get_available_slots', nonce: edel_front.nonce, service_id: service, staff_id: staff, date: date },
            success: function (response) {
                if (response.success) renderSlots(response.data);
                else $('#edel-slots-container').html('<p class="edel-error">Error: ' + response.data + '</p>');
            },
            error: function () {
                $('#edel-slots-container').html('<p class="edel-error">' + (l10n.error_fetch || 'Error') + '</p>');
            }
        });
    }

    function renderSlots(slots) {
        var container = $('#edel-slots-container');
        container.empty();
        if (slots.length === 0) {
            container.html('<p>' + (l10n.no_slots || 'No slots available.') + '</p>');
            return;
        }
        var html = '<div class="edel-slots-grid">';
        $.each(slots, function (index, time) {
            html += '<button class="edel-time-slot" data-time="' + time + '">' + time + '</button>';
        });
        html += '</div>';
        container.html(html);

        $('.edel-time-slot').on('click', function () {
            var selectedTime = $(this).data('time');
            var selectedDate = $('#edel-front-date').val();
            var serviceId = $('#edel-front-service').val();
            var staffId = $('#edel-front-staff').val();

            $('#hidden-service-id').val(serviceId);
            $('#hidden-staff-id').val(staffId);
            $('#hidden-date').val(selectedDate);
            $('#hidden-time').val(selectedTime);

            var duration = 0;
            if (edel_front.durations && edel_front.durations[serviceId]) {
                duration = parseInt(edel_front.durations[serviceId]);
            }
            var endTimeStr = calculateEndTime(selectedTime, duration);
            var timeRange = selectedTime + ' - ' + endTimeStr;

            $('#edel-summary-service').text($('#edel-front-service option:selected').text());
            $('#edel-summary-staff').text($('#edel-front-staff option:selected').text());
            $('#edel-summary-date').text(selectedDate);
            $('#edel-summary-time').text(timeRange);

            if (edel_front.hide_service == 1) {
                $('#edel-row-service').hide();
            } else {
                $('#edel-row-service').show();
            }
            if (edel_front.hide_staff == 1) {
                $('#edel-row-staff').hide();
            } else {
                $('#edel-row-staff').show();
            }

            if (edel_front.show_price == 1) {
                var price = 0;
                if (edel_front.base_prices && edel_front.base_prices[serviceId]) {
                    price = edel_front.base_prices[serviceId];
                }
                if (edel_front.custom_prices && edel_front.custom_prices[staffId] && edel_front.custom_prices[staffId][serviceId]) {
                    price = edel_front.custom_prices[staffId][serviceId];
                }
                var formattedPrice = '¥' + price.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                $('#edel-summary-price').text(formattedPrice);
                $('#edel-summary-price-row').show();
            } else {
                $('#edel-summary-price-row').hide();
            }

            $('#edel-step-2').hide();
            $('#edel-step-3').fadeIn();
        });
    }

    function calculateEndTime(startTime, durationMinutes) {
        var parts = startTime.split(':');
        var hour = parseInt(parts[0]);
        var min = parseInt(parts[1]);
        var totalMin = hour * 60 + min + durationMinutes;
        var endHour = Math.floor(totalMin / 60);
        var endMin = totalMin % 60;
        if (endHour >= 24) endHour = endHour - 24;
        var endHourStr = endHour.toString().padStart(2, '0');
        var endMinStr = endMin.toString().padStart(2, '0');
        return endHourStr + ':' + endMinStr;
    }

    $('#edel-front-booking-form').on('submit', function (e) {
        e.preventDefault();
        if (!confirm(l10n.confirm_booking || 'Confirm?')) return;
        var submitBtn = $('#edel-btn-submit');
        submitBtn.prop('disabled', true).text(l10n.processing || 'Processing...');
        var formData = $(this).serializeArray();
        formData.push({ name: 'action', value: 'edel_submit_booking_front' });
        formData.push({ name: 'nonce', value: edel_front.nonce });
        $.ajax({
            url: edel_front.ajaxurl,
            type: 'POST',
            data: formData,
            success: function (response) {
                if (response.success) {
                    if (response.data.created_account && edel_front.mypage_url) {
                        $('#edel-btn-home').text('マイページへ').attr('href', edel_front.mypage_url);
                    }
                    $('#edel-step-3').hide();
                    $('#edel-step-4').fadeIn();
                } else {
                    alert('Error: ' + response.data);
                    submitBtn.prop('disabled', false).text(l10n.btn_confirm || 'Confirm');
                }
            },
            error: function () {
                alert(l10n.error_fetch || 'Communication Error');
                submitBtn.prop('disabled', false).text(l10n.btn_confirm || 'Confirm');
            }
        });
    });
});
