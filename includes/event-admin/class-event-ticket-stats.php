<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Ticket counting helpers for the Event editor UI.
 */
class Event_Ticket_Stats
{
    /**
     * @return array{total:int,pending:int,checked_in:int,cancelled:int}
     */
    public function get_ticket_counts(int $event_id): array
    {
        $counts = [
            'total'      => 0,
            'pending'    => 0,
            'checked_in' => 0,
            'cancelled'  => 0,
        ];

        $counts['total'] = $this->count_tickets_for_event_by_meta($event_id, []);

        foreach (['pending', 'checked_in', 'cancelled'] as $status) {
            $counts[$status] = $this->count_tickets_for_event_by_meta(
                $event_id,
                [
                    [
                        'key'   => '_ticket_status',
                        'value' => $status,
                    ],
                ]
            );
        }

        return $counts;
    }

    /**
     * Count tickets for an event matching an optional extra meta_query clause,
     * using found_posts so the database does the counting rather than loading
     * every matching ticket ID.
     *
     * @param int   $event_id
     * @param array $extra_meta
     */
    private function count_tickets_for_event_by_meta(int $event_id, array $extra_meta): int
    {
        $meta_query = [
            [
                'key'   => '_ticket_event_id',
                'value' => $event_id,
            ],
        ];

        if (! empty($extra_meta)) {
            $meta_query = array_merge($meta_query, $extra_meta);
        }

        $query = new \WP_Query(
            [
                'post_type'      => CPT_Tickets::POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'no_found_rows'  => false,
                'meta_query'     => $meta_query,
            ]
        );

        return (int) $query->found_posts;
    }
}
