<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Stores and retrieves event timeslots.
 *
 * Meta:
 * - _evt_event_timeslots: array of slots (each: id,start,end,capacity)
 * - _evt_event_capacity_mode: event|slot|both
 */
class Event_Timeslots
{
    public const META_KEY_SLOTS = '_evt_event_timeslots';
    public const META_KEY_CAPACITY_MODE = '_evt_event_capacity_mode';
    public const META_KEY_TICKETING_MODE = '_evt_event_ticketing_mode';
    public const META_KEY_LIMIT_ONE_EMAIL = '_evt_event_limit_one_email';
    public const META_KEY_NORMALIZE_EMAIL = '_evt_event_normalize_email';
    public const META_KEY_MAX_PER_EMAIL = '_evt_event_max_tickets_per_email';

    public const CAPACITY_MODE_EVENT = 'event';
    public const CAPACITY_MODE_SLOT  = 'slot';
    public const CAPACITY_MODE_BOTH  = 'both';

    public const TICKETING_MODE_EVENT = 'event';
    public const TICKETING_MODE_SLOT  = 'slot';

    /**
     * @return array<int,array{id:string,start:string,end:string,capacity:int}>
     */
    public function get_slots(int $event_id): array
    {
        $event_id = absint($event_id);
        if (! $event_id) {
            return [];
        }

        $raw = get_post_meta($event_id, self::META_KEY_SLOTS, true);
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $slot) {
            if (! is_array($slot)) {
                continue;
            }

            $id = isset($slot['id']) ? sanitize_key((string) $slot['id']) : '';
            $start = isset($slot['start']) ? $this->normalize_datetime((string) $slot['start']) : '';
            $end   = isset($slot['end']) ? $this->normalize_datetime((string) $slot['end']) : '';
            $cap   = isset($slot['capacity']) ? absint($slot['capacity']) : 0;

            if ('' === $id || '' === $start || '' === $end) {
                continue;
            }
            if (strtotime($start) >= strtotime($end)) {
                continue;
            }

            $out[] = [
                'id'       => $id,
                'start'    => $start,
                'end'      => $end,
                'capacity' => $cap,
            ];
        }

        usort(
            $out,
            function (array $a, array $b): int {
                return strcmp($a['start'], $b['start']);
            }
        );

        return $out;
    }

    public function has_slots(int $event_id): bool
    {
        return ! empty($this->get_slots($event_id));
    }

    public function requires_timeslot_selection(int $event_id): bool
    {
        return $this->has_slots($event_id) && self::TICKETING_MODE_SLOT === $this->get_ticketing_mode($event_id);
    }

    /**
     * @return array{id:string,start:string,end:string,capacity:int}|null
     */
    public function get_slot(int $event_id, string $slot_id): ?array
    {
        $slot_id = sanitize_key($slot_id);
        if ('' === $slot_id) {
            return null;
        }

        foreach ($this->get_slots($event_id) as $slot) {
            if ($slot['id'] === $slot_id) {
                return $slot;
            }
        }
        return null;
    }

    public function get_capacity_mode(int $event_id): string
    {
        $mode = (string) get_post_meta($event_id, self::META_KEY_CAPACITY_MODE, true);
        $mode = trim($mode);

        if (! in_array($mode, [self::CAPACITY_MODE_EVENT, self::CAPACITY_MODE_SLOT, self::CAPACITY_MODE_BOTH], true)) {
            return self::CAPACITY_MODE_EVENT;
        }
        return $mode;
    }

    public function get_ticketing_mode(int $event_id): string
    {
        $mode = (string) get_post_meta($event_id, self::META_KEY_TICKETING_MODE, true);
        $mode = trim($mode);

        if (! in_array($mode, [self::TICKETING_MODE_EVENT, self::TICKETING_MODE_SLOT], true)) {
            return self::TICKETING_MODE_EVENT;
        }
        return $mode;
    }

    /**
     * Updates the event's primary start/end meta to cover the full slot range.
     */
    public function sync_primary_range_from_slots(int $event_id): void
    {
        $slots = $this->get_slots($event_id);
        if (empty($slots)) {
            return;
        }

        $min = $slots[0]['start'];
        $max = $slots[0]['end'];
        foreach ($slots as $slot) {
            if ($slot['start'] < $min) {
                $min = $slot['start'];
            }
            if ($slot['end'] > $max) {
                $max = $slot['end'];
            }
        }

        update_post_meta($event_id, '_evt_event_start', $min);
        update_post_meta($event_id, '_evt_event_end', $max);
    }

    /**
     * @return string Normalized "Y-m-d H:i" or empty string.
     */
    public function normalize_datetime(string $value): string
    {
        $value = trim($value);
        if ('' === $value) {
            return '';
        }

        // Accept HTML5 datetime-local "Y-m-d\TH:i".
        if (preg_match('/^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}$/', $value)) {
            $value = str_replace('T', ' ', $value);
        }

        // Accept "Y-m-d H:i".
        if (preg_match('/^\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}$/', $value)) {
            return $value;
        }

        $ts = strtotime($value);
        if (! $ts) {
            return '';
        }

        return (string) wp_date('Y-m-d H:i', $ts);
    }

    /**
     * Build a human-friendly label for a slot.
     */
    public function format_slot_label(array $slot): string
    {
        $start_ts = strtotime((string) ($slot['start'] ?? ''));
        $end_ts   = strtotime((string) ($slot['end'] ?? ''));
        if (! $start_ts || ! $end_ts) {
            return (string) ($slot['id'] ?? '');
        }

        $date_fmt = get_option('date_format');
        $time_fmt = get_option('time_format');

        $date = date_i18n($date_fmt, $start_ts);
        $start = date_i18n($time_fmt, $start_ts);
        $end = date_i18n($time_fmt, $end_ts);

        return sprintf('%s · %s–%s', $date, $start, $end);
    }
}
