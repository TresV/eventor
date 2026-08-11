<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Optional geocoder for event locations when only a human location string exists.
 *
 * Uses OpenStreetMap Nominatim (no API key) and caches results per event.
 */
class Event_Geocoder
{
    public const GEO_LAT_META = '_evt_event_geo_lat';
    public const GEO_LNG_META = '_evt_event_geo_lng';
    public const GEO_AT_META  = '_evt_event_geo_at';

    /**
     * @return array{lat:float,lng:float}|null
     */
    public function get_or_geocode(int $event_id, string $query, int $ttl_days = 30): ?array
    {
        $event_id = absint($event_id);
        if (! $event_id || '' === trim($query)) {
            return null;
        }

        $cached_lat = get_post_meta($event_id, self::GEO_LAT_META, true);
        $cached_lng = get_post_meta($event_id, self::GEO_LNG_META, true);
        $cached_at  = (int) get_post_meta($event_id, self::GEO_AT_META, true);

        if ($cached_lat !== '' && $cached_lng !== '') {
            if ($cached_at > 0 && $cached_at > (time() - ($ttl_days * DAY_IN_SECONDS))) {
                return $this->validate((float) $cached_lat, (float) $cached_lng);
            }
        }

        $coords = $this->geocode_query($query);
        if (! $coords) {
            return null;
        }

        update_post_meta($event_id, self::GEO_LAT_META, (string) $coords['lat']);
        update_post_meta($event_id, self::GEO_LNG_META, (string) $coords['lng']);
        update_post_meta($event_id, self::GEO_AT_META, time());

        return $coords;
    }

    /**
     * @return array{lat:float,lng:float}|null
     */
    private function geocode_query(string $query): ?array
    {
        $query = trim($query);
        if ('' === $query) {
            return null;
        }

        $lock_key = 'evt_geo_lock_' . md5($query);
        if (get_transient($lock_key)) {
            return null;
        }
        set_transient($lock_key, 1, 10);

        $url = add_query_arg(
            [
                'q'      => $query,
                'format' => 'json',
                'limit'  => 1,
            ],
            'https://nominatim.openstreetmap.org/search'
        );

        $ua = 'EventTicketsElementor/' . (defined('EVT_TICKETS_PLUGIN_FILE') ? 'plugin' : 'unknown') . ' (' . home_url('/') . ')';

        $resp = wp_remote_get(
            $url,
            [
                'timeout' => 6,
                'headers' => [
                    'User-Agent' => $ua,
                ],
            ]
        );

        if (is_wp_error($resp)) {
            return null;
        }

        $body = wp_remote_retrieve_body($resp);
        if (! is_string($body) || '' === $body) {
            return null;
        }

        $data = json_decode($body, true);
        if (! is_array($data) || empty($data[0]['lat']) || empty($data[0]['lon'])) {
            return null;
        }

        return $this->validate((float) $data[0]['lat'], (float) $data[0]['lon']);
    }

    /**
     * @return array{lat:float,lng:float}|null
     */
    private function validate(float $lat, float $lng): ?array
    {
        if (! is_finite($lat) || ! is_finite($lng)) {
            return null;
        }
        if ($lat < -90 || $lat > 90) {
            return null;
        }
        if ($lng < -180 || $lng > 180) {
            return null;
        }
        return ['lat' => $lat, 'lng' => $lng];
    }
}

