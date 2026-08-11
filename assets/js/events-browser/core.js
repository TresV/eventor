(function ($) {
  'use strict';

  window.EvtTicketsEventsBrowser = window.EvtTicketsEventsBrowser || {};

  function pad2(n) {
    return (n < 10 ? '0' : '') + n;
  }

  function ymdFromDate(d) {
    return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate());
  }

  function startOfDay(d) {
    return new Date(d.getFullYear(), d.getMonth(), d.getDate());
  }

  function monthTitle(d) {
    return d.toLocaleString(undefined, { month: 'long', year: 'numeric' });
  }

  function groupByMonth(events) {
    var out = {};
    (events || []).forEach(function (evt) {
      var localDate = evt.start_local_date || '';
      var key = '';
      if (typeof localDate === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(localDate)) {
        key = localDate.slice(0, 7);
      } else {
        var ts = evt.start_ts || evt.start;
        if (!ts) return;
        var d = new Date(ts * 1000);
        key = d.getFullYear() + '-' + pad2(d.getMonth() + 1);
      }
      if (!out[key]) out[key] = [];
      out[key].push(evt);
    });
    Object.keys(out).forEach(function (k) {
      out[k].sort(function (a, b) {
        return (a.start_ts || a.start || 0) - (b.start_ts || b.start || 0);
      });
    });
    return out;
  }

  function extractEventTs(evt) {
    return { start: evt.start_ts || evt.start || 0, end: evt.end_ts || evt.end || 0 };
  }

  function eventDayKey(evt) {
    if (evt && typeof evt.start_local_date === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(evt.start_local_date)) {
      return evt.start_local_date;
    }
    var ts = evt && (evt.start_ts || evt.start || 0);
    if (!ts) return '';
    return ymdFromDate(new Date(ts * 1000));
  }

  function formatTimeRange(evt) {
    var ts = extractEventTs(evt);
    if (!ts.start) return '';

    var opts = { hour: '2-digit', minute: '2-digit' };
    var tzIana = (evt && evt.timezone_iana) || '';
    var tzSuffix = (evt && evt.timezone) || '';
    // Render in the site timezone (not the visitor's) so the shown time always
    // matches the timezone abbreviation appended below.
    if (tzIana) opts.timeZone = tzIana;

    var start = new Date(ts.start * 1000);
    if (!ts.end || ts.end <= ts.start) {
      return start.toLocaleTimeString(undefined, opts) + (tzSuffix ? ' ' + tzSuffix : '');
    }
    var end = new Date(ts.end * 1000);
    return (
      start.toLocaleTimeString(undefined, opts) +
      ' - ' +
      end.toLocaleTimeString(undefined, opts) +
      (tzSuffix ? ' ' + tzSuffix : '')
    );
  }

  function escapeHtml(s) {
    return String(s || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function styleVarsToInline(style) {
    if (!style || typeof style !== 'object') return '';
    var parts = [];
    Object.keys(style).forEach(function (key) {
      if (!style[key]) return;
      parts.push(key + ':' + String(style[key]).replace(/"/g, '&quot;'));
    });
    return parts.join(';');
  }

  window.EvtTicketsEventsBrowser.core = {
    pad2: pad2,
    ymdFromDate: ymdFromDate,
    startOfDay: startOfDay,
    monthTitle: monthTitle,
    groupByMonth: groupByMonth,
    extractEventTs: extractEventTs,
    eventDayKey: eventDayKey,
    formatTimeRange: formatTimeRange,
    escapeHtml: escapeHtml,
    styleVarsToInline: styleVarsToInline
  };
})(jQuery);
