(function ($) {
    'use strict';

    function pad2(n) {
        return (n < 10 ? '0' : '') + n;
    }

    function ymd(date) {
        return date.getFullYear() + '-' + pad2(date.getMonth() + 1) + '-' + pad2(date.getDate());
    }

    function formatTitle(year, month) {
        var d = new Date(year, month - 1, 1);
        return d.toLocaleString(undefined, { month: 'long', year: 'numeric' });
    }

    function buildGrid(year, month) {
        var first = new Date(year, month - 1, 1);
        var start = new Date(first);
        // Sunday-start grid
        start.setDate(start.getDate() - start.getDay());

        var cells = [];
        for (var i = 0; i < 42; i++) {
            var d = new Date(start);
            d.setDate(start.getDate() + i);
            cells.push(d);
        }
        return cells;
    }

    function ensureGlobalFilterState() {
        if (!window.EvtEventsFilters) {
            window.EvtEventsFilters = { keyword: '', from: '', to: '' };
        }
        return window.EvtEventsFilters;
    }

    function fetchMonth($root, year, month) {
        var hideCancelled = $root.data('hide-cancelled') === 1 || $root.data('hide-cancelled') === '1';
        var hidePast = $root.data('hide-past') === 1 || $root.data('hide-past') === '1';
        var filters = ensureGlobalFilterState();

        var data = {
            action: 'evt_fetch_events_month',
            year: year,
            month: month,
            hide_cancelled: hideCancelled ? 1 : 0,
            hide_past: hidePast ? 1 : 0,
            s: filters.keyword || ''
        };

        // Optional date filtering (client sends as YYYY-MM-DD; backend can ignore for now if empty).
        if (filters.from) data.start_after = filters.from;
        if (filters.to) data.end_before = filters.to;

        return $.get(EvtTicketsMonthCal.ajaxUrl, data);
    }

    function renderMonth($root, year, month, daysMap) {
        var $title = $root.find('.evt-month-cal__title');
        var $grid = $root.find('.evt-month-cal__grid');

        $title.text(formatTitle(year, month));

        var labelsRaw = $root.attr('data-labels') || '{}';
        var labels = {};
        try { labels = JSON.parse(labelsRaw || '{}') || {}; } catch (e) { labels = {}; }
        var moreSuffix = labels.more || 'more';

        var maxPerDay = parseInt($root.data('max-per-day') || 3, 10);
        var showMore = $root.data('show-more') === 1 || $root.data('show-more') === '1';

        var cells = buildGrid(year, month);
        var html = '';

        html += '<div class="evt-month-cal__dow">';
        for (var i = 0; i < 7; i++) {
            var dd = new Date(year, month - 1, 1);
            // Use the same Sunday-start grid, but just format weekday names in locale.
            dd.setDate(dd.getDate() - dd.getDay() + i);
            html += '<div class="evt-month-cal__dow-cell">' + dd.toLocaleDateString(undefined, { weekday: 'short' }) + '</div>';
        }
        html += '</div>';

        html += '<div class="evt-month-cal__cells">';
        cells.forEach(function (d) {
            var inMonth = (d.getMonth() === (month - 1));
            var key = ymd(d);
            var events = (daysMap && daysMap[key]) ? daysMap[key] : [];
            html += '<div class="evt-month-cal__cell' + (inMonth ? '' : ' is-outside') + '" data-date="' + key + '">';
            html += '<div class="evt-month-cal__daynum">' + d.getDate() + '</div>';
            html += '<div class="evt-month-cal__events">';
            events.slice(0, maxPerDay).forEach(function (evt) {
                html += '<a class="evt-month-cal__event" href="' + evt.permalink + '" title="' + evt.title + '">' + evt.title + '</a>';
            });
            if (showMore && events.length > maxPerDay) {
                html += '<button type="button" class="evt-month-cal__more" data-more="' + key + '">+' + (events.length - maxPerDay) + ' ' + moreSuffix + '</button>';
            }
            html += '</div></div>';
        });
        html += '</div>';

        $grid.html(html);

        if (showMore) {
            $grid.off('click.more').on('click.more', '.evt-month-cal__more', function (e) {
                e.preventDefault();
                var key = $(this).data('more');
                var events = (daysMap && daysMap[key]) ? daysMap[key] : [];
                var list = '<ul class="evt-month-cal__more-list">';
                events.forEach(function (evt) {
                    list += '<li><a href="' + evt.permalink + '">' + evt.title + '</a></li>';
                });
                list += '</ul>';
                $grid.find('.evt-month-cal__cell[data-date="' + key + '"] .evt-month-cal__events').html(list);
            });
        }
    }

    function initWidget($scope) {
        var $root = $scope.find('.evt-month-cal');
        if (!$root.length) return;

        var state = {
            year: parseInt($root.data('year'), 10) || (new Date()).getFullYear(),
            month: parseInt($root.data('month'), 10) || ((new Date()).getMonth() + 1)
        };

        function load() {
            $root.addClass('is-loading');
            fetchMonth($root, state.year, state.month)
                .done(function (resp) {
                    var days = (resp && resp.success && resp.data && resp.data.days) ? resp.data.days : {};
                    renderMonth($root, state.year, state.month, days);
                })
                .fail(function () {
                    renderMonth($root, state.year, state.month, {});
                })
                .always(function () {
                    $root.removeClass('is-loading');
                });
        }

        $root.on('click', '[data-nav]', function (e) {
            e.preventDefault();
            var dir = $(this).data('nav');
            if (dir === 'prev') {
                state.month -= 1;
                if (state.month < 1) { state.month = 12; state.year -= 1; }
            } else if (dir === 'next') {
                state.month += 1;
                if (state.month > 12) { state.month = 1; state.year += 1; }
            }
            load();
        });

        window.addEventListener('evtEventsFilterChange', function () {
            load();
        });

        load();
    }

    $(window).on('elementor/frontend/init', function () {
        if (!window.elementorFrontend) return;
        elementorFrontend.hooks.addAction('frontend/element_ready/evt_events_month_calendar.default', initWidget);
    });
})(jQuery);
