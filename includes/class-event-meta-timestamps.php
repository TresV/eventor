<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Normalizes event date strings into timestamps for querying.
 */
class Event_Meta_Timestamps
{
    public const START_TS_META = '_evt_event_start_ts';
    public const END_TS_META   = '_evt_event_end_ts';

    public function __construct()
    {
        // Run after CPT meta save (and our custom metabox save action).
        add_action('evt_tickets_event_details_save', [$this, 'maybe_update_timestamps'], 20, 2);
        add_action('save_post', [$this, 'maybe_update_timestamps'], 20, 2);
    }

    public function maybe_update_timestamps(int $post_id, \WP_Post $post): void
    {
        if ($post->post_type !== CPT_Events::POST_TYPE) {
            return;
        }

        $start = get_post_meta($post_id, '_evt_event_start', true);
        $end   = get_post_meta($post_id, '_evt_event_end', true);

        $start_ts = $start ? $this->parse_datetime_to_ts((string) $start) : 0;
        $end_ts   = $end ? $this->parse_datetime_to_ts((string) $end) : 0;

        if ($start_ts > 0) {
            update_post_meta($post_id, self::START_TS_META, $start_ts);
        } else {
            delete_post_meta($post_id, self::START_TS_META);
        }

        if ($end_ts > 0) {
            update_post_meta($post_id, self::END_TS_META, $end_ts);
        } else {
            delete_post_meta($post_id, self::END_TS_META);
        }
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
}
