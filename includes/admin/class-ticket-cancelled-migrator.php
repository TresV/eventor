<?php

namespace EventTicketsElementor\Admin;

use EventTicketsElementor\CPT_Tickets;
use EventTicketsElementor\Ticket_Statuses;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Best-effort migration: move tickets with legacy meta "_ticket_status=cancelled"
 * from "publish" to the custom post_status "evt_cancelled" in small batches.
 */
class Ticket_Cancelled_Migrator
{
    private const OPTION_DONE = 'evt_tickets_cancel_migration_done';

    public function __construct()
    {
        add_action('admin_init', [$this, 'maybe_run']);
    }

    public function maybe_run(): void
    {
        if ('1' === get_option(self::OPTION_DONE, '0')) {
            return;
        }

        if (! current_user_can('manage_options')) {
            return;
        }

        $ids = $this->find_batch();
        if (empty($ids)) {
            update_option(self::OPTION_DONE, '1', false);
            return;
        }

        foreach ($ids as $ticket_id) {
            wp_update_post(
                [
                    'ID'          => $ticket_id,
                    'post_status' => Ticket_Statuses::STATUS_CANCELLED,
                ]
            );
        }
    }

    /**
     * @return array<int>
     */
    private function find_batch(): array
    {
        $query = new \WP_Query(
            [
                'post_type'              => CPT_Tickets::POST_TYPE,
                'post_status'            => 'publish',
                'posts_per_page'         => 50,
                'fields'                 => 'ids',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'meta_query'             => [
                    [
                        'key'   => '_ticket_status',
                        'value' => 'cancelled',
                    ],
                ],
            ]
        );

        return is_array($query->posts) ? $query->posts : [];
    }
}

