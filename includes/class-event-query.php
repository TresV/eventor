<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Lightweight event query helper with date + capacity filters.
 */
class Event_Query
{
    private Event_Capacity $capacity;
    private Event_Status_Badge_Resolver $status_badges;

    /**
     * Total matching events from the last query() call (from found_posts).
     * Used to build pagination metadata for the events browser.
     */
    private int $last_total = 0;

    public function __construct(Event_Capacity $capacity, Event_Status_Badge_Resolver $status_badges)
    {
        $this->capacity = $capacity;
        $this->status_badges = $status_badges;
    }

    /**
     * Format an event DTO (single source of truth).
     *
     * @param int|\WP_Post $event
     * @return array<string,mixed>
     */
    public function format_event($event): array
    {
        $post = is_numeric($event) ? get_post((int) $event) : $event;
        if (! $post || CPT_Events::POST_TYPE !== $post->post_type) {
            return [];
        }

        $event_id = (int) $post->ID;
        $start_ts = (int) get_post_meta($event_id, Event_Meta_Timestamps::START_TS_META, true);
        $end_ts   = (int) get_post_meta($event_id, Event_Meta_Timestamps::END_TS_META, true);

        // Back-compat: if timestamp meta isn't present yet, derive from normalized strings.
        if (! $start_ts) {
            $start_str = (string) get_post_meta($event_id, '_evt_event_start', true);
            $start_ts = $start_str ? $this->parse_datetime_to_ts($start_str) : 0;
        }
        if (! $end_ts) {
            $end_str = (string) get_post_meta($event_id, '_evt_event_end', true);
            $end_ts = $end_str ? $this->parse_datetime_to_ts($end_str) : 0;
        }

        $capacity = $this->capacity->get_capacity($event_id);
        $remaining = $this->capacity->remaining_capacity($event_id);
        if ($capacity <= 0) {
            $remaining = PHP_INT_MAX;
        }

        $venue_id = (int) get_post_meta($event_id, '_evt_event_venue_id', true);
        $organizer_id = (int) get_post_meta($event_id, '_evt_event_organizer_id', true);
        $map_url = (string) get_post_meta($event_id, Event_Location_Meta::MAP_URL_META, true);
        $lat = (string) get_post_meta($event_id, Event_Location_Meta::LAT_META, true);
        $lng = (string) get_post_meta($event_id, Event_Location_Meta::LNG_META, true);
        $cost = (string) get_post_meta($event_id, Event_Discovery_Meta::COST_META, true);
        $country = (string) get_post_meta($event_id, Event_Discovery_Meta::COUNTRY_META, true);
        $city = (string) get_post_meta($event_id, Event_Discovery_Meta::CITY_META, true);
        $postponed = (bool) get_post_meta($event_id, Event_Status::META_POSTPONED, true);

        $meeting = (string) get_post_meta($event_id, Event_Virtual_Meta::MEETING_URL_META, true);
        $stream  = (string) get_post_meta($event_id, Event_Virtual_Meta::LIVESTREAM_URL_META, true);
        $is_virtual = ('' !== trim($meeting) || '' !== trim($stream));

        // If coordinates are missing but a map link exists, try best-effort extraction.
        if (('' === $lat || '' === $lng) && $map_url) {
            $coords = Map_Link_Parser::extract_coords($map_url);
            if ($coords) {
                $lat = (string) $coords['lat'];
                $lng = (string) $coords['lng'];
            }
        }

        $terms_category = $this->format_terms($event_id, Event_Taxonomies::TAX_CATEGORY);
        $terms_tag = $this->format_terms($event_id, Event_Taxonomies::TAX_TAG);
        $terms_venue = $this->format_terms($event_id, Event_Taxonomies::TAX_VENUE);
        $terms_organizer = $this->format_terms($event_id, Event_Taxonomies::TAX_ORGANIZER);
        $status = $this->status_badges->resolve_for_event(
            $event_id,
            [
                'start_ts' => $start_ts,
                'end_ts'   => $end_ts,
            ]
        );

        return [
            'id'          => $event_id,
            'title'       => $post->post_title,
            'permalink'   => get_permalink($post),
            'start_ts'    => $start_ts,
            'end_ts'      => $end_ts,
            'start_iso'   => $start_ts ? gmdate('c', $start_ts) : '',
            'end_iso'     => $end_ts ? gmdate('c', $end_ts) : '',
            'start_local_date' => $start_ts ? wp_date('Y-m-d', $start_ts) : '',
            'end_local_date'   => $end_ts ? wp_date('Y-m-d', $end_ts) : '',
            'timezone'         => self::timezone_label(),
            'timezone_iana'    => self::timezone_iana(),
            'excerpt'     => wp_trim_words(wp_strip_all_tags((string) $post->post_content), 28),
            'location'    => (string) get_post_meta($event_id, '_evt_event_location', true),
            'cancelled'   => (bool) get_post_meta($event_id, Event_Status::META_CANCELLED, true),
            'postponed'   => $postponed,
            'status_key'  => $status['status_key'],
            'status_label' => $status['status_label'],
            'status_badge' => $status['status_badge'],
            'is_recurring' => Event_Recurrence::is_series_parent($event_id),
            'is_occurrence' => Event_Recurrence::is_occurrence($event_id),
            'series_parent_id' => Event_Recurrence::series_parent_id($event_id),
            'occurrence_index' => (int) get_post_meta($event_id, Event_Recurrence::META_OCCURRENCE_INDEX, true),
            'is_virtual'  => $is_virtual,
            'lat'         => $lat,
            'lng'         => $lng,
            'map_url'     => $map_url,
            'capacity'    => $capacity,
            'remaining'   => $remaining,
            'cost'        => $cost,
            'country'     => $country,
            'city'        => $city,
            'image_url'   => (string) get_the_post_thumbnail_url($post, 'medium'),
            'venue_id'    => $venue_id,
            'organizer_id' => $organizer_id,
            'categories'  => $terms_category,
            'tags'        => $terms_tag,
            'venues'      => $terms_venue,
            'organizers'  => $terms_organizer,
        ];
    }

