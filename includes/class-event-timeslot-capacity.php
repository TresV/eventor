<?php

namespace EventTicketsElementor;

use WP_Query;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Capacity enforcement for multi-timeslot events.
 *
 * Supports per-event, per-slot, or combined capacity modes (per event setting).
 */
class Event_Timeslot_Capacity
{
    private Event_Capacity $event_capacity;
    private Event_Timeslots $timeslots;

    public function __construct(Event_Capacity $event_capacity, Event_Timeslots $timeslots)
    {
        $this->event_capacity = $event_capacity;
        $this->timeslots      = $timeslots;
    }

    /**
     * Remaining capacity for an event + optional slot.
     *
     * @return int PHP_INT_MAX if unlimited
     */
    public function remaining(int $event_id, string $timeslot_id = ''): int
    {
        $event_id = absint($event_id);
        $timeslot_id = sanitize_key($timeslot_id);

        if (! $event_id) {
            return 0;
        }

        $mode = $this->timeslots->get_capacity_mode($event_id);

        if (Event_Timeslots::CAPACITY_MODE_EVENT === $mode || '' === $timeslot_id) {
            return $this->event_capacity->remaining_capacity($event_id);
        }

        $slot_remaining = $this->remaining_for_slot($event_id, $timeslot_id);

        if (Event_Timeslots::CAPACITY_MODE_SLOT === $mode) {
            return $slot_remaining;
        }

        // both: take the tighter limit.
        $event_remaining = $this->event_capacity->remaining_capacity($event_id);
        if (PHP_INT_MAX === $event_remaining) {
            return $slot_remaining;
        }
        if (PHP_INT_MAX === $slot_remaining) {
            return $event_remaining;
        }
        return min($event_remaining, $slot_remaining);
    }

    /**
     * @return int PHP_INT_MAX if unlimited or slot missing capacity
     */
    public function remaining_for_slot(int $event_id, string $timeslot_id): int
    {
        $event_id = absint($event_id);
        $timeslot_id = sanitize_key($timeslot_id);
        if (! $event_id || '' === $timeslot_id) {
            return 0;
        }

        $slot = $this->timeslots->get_slot($event_id, $timeslot_id);
        if (! $slot) {
            return 0;
        }

        $capacity = (int) ($slot['capacity'] ?? 0);
        if ($capacity <= 0) {
            return PHP_INT_MAX;
        }

        $used = $this->count_active_tickets_for_slot($event_id, $timeslot_id, $capacity + 1);
        return max(0, $capacity - $used);
    }

    /**
     * Count active tickets for a given event+slot.
     *
     * Uses a fast query and allows an optional early-exit limit.
     */
    public function count_active_tickets_for_slot(int $event_id, string $timeslot_id, ?int $limit = null): int
    {
        $event_id = absint($event_id);
        $timeslot_id = sanitize_key($timeslot_id);
        if (! $event_id || '' === $timeslot_id) {
            return 0;
        }

        $args = [
            'post_type'              => CPT_Tickets::POST_TYPE,
            'post_status'            => 'publish',
            'posts_per_page'         => $limit ? max(1, (int) $limit) : -1,
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'meta_query'             => [
                [
                    'key'   => '_ticket_event_id',
                    'value' => $event_id,
                ],
                [
                    'key'   => '_ticket_timeslot_id',
                    'value' => $timeslot_id,
                ],
            ],
        ];

        $q = new WP_Query($args);
        return is_array($q->posts) ? count($q->posts) : 0;
    }
}

