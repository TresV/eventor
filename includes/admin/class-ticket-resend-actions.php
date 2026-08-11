<?php

namespace EventTicketsElementor\Admin;

use EventTicketsElementor\CPT_Tickets;
use EventTicketsElementor\Plugin;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Adds "Resend ticket email" actions to the Tickets list table.
 */
class Ticket_Resend_Actions
{
    private const BULK_ACTION = 'evt_resend_ticket_email';
    private const SINGLE_ACTION = 'evt_resend_ticket_email_single';

    public function __construct()
    {
        add_filter('bulk_actions-edit-' . CPT_Tickets::POST_TYPE, [$this, 'add_bulk_action']);
        add_filter('handle_bulk_actions-edit-' . CPT_Tickets::POST_TYPE, [$this, 'handle_bulk_action'], 10, 3);
        add_filter('post_row_actions', [$this, 'add_row_action'], 10, 2);

        add_action('admin_action_' . self::SINGLE_ACTION, [$this, 'handle_single_action']);
        add_action('admin_notices', [$this, 'admin_notice']);
    }

    public function add_bulk_action(array $actions): array
    {
        $actions[self::BULK_ACTION] = __('Resend ticket email', 'Event-Tickets-for-Elementor');
        return $actions;
    }

    /**
     * @param string $redirect_to
     * @param string $doaction
     * @param array<int,string|int> $post_ids
     */
    public function handle_bulk_action(string $redirect_to, string $doaction, array $post_ids): string
    {
        if (self::BULK_ACTION !== $doaction) {
            return $redirect_to;
        }

        $sent = 0;
        $failed = 0;

        foreach ($post_ids as $post_id) {
            $ticket_id = absint($post_id);
            if (! $ticket_id) {
                continue;
            }

            if (! current_user_can('edit_post', $ticket_id)) {
                $failed++;
                continue;
            }

            $ok = Plugin::instance()->mailer()->send_ticket_email($ticket_id);
            if ($ok) {
                $sent++;
            } else {
                $failed++;
            }
        }

        return add_query_arg(
            [
                'evt_resend_sent'   => $sent,
                'evt_resend_failed' => $failed,
            ],
            $redirect_to
        );
    }

    /**
     * Add a row action to the tickets list.
     *
     * @param array<string,string> $actions
     * @param \WP_Post             $post
     * @return array<string,string>
     */
    public function add_row_action(array $actions, $post): array
    {
        if (! $post || CPT_Tickets::POST_TYPE !== ($post->post_type ?? '')) {
            return $actions;
        }

        if (! current_user_can('edit_post', $post->ID)) {
            return $actions;
        }

        $url = wp_nonce_url(
            admin_url('admin.php?action=' . self::SINGLE_ACTION . '&ticket_id=' . absint($post->ID)),
            self::SINGLE_ACTION . '_' . absint($post->ID)
        );

        $actions['evt_resend'] = '<a href="' . esc_url($url) . '">' . esc_html__('Resend ticket email', 'Event-Tickets-for-Elementor') . '</a>';

        return $actions;
    }

    public function handle_single_action(): void
    {
        $ticket_id = isset($_GET['ticket_id']) ? absint($_GET['ticket_id']) : 0;
        if (! $ticket_id) {
            wp_safe_redirect(admin_url('edit.php?post_type=' . CPT_Tickets::POST_TYPE));
            exit;
        }

        if (! current_user_can('edit_post', $ticket_id)) {
            wp_die(esc_html__('You are not allowed to perform this action.', 'Event-Tickets-for-Elementor'));
        }

        check_admin_referer(self::SINGLE_ACTION . '_' . $ticket_id);

        $ok = Plugin::instance()->mailer()->send_ticket_email($ticket_id);

        $redirect = wp_get_referer();
        if (! $redirect) {
            $redirect = admin_url('edit.php?post_type=' . CPT_Tickets::POST_TYPE);
        }

        $redirect = add_query_arg(
            [
                'evt_resend_sent'   => $ok ? 1 : 0,
                'evt_resend_failed' => $ok ? 0 : 1,
            ],
            $redirect
        );

        wp_safe_redirect($redirect);
        exit;
    }

    public function admin_notice(): void
    {
        if (! is_admin()) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (! $screen || ('edit-' . CPT_Tickets::POST_TYPE) !== $screen->id) {
            return;
        }

        if (! isset($_GET['evt_resend_sent']) && ! isset($_GET['evt_resend_failed'])) {
            return;
        }

        $sent = isset($_GET['evt_resend_sent']) ? absint($_GET['evt_resend_sent']) : 0;
        $failed = isset($_GET['evt_resend_failed']) ? absint($_GET['evt_resend_failed']) : 0;

        if ($sent) {
            echo '<div class="notice notice-success is-dismissible"><p>';
            /* translators: %d is the number of emails resent. */
            echo esc_html(sprintf(_n('%d ticket email resent.', '%d ticket emails resent.', $sent, 'Event-Tickets-for-Elementor'), $sent));
            echo '</p></div>';
        }

        if ($failed) {
            echo '<div class="notice notice-warning is-dismissible"><p>';
            /* translators: %d is the number of emails that failed to send. */
            echo esc_html(sprintf(_n('%d ticket email failed to send.', '%d ticket emails failed to send.', $failed, 'Event-Tickets-for-Elementor'), $failed));
            echo '</p></div>';
        }
    }
}

