<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Adds lat/lng fields for events (for map view).
 */
class Event_Location_Meta
{
    public const LAT_META = '_evt_event_lat';
    public const LNG_META = '_evt_event_lng';
    public const MAP_URL_META = '_evt_event_map_url';

    public function __construct()
    {
        add_action('evt_tickets_event_section_location', [$this, 'render_event_details_fields'], 20);
        add_action('evt_tickets_event_details_save', [$this, 'save_event_details_fields'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public function render_event_details_fields(\WP_Post $post): void
    {
        $lat = get_post_meta($post->ID, self::LAT_META, true);
        $lng = get_post_meta($post->ID, self::LNG_META, true);
        $map_url = get_post_meta($post->ID, self::MAP_URL_META, true);
?>
        <p class="evt-event-field">
            <label for="evt_event_map_url"><strong><?php esc_html_e('Google Maps share link', 'Event-Tickets-for-Elementor'); ?> (<?php esc_html_e('optional', 'Event-Tickets-for-Elementor'); ?>)</strong></label><br />
            <input
                type="url"
                id="evt_event_map_url"
                name="evt_event_map_url"
                class="regular-text"
                value="<?php echo esc_attr($map_url); ?>"
                placeholder="<?php esc_attr_e('Paste a Google Maps link…', 'Event-Tickets-for-Elementor'); ?>" />
        <div id="evt-map-url-helper"></div>
        <p class="description">
            <?php esc_html_e('Paste a full Google Maps URL to auto-fill latitude/longitude.', 'Event-Tickets-for-Elementor'); ?>
            <?php esc_html_e('Short links (maps.app.goo.gl) may need to be opened and expanded first.', 'Event-Tickets-for-Elementor'); ?>
        </p>
        </p>

        <div class="evt-event-grid evt-event-grid--2">
            <p class="evt-event-field">
                <label for="evt_event_lat"><strong><?php esc_html_e('Latitude', 'Event-Tickets-for-Elementor'); ?> (<?php esc_html_e('optional', 'Event-Tickets-for-Elementor'); ?>)</strong></label><br />
                <input type="text" id="evt_event_lat" name="evt_event_lat" class="regular-text" value="<?php echo esc_attr($lat); ?>" />
            </p>
            <p class="evt-event-field">
                <label for="evt_event_lng"><strong><?php esc_html_e('Longitude', 'Event-Tickets-for-Elementor'); ?> (<?php esc_html_e('optional', 'Event-Tickets-for-Elementor'); ?>)</strong></label><br />
                <input type="text" id="evt_event_lng" name="evt_event_lng" class="regular-text" value="<?php echo esc_attr($lng); ?>" />
            </p>
        </div>
<?php
    }

    public function enqueue_admin_assets(string $hook): void
    {
        if (! in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (! $screen || empty($screen->post_type) || $screen->post_type !== CPT_Events::POST_TYPE) {
            return;
        }

        wp_enqueue_script(
            'evt-tickets-admin-event-location',
            EVT_TICKETS_PLUGIN_URL . 'assets/js/admin-event-location.js',
            [],
            defined('EVT_TICKETS_VERSION') ? \EVT_TICKETS_VERSION : 'dev',
            true
        );

        wp_localize_script(
            'evt-tickets-admin-event-location',
            'evtTicketsEventLocation',
            [
                'shortLinkMessage' => __('Short link detected. Click “Open map”, then copy the full Google Maps URL from the address bar and paste it here.', 'Event-Tickets-for-Elementor'),
                'openMapButton' => __('Open map', 'Event-Tickets-for-Elementor'),
                'coordsDetectedMessage' => __('Coordinates detected and applied:', 'Event-Tickets-for-Elementor'),
            ]
        );
    }

    public function save_event_details_fields(int $post_id, \WP_Post $post): void
    {
        if ($post->post_type !== CPT_Events::POST_TYPE) {
            return;
        }

        $lat = isset($_POST['evt_event_lat']) ? sanitize_text_field(wp_unslash($_POST['evt_event_lat'])) : '';
        $lng = isset($_POST['evt_event_lng']) ? sanitize_text_field(wp_unslash($_POST['evt_event_lng'])) : '';
        $map_url = isset($_POST['evt_event_map_url']) ? esc_url_raw(wp_unslash($_POST['evt_event_map_url'])) : '';

        if ('' !== $map_url) {
            $coords = $this->extract_coords_from_google_maps_url($map_url);
            if ($coords) {
                $lat = (string) $coords['lat'];
                $lng = (string) $coords['lng'];
            }
            update_post_meta($post_id, self::MAP_URL_META, $map_url);
        } else {
            delete_post_meta($post_id, self::MAP_URL_META);
        }

        // Validate numerically so only real coordinates are stored — arbitrary
        // strings (or out-of-range values) are rejected.
        $lat = $this->sanitize_coord($lat, -90.0, 90.0);
        $lng = $this->sanitize_coord($lng, -180.0, 180.0);

        if ($lat !== '') {
            update_post_meta($post_id, self::LAT_META, $lat);
        } else {
            delete_post_meta($post_id, self::LAT_META);
        }

        if ($lng !== '') {
            update_post_meta($post_id, self::LNG_META, $lng);
        } else {
            delete_post_meta($post_id, self::LNG_META);
        }
    }

    /**
     * Validate and normalize a coordinate value.
     *
     * Returns an empty string for missing, non-numeric or out-of-range input,
     * otherwise a string rounded to 6 decimal places (~0.1 m precision).
     *
     * @param mixed $value
     */
    private function sanitize_coord($value, float $min, float $max): string
    {
        $value = trim((string) $value);
        if ('' === $value || ! is_numeric($value)) {
            return '';
        }

        $num = (float) $value;
        if ($num < $min || $num > $max) {
            return '';
        }

        return (string) round($num, 6);
    }

    /**
     * Best-effort extraction for common Google Maps share URL formats.
     *
     * Limitations: short links like maps.app.goo.gl/goo.gl/maps can't be resolved without network access.
     *
     * @return array{lat:float,lng:float}|null
     */
    private function extract_coords_from_google_maps_url(string $url): ?array
    {
        $url = trim($url);
        if ('' === $url) {
            return null;
        }

        // Prefer the actual place pin coords used in many Google "place" URLs.
        if (preg_match('/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/', $url, $m)) {
            return $this->validate_coords((float) $m[1], (float) $m[2]);
        }

        // Common pattern: .../@{lat},{lng},...
        if (preg_match('/@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/', $url, $m)) {
            return $this->validate_coords((float) $m[1], (float) $m[2]);
        }

        $parts = wp_parse_url($url);
        if (is_array($parts) && ! empty($parts['query'])) {
            parse_str($parts['query'], $q);

            foreach (['q', 'query', 'destination', 'll'] as $key) {
                if (empty($q[$key]) || ! is_string($q[$key])) {
                    continue;
                }

                if (preg_match('/(-?\d+(?:\.\d+)?)\\s*,\\s*(-?\d+(?:\.\d+)?)/', $q[$key], $m)) {
                    return $this->validate_coords((float) $m[1], (float) $m[2]);
                }
            }
        }

        // Last resort: any "lat,lng" in the URL.
        if (preg_match('/(-?\d{1,2}(?:\.\d+)?)\\s*,\\s*(-?\d{1,3}(?:\.\d+)?)/', $url, $m)) {
            return $this->validate_coords((float) $m[1], (float) $m[2]);
        }

        return null;
    }

    /**
     * @return array{lat:float,lng:float}|null
     */
    private function validate_coords(float $lat, float $lng): ?array
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
