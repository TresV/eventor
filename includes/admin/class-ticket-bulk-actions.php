<?php

namespace EventTicketsElementor\Admin;

use EventTicketsElementor\CPT_Tickets;
use EventTicketsElementor\Plugin;
use EventTicketsElementor\Tickets\Ticket_Cancellation_Service;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Adds bulk and row actions to the Tickets list table:
 * - Cancel tickets
 * - Mark tickets as checked-in
 * - Export selected tickets as CSV
 */
class Ticket_Bulk_Actions
{
    private const BULK_CANCEL  = 'evt_bulk_cancel_tickets';
    private const BULK_CHECKIN = 'evt_bulk_checkin_tickets';
    private const BULK_EXPORT  = 'evt_bulk_export_tickets';
    private const SINGLE_CANCEL = 'evt_cancel_ticket_single';
    private const SINGLE_CHECKIN = 'evt_checkin_ticket_single';

    public function __construct()
    {
        add_filter('bulk_actions-edit-' . CPT_Tickets::POST_TYPE, [$this, 'add_bulk_actions']);
        add_filter('handle_bulk_actions-edit-' . CPT_Tickets::POST_TYPE, [$this, 'handle_bulk_action'], 10, 3);
        add_filter('post_row_actions', [$this, 'add_row_actions'], 10, 2);

        add_action('admin_action_' . self::SINGLE_CANCEL, [$this, 'handle_single_cancel']);
        add_action('admin_action_' . self::SINGLE_CHECKIN, [$this, 'handle_single_checkin']);
        add_action('admin_notices', [$this, 'admin_notice']);
    }

    public function add_bulk_actions(array $actions): array
    {
        $actions[self::BULK_CANCEL]  = __('Cancel tickets', 'Event-Tickets-for-Elementor');
        $actions[self::BULK_CHECKIN] = __('Mark as checked-in', 'Event-Tickets-for-Elementor');
        $actions[self::BULK_EXPORT]  = __('Export selected as CSV', 'Event-Tickets-for-Elementor');
        return $actions;
    }

    /**
     * @param string $redirect_to
     * @param string $doaction
     * @param array<int,string|int> $post_ids
     */
    public function handle_bulk_action(string $redirect_to, string $doaction, array $post_ids): string
    {
        if (self::BULK_CANCEL === $doaction) {
            return $this->bulk_cancel($redirect_to, $post_ids);
        }

        if (self::BULK_CHECKIN === $doaction) {
            return $this->bulk_checkin($redirect_to, $post_ids);
        }

        if (self::BULK_EXPORT === $doaction) {
            $this->export_selected($post_ids);
            // export_selected() exits.
        }

        return $redirect_to;
    }

    /**
     * @param array<int,string|int> $post_ids
     */
    private function bulk_cancel(string $redirect_to, array $post_ids): string
    {
        $service = new Ticket_Cancellation_Service(Plugin::instance()->ticket_service);
        $user_id = get_current_user_id();
        $cancelled = 0;
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

            $result = $service->cancel_ticket_by_organizer($ticket_id, $user_id);
            if (! is_wp_error($result)) {
                $cancelled++;
            } else {
                $failed++;
            }
        }

