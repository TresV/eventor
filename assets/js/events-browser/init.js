(function ($) {
  'use strict';

  var core = window.EvtTicketsEventsBrowser && window.EvtTicketsEventsBrowser.core;
  var filters = window.EvtTicketsEventsBrowser && window.EvtTicketsEventsBrowser.filters;
  var views = window.EvtTicketsEventsBrowser && window.EvtTicketsEventsBrowser.views;
  if (!core || !filters || !views) return;

  function fetchEvents(anchor, config, state, cb, page) {
    var params = filters.buildQueryParams(state.view, anchor, config, state, page);
    $.get(EvtTicketsEventBrowser.ajaxUrl, params)
      .done(function (resp) {
        var events = resp && resp.success && resp.data && resp.data.events ? resp.data.events : [];
        var meta = resp && resp.success && resp.data ? resp.data : null;
        cb(events, meta);
      })
      .fail(function () {
        cb([], null);
      });
  }

  function applyViewUi($widget, state, anchor) {
    var view = state.view;
    var $month = $widget.find('.evt-browser__monthpick');
    var $from = $widget.find('.evt-browser__from');
    var $to = $widget.find('.evt-browser__to');

    // Always show the date range filters (applies to all views).
    $from.show();
    $to.show();
    if (!$from.val() && state.from) $from.val(state.from);
    if (!$to.val() && state.to) $to.val(state.to);

    // Month picker is available for quick navigation in Month view.
    if (view === 'month') {
      $month.show();
      $month.val(anchor.getFullYear() + '-' + core.pad2(anchor.getMonth() + 1));
    } else {
      $month.hide();
    }
  }

  function initBrowser($scope) {
    var $widget = $scope.find('.evt-events-browser');
    if (!$widget.length) return;

    var cfgRaw = $widget.attr('data-config') || '{}';
    var config = {};
    try {
      config = JSON.parse(cfgRaw);
    } catch (e) {
      config = {};
    }

    var labelsRaw = $widget.attr('data-labels') || '{}';
    var labels = {};
    try {
      labels = JSON.parse(labelsRaw || '{}') || {};
    } catch (e2) {
      labels = {};
    }
    config.labels = labels || {};

    function applyLabels() {
      var l = labels || {};
      if (l.today) $widget.find('.evt-browser__btn[data-nav="today"]').text(l.today);
      if (l.search_placeholder) $widget.find('.evt-browser__searchinput').attr('placeholder', l.search_placeholder);
      if (l.search_button) $widget.find('.evt-browser__searchbtn').text(l.search_button);
      if (l.load_more) $widget.find('[data-load-more]').text(l.load_more);
      if (l.filters_title) $widget.find('.evt-sidebar__title').text(l.filters_title);
      if (l.your_selections) $widget.find('.evt-sidebar__selections-label').text(l.your_selections);
      if (l.clear) $widget.find('.evt-sidebar__clear').text(l.clear);

      var viewsLabels = (l.views || {});
      $widget.find('.evt-browser__view option').each(function () {
        var v = $(this).attr('value');
        if (viewsLabels[v]) $(this).text(viewsLabels[v]);
      });
      $widget.find('.evt-browser__view--mobile option').each(function () {
        var v = $(this).attr('value');
        if (viewsLabels[v]) $(this).text(viewsLabels[v]);
      });

      var acc = (l.acc || {});
      function setAcc(key, text) {
        if (!text) return;
        $widget.find('.evt-acc[data-acc="' + key + '"] .evt-acc__head span').first().text(text);
      }
      setAcc('category', acc.category);
      setAcc('cost', acc.cost);
      setAcc('tags', acc.tags);
      setAcc('venues', acc.venues);
      setAcc('organizers', acc.organizers);
      setAcc('day', acc.day);
      setAcc('country-city', acc.country_city);
      setAcc('status', acc.status);
      setAcc('virtual', acc.virtual);
      setAcc('date', acc.date);
    }

    var anchor = new Date(
      parseInt($widget.data('year'), 10) || new Date().getFullYear(),
      (parseInt($widget.data('month'), 10) || new Date().getMonth() + 1) - 1,
      parseInt($widget.data('day'), 10) || new Date().getDate()
    );

    var isMobile = window.matchMedia && window.matchMedia('(max-width: 767px)').matches;
    $widget.toggleClass('is-mobile', !!isMobile);

    function computeDefaultRange() {
      var today = new Date();
      today = core.startOfDay(today);
      var endOfYear = new Date(today.getFullYear(), 11, 31);
      var mode = (config.defaultRangeMode || 'today_to_eoy');

      function isYmd(s) {
        return typeof s === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(s);
      }

      if (mode === 'custom') {
        var from = isYmd(config.defaultFrom) ? config.defaultFrom : '';
        var to = isYmd(config.defaultTo) ? config.defaultTo : '';
        return { from: from, to: to };
      }

      return { from: core.ymdFromDate(today), to: core.ymdFromDate(endOfYear) };
    }

    var defaults = computeDefaultRange();

    var state = {
      view: config.initialView || 'month',
      keyword: '',
      from: defaults.from || '',
      to: defaults.to || '',
      onlyCapacity: !!config.onlyCapacity,
      hideCancelled: !!config.hideCancelled,
      hidePostponed: !!config.hidePostponed,
      virtualMode: 'all',
      categoryIds: [],
      tagIds: [],
      venueIds: [],
      organizerIds: [],
      dayOfWeek: null,
      country: '',
      city: '',
      costMin: null,
      costMax: null,
      gridColumns: config.gridColumns || 3
    };

    var $body = $widget.find('[data-body]');
    $widget.find('.evt-browser__view').val(state.view);
    applyLabels();

    // Load-more pagination state (only used for list-like views).
    var loadedPage = 1;
    var allEvents = [];

    function updateLoadMore(meta) {
      var isPaginated = state.view === 'list' || state.view === 'grid' || state.view === 'summary' || state.view === 'map';
      var hasMore = isPaginated && !!meta && !!meta.has_more;
      $widget.find('[data-pagination]').attr('hidden', hasMore ? null : '');
      $widget.find('[data-load-more]').prop('disabled', false);
    }

    function render(events) {
      applyViewUi($widget, state, anchor);
      if (state.view === 'month') {
        views.renderMonth($body, anchor, events, { compact: isMobile, labels: labels, config: config });
      } else if (state.view === 'grid') {
        views.renderGrid($body, events, state.gridColumns, { labels: labels, config: config });
      } else if (state.view === 'summary') {
        views.renderListLike($body, events, true, { labels: labels, config: config });
      } else if (state.view === 'map') {
        views.renderMap($body, events, { labels: labels, config: config });
      } else {
        // day/week/list share the list UI for now.
        views.renderListLike($body, events, false, { labels: labels, config: config });
      }
    }

    function refresh() {
      loadedPage = 1;
      allEvents = [];
      $body.html('<p class="evt-browser__loading">' + (labels.loading || 'Loading…') + '</p>');
      fetchEvents(anchor, config, state, function (events, meta) {
        allEvents = events;
        $body.data('last-events', events);
        render(events);
        updateLoadMore(meta);

        if (isMobile && state.view === 'month') {
          var key = core.ymdFromDate(anchor);
          var $cell = $body.find('.evt-month__cell[data-day="' + key + '"]');
          if ($cell.length) {
            $cell.trigger('click');
          }
        }
      }, 1);
    }

    filters.renderFilters($widget, config, state);
    applyViewUi($widget, state, anchor);
    refresh();

    function closeOverlays() {
      $widget.removeClass('is-filters-open is-search-open is-view-open');
      $widget.find('[data-overlay]').prop('hidden', true);
    }

    function toggleFilters() {
      $widget.toggleClass('is-filters-open');
      var open = $widget.hasClass('is-filters-open');
      $widget.find('[data-overlay]').prop('hidden', !open);
      if (open) {
        $widget.removeClass('is-search-open is-view-open');
      }
    }

    function toggleSearch() {
      $widget.toggleClass('is-search-open');
      if ($widget.hasClass('is-search-open')) {
        $widget.removeClass('is-filters-open');
        $widget.find('[data-overlay]').prop('hidden', true);
        setTimeout(function () {
          $widget.find('.evt-browser__searchinput').trigger('focus');
        }, 0);
      }
    }

    $widget.on('click', '[data-action="filters"]', function (e) {
      e.preventDefault();
      toggleFilters();
    });

    $widget.on('click', '[data-action="search"]', function (e) {
      e.preventDefault();
      toggleSearch();
    });

    $widget.on('click', '[data-overlay]', function () {
      closeOverlays();
    });

    $widget.on('click', '.evt-browser__searchbtn', function () {
      state.keyword = ($widget.find('.evt-browser__searchinput').val() || '').trim();
      refresh();
    });

    $widget.on('keydown', '.evt-browser__searchinput', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        $widget.find('.evt-browser__searchbtn').trigger('click');
      }
    });

    $widget.on('change', '.evt-browser__view', function () {
      state.view = $(this).val();
      $widget.find('.evt-browser__view').val(state.view);
      refresh();
    });

    $widget.on('change', '.evt-browser__monthpick', function () {
      var v = $(this).val();
      if (!v || !/^\d{4}-\d{2}$/.test(v)) return;
      var parts = v.split('-');
      anchor = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, 1);
      refresh();
    });

    $widget.on('change', '.evt-browser__from', function () {
      state.from = $(this).val() || '';
      refresh();
    });

    $widget.on('change', '.evt-browser__to', function () {
      state.to = $(this).val() || '';
      refresh();
    });

    $widget.on('click', '[data-nav]', function () {
      var nav = $(this).data('nav');
      if (nav === 'today') {
        anchor = new Date();
      } else if (nav === 'prev') {
        if (state.view === 'month') anchor = new Date(anchor.getFullYear(), anchor.getMonth() - 1, 1);
        else if (state.view === 'week') anchor = new Date(anchor.getFullYear(), anchor.getMonth(), anchor.getDate() - 7);
        else anchor = new Date(anchor.getFullYear(), anchor.getMonth(), anchor.getDate() - 1);
      } else if (nav === 'next') {
        if (state.view === 'month') anchor = new Date(anchor.getFullYear(), anchor.getMonth() + 1, 1);
        else if (state.view === 'week') anchor = new Date(anchor.getFullYear(), anchor.getMonth(), anchor.getDate() + 7);
        else anchor = new Date(anchor.getFullYear(), anchor.getMonth(), anchor.getDate() + 1);
      }
      refresh();
    });

    $widget.on('click', '[data-load-more]', function () {
      var $btn = $(this);
      if ($btn.prop('disabled')) return;
      var next = loadedPage + 1;
      $btn.prop('disabled', true).text(labels.loading_more || (labels.loading || 'Loading…'));

      fetchEvents(anchor, config, state, function (events, meta) {
        $btn.prop('disabled', false).text(labels.load_more || 'Load more events');
        if (!events || !events.length) {
          updateLoadMore(meta);
          return;
        }
        loadedPage = next;
        allEvents = allEvents.concat(events);
        $body.data('last-events', allEvents);
        render(allEvents);
        updateLoadMore(meta);
      }, next);
    });

    // Mobile month view: select day + show details between calendar and bottom nav.
    $widget.on('click', '.evt-month__cell[data-day]', function () {
      if (!isMobile) return;
      var dayKey = String($(this).data('day') || '');
      if (!dayKey) return;

      $widget.find('.evt-month__cell').removeClass('is-selected');
      $(this).addClass('is-selected');

      var currentEvents = $body.data('last-events') || [];
      var dayEvents = (currentEvents || []).filter(function (evt) {
        return core.eventDayKey(evt) === dayKey;
      });

      var $detail = $body.find('[data-month-detail]');
      if (!$detail.length) return;

      if (!dayEvents.length) {
        $detail.html('');
        return;
      }

      var titleDate = new Date(dayKey + 'T00:00:00');
      var html =
        '<div class="evt-month__detail-title">' +
        core.escapeHtml(
          titleDate.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' })
        ) +
        '</div>';
      dayEvents.slice(0, 3).forEach(function (evt) {
        html += '<div class="evt-month__detail-item">';
        html += views.renderStatusBadge(evt, { config: config });
        html += '<div class="evt-month__detail-time">' + core.escapeHtml(core.formatTimeRange(evt)) + '</div>';
        html +=
          '<a class="evt-month__detail-link" href="' +
          core.escapeHtml(evt.permalink || evt.url || '#') +
          '">' +
          core.escapeHtml(evt.title) +
          '</a>';
        html += '</div>';
      });
      $detail.html(html);
    });

    $widget.on('click', '[data-month-nav]', function (e) {
      if (!isMobile) return;
      e.preventDefault();
      var nav = $(this).data('month-nav');
      if (nav === 'today') {
        anchor = new Date();
      } else if (nav === 'prev') {
        anchor = new Date(anchor.getFullYear(), anchor.getMonth() - 1, 1);
      } else if (nav === 'next') {
        anchor = new Date(anchor.getFullYear(), anchor.getMonth() + 1, 1);
      }
      refresh();
    });

    // Sidebar filter interactions
    $widget.on('change', 'input[data-filter="category"]', function () {
      var id = parseInt($(this).val(), 10);
      if ($(this).is(':checked')) {
        if (state.categoryIds.indexOf(id) === -1) state.categoryIds.push(id);
      } else {
        state.categoryIds = state.categoryIds.filter(function (x) {
          return x !== id;
        });
      }
      filters.renderFilters($widget, config, state);
      refresh();
    });

    $widget.on('click', 'button[data-filter="tag"]', function () {
      var id = parseInt($(this).data('id'), 10);
      if (state.tagIds.indexOf(id) === -1) state.tagIds.push(id);
      else
        state.tagIds = state.tagIds.filter(function (x) {
          return x !== id;
        });
      filters.renderFilters($widget, config, state);
      refresh();
    });

    $widget.on('change', 'input[data-filter="venue"]', function () {
      var id = parseInt($(this).val(), 10);
      if ($(this).is(':checked')) {
        if (state.venueIds.indexOf(id) === -1) state.venueIds.push(id);
      } else {
        state.venueIds = state.venueIds.filter(function (x) {
          return x !== id;
        });
      }
      filters.renderFilters($widget, config, state);
      refresh();
    });

    $widget.on('change', 'input[data-filter="organizer"]', function () {
      var id = parseInt($(this).val(), 10);
      if ($(this).is(':checked')) {
        if (state.organizerIds.indexOf(id) === -1) state.organizerIds.push(id);
      } else {
        state.organizerIds = state.organizerIds.filter(function (x) {
          return x !== id;
        });
      }
      filters.renderFilters($widget, config, state);
      refresh();
    });

    $widget.on('change', 'input[data-filter="dow"]', function () {
      var v = $(this).val();
      state.dayOfWeek = v === '' ? null : parseInt(v, 10);
      filters.renderFilters($widget, config, state);
      refresh();
    });

    $widget.on('change', 'select[data-filter="country"]', function () {
      state.country = $(this).val() || '';
      filters.renderFilters($widget, config, state);
      refresh();
    });

    $widget.on('change', 'select[data-filter="city"]', function () {
      state.city = $(this).val() || '';
      filters.renderFilters($widget, config, state);
      refresh();
    });

    $widget.on('change', 'input[data-filter="hide_cancelled"]', function () {
      state.hideCancelled = $(this).is(':checked');
      filters.renderFilters($widget, config, state);
      refresh();
    });

    $widget.on('change', 'input[data-filter="hide_postponed"]', function () {
      state.hidePostponed = $(this).is(':checked');
      filters.renderFilters($widget, config, state);
      refresh();
    });

    $widget.on('change', 'input[data-filter="virtual"]', function () {
      state.virtualMode = $(this).val() || 'all';
      filters.renderFilters($widget, config, state);
      refresh();
    });

    $widget.on('input', 'input[data-filter="cost_min"], input[data-filter="cost_max"]', function () {
      var $min = $widget.find('input[data-filter="cost_min"]');
      var $max = $widget.find('input[data-filter="cost_max"]');
      var vmin = parseFloat($min.val());
      var vmax = parseFloat($max.val());
      if (vmin > vmax) {
        var tmp = vmin;
        vmin = vmax;
        vmax = tmp;
        $min.val(vmin);
        $max.val(vmax);
      }
      state.costMin = vmin;
      state.costMax = vmax;
      $widget.find('[data-cost-label]').text(vmin + ' - ' + vmax);
    });

    $widget.on('change', 'input[data-filter="cost_min"], input[data-filter="cost_max"]', function () {
      filters.renderFilters($widget, config, state);
      refresh();
    });

    $widget.on('click', '[data-clear]', function () {
      state.keyword = '';
      state.from = '';
      state.to = '';
      state.categoryIds = [];
      state.tagIds = [];
      state.venueIds = [];
      state.organizerIds = [];
      state.dayOfWeek = null;
      state.country = '';
      state.city = '';
      state.virtualMode = 'all';
      state.costMin = null;
      state.costMax = null;
      state.hideCancelled = !!config.hideCancelled;
      state.hidePostponed = !!config.hidePostponed;
      $widget.find('.evt-browser__searchinput').val('');
      filters.renderFilters($widget, config, state);
      refresh();
    });

    $widget.on('click', '.evt-pill__x', function () {
      var pill = $(this).data('pill') || {};
      if (pill.key === 'category')
        state.categoryIds = state.categoryIds.filter(function (x) {
          return String(x) !== String(pill.value);
        });
      if (pill.key === 'tag')
        state.tagIds = state.tagIds.filter(function (x) {
          return String(x) !== String(pill.value);
        });
      if (pill.key === 'venue')
        state.venueIds = state.venueIds.filter(function (x) {
          return String(x) !== String(pill.value);
        });
      if (pill.key === 'organizer')
        state.organizerIds = state.organizerIds.filter(function (x) {
          return String(x) !== String(pill.value);
        });
      if (pill.key === 'country') state.country = '';
      if (pill.key === 'city') state.city = '';
      if (pill.key === 'dow') state.dayOfWeek = null;
      if (pill.key === 'virtual') state.virtualMode = 'all';
      if (pill.key === 'hide_cancelled') state.hideCancelled = false;
      if (pill.key === 'hide_postponed') state.hidePostponed = false;
      if (pill.key === 'cost') {
        state.costMin = null;
        state.costMax = null;
      }
      filters.renderFilters($widget, config, state);
      refresh();
    });
  }

  $(window).on('elementor/frontend/init', function () {
    if (!window.elementorFrontend) return;
    elementorFrontend.hooks.addAction('frontend/element_ready/evt_events_browser.default', initBrowser);
  });
})(jQuery);
