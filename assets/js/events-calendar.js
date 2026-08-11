(function ($) {
    'use strict';

    function ensureGlobalFilterState() {
        if (!window.EvtEventsFilters) {
            window.EvtEventsFilters = { keyword: '', from: '', to: '' };
        }
        return window.EvtEventsFilters;
    }

    function getLabels($widget) {
        var raw = ($widget && $widget.attr) ? ($widget.attr('data-labels') || '{}') : '{}';
        var labels = {};
        try { labels = JSON.parse(raw || '{}') || {}; } catch (e) { labels = {}; }
        return labels;
    }

    function pad2(n) {
        return (n < 10 ? '0' : '') + n;
    }

    function startOfDay(d) {
        return new Date(d.getFullYear(), d.getMonth(), d.getDate());
    }

    function sameDay(a, b) {
        return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
    }

    function groupEventsByDay(events) {
        var map = {};
        events.forEach(function (evt) {
            if (!evt.start) return;
            var d = new Date(evt.start * 1000);
            var key = d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate());
            if (!map[key]) map[key] = [];
            map[key].push(evt);
        });
        Object.keys(map).forEach(function (k) {
            map[k].sort(function (a, b) { return (a.start || 0) - (b.start || 0); });
        });
        return map;
    }

    function formatTitleMonth(year, month) {
        var d = new Date(year, month - 1, 1);
        return d.toLocaleString(undefined, { month: 'long', year: 'numeric' });
    }

    function formatTitleWeek(anchor) {
        var start = new Date(anchor);
        var day = start.getDay();
        var startOfWeek = (typeof EvtTicketsCalendar.startOfWeek !== 'undefined') ? EvtTicketsCalendar.startOfWeek : 1;
        var diff = (day - startOfWeek + 7) % 7;
        start.setDate(start.getDate() - diff);
        start = startOfDay(start);
        var end = new Date(start);
        end.setDate(end.getDate() + 6);
        var s = start.toLocaleDateString();
        var e = end.toLocaleDateString();
        return s + ' – ' + e;
    }

    function formatTitleDay(anchor) {
        return anchor.toLocaleDateString(undefined, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    }

    function renderList($container, events, limit) {
        limit = limit || events.length;
        if (!events.length) {
            var labels = getLabels($container.closest('.evt-events-calendar'));
            $container.html('<p class="evt-cal__empty">' + (labels.no_events || 'No events.') + '</p>');
            return;
        }
        var html = '<ul class="evt-cal__list">';
        events.slice(0, limit).forEach(function (evt) {
            var dateStr = evt.start ? new Date(evt.start * 1000).toLocaleString() : '';
            html += '<li class="evt-cal__list-item"><a href="' + evt.url + '">' + evt.title + '</a>' +
                (dateStr ? ' <span class="evt-cal__list-date">' + dateStr + '</span>' : '') + '</li>';
        });
        html += '</ul>';
        $container.html(html);
    }

    function renderMonthGrid($container, year, month, events) {
        var labels = getLabels($container.closest('.evt-events-calendar'));
        var first = new Date(year, month - 1, 1);
        var start = new Date(first);
        var startOfWeek = (typeof EvtTicketsCalendar.startOfWeek !== 'undefined') ? EvtTicketsCalendar.startOfWeek : 1;
        var diff = (start.getDay() - startOfWeek + 7) % 7;
        start.setDate(start.getDate() - diff);
        start = startOfDay(start);

        var today = new Date();
        today = startOfDay(today);
        var byDay = groupEventsByDay(events);

        var html = '<div class="evt-cal__dow">';
        for (var i = 0; i < 7; i++) {
            var d = new Date(start);
            d.setDate(d.getDate() + i);
            html += '<div class="evt-cal__dow-cell">' + d.toLocaleDateString(undefined, { weekday: 'short' }) + '</div>';
        }
        html += '</div>';

        html += '<div class="evt-cal__grid">';
        for (var cell = 0; cell < 42; cell++) {
            var dcell = new Date(start);
            dcell.setDate(dcell.getDate() + cell);
            var inMonth = dcell.getMonth() === (month - 1);
            var key = dcell.getFullYear() + '-' + pad2(dcell.getMonth() + 1) + '-' + pad2(dcell.getDate());
            var dayEvents = byDay[key] || [];
            var classes = 'evt-cal__cell' + (inMonth ? '' : ' is-outside') + (sameDay(dcell, today) ? ' is-today' : '');

            html += '<div class="' + classes + '" data-date="' + key + '">';
            html += '<div class="evt-cal__cell-top"><span class="evt-cal__daynum">' + dcell.getDate() + '</span></div>';
            html += '<div class="evt-cal__cell-events">';
            dayEvents.slice(0, 3).forEach(function (evt) {
                html += '<a class="evt-cal__event" href="' + evt.url + '" title="' + evt.title + '">' + evt.title + '</a>';
            });
            if (dayEvents.length > 3) {
                html += '<button type="button" class="evt-cal__more" data-more="' + key + '">+' + (dayEvents.length - 3) + ' ' + (labels.more || 'more') + '</button>';
            }
            html += '</div></div>';
        }
        html += '</div>';

        $container.html(html);
        $container.off('click.evtMore').on('click.evtMore', '.evt-cal__more', function (e) {
            e.preventDefault();
            var key = $(this).data('more');
            var items = byDay[key] || [];
            var list = items.map(function (evt) {
                var t = evt.start ? new Date(evt.start * 1000).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '';
                return '<li><a href="' + evt.url + '">' + evt.title + '</a>' + (t ? ' <span>' + t + '</span>' : '') + '</li>';
            }).join('');
            var $cell = $container.find('.evt-cal__cell[data-date="' + key + '"] .evt-cal__cell-events');
            $cell.html('<ul class="evt-cal__more-list">' + list + '</ul>');
        });
    }

    function minutesSinceMidnight(ts) {
        var d = new Date(ts * 1000);
        return d.getHours() * 60 + d.getMinutes();
    }

    function clamp(n, min, max) {
        return Math.max(min, Math.min(max, n));
    }

    function renderTimeline($container, mode, anchor, events, startHour, endHour) {
        startHour = clamp(parseInt(startHour, 10), 0, 23);
        endHour = clamp(parseInt(endHour, 10), 1, 24);
        if (endHour <= startHour) endHour = Math.min(24, startHour + 8);
        var totalMinutes = (endHour - startHour) * 60;

        var start = new Date(anchor);
        var startOfWeek = (typeof EvtTicketsCalendar.startOfWeek !== 'undefined') ? EvtTicketsCalendar.startOfWeek : 1;
        if (mode === 'week') {
            var diff = (start.getDay() - startOfWeek + 7) % 7;
            start.setDate(start.getDate() - diff);
        }
        start = startOfDay(start);

        var days = (mode === 'day') ? 1 : 7;

        var hoursHtml = '<div class="evt-cal__timeline-hours"><div class="evt-cal__timeline-hourhead"></div>';
        for (var h = startHour; h < endHour; h++) {
            var label = pad2(h % 24) + ':00';
            hoursHtml += '<div class="evt-cal__timeline-hour">' + label + '</div>';
        }
        hoursHtml += '</div>';

        var daysHtml = '<div class="evt-cal__timeline-days" style="grid-template-columns: repeat(' + days + ', 1fr)">';
        for (var i = 0; i < days; i++) {
            var dayDate = new Date(start);
            dayDate.setDate(dayDate.getDate() + i);
            daysHtml += '<div class="evt-cal__timeline-day">';
            daysHtml += '<div class="evt-cal__timeline-dayhead">' + dayDate.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' }) + '</div>';
            daysHtml += '<div class="evt-cal__timeline-lanes">';
            daysHtml += '<div class="evt-cal__timeline-lane" data-day="' + (dayDate.getFullYear() + '-' + pad2(dayDate.getMonth() + 1) + '-' + pad2(dayDate.getDate())) + '"></div>';
            daysHtml += '</div></div>';
        }
        daysHtml += '</div>';

        $container.html('<div class="evt-cal__timeline">' + hoursHtml + daysHtml + '</div>');

        var laneHeight = (endHour - startHour) * 60; // 60px per hour
        $container.find('.evt-cal__timeline-lane').css('height', laneHeight + 'px');

        var byDay = groupEventsByDay(events);
        Object.keys(byDay).forEach(function (dayKey) {
            var $lane = $container.find('.evt-cal__timeline-lane[data-day="' + dayKey + '"]');
            if (! $lane.length) return;
            byDay[dayKey].forEach(function (evt) {
                var startMin = minutesSinceMidnight(evt.start) - startHour * 60;
                var endMin = evt.end ? (minutesSinceMidnight(evt.end) - startHour * 60) : (startMin + 60);
                startMin = clamp(startMin, 0, totalMinutes);
                endMin = clamp(endMin, startMin + 15, totalMinutes);
                var topPct = (startMin / totalMinutes) * 100;
                var heightPct = ((endMin - startMin) / totalMinutes) * 100;
                var $ev = $('<a class="evt-cal__event evt-cal__event--block"></a>');
                $ev.attr('href', evt.url).text(evt.title);
                $ev.css({ top: topPct + '%', height: heightPct + '%' });
                $lane.append($ev);
            });
        });
    }

    function initCalendar($scope) {
        var $widget = $scope.find('.evt-events-calendar');
        if (! $widget.length) {
            return;
        }

        var initialView = $widget.data('initial-view') || 'month';
        var hideCancelled = $widget.data('hide-cancelled') === 1 || $widget.data('hide-cancelled') === '1';
        var onlyCapacity = $widget.data('only-capacity') === 1 || $widget.data('only-capacity') === '1';
        var listLimit = parseInt($widget.data('list-limit') || 10, 10);
        var timelineStartHour = parseInt($widget.data('timeline-start') || EvtTicketsCalendar.timelineStartHour || 7, 10);
        var timelineEndHour = parseInt($widget.data('timeline-end') || EvtTicketsCalendar.timelineEndHour || 21, 10);
        var $body = $widget.find('.evt-cal__body');
        var cachedEvents = $body.data('events') || [];
        var $title = $widget.find('.evt-cal__title');

        var state = {
            view: initialView,
            year: parseInt($widget.data('year'), 10) || (new Date()).getFullYear(),
            month: parseInt($widget.data('month'), 10) || ((new Date()).getMonth() + 1),
            day: parseInt($widget.data('day'), 10) || (new Date()).getDate()
        };

        function syncActiveButtons() {
            $widget.find('.evt-calendar-toggle [data-view]').removeClass('is-active');
            $widget.find('.evt-calendar-toggle [data-view="' + state.view + '"]').addClass('is-active');
        }

        function updateTitle() {
            var labels = getLabels($widget);
            if (state.view === 'month') {
                $title.text(formatTitleMonth(state.year, state.month));
            } else if (state.view === 'week') {
                $title.text(formatTitleWeek(new Date(state.year, state.month - 1, state.day)));
            } else if (state.view === 'day') {
                $title.text(formatTitleDay(new Date(state.year, state.month - 1, state.day)));
            } else {
                $title.text(labels.upcoming || 'Upcoming Events');
            }
        }

        function render(view, events) {
            updateTitle();
            syncActiveButtons();
            if (view === 'month') {
                renderMonthGrid($body, state.year, state.month, events);
            } else if (view === 'week') {
                renderTimeline($body, 'week', new Date(state.year, state.month - 1, state.day), events, timelineStartHour, timelineEndHour);
            } else if (view === 'day') {
                renderTimeline($body, 'day', new Date(state.year, state.month - 1, state.day), events, timelineStartHour, timelineEndHour);
            } else {
                renderList($body, events, listLimit);
            }
        }

        function fetchEvents() {
            var filters = ensureGlobalFilterState();
            var data = {
                action: 'evt_fetch_events',
                view: state.view,
                year: state.year,
                month: state.month,
                day: state.day,
                hide_cancelled: hideCancelled ? 1 : 0,
                only_capacity: onlyCapacity ? 1 : 0,
                limit: state.view === 'list' ? listLimit : -1,
                s: filters.keyword || ''
            };

            if (state.view === 'list') {
                var listDays = parseInt($widget.data('list-days') || 90, 10);
                if (!isNaN(listDays) && listDays > 0) data.list_days = listDays;
            }

            if (filters.from) data.start_after = filters.from;
            if (filters.to) data.end_before = filters.to;

            $body.addClass('is-loading');
            $.get(EvtTicketsCalendar.ajaxUrl, data)
                .done(function (response) {
                    var evts = (response && response.success && response.data && response.data.events) ? response.data.events : cachedEvents;
                    render(state.view, evts);
                })
                .fail(function () {
                    render(state.view, cachedEvents);
                })
                .always(function () {
                    $body.removeClass('is-loading');
                });
        }

        $widget.on('click', '.evt-calendar-toggle button', function (e) {
            e.preventDefault();
            var view = $(this).data('view');
            state.view = view;
            fetchEvents();
        });

        $widget.on('click', '.evt-cal__nav [data-nav]', function (e) {
            e.preventDefault();
            var nav = $(this).data('nav');
            if (nav === 'today') {
                var now = new Date();
                state.year = now.getFullYear();
                state.month = now.getMonth() + 1;
                state.day = now.getDate();
            } else if (nav === 'prev') {
                if (state.view === 'month') {
                    state.month -= 1;
                    if (state.month < 1) { state.month = 12; state.year -= 1; }
                } else if (state.view === 'week') {
                    var d = new Date(state.year, state.month - 1, state.day);
                    d.setDate(d.getDate() - 7);
                    state.year = d.getFullYear(); state.month = d.getMonth() + 1; state.day = d.getDate();
                } else if (state.view === 'day') {
                    var d2 = new Date(state.year, state.month - 1, state.day);
                    d2.setDate(d2.getDate() - 1);
                    state.year = d2.getFullYear(); state.month = d2.getMonth() + 1; state.day = d2.getDate();
                }
            } else if (nav === 'next') {
                if (state.view === 'month') {
                    state.month += 1;
                    if (state.month > 12) { state.month = 1; state.year += 1; }
                } else if (state.view === 'week') {
                    var d3 = new Date(state.year, state.month - 1, state.day);
                    d3.setDate(d3.getDate() + 7);
                    state.year = d3.getFullYear(); state.month = d3.getMonth() + 1; state.day = d3.getDate();
                } else if (state.view === 'day') {
                    var d4 = new Date(state.year, state.month - 1, state.day);
                    d4.setDate(d4.getDate() + 1);
                    state.year = d4.getFullYear(); state.month = d4.getMonth() + 1; state.day = d4.getDate();
                }
            }
            fetchEvents();
        });

        window.addEventListener('evtEventsFilterChange', function () {
            fetchEvents();
        });

        // Initial render
        render(state.view, cachedEvents);
        if (!cachedEvents.length) {
            fetchEvents();
        } else {
            updateTitle();
        }
    }

    $(window).on('elementor/frontend/init', function () {
        if (! window.elementorFrontend) {
            return;
        }

        elementorFrontend.hooks.addAction('frontend/element_ready/evt_events_calendar.default', initCalendar);
    });
})(jQuery);
