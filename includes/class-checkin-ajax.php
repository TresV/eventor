<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Handles AJAX requests for the staff check-in widget.
 *
 * Endpoint: admin-ajax.php?action=evt_tickets_checkin
 */
class Checkin_Ajax
{

    /** @var Ticket_Service */
    protected $ticket_service;

    public function __construct(Ticket_Service $ticket_service)
    {
        $this->ticket_service = $ticket_service;

        // Logged-in only – staff must be logged in.
        add_action('wp_ajax_evt_tickets_checkin', [$this, 'handle']);
    }

    /**
     * Main AJAX handler.
     */
    public function handle(): void
    {
        // Nonce check.
        check_ajax_referer('evt_checkin_ajax', 'nonce');

        if (! is_user_logged_in() || ! current_user_can(Staff_Access_Manager::CAP_USE_CHECKIN)) {
            \evt_json_error('evt_forbidden', __('You are not allowed to perform this action.', 'Event-Tickets-for-Elementor'), 403);
        }

        $mode = isset($_POST['mode']) ? sanitize_text_field(wp_unslash($_POST['mode'])) : 'lookup';

        if ('confirm' === $mode) {
            $this->handle_confirm();
        } else {
            $this->handle_lookup();
        }
    }

    /**
     * Handle "lookup" (check a ticket code).
     */
    protected function handle_lookup(): void
    {
        $ticket_code = isset($_POST['ticket_code'])
            ? sanitize_text_field(wp_unslash($_POST['ticket_code']))
            : '';

        if ('' === $ticket_code) {
            \evt_json_error('evt_missing_code', __('Please provide a ticket code.', 'Event-Tickets-for-Elementor'), 400);
        }

        $ticket = $this->ticket_service->get_ticket_by_code($ticket_code);

        if (! $ticket) {
            \evt_json_error('evt_ticket_not_found', __('No ticket was found for this code.', 'Event-Tickets-for-Elementor'), 404);
        }

        $ticket_data = $this->get_ticket_data($ticket->ID);

        // Build status info.
        if ('checked_in' === $ticket_data['status']) {
            $status = [
                'type'  => 'warning',
                'title' => __('Ticket already checked in', 'Event-Tickets-for-Elementor'),
                'body'  => $ticket_data['checked_in_at']
                    ? sprintf(
                        /* translators: %s is the check-in time. */
                        __('This ticket was checked in at %s.', 'Event-Tickets-for-Elementor'),
                        $ticket_data['checked_in_at']
                    )
                    : __('This ticket has already been used.', 'Event-Tickets-for-Elementor'),
            ];
        } else {
            $status = [
                'type'  => 'success',
                'title' => __('Valid ticket', 'Event-Tickets-for-Elementor'),
                'body'  => __('This ticket is valid and not yet checked in.', 'Event-Tickets-for-Elementor'),
            ];
        }

        \evt_json_success(['status' => $status, 'ticket' => $ticket_data]);
    }

    /**
     * Handle "confirm" (mark ticket as checked-in).
     */
    protected function handle_confirm(): void
    {
        $ticket_id = isset($_POST['ticket_id'])
            ? absint($_POST['ticket_id'])
            : 0;

        if (! $ticket_id) {
            \evt_json_error('evt_missing_ticket_id', __('Missing ticket ID.', 'Event-Tickets-for-Elementor'), 400);
        }

        $ticket = get_post($ticket_id);
        if (! $ticket || CPT_Tickets::POST_TYPE !== $ticket->post_type) {
            \evt_json_error('evt_ticket_not_found', __('Ticket not found.', 'Event-Tickets-for-Elementor'), 404);
        }

        if (! $this->ticket_service->is_active_ticket($ticket_id)) {
            \evt_json_error('evt_ticket_cancelled', __('This ticket is cancelled and cannot be checked in.', 'Event-Tickets-for-Elementor'), 400);
        }

        $this->ticket_service->check_in_ticket($ticket_id, get_current_user_id());
        $ticket_data = $this->get_ticket_data($ticket_id);

        $status = [
            'type'  => 'success',
            'title' => __('Ticket checked in', 'Event-Tickets-for-Elementor'),
            'body'  => __('The ticket has been successfully marked as checked in.', 'Event-Tickets-for-Elementor'),
        ];

        \evt_json_success(['status' => $status, 'ticket' => $ticket_data]);
    }

    /**
     * Normalize ticket data for JSON responses.
     *
     * @param int $ticket_id
     * @return array
     */
    protected function get_ticket_data(int $ticket_id): array
    {
        $ticket = get_post($ticket_id);
        if (! $ticket || CPT_Tickets::POST_TYPE !== $ticket->post_type) {
            return [];
        }

        $code          = get_post_meta($ticket_id, '_ticket_code', true);
        $name          = get_post_meta($ticket_id, '_ticket_name', true);
        $email         = get_post_meta($ticket_id, '_ticket_email', true);
        $event_name    = get_post_meta($ticket_id, '_ticket_event_name', true);
        $status        = get_post_meta($ticket_id, '_ticket_status', true);
        $checked_in_at = get_post_meta($ticket_id, '_ticket_checked_in_at', true);

        if (! $status) {
            $status = 'pending';
        }

        return [
            'id'            => $ticket_id,
            'code'          => (string) $code,
            'name'          => (string) $name,
            'email'         => (string) $email,
            'event_name'    => (string) $event_name,
            'status'        => (string) $status,
            'checked_in_at' => (string) $checked_in_at,
        ];
    }
}
