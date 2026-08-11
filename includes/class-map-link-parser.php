<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Best-effort parsing of map share links into coordinates.
 *
 * Note: short links (maps.app.goo.gl / goo.gl/maps) require a redirect follow,
 * which we intentionally avoid (networkless + privacy-friendly).
 */
class Map_Link_Parser
{
    /**
     * @return array{lat:float,lng:float}|null
     */
    public static function extract_coords(string $url): ?array
    {
        $url = trim($url);
        if ('' === $url) {
            return null;
        }

        // Google "place" URLs often include the actual pin location as ...!3d{lat}!4d{lng}...
        // Prefer this over the map center "@lat,lng", which can drift.
        if (preg_match('/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/', $url, $m)) {
            return self::validate((float) $m[1], (float) $m[2]);
        }

        // Common pattern: .../@{lat},{lng},...
        if (preg_match('/@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/', $url, $m)) {
            return self::validate((float) $m[1], (float) $m[2]);
        }

        $parts = wp_parse_url($url);
        if (is_array($parts) && ! empty($parts['query'])) {
            parse_str($parts['query'], $q);

            foreach (['q', 'query', 'destination', 'll'] as $key) {
                if (empty($q[$key]) || ! is_string($q[$key])) {
                    continue;
                }
                if (preg_match('/(-?\d+(?:\.\d+)?)\\s*,\\s*(-?\d+(?:\.\d+)?)/', $q[$key], $m)) {
                    return self::validate((float) $m[1], (float) $m[2]);
                }
            }
        }

        // Last resort: any "lat,lng" in the URL.
        if (preg_match('/(-?\d{1,2}(?:\.\d+)?)\\s*,\\s*(-?\d{1,3}(?:\.\d+)?)/', $url, $m)) {
            return self::validate((float) $m[1], (float) $m[2]);
        }

        return null;
    }

    /**
     * @return array{lat:float,lng:float}|null
     */
    private static function validate(float $lat, float $lng): ?array
    {
        if ($lat < -90 || $lat > 90) {
            return null;
        }
        if ($lng < -180 || $lng > 180) {
            return null;
        }
        return ['lat' => $lat, 'lng' => $lng];
    }
}