    /**
     * Human-readable label for the site timezone (e.g. "EEST" or "GMT+03:00")
     * so displayed event times are unambiguous to visitors in other zones.
     */
    public static function timezone_label(): string
    {
        if (! function_exists('wp_timezone_string')) {
            return 'GMT';
        }

        $tz = (string) wp_timezone_string();

        // Offset form, e.g. "+03:00" or "-05:30".
        if (preg_match('#^([+-]\d{2}):(\d{2})$#', $tz, $m)) {
            $offset = (int) $m[1] + ((int) $m[2] / 60);
            if (0 === $offset) {
                return 'GMT';
            }
            $hours   = (int) floor(abs($offset));
            $minutes = (int) (round((abs($offset) - $hours) * 60));
            $sign = ($offset < 0) ? '-' : '+';
            $minutes_str = $minutes ? ':' . str_pad((string) $minutes, 2, '0', STR_PAD_LEFT) : '';
            return 'GMT' . $sign . $hours . $minutes_str;
        }

        // Named zone, e.g. "Europe/Sofia" — resolve to its abbreviation.
        $dtz = timezone_open($tz);
        if ($dtz instanceof \DateTimeZone) {
            $abbr = (new \DateTime('now', $dtz))->format('T');
            if (is_string($abbr) && '' !== $abbr && 'GMT' !== $abbr) {
                return $abbr;
            }
        }

        return $tz;
    }

    /**
     * IANA timezone name of the site (e.g. "Europe/Sofia"), used by the
     * events browser to render times in the site timezone in the browser.
     */
    public static function timezone_iana(): string
    {
        if (function_exists('wp_timezone') && wp_timezone() instanceof \DateTimeZone) {
            return (string) wp_timezone()->getName();
        }

        return '';
    }

    /**
     * Cached event feed for widgets/AJAX.
     *
     * @param array $args
     * @return array<int,array<string,mixed>>
     */
    public function get_feed(array $args): array
    {
        $key = 'evt_event_feed_' . md5(wp_json_encode($args));
        $cached = get_transient($key);
        if (is_array($cached)) {
            return $cached;
        }

        $events = $this->query($args);
        $payload = array_map([$this, 'format_event'], $events);

        set_transient($key, $payload, 60);
        return $payload;
    }

