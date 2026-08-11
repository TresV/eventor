<?php

namespace EventTicketsElementor\Admin;

use EventTicketsElementor\CPT_Tickets;
use EventTicketsElementor\Ticket_Statuses;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Ensures "Cancelled" tickets remain visible in the Tickets admin list.
 *
 * Some WP admin list queries default to post_status=publish for non-public CPTs,
 * which makes custom statuses appear "deleted" even though they're not.
 */
class Ticket_Admin_List_Statuses
{
    public function __construct()
    {
        add_action('pre_get_posts', [$this, 'ensure_statuses'], 9);
    }

    public function ensure_statuses(\WP_Query $query): void
    {
        if (! is_admin() || ! $query->is_main_query()) {
            return;
        }

        global $pagenow;
        if ('edit.php' !== $pagenow) {
            return;
        }

        if (CPT_Tickets::POST_TYPE !== $query->get('post_type')) {
            return;
        }

        // If user explicitly filters by status (e.g. Cancelled), don't interfere.
        if (isset($_GET['post_status']) && '' !== sanitize_key(wp_unslash($_GET['post_status']))) {
            return;
        }

        $post_status = $query->get('post_status');
        if (! $post_status || 'publish' === $post_status) {
            $query->set('post_status', ['publish', Ticket_Statuses::STATUS_CANCELLED]);
        }
    }
}

