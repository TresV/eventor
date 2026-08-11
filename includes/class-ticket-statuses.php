<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Registers custom post statuses for tickets (e.g. cancelled).
 */
class Ticket_Statuses
{
    public const STATUS_CANCELLED = 'evt_cancelled';

    public function __construct()
    {
        add_action('init', [$this, 'register_statuses']);
    }

    public function register_statuses(): void
    {
        register_post_status(
            self::STATUS_CANCELLED,
            [
                'label'                     => _x('Cancelled', 'ticket status', 'Event-Tickets-for-Elementor'),
                'public'                    => false,
                'internal'                  => false,
                'exclude_from_search'       => true,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                /* translators: %s is the number of cancelled tickets. */
                'label_count'               => _n_noop('Cancelled <span class="count">(%s)</span>', 'Cancelled <span class="count">(%s)</span>', 'Event-Tickets-for-Elementor'),
            ]
        );
    }
}

