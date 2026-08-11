(function ($) {
    'use strict';

    var STORAGE_KEY = 'evt_tickets_saved_views';

    function ensureGlobalFilterState() {
        if (!window.EvtEventsFilters) {
            window.EvtEventsFilters = { keyword: '', from: '', to: '' };
        }
        return window.EvtEventsFilters;
    }

    function loadLocalViews() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return [];
            var parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            return [];
        }
    }

    function saveLocalViews(views) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(views || []));
        } catch (e) {
            // ignore
        }
    }

    function syncUserViews(views) {
        if (!window.EvtTicketsFilterBar || !EvtTicketsFilterBar.loggedIn) {
            return $.Deferred().resolve().promise();
        }

        return $.post(EvtTicketsFilterBar.ajaxUrl, {
            action: 'evt_saved_views_set',
            nonce: EvtTicketsFilterBar.nonce,
            views: JSON.stringify(views || [])
        });
    }

    function fetchUserViews() {
        if (!window.EvtTicketsFilterBar || !EvtTicketsFilterBar.loggedIn) {
            return $.Deferred().resolve({ success: true, data: { views: [] } }).promise();
        }
        return $.get(EvtTicketsFilterBar.ajaxUrl, {
            action: 'evt_saved_views_get',
            nonce: EvtTicketsFilterBar.nonce
        });
    }

    function emitChange() {
        window.dispatchEvent(new CustomEvent('evtEventsFilterChange', { detail: ensureGlobalFilterState() }));
    }

    function populateSavedViews($select, views) {
        $select.empty();
        $select.append('<option value="">Select…</option>');
        (views || []).forEach(function (v, idx) {
            $select.append('<option value="' + idx + '">' + String(v.name || ('View ' + (idx + 1))) + '</option>');
        });
    }

    function initWidget($scope) {
        var $root = $scope.find('.evt-filter-bar');
        if (!$root.length) return;

        var $form = $root.find('.evt-filter-bar__form');
        var state = ensureGlobalFilterState();

        var $keyword = $form.find('.evt-filter-bar__keyword');
        var $from = $form.find('input[name="evt_from"]');
        var $to = $form.find('input[name="evt_to"]');
        var $savedSelect = $form.find('.evt-filter-bar__saved');
        var $saveBtn = $form.find('.evt-filter-bar__save');
        var savedEnabled = $root.data('saved-views') === 1 || $root.data('saved-views') === '1';

        // Restore current state
        if ($keyword.length) $keyword.val(state.keyword || '');
        if ($from.length) $from.val(state.from || '');
        if ($to.length) $to.val(state.to || '');

        var localViews = loadLocalViews();
        if (savedEnabled && $savedSelect.length) {
            populateSavedViews($savedSelect, localViews);
            fetchUserViews().done(function (resp) {
                if (resp && resp.success && resp.data && Array.isArray(resp.data.views) && resp.data.views.length) {
                    // Merge: user views first, then local ones (dedupe by name).
                    var names = {};
                    var merged = [];
                    resp.data.views.forEach(function (v) {
                        if (!v || !v.name) return;
                        names[v.name] = true;
                        merged.push(v);
                    });
                    localViews.forEach(function (v) {
                        if (!v || !v.name || names[v.name]) return;
                        merged.push(v);
                    });
                    localViews = merged;
                    saveLocalViews(localViews);
                    populateSavedViews($savedSelect, localViews);
                }
            });
        }

        var debounceTimer;
        function scheduleEmit() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(emitChange, 150);
        }

        $form.on('input change', 'input', function () {
            if ($keyword.length) state.keyword = String($keyword.val() || '').trim();
            if ($from.length) state.from = String($from.val() || '').trim();
            if ($to.length) state.to = String($to.val() || '').trim();
            scheduleEmit();
        });

        $form.on('reset', function () {
            state.keyword = '';
            state.from = '';
            state.to = '';
            setTimeout(emitChange, 0);
        });

        if (savedEnabled && $savedSelect.length) {
            $savedSelect.on('change', function () {
                var idx = parseInt($(this).val(), 10);
                if (isNaN(idx) || !localViews[idx]) {
                    return;
                }
                var v = localViews[idx];
                state.keyword = String(v.keyword || '');
                state.from = String(v.from || '');
                state.to = String(v.to || '');
                if ($keyword.length) $keyword.val(state.keyword);
                if ($from.length) $from.val(state.from);
                if ($to.length) $to.val(state.to);
                emitChange();
            });
        }

        if (savedEnabled && $saveBtn.length) {
            $saveBtn.on('click', function () {
                var name = window.prompt((EvtTicketsFilterBar && EvtTicketsFilterBar.i18n && EvtTicketsFilterBar.i18n.savePrompt) ? EvtTicketsFilterBar.i18n.savePrompt : 'Name this view');
                if (!name) return;
                name = String(name).trim();
                if (!name) return;

                var view = {
                    name: name,
                    keyword: String(state.keyword || ''),
                    from: String(state.from || ''),
                    to: String(state.to || ''),
                    type: ''
                };

                // Upsert by name
                var found = false;
                localViews = (localViews || []).map(function (v) {
                    if (v && v.name === name) {
                        found = true;
                        return view;
                    }
                    return v;
                });
                if (!found) localViews.push(view);

                saveLocalViews(localViews);
                populateSavedViews($savedSelect, localViews);
                syncUserViews(localViews);
            });
        }

        emitChange();
    }

    $(window).on('elementor/frontend/init', function () {
        if (!window.elementorFrontend) return;
        elementorFrontend.hooks.addAction('frontend/element_ready/evt_events_filter_bar.default', initWidget);
        // Allow embedding the same markup inside other widgets (calendar/list/summary).
        elementorFrontend.hooks.addAction('frontend/element_ready/evt_events_calendar.default', initWidget);
        elementorFrontend.hooks.addAction('frontend/element_ready/evt_events_month_calendar.default', initWidget);
        elementorFrontend.hooks.addAction('frontend/element_ready/evt_events_list.default', initWidget);
        elementorFrontend.hooks.addAction('frontend/element_ready/evt_events_summary.default', initWidget);
    });
})(jQuery);