    /**
     * Paginated event feed for the events browser "load more" feature.
     *
     * Returns a structured payload with the page slice plus pagination
     * metadata so the client can append results and show/hide the button.
     *
     * @param array $args
     * @return array{events:array<int,array<string,mixed>>,page:int,per_page:int,total:int,has_more:bool}
     */
    public function get_paginated_feed(array $args): array
    {
        $page  = isset($args['page']) ? max(1, (int) $args['page']) : 1;
        $limit = isset($args['limit']) ? (int) $args['limit'] : 50;
        if ($limit < 1 || $limit > 500) {
            $limit = 500;
        }

        $args['page']  = $page;
        $args['limit'] = $limit;

        $key = 'evt_event_feed_' . md5(wp_json_encode($args));
        $cached = get_transient($key);
        if (is_array($cached) && isset($cached['events'])) {
            return $cached;
        }

        $this->last_total = 0;
        $events = $this->query($args);
        $total  = max(0, $this->last_total);

        $result = [
            'events'   => array_map([$this, 'format_event'], $events),
            'page'     => $page,
            'per_page' => $limit,
            'total'    => $total,
            'has_more' => (($page * $limit) < $total),
        ];

        set_transient($key, $result, 60);
        return $result;
    }

    /**
     * Query events.
     *
     * Supported $args:
     * - limit (int)
     * - page (int) 1-based page for pagination
     * - order (ASC|DESC)
     * - only_with_capacity (bool)
     * - hide_cancelled (bool)
     * - start_ts (int) inclusive
     * - end_ts (int) inclusive
     *
     * @param array $args
     * @return \WP_Post[]
     */
    public function query(array $args): array
    {
        $meta_query = [];
        $tax_query = [];

        $meta_query[] = [
            'relation' => 'OR',
            [
                'key'     => Event_Recurrence::META_IS_SERIES,
                'compare' => 'NOT EXISTS',
            ],
            [
                'key'     => Event_Recurrence::META_IS_SERIES,
                'value'   => '1',
                'compare' => '!=',
            ],
        ];

        if (! empty($args['hide_cancelled'])) {
            $meta_query[] = [
                'key'     => Event_Status::META_CANCELLED,
                'compare' => 'NOT EXISTS',
            ];
        }

        if (! empty($args['hide_postponed'])) {
            $meta_query[] = [
                'key'     => Event_Status::META_POSTPONED,
                'compare' => 'NOT EXISTS',
            ];
        }

        // Range filter uses numeric timestamp meta. For back-compat with older
        // events that don't have timestamp meta yet, fall back to string compare
        // on _evt_event_start (works for normalized "Y-m-d H:i").
        if (! empty($args['start_ts']) || ! empty($args['end_ts'])) {
            $range_numeric = [];
            if (! empty($args['start_ts'])) {
                $range_numeric[] = [
                    'key'     => Event_Meta_Timestamps::START_TS_META,
                    'value'   => (int) $args['start_ts'],
                    'compare' => '>=',
                    'type'    => 'NUMERIC',
                ];
            }
            if (! empty($args['end_ts'])) {
                $range_numeric[] = [
                    'key'     => Event_Meta_Timestamps::START_TS_META,
                    'value'   => (int) $args['end_ts'],
                    'compare' => '<=',
                    'type'    => 'NUMERIC',
                ];
            }

            $range_string = [];
            $start_str = ! empty($args['start_ts']) ? wp_date('Y-m-d H:i', (int) $args['start_ts']) : '';
            $end_str   = ! empty($args['end_ts']) ? wp_date('Y-m-d H:i', (int) $args['end_ts']) : '';
            if ($start_str) {
                $range_string[] = [
                    'key'     => '_evt_event_start',
                    'value'   => $start_str,
                    'compare' => '>=',
                    'type'    => 'CHAR',
                ];
            }
            if ($end_str) {
                $range_string[] = [
                    'key'     => '_evt_event_start',
                    'value'   => $end_str,
                    'compare' => '<=',
                    'type'    => 'CHAR',
                ];
            }

            $meta_query[] = [
                'relation' => 'OR',
                (count($range_numeric) === 1) ? $range_numeric[0] : array_merge(['relation' => 'AND'], $range_numeric),
                (count($range_string) === 1) ? $range_string[0] : array_merge(['relation' => 'AND'], $range_string),
            ];
        }

        if (! empty($args['venue_id'])) {
            $meta_query[] = [
                'key'   => '_evt_event_venue_id',
                'value' => absint($args['venue_id']),
            ];
        }

        if (! empty($args['organizer_id'])) {
            $meta_query[] = [
                'key'   => '_evt_event_organizer_id',
                'value' => absint($args['organizer_id']),
            ];
        }

        if (! empty($args['cost_min']) || ! empty($args['cost_max'])) {
            $min = isset($args['cost_min']) ? (float) $args['cost_min'] : 0.0;
            $max = isset($args['cost_max']) ? (float) $args['cost_max'] : 0.0;
            if ($max > 0 && $max >= $min) {
                $meta_query[] = [
                    'key'     => Event_Discovery_Meta::COST_META,
                    'value'   => [$min, $max],
                    'compare' => 'BETWEEN',
                    'type'    => 'NUMERIC',
                ];
            } elseif ($min > 0) {
                $meta_query[] = [
                    'key'     => Event_Discovery_Meta::COST_META,
                    'value'   => $min,
                    'compare' => '>=',
                    'type'    => 'NUMERIC',
                ];
            }
        }

        if (! empty($args['country'])) {
            $meta_query[] = [
                'key'     => Event_Discovery_Meta::COUNTRY_META,
                'value'   => (string) $args['country'],
                'compare' => '=',
            ];
        }

        if (! empty($args['city'])) {
            $meta_query[] = [
                'key'     => Event_Discovery_Meta::CITY_META,
                'value'   => (string) $args['city'],
                'compare' => '=',
            ];
        }

        if (! empty($args['virtual_mode'])) {
            $mode = (string) $args['virtual_mode'];
            if ('only' === $mode) {
                $meta_query[] = [
                    'relation' => 'OR',
                    ['key' => Event_Virtual_Meta::MEETING_URL_META, 'compare' => 'EXISTS'],
                    ['key' => Event_Virtual_Meta::LIVESTREAM_URL_META, 'compare' => 'EXISTS'],
                ];
            } elseif ('hide' === $mode) {
                $meta_query[] = [
                    'relation' => 'AND',
                    ['key' => Event_Virtual_Meta::MEETING_URL_META, 'compare' => 'NOT EXISTS'],
                    ['key' => Event_Virtual_Meta::LIVESTREAM_URL_META, 'compare' => 'NOT EXISTS'],
                ];
            }
        }

        $tax_filters = [
            'category_ids'  => Event_Taxonomies::TAX_CATEGORY,
            'tag_ids'       => Event_Taxonomies::TAX_TAG,
            'venue_ids'     => Event_Taxonomies::TAX_VENUE,
            'organizer_ids' => Event_Taxonomies::TAX_ORGANIZER,
        ];

        foreach ($tax_filters as $arg_key => $taxonomy) {
            if (empty($args[$arg_key])) {
                continue;
            }
            $ids = is_array($args[$arg_key]) ? $args[$arg_key] : [(string) $args[$arg_key]];
            $ids = array_filter(array_map('absint', $ids));
            if (empty($ids)) {
                continue;
            }
            $tax_query[] = [
                'taxonomy' => $taxonomy,
                'field'    => 'term_id',
                'terms'    => $ids,
                'operator' => 'IN',
            ];
        }

        // Do not set meta_key ordering here: WP_Query uses an INNER JOIN and will
        // exclude events missing that meta. We'll sort by computed start_ts in PHP.
        $limit = isset($args['limit']) ? (int) $args['limit'] : 10;
        // Guard against unbounded/-1 and absurdly large values on public feeds.
        if ($limit < 1 || $limit > 500) {
            $limit = 500;
        }

        // Optional pagination (used by the events browser "load more").
        $page = isset($args['page']) ? max(1, (int) $args['page']) : 1;

        $wp_args = [
            'post_type'      => CPT_Events::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'paged'          => $page,
            'no_found_rows'  => false,
            'orderby'        => 'date',
            'order'          => $args['order'] ?? 'ASC',
            'meta_query'     => $meta_query,
            'tax_query'      => $tax_query,
        ];

        $search = isset($args['s']) ? trim((string) $args['s']) : '';
        if ('' !== $search) {
            $wp_args['s'] = $search;
        }

        $query = new \WP_Query($wp_args);

        // Remember the total number of matches so callers can paginate.
        $this->last_total = (int) $query->found_posts;

        if (empty($query->posts)) {
            return [];
        }

        $events = $query->posts;

        // Prime post meta and taxonomy term caches for the whole result set in
        // a single round-trip each, instead of the per-post lookups that
        // happen during sorting/formatting below (N+1 query avoidance).
        if (function_exists('update_meta_cache')) {
            update_meta_cache('post', wp_list_pluck($events, 'ID'));
        }
        if (function_exists('update_object_term_cache')) {
            update_object_term_cache(wp_list_pluck($events, 'ID'), CPT_Events::POST_TYPE);
        }

        usort(
            $events,
            function (\WP_Post $a, \WP_Post $b) use ($args) {
                $a_ts = (int) get_post_meta($a->ID, Event_Meta_Timestamps::START_TS_META, true);
                if (! $a_ts) {
                    $a_str = (string) get_post_meta($a->ID, '_evt_event_start', true);
                    $a_ts = $a_str ? $this->parse_datetime_to_ts($a_str) : 0;
                }

                $b_ts = (int) get_post_meta($b->ID, Event_Meta_Timestamps::START_TS_META, true);
                if (! $b_ts) {
                    $b_str = (string) get_post_meta($b->ID, '_evt_event_start', true);
                    $b_ts = $b_str ? $this->parse_datetime_to_ts($b_str) : 0;
                }

                $cmp = $a_ts <=> $b_ts;
                if ('DESC' === ($args['order'] ?? 'ASC')) {
                    $cmp = -$cmp;
                }

                // Tie-breaker for deterministic ordering.
                if (0 === $cmp) {
                    $cmp = $a->ID <=> $b->ID;
                    if ('DESC' === ($args['order'] ?? 'ASC')) {
                        $cmp = -$cmp;
                    }
                }

                return $cmp;
            }
        );

        if (! empty($args['only_with_capacity'])) {
            $events = array_filter(
                $events,
                function (\WP_Post $event) {
                    $capacity = $this->capacity->get_capacity($event->ID);
                    if ($capacity <= 0) {
                        return true;
                    }
                    return $this->capacity->remaining_capacity($event->ID) > 0;
                }
            );
        }

        if (isset($args['day_of_week']) && '' !== (string) $args['day_of_week']) {
            $wanted = (int) $args['day_of_week'];
            $events = array_values(
                array_filter(
                    $events,
                    function (\WP_Post $event) use ($wanted) {
                        $start_ts = (int) get_post_meta($event->ID, Event_Meta_Timestamps::START_TS_META, true);
                        if (! $start_ts) {
                            $start_str = (string) get_post_meta($event->ID, '_evt_event_start', true);
                            $start_ts = $start_str ? $this->parse_datetime_to_ts($start_str) : 0;
                            if (! $start_ts) {
                                return false;
                            }
                        }
                        $dow = (int) wp_date('w', $start_ts);
                        return $dow === $wanted;
                    }
                )
            );
        }

        return array_values($events);
    }

    private function parse_datetime_to_ts(string $value): int
    {
        $value = trim($value);
        if ('' === $value) {
            return 0;
        }

        $tz = function_exists('wp_timezone') ? wp_timezone() : null;
        if ($tz instanceof \DateTimeZone) {
            $dt = date_create_immutable($value, $tz);
            if ($dt instanceof \DateTimeImmutable) {
                return (int) $dt->getTimestamp();
            }
        }

        $ts = strtotime($value);
        return $ts ? (int) $ts : 0;
    }

    /**
     * @return array<int,array{id:int,name:string,slug:string}>
     */
    private function format_terms(int $event_id, string $taxonomy): array
    {
        $terms = wp_get_post_terms($event_id, $taxonomy);
        if (is_wp_error($terms) || empty($terms)) {
            return [];
        }

        $out = [];
        foreach ($terms as $t) {
            $out[] = [
                'id'   => (int) $t->term_id,
                'name' => (string) $t->name,
                'slug' => (string) $t->slug,
            ];
        }
        return $out;
    }
}
