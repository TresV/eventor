<?php

namespace EventTicketsElementor\Admin;

use EventTicketsElementor\CPT_Tickets;
use EventTicketsElementor\Ticket_Statuses;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Keeps ticket post_status in sync with admin-set _ticket_status meta.
 *
 * CPT_Tickets admin dropdown edits _ticket_status, but check-in and capacity
 * rely primarily on post_status.
 */
class Ticket_Admin_Status_Sync
{
    private static bool $updating = false;

    public function __construct()
    {
        add_action('save_post', [$this, 'sync'], 20, 2);
    }

    public function sync(int $post_id, \WP_Post $post): void
    {
        if (self::$updating) {
            return;
        }

        if (! $post || CPT_Tickets::POST_TYPE !== ($post->post_type ?? '')) {
            return;
        }

        // Only act on real admin edits (avoid background imports where meta isn't present).
        if (
            empty($_POST['evt_ticket_meta_nonce'])
            || ! wp_verify_nonce(wp_unslash($_POST['evt_ticket_meta_nonce']), 'evt_save_ticket_meta')
        ) {
            return;
        }

        $status_meta = (string) get_post_meta($post_id, '_ticket_status', true);
        $status_meta = $status_meta ? $status_meta : 'pending';

        $target_status = 'publish';
        if ('cancelled' === $status_meta) {
            $target_status = Ticket_Statuses::STATUS_CANCELLED;
            if (! get_post_meta($post_id, '_ticket_cancelled_at', true)) {
                update_post_meta($post_id, '_ticket_cancelled_at', current_time('mysql'));
            }
        }

        if ($post->post_status === $target_status) {
            return;
        }

        self::$updating = true;
        wp_update_post(
            [
                'ID'          => $post_id,
                'post_status' => $target_status,
            ]
        );
        self::$updating = false;
    }
}