        return add_query_arg(
            [
                'evt_bulk_cancelled' => $cancelled,
                'evt_bulk_failed'    => $failed,
            ],
            $redirect_to
        );
    }

    /**
     * @param array<int,string|int> $post_ids
     */
    private function bulk_checkin(string $redirect_to, array $post_ids): string
    {
        $tickets = Plugin::instance()->ticket_service;
        $user_id = get_current_user_id();
        $checked = 0;
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

            $before = (string) get_post_meta($ticket_id, '_ticket_status', true);
            $tickets->check_in_ticket($ticket_id, $user_id);
            $after = (string) get_post_meta($ticket_id, '_ticket_status', true);

            if ('checked_in' === $after && 'checked_in' !== $before) {
                $checked++;
            } else {
                $failed++;
            }
        }

        return add_query_arg(
            [
                'evt_bulk_checked' => $checked,
                'evt_bulk_failed'  => $failed,
            ],
            $redirect_to
        );
    }

    /**
     * Stream a CSV of the selected tickets and exit.
     *
     * @param array<int,string|int> $post_ids
     */
    private function export_selected(array $post_ids): void
    {
        $ids = [];
        foreach ($post_ids as $post_id) {
            $ticket_id = absint($post_id);
            if (! $ticket_id || ! current_user_can('edit_post', $ticket_id)) {
                continue;
            }
            $ids[] = $ticket_id;
        }

        $ids = array_values(array_unique($ids));

        $filename = 'tickets-selected-' . gmdate('Ymd-His') . '.csv';

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        fputcsv($output, [
            'Ticket ID',
            'Code',
            'Attendee Name',
            'Attendee Email',
            'Status',
            'Checked-in At',
            'Event Name',
            'Event Start',
            'Event End',
            'Event Location',
        ]);

        foreach ($ids as $ticket_id) {
            fputcsv($output, [
                $this->escape_cell($ticket_id),
                $this->escape_cell(get_post_meta($ticket_id, '_ticket_code', true)),
                $this->escape_cell(get_post_meta($ticket_id, '_ticket_name', true)),
                $this->escape_cell(get_post_meta($ticket_id, '_ticket_email', true)),
                $this->escape_cell(get_post_meta($ticket_id, '_ticket_status', true) ?: 'pending'),
                $this->escape_cell(get_post_meta($ticket_id, '_ticket_checked_in_at', true)),
                $this->escape_cell(get_post_meta($ticket_id, '_ticket_event_name', true)),
                $this->escape_cell(get_post_meta($ticket_id, '_ticket_event_start', true)),
                $this->escape_cell(get_post_meta($ticket_id, '_ticket_event_end', true)),
                $this->escape_cell(get_post_meta($ticket_id, '_ticket_event_location', true)),
            ]);
        }

        exit;
    }

    /**
     * Add row actions to the tickets list.
     *
     * @param array<string,string> $actions
     * @param \WP_Post             $post
     * @return array<string,string>
     */
    public function add_row_actions(array $actions, $post): array
    {
        if (! $post || CPT_Tickets::POST_TYPE !== ($post->post_type ?? '')) {
            return $actions;
        }

        if (! current_user_can('edit_post', $post->ID)) {
            return $actions;
        }

        $ticket_id = absint($post->ID);

        $cancel_url = wp_nonce_url(
            admin_url('admin.php?action=' . self::SINGLE_CANCEL . '&ticket_id=' . $ticket_id),
            self::SINGLE_CANCEL . '_' . $ticket_id
        );
        $actions['evt_cancel'] = '<a href="' . esc_url($cancel_url) . '">' . esc_html__('Cancel ticket', 'Event-Tickets-for-Elementor') . '</a>';

        $checkin_url = wp_nonce_url(
            admin_url('admin.php?action=' . self::SINGLE_CHECKIN . '&ticket_id=' . $ticket_id),
            self::SINGLE_CHECKIN . '_' . $ticket_id
        );
        $actions['evt_checkin'] = '<a href="' . esc_url($checkin_url) . '">' . esc_html__('Mark checked-in', 'Event-Tickets-for-Elementor') . '</a>';

        return $actions;
    }

    public function handle_single_cancel(): void
    {
        $ticket_id = isset($_GET['ticket_id']) ? absint($_GET['ticket_id']) : 0;
        $this->redirect_back_or_die($ticket_id);

        check_admin_referer(self::SINGLE_CANCEL . '_' . $ticket_id);

        $service = new Ticket_Cancellation_Service(Plugin::instance()->ticket_service);
        $result = $service->cancel_ticket_by_organizer($ticket_id, get_current_user_id());

        $this->redirect_back([
            'evt_bulk_cancelled' => is_wp_error($result) ? 0 : 1,
            'evt_bulk_failed'    => is_wp_error($result) ? 1 : 0,
        ]);
    }

    public function handle_single_checkin(): void
    {
        $ticket_id = isset($_GET['ticket_id']) ? absint($_GET['ticket_id']) : 0;
        $this->redirect_back_or_die($ticket_id);

        check_admin_referer(self::SINGLE_CHECKIN . '_' . $ticket_id);

        $before = (string) get_post_meta($ticket_id, '_ticket_status', true);
        Plugin::instance()->ticket_service->check_in_ticket($ticket_id, get_current_user_id());
        $after = (string) get_post_meta($ticket_id, '_ticket_status', true);

        $ok = ('checked_in' === $after && 'checked_in' !== $before);
        $this->redirect_back([
            'evt_bulk_checked' => $ok ? 1 : 0,
            'evt_bulk_failed'  => $ok ? 0 : 1,
        ]);
    }

    private function redirect_back_or_die(int $ticket_id): void
    {
        if (! $ticket_id) {
            wp_safe_redirect(admin_url('edit.php?post_type=' . CPT_Tickets::POST_TYPE));
            exit;
        }

        if (! current_user_can('edit_post', $ticket_id)) {
            wp_die(esc_html__('You are not allowed to perform this action.', 'Event-Tickets-for-Elementor'));
        }
    }

    /**
     * @param array<string,int> $query_args
     */
    private function redirect_back(array $query_args): void
    {
        $redirect = wp_get_referer();
        if (! $redirect) {
            $redirect = admin_url('edit.php?post_type=' . CPT_Tickets::POST_TYPE);
        }

        wp_safe_redirect(add_query_arg($query_args, $redirect));
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

        $has_cancel = isset($_GET['evt_bulk_cancelled']) || isset($_GET['evt_bulk_failed']);
        $has_check  = isset($_GET['evt_bulk_checked']) || isset($_GET['evt_bulk_failed']);

        if ($has_cancel) {
            $cancelled = isset($_GET['evt_bulk_cancelled']) ? absint($_GET['evt_bulk_cancelled']) : 0;
            $failed = isset($_GET['evt_bulk_failed']) ? absint($_GET['evt_bulk_failed']) : 0;
            if ($cancelled) {
                echo '<div class="notice notice-success is-dismissible"><p>';
                /* translators: %d is the number of cancelled tickets. */
                echo esc_html(sprintf(_n('%d ticket cancelled.', '%d tickets cancelled.', $cancelled, 'Event-Tickets-for-Elementor'), $cancelled));
                echo '</p></div>';
            }
            if ($failed) {
                echo '<div class="notice notice-warning is-dismissible"><p>';
                /* translators: %d is the number of tickets that could not be cancelled. */
                echo esc_html(sprintf(_n('%d ticket could not be cancelled.', '%d tickets could not be cancelled.', $failed, 'Event-Tickets-for-Elementor'), $failed));
                echo '</p></div>';
            }
            return;
        }

        if ($has_check) {
            $checked = isset($_GET['evt_bulk_checked']) ? absint($_GET['evt_bulk_checked']) : 0;
            $failed = isset($_GET['evt_bulk_failed']) ? absint($_GET['evt_bulk_failed']) : 0;
            if ($checked) {
                echo '<div class="notice notice-success is-dismissible"><p>';
                /* translators: %d is the number of tickets marked checked-in. */
                echo esc_html(sprintf(_n('%d ticket marked as checked-in.', '%d tickets marked as checked-in.', $checked, 'Event-Tickets-for-Elementor'), $checked));
                echo '</p></div>';
            }
            if ($failed) {
                echo '<div class="notice notice-warning is-dismissible"><p>';
                /* translators: %d is the number of tickets that could not be checked in. */
                echo esc_html(sprintf(_n('%d ticket could not be checked in.', '%d tickets could not be checked in.', $failed, 'Event-Tickets-for-Elementor'), $failed));
                echo '</p></div>';
            }
        }
    }

    /**
     * Escape a CSV cell against spreadsheet formula injection.
     *
     * @param mixed $value
     */
    private function escape_cell($value): string
    {
        $value = (string) $value;
        if ('' === $value) {
            return '';
        }

        $first = $value[0];
        if (in_array($first, ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $value;
        }

        return $value;
    }
}
