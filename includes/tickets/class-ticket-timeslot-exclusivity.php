<?php

namespace EventTicketsElementor\Tickets;

use EventTicketsElementor\CPT_Events;
use EventTicketsElementor\CPT_Tickets;
use EventTicketsElementor\Ticket_Statuses;
use WP_Query;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Enforces "one attendee email cannot hold overlapping events" rule.
 *
 * MVP implementation loops over existing tickets. Can be optimized later.
 */
class Ticket_Timeslot_Exclusivity
{
    private int $buffer_minutes;

    public function __construct(int $buffer_minutes = 0)
    {
        $this->buffer_minutes = max(0, (int) $buffer_minutes);
    }

    /**
     * @return true|\WP_Error
     */
    public function assert_no_timeslot_conflict(int $event_id, string $email, ?array $timerange = null)
    {
        $event_id = absint($event_id);
        $email = strtolower(trim((string) $email));

        if (! $event_id || '' === $email) {
            return true;
        }

        $a = $this->normalize_timerange($timerange) ?: $this->get_event_timerange($event_id);
        if (! $a) {
            return true;
        }

        $ticket_ids = $this->find_active_ticket_ids_by_email($email);
        if (empty($ticket_ids)) {
            return true;
        }

        foreach ($ticket_ids as $ticket_id) {
            $other_event_id = (int) get_post_meta($ticket_id, '_ticket_event_id', true);
            if (! $other_event_id || $other_event_id === $event_id) {
                continue;
            }

            $b = $this->get_ticket_timerange((int) $ticket_id) ?: $this->get_event_timerange($other_event_id);
            if (! $b) {
                continue;
            }

            if ($this->ranges_overlap($a['start_ts'], $a['end_ts'], $b['start_ts'], $b['end_ts'])) {
                $title = '';
                $post = get_post($other_event_id);
                if ($post && CPT_Events::POST_TYPE === $post->post_type) {
                    $title = (string) $post->post_title;
                }

                return new \WP_Error(
                    'evt_timeslot_conflict',
                    __('You already have a ticket for another workshop at this time.', 'Event-Tickets-for-Elementor'),
                    [
                        'conflicting_event_id'    => $other_event_id,
                        'conflicting_event_title' => $title,
                    ]
                );
            }
        }

        return true;
    }

    /**
     * @param array{start_ts?:mixed,end_ts?:mixed}|null $timerange
     * @return array{start_ts:int,end_ts:int}|null
     */
    private function normalize_timerange(?array $timerange): ?array
    {
        if (empty($timerange) || ! is_array($timerange)) {
            return null;
        }

        $start_ts = isset($timerange['start_ts']) ? (int) $timerange['start_ts'] : 0;
        $end_ts   = isset($timerange['end_ts']) ? (int) $timerange['end_ts'] : 0;
        if (! $start_ts) {
            return null;
        }
        if (! $end_ts || $end_ts <= $start_ts) {
            $end_ts = $start_ts + HOUR_IN_SECONDS;
        }

        return [
            'start_ts' => $start_ts,
            'end_ts'   => $end_ts,
        ];
    }

    /**
     * @return array{start_ts:int,end_ts:int}|null
     */
    public function get_event_timerange(int $event_id): ?array
    {
        $event_id = absint($event_id);
        if (! $event_id) {
            return null;
        }

        $start_ts = (int) get_post_meta($event_id, '_evt_event_start_ts', true);
        $end_ts   = (int) get_post_meta($event_id, '_evt_event_end_ts', true);

        if (! $start_ts) {
            $start_raw = (string) get_post_meta($event_id, '_evt_event_start', true);
            $start_ts  = $start_raw ? (int) strtotime($start_raw) : 0;
        }
        if (! $end_ts) {
            $end_raw = (string) get_post_meta($event_id, '_evt_event_end', true);
            $end_ts  = $end_raw ? (int) strtotime($end_raw) : 0;
        }

        if (! $start_ts) {
            return null;
        }

        if (! $end_ts || $end_ts <= $start_ts) {
            $end_ts = $start_ts + HOUR_IN_SECONDS;
        }

        return [
            'start_ts' => $start_ts,
            'end_ts'   => $end_ts,
        ];
    }

    /**
     * Prefers ticket snapshot range when available.
     *
     * @return array{start_ts:int,end_ts:int}|null
     */
    private function get_ticket_timerange(int $ticket_id): ?array
    {
        $ticket_id = absint($ticket_id);
        if (! $ticket_id) {
            return null;
        }

        $start_raw = (string) get_post_meta($ticket_id, '_ticket_event_start', true);
        $end_raw   = (string) get_post_meta($ticket_id, '_ticket_event_end', true);

        $start_ts = $start_raw ? (int) strtotime($start_raw) : 0;
        if (! $start_ts) {
            return null;
        }
        $end_ts = $end_raw ? (int) strtotime($end_raw) : 0;
        if (! $end_ts || $end_ts <= $start_ts) {
            $end_ts = $start_ts + HOUR_IN_SECONDS;
        }

        return [
            'start_ts' => $start_ts,
            'end_ts'   => $end_ts,
        ];
    }

    /**
     * @return array<int,int>
     */
    private function find_active_ticket_ids_by_email(string $email): array
    {
        $query = new WP_Query(
            [
                'post_type'              => CPT_Tickets::POST_TYPE,
                'post_status'            => 'publish',
                // Bound the conflict scan: an attendee holding more than 500
                // active tickets is not realistic, and this keeps the query
                // from loading the whole table on each ticket creation.
                'posts_per_page'         => 500,
                'fields'                 => 'ids',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'meta_query'             => [
                    [
                        'key'   => '_ticket_email',
                        'value' => $email,
                    ],
                    [
                        'key'     => '_ticket_event_id',
                        'compare' => 'EXISTS',
                    ],
                ],
            ]
        );

        return is_array($query->posts) ? array_map('intval', $query->posts) : [];
    }

    private function ranges_overlap(int $a_start, int $a_end, int $b_start, int $b_end): bool
    {
        $buffer = $this->buffer_minutes * MINUTE_IN_SECONDS;

        return ($a_start < ($b_end + $buffer)) && ($b_start < ($a_end + $buffer));
    }
}
