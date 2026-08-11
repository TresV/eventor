(function ($) {
  'use strict';

  var core = window.EvtTicketsEventsBrowser && window.EvtTicketsEventsBrowser.core;
  if (!core) return;

  function renderStatusBadge(evt, opts) {
    opts = opts || {};
    var config = opts.config || {};
    if (!config.showStatusBadges) return '';
    var badge = evt && evt.status_badge;
    if (!badge || !badge.enabled || !badge.label) return '';
    var className = badge.class_name || 'evt-event-status';
    var style = core.styleVarsToInline(badge.style || {});
    return (
      '<span class="' +
      core.escapeHtml(className) +
      '"' +
      (style ? ' style="' + style + '"' : '') +
      '>' +
      core.escapeHtml(badge.label) +
      '</span>'
    );
  }

  function renderMonth($body, anchor, events, opts) {
    opts = opts || {};
    var labels = opts.labels || {};
    var year = anchor.getFullYear();
    var month = anchor.getMonth() + 1;
    var first = new Date(year, month - 1, 1);
    var start = new Date(first);
    var startOfWeek =
      typeof EvtTicketsEventBrowser !== 'undefined' && typeof EvtTicketsEventBrowser.startOfWeek !== 'undefined'
        ? EvtTicketsEventBrowser.startOfWeek
        : 1;
    var diff = (start.getDay() - startOfWeek + 7) % 7;
    start.setDate(start.getDate() - diff);
    start = core.startOfDay(start);

    var byDay = {};
    (events || []).forEach(function (evt) {
      var key = core.eventDayKey(evt);
      if (!key) return;
      if (!byDay[key]) byDay[key] = [];
      byDay[key].push(evt);
    });

    var html = '<div class="evt-month" data-month-view>';
    html += '<div class="evt-month__dow">';
    for (var i = 0; i < 7; i++) {
      var dd = new Date(start);
      dd.setDate(dd.getDate() + i);
      html += '<div class="evt-month__dowcell">' + dd.toLocaleDateString(undefined, { weekday: 'short' }) + '</div>';
    }
    html += '</div>';
    html += '<div class="evt-month__grid">';
    for (var cell = 0; cell < 42; cell++) {
      var dcell = new Date(start);
      dcell.setDate(dcell.getDate() + cell);
      var key2 = core.ymdFromDate(dcell);
      var dayEvents = byDay[key2] || [];
      html += '<div class="evt-month__cell" data-day="' + core.escapeHtml(key2) + '">';
      html += '<div class="evt-month__day">' + dcell.getDate() + '</div>';
      if (opts.compact) {
        if (dayEvents.length > 0) {
          html += '<div class="evt-month__dots">';
          html += '<span class="evt-month__dot" aria-hidden="true"></span>';
          html += '</div>';
        }
      } else {
        dayEvents.slice(0, 3).forEach(function (evt) {
          html +=
            '<a class="evt-month__event" href="' +
            core.escapeHtml(evt.permalink || evt.url || '#') +
            '">' +
            renderStatusBadge(evt, opts) +
            '<span class="evt-month__event-title">' +
            core.escapeHtml(evt.title) +
            '</span>' +
            '</a>';
        });
        if (dayEvents.length > 3) {
          html +=
            '<div class="evt-month__day" style="margin-top:6px">+' +
            (dayEvents.length - 3) +
            ' ' +
            core.escapeHtml(labels.more || 'more') +
            '</div>';
        }
      }
      html += '</div>';
    }
    html += '</div></div>';

    if (opts.compact) {
      html += '<div class="evt-month__detail" data-month-detail></div>';
      html +=
        '<div class="evt-month__bottomnav" data-month-bottomnav>' +
        '<button type="button" class="evt-month__bottomnav-btn evt-month__bottomnav-btn--left" data-month-nav="prev">&lt; ' +
        core.escapeHtml(new Date(year, month - 2, 1).toLocaleDateString(undefined, { month: 'short' })) +
        '</button>' +
        '<button type="button" class="evt-month__bottomnav-btn evt-month__bottomnav-center" data-month-nav="today">' +
        core.escapeHtml(labels.this_month || 'This Month') +
        '</button>' +
        '<button type="button" class="evt-month__bottomnav-btn evt-month__bottomnav-btn--right" data-month-nav="next">' +
        core.escapeHtml(new Date(year, month, 1).toLocaleDateString(undefined, { month: 'short' })) +
        ' &gt;</button>' +
        '</div>';
    }

    $body.html(html);
  }

  function renderEmptyState($body, labels, opts) {
    opts = opts || {};
    labels = labels || {};
    var title = labels.no_events_title || 'No events found';
    var hint = labels.no_events_hint || 'Try adjusting your filters or date range, or check back later.';
    var html =
      '<div class="evt-browser__empty">' +
      '<div class="evt-browser__empty-icon" aria-hidden="true">&#128197;</div>' +
      '<p class="evt-browser__empty-title">' +
      core.escapeHtml(title) +
      '</p>' +
      '<p class="evt-browser__empty-hint">' +
      core.escapeHtml(hint) +
      '</p>' +
      '</div>';
    $body.html(html);
  }

  function renderListLike($body, events, summary, opts) {
    opts = opts || {};
    var labels = opts.labels || {};
    var groups = core.groupByMonth(events);
    var keys = Object.keys(groups).sort();
    if (!keys.length) {
      renderEmptyState($body, labels, opts);
      return;
    }
    var html = '<div class="evt-list' + (summary ? ' evt-summary' : '') + '">';
    keys.forEach(function (k) {
      var year = parseInt(k.split('-')[0], 10);
      var month = parseInt(k.split('-')[1], 10);
      html += '<div class="evt-list__month">' + core.escapeHtml(core.monthTitle(new Date(year, month - 1, 1))) + '</div>';
      groups[k].forEach(function (evt) {
        var ts = core.extractEventTs(evt);
        var d = ts.start ? new Date(ts.start * 1000) : null;
        var dow = d ? d.toLocaleDateString(undefined, { weekday: 'short' }) : '';
        var time = core.formatTimeRange(evt);
        var venue = evt.venues && evt.venues.length ? evt.venues[0].name : '';
        var loc = evt.location || '';
        var meta = '';
        if (venue) meta += core.escapeHtml(venue) + (loc ? ' ' + core.escapeHtml(loc) : '');
        else meta += core.escapeHtml(loc);

        var price = '';
        if (evt.cost !== '' && evt.cost != null) {
          var c = parseFloat(evt.cost);
          price = isFinite(c) ? (c === 0 ? (labels.free || 'Free') : '$' + c.toFixed(2)) : core.escapeHtml(evt.cost);
        }
        var badgeHtml = renderStatusBadge(evt, opts);

        html += '<div class="evt-event-row">';
        html += '<div class="evt-event-row__date">';
        html += '<div class="evt-event-row__dow">' + core.escapeHtml(dow) + '</div>';
        html += '<div class="evt-event-row__day">' + (d ? d.getDate() : '') + '</div>';
        html += '<div class="evt-event-row__time">' + core.escapeHtml(time) + '</div>';
        html += '</div>';
        html += '<div>';
        if (badgeHtml) html += '<div class="evt-event-row__badge">' + badgeHtml + '</div>';
        html +=
          '<a class="evt-event-row__title" href="' +
          core.escapeHtml(evt.permalink || '#') +
          '">' +
          core.escapeHtml(evt.title) +
          '</a>';
        if (meta) html += '<div class="evt-event-row__meta">' + meta + '</div>';
        if (evt.excerpt) html += '<div class="evt-event-row__excerpt">' + core.escapeHtml(evt.excerpt) + '</div>';
        html += '<div class="evt-event-row__cta">';
        html +=
          '<a class="evt-event-row__tickets" href="' +
          core.escapeHtml(evt.permalink || '#') +
          '">' +
          core.escapeHtml(labels.get_tickets || 'Get Tickets') +
          '</a>';
        if (price) html += '<span class="evt-event-row__price">' + core.escapeHtml(price) + '</span>';
        html += '</div>';
        html += '</div>';
        if (!summary) {
          html += '<div class="evt-event-row__img-wrap">';
          if (evt.image_url) {
            html += '<img class="evt-event-row__img" src="' + core.escapeHtml(evt.image_url) + '" alt="" />';
          } else {
            html += '<div class="evt-event-row__img"></div>';
          }
          html += '</div>';
        }
        html += '</div>';
      });
    });
    html += '</div>';
    $body.html(html);
  }

  function renderGrid($body, events, columns, opts) {
    opts = opts || {};
    var labels = opts.labels || {};
    columns = columns || 3;
    if (!events || !events.length) {
      renderEmptyState($body, labels, opts);
      return;
    }
    var html = '<div class="evt-grid" style="grid-template-columns: repeat(' + columns + ', minmax(0, 1fr))">';
    events.forEach(function (evt) {
      var ts = core.extractEventTs(evt);
      var d = ts.start ? new Date(ts.start * 1000) : null;
      var dateLabel = d
        ? d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' }) + ' · ' + core.formatTimeRange(evt)
        : '';
      var badgeHtml = renderStatusBadge(evt, opts);
      html += '<div class="evt-card">';
      if (evt.image_url) html += '<img class="evt-card__img" src="' + core.escapeHtml(evt.image_url) + '" alt="" />';
      else html += '<div class="evt-card__img"></div>';
      html += '<div class="evt-card__body">';
      if (badgeHtml) html += '<div class="evt-card__badge">' + badgeHtml + '</div>';
      html += '<div class="evt-card__date">' + core.escapeHtml(dateLabel) + '</div>';
      html += '<a class="evt-card__title" href="' + core.escapeHtml(evt.permalink || '#') + '">' + core.escapeHtml(evt.title) + '</a>';
      html +=
        '<a class="evt-event-row__tickets" href="' +
        core.escapeHtml(evt.permalink || '#') +
        '">' +
        core.escapeHtml(labels.get_tickets || 'Get Tickets') +
        '</a>';
      html += '</div></div>';
    });
    html += '</div>';
    $body.html(html);
  }

  function renderMap($body, events, opts) {
    opts = opts || {};
    var labels = opts.labels || {};
    if (!events || !events.length) {
      renderEmptyState($body, labels, opts);
      return;
    }
    var html =
      '<div class="evt-mapview"><div class="evt-mapview__map" data-map></div><div class="evt-mapview__list" data-map-list></div></div>';
    $body.html(html);

    var $mapEl = $body.find('[data-map]');
    if (typeof L === 'undefined' || !$mapEl.length) return;

    var map = L.map($mapEl[0]).setView([0, 0], 2);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    var bounds = [];
    var markersById = {};
    (events || []).forEach(function (evt) {
      var lat = parseFloat(evt.lat);
      var lng = parseFloat(evt.lng);
      if (!isFinite(lat) || !isFinite(lng)) return;
      var m = L.marker([lat, lng]).addTo(map);
      m.bindPopup('<strong>' + core.escapeHtml(evt.title) + '</strong>');
      bounds.push([lat, lng]);
      markersById[evt.id] = m;
    });
    if (bounds.length) map.fitBounds(bounds, { padding: [20, 20] });

    var $list = $body.find('[data-map-list]').empty();
    (events || []).forEach(function (evt) {
      var ts = core.extractEventTs(evt);
      var d = ts.start ? new Date(ts.start * 1000) : null;
      var mon = d ? d.toLocaleDateString(undefined, { month: 'short' }) : '';
      var day = d ? d.getDate() : '';
      var time = core.formatTimeRange(evt);
      var meta = evt.location || '';
      var badgeHtml = renderStatusBadge(evt, opts);
      var thumb = evt.image_url
        ? '<img class="evt-mapview__thumb" src="' + core.escapeHtml(evt.image_url) + '" alt="" />'
        : '<div class="evt-mapview__thumb"></div>';
      var item =
        '<div class="evt-mapview__item" data-eid="' +
        core.escapeHtml(evt.id) +
        '">' +
        '<div><div class="evt-mapview__mdate">' +
        core.escapeHtml(mon) +
        '</div><div class="evt-mapview__mday">' +
        core.escapeHtml(day) +
        '</div></div>' +
        '<div><div class="evt-mapview__title">' +
        core.escapeHtml(evt.title) +
        '</div>' +
        (badgeHtml ? '<div class="evt-mapview__badge">' + badgeHtml + '</div>' : '') +
        '<div class="evt-mapview__meta">' +
        core.escapeHtml(time) +
        (meta ? ' · ' + core.escapeHtml(meta) : '') +
        '</div></div>' +
        '<div>' +
        thumb +
        '</div></div>';
      $list.append(item);
    });

    $list.off('click.evtMapItem').on('click.evtMapItem', '.evt-mapview__item', function () {
      var id = parseInt($(this).data('eid'), 10);
      var m = markersById[id];
      if (!m) return;
      map.setView(m.getLatLng(), 14);
      m.openPopup();
    });
  }

  window.EvtTicketsEventsBrowser.views = {
    renderStatusBadge: renderStatusBadge,
    renderMonth: renderMonth,
    renderListLike: renderListLike,
    renderGrid: renderGrid,
    renderMap: renderMap
  };
})(jQuery);
