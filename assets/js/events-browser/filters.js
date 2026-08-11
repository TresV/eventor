(function ($) {
  'use strict';

  var core = window.EvtTicketsEventsBrowser && window.EvtTicketsEventsBrowser.core;
  if (!core) return;

  function updateSelections($widget, config, state) {
    var labels = (config && config.labels) || {};
    var dowShort = Array.isArray(labels.dow_short) && labels.dow_short.length === 7 ? labels.dow_short : ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    var pills = [];
    function addPill(key, label, value) {
      pills.push({ key: key, label: label, value: value });
    }

    if (state.costMin != null || state.costMax != null) {
      addPill(
        'cost',
        (labels.cost_prefix || 'Cost ($):') + ' ' + (state.costMin != null ? state.costMin : '') + '-' + (state.costMax != null ? state.costMax : ''),
        '1'
      );
    }
    if (state.country) addPill('country', (labels.country || 'Country') + ': ' + state.country, state.country);
    if (state.city) addPill('city', (labels.city || 'City') + ': ' + state.city, state.city);
    if (state.dayOfWeek != null) addPill('dow', (labels.acc && labels.acc.day ? labels.acc.day : 'Day') + ': ' + (dowShort[state.dayOfWeek] || ''), String(state.dayOfWeek));
    if (state.virtualMode && state.virtualMode !== 'all') addPill('virtual', (labels.virtual || 'Virtual') + ': ' + state.virtualMode, state.virtualMode);
    if (state.hideCancelled) addPill('hide_cancelled', labels.hide_cancelled || 'Hide cancelled', '1');
    if (state.hidePostponed) addPill('hide_postponed', labels.hide_postponed || 'Hide postponed', '1');

    (state.categoryIds || []).forEach(function (id) {
      var t = (config.terms.categories || []).find(function (x) {
        return x.id === id;
      });
      addPill('category', (labels.acc && labels.acc.category ? labels.acc.category : 'Category') + ': ' + (t ? t.name : id), String(id));
    });
    (state.tagIds || []).forEach(function (id) {
      var t = (config.terms.tags || []).find(function (x) {
        return x.id === id;
      });
      addPill('tag', (labels.acc && labels.acc.tags ? labels.acc.tags : 'Tag') + ': ' + (t ? t.name : id), String(id));
    });
    (state.venueIds || []).forEach(function (id) {
      var t = (config.terms.venues || []).find(function (x) {
        return x.id === id;
      });
      addPill('venue', (labels.acc && labels.acc.venues ? labels.acc.venues : 'Venue') + ': ' + (t ? t.name : id), String(id));
    });
    (state.organizerIds || []).forEach(function (id) {
      var t = (config.terms.organizers || []).find(function (x) {
        return x.id === id;
      });
      addPill('organizer', (labels.acc && labels.acc.organizers ? labels.acc.organizers : 'Organizer') + ': ' + (t ? t.name : id), String(id));
    });

    var $pills = $widget.find('[data-pills]').empty();
    pills.forEach(function (p) {
      var $pill = $('<span class="evt-pill"></span>');
      $pill.append(document.createTextNode(p.label));
      var $x = $('<button type="button" class="evt-pill__x" aria-label="' + core.escapeHtml(labels.remove || 'Remove') + '">×</button>');
      $x.data('pill', p);
      $pill.append($x);
      $pills.append($pill);
    });
    $widget.find('[data-selections]').toggle(pills.length > 0);
  }

  function renderFilters($widget, config, state) {
    var labels = (config && config.labels) || {};
    var dowShort = Array.isArray(labels.dow_short) && labels.dow_short.length === 7 ? labels.dow_short : ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    var fromLabel = labels.from || 'From';
    var toLabel = labels.to || 'To';
    var enabled = (config && config.filtersEnabled) || {};

    function setAccVisible(key, on) {
      var $acc = $widget.find('.evt-acc[data-acc="' + key + '"]');
      $acc.toggle(!!on);
    }

    setAccVisible('category', enabled.category);
    setAccVisible('cost', enabled.cost);
    setAccVisible('tags', enabled.tags);
    setAccVisible('venues', enabled.venues);
    setAccVisible('organizers', enabled.organizers);
    setAccVisible('day', enabled.day);
    setAccVisible('country-city', enabled.countryCity);
    setAccVisible('status', enabled.status);
    setAccVisible('virtual', enabled.virtual);
    setAccVisible('date', true);

    $widget.off('click.evtAcc').on('click.evtAcc', '.evt-acc__head', function () {
      var $head = $(this);
      var $acc = $head.closest('.evt-acc');
      var $panel = $acc.find('.evt-acc__panel');
      var expanded = $head.attr('aria-expanded') === 'true';
      $head.attr('aria-expanded', expanded ? 'false' : 'true');
      $panel.prop('hidden', expanded);
      $acc.find('.evt-acc__icon').text(expanded ? '+' : '−');
    });

    // Category list
    var categories = (config.terms && config.terms.categories) || [];
    var $cat = $widget.find('[data-panel="category"]').empty();
    if (enabled.category) {
      var catHtml = '<div class="evt-filter__list">';
      categories.forEach(function (t) {
        var checked = state.categoryIds.indexOf(t.id) !== -1 ? ' checked' : '';
        catHtml +=
          '<label class="evt-filter__row"><input type="checkbox" data-filter="category" value="' +
          t.id +
          '"' +
          checked +
          '> ' +
          core.escapeHtml(t.name) +
          '</label>';
      });
      catHtml += '</div>';
      $cat.html(catHtml);
    }

    // Tags as pills
    var tags = (config.terms && config.terms.tags) || [];
    var $tags = $widget.find('[data-panel="tags"]').empty();
    if (enabled.tags) {
      var tagsHtml = '<div class="evt-filter__pills">';
      tags.forEach(function (t) {
        var active = state.tagIds.indexOf(t.id) !== -1 ? ' is-active' : '';
        tagsHtml +=
          '<button type="button" class="evt-filter__pillbtn' +
          active +
          '" data-filter="tag" data-id="' +
          t.id +
          '">' +
          core.escapeHtml(t.name) +
          '</button>';
      });
      tagsHtml += '</div>';
      $tags.html(tagsHtml);
    }

    // Venues list
    var venues = (config.terms && config.terms.venues) || [];
    var $venues = $widget.find('[data-panel="venues"]').empty();
    if (enabled.venues) {
      var vHtml = '<div class="evt-filter__list">';
      venues.forEach(function (t) {
        var checked = state.venueIds.indexOf(t.id) !== -1 ? ' checked' : '';
        vHtml +=
          '<label class="evt-filter__row"><input type="checkbox" data-filter="venue" value="' +
          t.id +
          '"' +
          checked +
          '> ' +
          core.escapeHtml(t.name) +
          '</label>';
      });
      vHtml += '</div>';
      $venues.html(vHtml);
    }

    // Organizers list
    var orgs = (config.terms && config.terms.organizers) || [];
    var $orgs = $widget.find('[data-panel="organizers"]').empty();
    if (enabled.organizers) {
      var oHtml = '<div class="evt-filter__list">';
      orgs.forEach(function (t) {
        var checked = state.organizerIds.indexOf(t.id) !== -1 ? ' checked' : '';
        oHtml +=
          '<label class="evt-filter__row"><input type="checkbox" data-filter="organizer" value="' +
          t.id +
          '"' +
          checked +
          '> ' +
          core.escapeHtml(t.name) +
          '</label>';
      });
      oHtml += '</div>';
      $orgs.html(oHtml);
    }

    // Day of week
    var $day = $widget.find('[data-panel="day"]').empty();
    if (enabled.day) {
      var dayNames = dowShort;
      var dHtml = '<div class="evt-filter__list">';
      dayNames.forEach(function (label, i) {
        var checked = state.dayOfWeek === i ? ' checked' : '';
        dHtml +=
          '<label class="evt-filter__row"><input type="radio" name="evt_dow" data-filter="dow" value="' +
          i +
          '"' +
          checked +
          '> ' +
          core.escapeHtml(label) +
          '</label>';
      });
      dHtml +=
        '<label class="evt-filter__row"><input type="radio" name="evt_dow" data-filter="dow" value=""' +
        (state.dayOfWeek === null ? ' checked' : '') +
        '> ' + core.escapeHtml(labels.any || 'Any') + '</label>';
      dHtml += '</div>';
      $day.html(dHtml);
    }

    // Country / City (lists)
    var $cc = $widget.find('[data-panel="country-city"]').empty();
    if (enabled.countryCity) {
      var countries = config.countries || [];
      var cities = config.cities || [];
      var ccHtml = '';
      ccHtml += '<div class="evt-filter__list">';
      ccHtml += '<label class="evt-filter__row">' + core.escapeHtml(labels.country || 'Country') + '</label>';
      ccHtml += '<select data-filter="country" class="evt-browser__view" style="width:100%">';
      ccHtml += '<option value="">' + core.escapeHtml(labels.any || 'Any') + '</option>';
      countries.forEach(function (c) {
        var sel = state.country === c ? ' selected' : '';
        ccHtml += '<option value="' + core.escapeHtml(c) + '"' + sel + '>' + core.escapeHtml(c) + '</option>';
      });
      ccHtml += '</select>';
      ccHtml += '</div>';
      ccHtml += '<div class="evt-filter__list" style="margin-top:10px">';
      ccHtml += '<label class="evt-filter__row">' + core.escapeHtml(labels.city || 'City') + '</label>';
      ccHtml += '<select data-filter="city" class="evt-browser__view" style="width:100%">';
      ccHtml += '<option value="">' + core.escapeHtml(labels.any || 'Any') + '</option>';
      cities.forEach(function (c) {
        var sel = state.city === c ? ' selected' : '';
        ccHtml += '<option value="' + core.escapeHtml(c) + '"' + sel + '>' + core.escapeHtml(c) + '</option>';
      });
      ccHtml += '</select>';
      ccHtml += '</div>';
      $cc.html(ccHtml);
    }

    // Status (hide cancelled/postponed)
    var $status = $widget.find('[data-panel="status"]').empty();
    if (enabled.status) {
      var sHtml = '<div class="evt-filter__list">';
      sHtml +=
        '<label class="evt-filter__row"><input type="checkbox" data-filter="hide_cancelled" value="1"' +
        (state.hideCancelled ? ' checked' : '') +
        '> ' + core.escapeHtml(labels.hide_cancelled || 'Hide cancelled') + '</label>';
      sHtml +=
        '<label class="evt-filter__row"><input type="checkbox" data-filter="hide_postponed" value="1"' +
        (state.hidePostponed ? ' checked' : '') +
        '> ' + core.escapeHtml(labels.hide_postponed || 'Hide postponed') + '</label>';
      sHtml += '</div>';
      $status.html(sHtml);
    }

    // Virtual events
    var $virt = $widget.find('[data-panel="virtual"]').empty();
    if (enabled.virtual) {
      var vmode = state.virtualMode || 'all';
      var vHtml2 = '<div class="evt-filter__list">';
      ['all', 'only', 'hide'].forEach(function (m) {
        var label =
          m === 'all'
            ? (labels.virtual_all || 'Show all')
            : m === 'only'
              ? (labels.virtual_only || 'Show only virtual')
              : (labels.virtual_hide || 'Hide virtual');
        vHtml2 +=
          '<label class="evt-filter__row"><input type="radio" name="evt_virtual" data-filter="virtual" value="' +
          m +
          '"' +
          (vmode === m ? ' checked' : '') +
          '> ' +
          label +
          '</label>';
      });
      vHtml2 += '</div>';
      $virt.html(vHtml2);
    }

    // Cost slider (two ranges)
    var $cost = $widget.find('[data-panel="cost"]').empty();
    if (enabled.cost) {
      var min = Number.isFinite(config.costMin) ? config.costMin : 0;
      var max = Number.isFinite(config.costMax) && config.costMax > 0 ? config.costMax : 100;
      var curMin = state.costMin != null ? state.costMin : min;
      var curMax = state.costMax != null ? state.costMax : max;
      var cHtml =
        '<div class="evt-filter__list">' +
        '<div class="evt-filter__row"><strong>$</strong> <span data-cost-label>' +
        core.escapeHtml(curMin) +
        ' - ' +
        core.escapeHtml(curMax) +
        '</span></div>' +
        '<input type="range" data-filter="cost_min" min="' +
        min +
        '" max="' +
        max +
        '" value="' +
        curMin +
        '" step="1" />' +
        '<input type="range" data-filter="cost_max" min="' +
        min +
        '" max="' +
        max +
        '" value="' +
        curMax +
        '" step="1" />' +
        '</div>';
      $cost.html(cHtml);
    }

    // Date range panel (always available, used primarily on mobile)
    var $date = $widget.find('[data-panel="date"]').empty();
    var dateHtml = '<div class="evt-filter__list">';
    dateHtml += '<label class="evt-filter__row">' + core.escapeHtml(fromLabel) + '</label>';
    dateHtml += '<input type="date" class="evt-browser__from evt-browser__from--sidebar" data-filter="range_from" value="' + core.escapeHtml(state.from || '') + '" />';
    dateHtml += '<label class="evt-filter__row" style="margin-top:10px">' + core.escapeHtml(toLabel) + '</label>';
    dateHtml += '<input type="date" class="evt-browser__to evt-browser__to--sidebar" data-filter="range_to" value="' + core.escapeHtml(state.to || '') + '" />';
    dateHtml += '</div>';
    $date.html(dateHtml);

    updateSelections($widget, config, state);
  }

  function buildQueryParams(view, anchor, config, state, page) {
    var params = {
      view: view,
      year: anchor.getFullYear(),
      month: anchor.getMonth() + 1,
      day: anchor.getDate(),
      limit: -1,
      full: 1
    };

    if (page && page > 1) params.page = page;

    if (state.keyword) params.s = state.keyword;
    if (state.onlyCapacity) params.only_capacity = 1;
    if (state.hideCancelled) params.hide_cancelled = 1;
    if (state.hidePostponed) params.hide_postponed = 1;

    if (state.from) params.start_after = state.from;
    if (state.to) params.end_before = state.to;

    if (state.categoryIds.length) params.category_ids = state.categoryIds.join(',');
    if (state.tagIds.length) params.tag_ids = state.tagIds.join(',');
    if (state.venueIds.length) params.venue_ids = state.venueIds.join(',');
    if (state.organizerIds.length) params.organizer_ids = state.organizerIds.join(',');
    if (state.dayOfWeek != null) params.day_of_week = state.dayOfWeek;
    if (state.country) params.country = state.country;
    if (state.city) params.city = state.city;
    if (state.virtualMode && state.virtualMode !== 'all') params.virtual_mode = state.virtualMode;
    if (state.costMin != null) params.cost_min = state.costMin;
    if (state.costMax != null) params.cost_max = state.costMax;

    if (view === 'list' || view === 'grid' || view === 'summary' || view === 'map') {
      params.limit = config.itemsPerList || 50;
      params.list_days = config.listRangeDays || 90;
    }
    return params;
  }

  window.EvtTicketsEventsBrowser.filters = {
    renderFilters: renderFilters,
    buildQueryParams: buildQueryParams
  };
})(jQuery);
