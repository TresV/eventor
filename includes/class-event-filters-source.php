<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Provides option lists for frontend filters (distinct meta values, ranges).
 */
class Event_Filters_Source
{
    public function __construct()
    {
        add_action('save_post_' . CPT_Events::POST_TYPE, [$this, 'flush_cache']);
        add_action('delete_post', [$this, 'flush_cache']);
    }

    /**
     * Invalidate cached filter values whenever events change.
     */
    public function flush_cache(): void
    {
        update_option('evt_filters_cache_version', $this->cache_version() + 1, false);
    }

    private function cache_version(): int
    {
        return (int) get_option('evt_filters_cache_version', 1);
    }

    /**
     * @return string[]
     */
    public function get_distinct_meta_values(string $meta_key, int $limit = 200): array
    {
        $limit = max(1, min(500, (int) $limit));
        $cache_key = 'evt_filters_values_' . $this->cache_version() . '_' . md5($meta_key . '_' . $limit);
        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            return $cached;
        }

        global $wpdb;

        $rows = $wpdb->get_col(
            $wpdb->prepare(
                "
                SELECT DISTINCT pm.meta_value
                FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                WHERE p.post_type = %s
                  AND p.post_status = 'publish'
                  AND pm.meta_key = %s
                  AND pm.meta_value <> ''
                ORDER BY pm.meta_value ASC
                LIMIT %d
                ",
                CPT_Events::POST_TYPE,
                $meta_key,
                $limit
            )
        );
        if (! is_array($rows)) {
            return [];
        }

        $values = array_values(array_filter(array_map('strval', $rows)));
        set_transient($cache_key, $values, HOUR_IN_SECONDS);
        return $values;
    }

    /**
     * @return array{min:float,max:float}
     */
    public function get_cost_range(): array
    {
        $cache_key = 'evt_filters_cost_range_' . $this->cache_version();
        $cached = get_transient($cache_key);
        if (is_array($cached) && isset($cached['min'], $cached['max'])) {
            return $cached;
        }

        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "
                SELECT MIN(CAST(pm.meta_value AS DECIMAL(10,2))) AS min_cost,
                       MAX(CAST(pm.meta_value AS DECIMAL(10,2))) AS max_cost
                FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                WHERE p.post_type = %s
                  AND p.post_status = 'publish'
                  AND pm.meta_key = %s
                  AND pm.meta_value <> ''
                ",
                CPT_Events::POST_TYPE,
                Event_Discovery_Meta::COST_META
            ),
            ARRAY_A
        );
        if (! is_array($row)) {
            return ['min' => 0.0, 'max' => 0.0];
        }

        $min = isset($row['min_cost']) ? (float) $row['min_cost'] : 0.0;
        $max = isset($row['max_cost']) ? (float) $row['max_cost'] : 0.0;

        if ($min < 0) {
            $min = 0.0;
        }
        if ($max < 0) {
            $max = 0.0;
        }

        $result = ['min' => $min, 'max' => $max];
        set_transient($cache_key, $result, HOUR_IN_SECONDS);
        return $result;
    }
}
