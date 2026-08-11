<?php

namespace EventTicketsElementor\Tickets;

use EventTicketsElementor\CPT_Tickets;
use EventTicketsElementor\Plugin;
use EventTicketsElementor\Ticket_Service;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Higher-level ticket cancellation flows (user/organizer + optional refund request).
 */
class Ticket_Cancellation_Service
{
    public const REFUND_NONE = 'none';
    public const REFUND_REQUESTED = 'requested';
    public const REFUND_APPROVED = 'approved';
    public const REFUND_DECLINED = 'declined';
    public const REFUND_REFUNDED = 'refunded';

    private Ticket_Service $tickets;

    public function __construct(Ticket_Service $tickets)
    {
        $this->tickets = $tickets;
    }

    /**
     * Cancel by user (optionally with refund request flag).
     *
     * @return true|\WP_Error
     */
    public function cancel_ticket_by_user(int $ticket_id, ?string $reason = null, bool $refund_requested = false)
    {
        $ticket = get_post($ticket_id);
        if (! $ticket || CPT_Tickets::POST_TYPE !== $ticket->post_type) {
            return new \WP_Error('evt_ticket_not_found', __('Ticket not found.', 'Event-Tickets-for-Elementor'));
        }

        $current_status = (string) get_post_meta($ticket_id, '_ticket_status', true);
        if ('checked_in' === $current_status) {
            return new \WP_Error('evt_ticket_checked_in', __('Checked-in tickets cannot be cancelled.', 'Event-Tickets-for-Elementor'));
        }

        if ($this->is_cancelled($ticket_id, $ticket)) {
            // Idempotent success. Still allow setting refund request if missing.
            if ($refund_requested) {
                $this->maybe_mark_refund_requested($ticket_id, $reason);
            }

            return true;
        }

        update_post_meta($ticket_id, '_ticket_cancel_source', 'user');
        if (null !== $reason && '' !== trim($reason)) {
            update_post_meta($ticket_id, '_ticket_cancel_reason', sanitize_text_field($reason));
        }

        $context = [
            'source'          => 'user',
            'refund_requested'=> $refund_requested,
            'reason'          => $reason ? sanitize_text_field($reason) : null,
        ];

        $ok = $this->tickets->cancel_ticket($ticket_id, $reason);
        if (! $ok) {
            return new \WP_Error('evt_cancel_failed', __('Unable to cancel ticket.', 'Event-Tickets-for-Elementor'));
        }

        do_action('evt_ticket_cancelled', $ticket_id, $context);

        if ($refund_requested) {
            $this->mark_refund_requested($ticket_id, $reason);
            do_action('evt_ticket_refund_requested', $ticket_id, $context);
        }

        return true;
    }

    /**
     * Cancel by organizer/admin (keeps checked-in protection).
     *
     * @return true|\WP_Error
     */
    public function cancel_ticket_by_organizer(int $ticket_id, int $user_id, ?string $reason = null)
    {
        $ticket = get_post($ticket_id);
        if (! $ticket || CPT_Tickets::POST_TYPE !== $ticket->post_type) {
            return new \WP_Error('evt_ticket_not_found', __('Ticket not found.', 'Event-Tickets-for-Elementor'));
        }

        $current_status = (string) get_post_meta($ticket_id, '_ticket_status', true);
        if ('checked_in' === $current_status) {
            return new \WP_Error('evt_ticket_checked_in', __('Checked-in tickets cannot be cancelled.', 'Event-Tickets-for-Elementor'));
        }

        if ($this->is_cancelled($ticket_id, $ticket)) {
            return true;
        }

        update_post_meta($ticket_id, '_ticket_cancel_source', 'organizer');
        update_post_meta($ticket_id, '_ticket_cancelled_by', absint($user_id));
        if (null !== $reason && '' !== trim($reason)) {
            update_post_meta($ticket_id, '_ticket_cancel_reason', sanitize_text_field($reason));
        }

        $context = [
            'source'      => 'organizer',
            'cancelled_by'=> absint($user_id),
            'reason'      => $reason ? sanitize_text_field($reason) : null,
        ];

        $ok = $this->tickets->cancel_ticket($ticket_id, $reason);
        if (! $ok) {
            return new \WP_Error('evt_cancel_failed', __('Unable to cancel ticket.', 'Event-Tickets-for-Elementor'));
        }

        do_action('evt_ticket_cancelled', $ticket_id, $context);

        return true;
    }

    public function get_refund_status(int $ticket_id): string
    {
        $status = (string) get_post_meta($ticket_id, '_ticket_refund_status', true);
        $status = strtolower(trim($status));
        if ('' === $status) {
            return self::REFUND_NONE;
        }

        $allowed = [self::REFUND_NONE, self::REFUND_REQUESTED, self::REFUND_APPROVED, self::REFUND_DECLINED, self::REFUND_REFUNDED];
        return in_array($status, $allowed, true) ? $status : self::REFUND_NONE;
    }

    private function is_cancelled(int $ticket_id, \WP_Post $ticket): bool
    {
        if ('cancelled' === (string) get_post_meta($ticket_id, '_ticket_status', true)) {
            return true;
        }

        return $ticket->post_status !== 'publish';
    }

    private function maybe_mark_refund_requested(int $ticket_id, ?string $reason): void
    {
        if (self::REFUND_NONE !== $this->get_refund_status($ticket_id)) {
            return;
        }

        $this->mark_refund_requested($ticket_id, $reason);
    }

    private function mark_refund_requested(int $ticket_id, ?string $reason): void
    {
        update_post_meta($ticket_id, '_ticket_refund_status', self::REFUND_REQUESTED);
        update_post_meta($ticket_id, '_ticket_refund_requested_at', current_time('mysql'));
        if (null !== $reason && '' !== trim($reason)) {
            update_post_meta($ticket_id, '_ticket_refund_notes', sanitize_text_field($reason));
        }
    }
}

