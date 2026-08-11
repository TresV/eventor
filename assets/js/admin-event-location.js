/* global evtTicketsEventLocation */
(function () {
  'use strict';

  function isShortGoogleMapsUrl(urlString) {
    try {
      var url = new URL(urlString);
      var host = (url.hostname || '').toLowerCase();
      if (host === 'maps.app.goo.gl') return true;
      if (host === 'goo.gl' && /^\/maps/i.test(url.pathname || '')) return true;
      return false;
    } catch (e) {
      return false;
    }
  }

  function parseCoordsFromGoogleMapsUrl(urlString) {
    var trimmed = (urlString || '').trim();
    if (!trimmed) return null;

    // Prefer Google place pin coords (!3dlat!4dlng) over map center (@lat,lng).
    var pinMatch = trimmed.match(/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/);
    if (pinMatch) return validateCoords(parseFloat(pinMatch[1]), parseFloat(pinMatch[2]));

    var atMatch = trimmed.match(/@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/);
    if (atMatch) return validateCoords(parseFloat(atMatch[1]), parseFloat(atMatch[2]));

    try {
      var url = new URL(trimmed);
      var params = url.searchParams;
      var keys = ['q', 'query', 'destination', 'll'];
      for (var i = 0; i < keys.length; i++) {
        var val = params.get(keys[i]);
        if (!val) continue;
        var m = val.match(/(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)/);
        if (m) return validateCoords(parseFloat(m[1]), parseFloat(m[2]));
      }
    } catch (e) {
      // ignore and fall through
    }

    var anyMatch = trimmed.match(/(-?\d{1,2}(?:\.\d+)?)\s*,\s*(-?\d{1,3}(?:\.\d+)?)/);
    if (anyMatch) return validateCoords(parseFloat(anyMatch[1]), parseFloat(anyMatch[2]));

    return null;
  }

  function validateCoords(lat, lng) {
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return null;
    if (lat < -90 || lat > 90) return null;
    if (lng < -180 || lng > 180) return null;
    return { lat: lat, lng: lng };
  }

  function el(tag, attrs, text) {
    var node = document.createElement(tag);
    if (attrs) {
      Object.keys(attrs).forEach(function (k) {
        if (k === 'class') node.className = attrs[k];
        else if (k === 'href') node.setAttribute('href', attrs[k]);
        else if (k === 'target') node.setAttribute('target', attrs[k]);
        else if (k === 'rel') node.setAttribute('rel', attrs[k]);
        else if (k === 'type') node.setAttribute('type', attrs[k]);
        else node.setAttribute(k, attrs[k]);
      });
    }
    if (typeof text === 'string') node.appendChild(document.createTextNode(text));
    return node;
  }

  function init() {
    var mapUrlInput = document.getElementById('evt_event_map_url');
    var latInput = document.getElementById('evt_event_lat');
    var lngInput = document.getElementById('evt_event_lng');
    if (!mapUrlInput || !latInput || !lngInput) return;

    var helper = document.getElementById('evt-map-url-helper');
    if (!helper) {
      helper = el('div', { id: 'evt-map-url-helper' });
      mapUrlInput.insertAdjacentElement('afterend', helper);
    }

    var latTouched = false;
    var lngTouched = false;
    var lastAuto = null;
    var debounceTimer = null;

    function clearHelper() {
      helper.innerHTML = '';
    }

    function renderShortLinkNotice(urlString) {
      clearHelper();
      var wrap = el('div', { class: 'notice notice-warning inline', style: 'margin:8px 0 0; padding: 8px 12px;' });
      wrap.appendChild(el('p', { style: 'margin:0 0 6px;' }, evtTicketsEventLocation.shortLinkMessage));
      var btn = el(
        'a',
        { class: 'button button-secondary', href: urlString, target: '_blank', rel: 'noopener noreferrer' },
        evtTicketsEventLocation.openMapButton
      );
      wrap.appendChild(btn);
      helper.appendChild(wrap);
    }

    function renderParsedNotice(coords) {
      clearHelper();
      var wrap = el('div', { class: 'notice notice-success inline', style: 'margin:8px 0 0; padding: 8px 12px;' });
      wrap.appendChild(
        el('p', { style: 'margin:0;' }, evtTicketsEventLocation.coordsDetectedMessage + ' ' + coords.lat + ', ' + coords.lng)
      );
      helper.appendChild(wrap);
    }

    function maybeAutofill(coords) {
      if (!coords) return;

      var latCurrent = (latInput.value || '').trim();
      var lngCurrent = (lngInput.value || '').trim();

      var shouldFillLat = !latTouched || latCurrent === '' || (lastAuto && latCurrent === lastAuto.lat);
      var shouldFillLng = !lngTouched || lngCurrent === '' || (lastAuto && lngCurrent === lastAuto.lng);

      if (shouldFillLat) latInput.value = String(coords.lat);
      if (shouldFillLng) lngInput.value = String(coords.lng);
      lastAuto = { lat: String(coords.lat), lng: String(coords.lng) };
    }

    function handleMapUrlChange() {
      var urlString = (mapUrlInput.value || '').trim();
      if (!urlString) {
        clearHelper();
        return;
      }

      if (isShortGoogleMapsUrl(urlString)) {
        renderShortLinkNotice(urlString);
        return;
      }

      var coords = parseCoordsFromGoogleMapsUrl(urlString);
      if (coords) {
        maybeAutofill(coords);
        renderParsedNotice(coords);
      } else {
        clearHelper();
      }
    }

    function debouncedHandle() {
      if (debounceTimer) window.clearTimeout(debounceTimer);
      debounceTimer = window.setTimeout(handleMapUrlChange, 250);
    }

    latInput.addEventListener('input', function () {
      latTouched = true;
    });
    lngInput.addEventListener('input', function () {
      lngTouched = true;
    });
    mapUrlInput.addEventListener('input', debouncedHandle);
    mapUrlInput.addEventListener('change', handleMapUrlChange);

    // Initial run when editing existing events.
    handleMapUrlChange();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
