(function ($) {
    'use strict';

    function extractCoordsFromUrl(url) {
        if (!url) return null;
        // Prefer Google place pin coords (!3dlat!4dlng) over map center (@lat,lng).
        var mPin = String(url).match(/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/);
        if (mPin) return { lat: parseFloat(mPin[1]), lng: parseFloat(mPin[2]) };
        var m = String(url).match(/@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/);
        if (m) return { lat: parseFloat(m[1]), lng: parseFloat(m[2]) };
        // Look for common query params like q=lat,lng or ll=lat,lng
        var qm = String(url).match(/[?&](?:q|query|destination|ll)=([^&#]+)/);
        if (qm) {
            var decoded = decodeURIComponent(qm[1]);
            var m2 = decoded.match(/(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)/);
            if (m2) return { lat: parseFloat(m2[1]), lng: parseFloat(m2[2]) };
        }
        return null;
    }

    function initMap($scope) {
        var $widget = $scope.find('.evt-events-map');
        if (! $widget.length) {
            return;
        }

        if (typeof L === 'undefined') {
            return;
        }

        var markers = $widget.data('markers') || [];
        var linkMarkers = $widget.data('link-markers') || [];
        var i18nRaw = $widget.attr('data-i18n') || '{}';
        var i18n = {};
        try { i18n = JSON.parse(i18nRaw || '{}') || {}; } catch (e) { i18n = {}; }
        var tView = i18n.view_event || 'View event';
        var tOpen = i18n.open_map || 'Open map';

        var $canvas = $widget.find('.evt-events-map__canvas');
        var map = L.map($canvas[0]).setView([0, 0], 2);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        var bounds = [];
        markers.forEach(function (marker) {
            var m = L.marker([marker.lat, marker.lng]).addTo(map);
            var popup = '<strong>' + marker.title + '</strong><br/>' +
                (marker.date_time ? marker.date_time + '<br/>' : '') +
                '<a href="' + marker.url + '">' + tView + '</a>' +
                (marker.map_url ? ('<br/><a href="' + marker.map_url + '" target="_blank" rel="noopener noreferrer">' + tOpen + '</a>') : '');
            m.bindPopup(popup);
            bounds.push([marker.lat, marker.lng]);
        });

        // Best-effort pins for "map link only" events (works for share URLs containing coordinates).
        linkMarkers.forEach(function (marker) {
            if (!marker || !marker.map_url) return;
            var coords = extractCoordsFromUrl(marker.map_url);
            if (!coords || !isFinite(coords.lat) || !isFinite(coords.lng)) return;
            if (coords.lat < -90 || coords.lat > 90 || coords.lng < -180 || coords.lng > 180) return;

            var m = L.marker([coords.lat, coords.lng]).addTo(map);
            var popup = '<strong>' + marker.title + '</strong><br/>' +
                (marker.date_time ? marker.date_time + '<br/>' : '') +
                '<a href="' + marker.url + '">' + tView + '</a>' +
                '<br/><a href="' + marker.map_url + '" target="_blank" rel="noopener noreferrer">' + tOpen + '</a>';
            m.bindPopup(popup);
            bounds.push([coords.lat, coords.lng]);
        });

        if (bounds.length) {
            map.fitBounds(bounds, { padding: [20, 20] });
        }
    }

    $(window).on('elementor/frontend/init', function () {
        if (! window.elementorFrontend) {
            return;
        }

        elementorFrontend.hooks.addAction('frontend/element_ready/evt_events_map.default', initMap);
    });
})(jQuery);
