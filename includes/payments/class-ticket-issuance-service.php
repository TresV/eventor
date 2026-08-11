<?php

namespace EventTicketsElementor\Payments;

use EventTicketsElementor\Email_Service;
use EventTicketsElementor\Event_Lock;
use EventTicketsElementor\Event_Timeslots;
use EventTicketsElementor\Ticket_Service;
use EventTicketsElementor\Tickets\Ticket_Email_Normalizer;
use EventTicketsElementor\Tickets\Ticket_Rules;
use EventTicketsElementor\Tickets\Ticket_Timeslot_Exclusivity;
use WP_Error;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Single source of truth for issuing tickets after payment (direct processors,
 * WooCommerce fallback) or for free checkout.
 *
 * Extracted from WooCommerce_Order_Ticketing::issue_tickets_for_item() and the
 * inline free path in Ticket_Box_Ajax. Enforces, once: timeslot validity, seat
 * availability (tickets + active holds), per-email limits, timeslot
 * exclusivity, rollback on failure, and email send. All issuance paths must go
 * through here so paid and free flows behave identically.
 */
class Ticket_Issuance_Service
{
    private Ticket_Service $tickets;
    private Email_Service $emails;
    private Reservation_Service $reservations;
    private Event_Timeslots $timeslots;
    private Event_Lock $lock;

    public function __construct(
        Ticket_Service $tickets,
        Email_Service $emails,
        Reservation_Service $reservations
    ) {
        $this->tickets      = $tickets;
        $this->emails       = $emails;
        $this->reservations = $reservations;
        $this->timeslots    = new Event_Timeslots();
        $this->lock         = new Event_Lock();
    }

    /**
     * Issue tickets for an event.
     *
     * @param array $args {
     *   event_id:int (required), timeslot_id:string, quantity:int,
     *   attendee_name:string, attendee_email:string (required),
     *   attendee_phone:string, source:string, source_id:string,
     *   payment_provider:string (optional: stripe|epay|woocommerce),
     *   payment_status:string (optional), order_id:int (optional),
     *   order_item_id:int (optional)
     * }
     * @return array<int,int>|\WP_Error list of ticket ids
     */
    public function issue(array $args)
    {
        $event_id       = isset($args['event_id']) ? absint($args['event_id']) : 0;
        $attendee_email = isset($args['attendee_email']) ? sanitize_email((string) $args['attendee_email']) : '';
        $attendee_name  = isset($args['attendee_name']) ? sanitize_text_field((string) $args['attendee_name']) : '';
        $attendee_phone = isset($args['attendee_phone']) ? sanitize_text_field((string) $args['attendee_phone']) : '';
        $timeslot_id    = isset($args['timeslot_id']) ? sanitize_key((string) $args['timeslot_id']) : '';
        $quantity       = max(1, isset($args['quantity']) ? absint($args['quantity']) : 1);

        if (! $event_id || '' === $attendee_email) {
            return new WP_Error('evt_issue_invalid', __('Missing event or attendee email.', 'Event-Tickets-for-Elementor'));
        }

        $limit_one     = (int) get_post_meta($event_id, Event_Timeslots::META_KEY_LIMIT_ONE_EMAIL, true);
        $max_per_email = (int) get_post_meta($event_id, Event_Timeslots::META_KEY_MAX_PER_EMAIL, true);
        $normalize     = (int) get_post_meta($event_id, Event_Timeslots::META_KEY_NORMALIZE_EMAIL, true);
        if ($limit_one) {
            $max_per_email = 1;
            $quantity      = 1;
        }
        $normalized = $normalize ? Ticket_Email_Normalizer::normalize($attendee_email) : '';

        $event_start = '';
        $event_end   = '';
        if ($this->timeslots->requires_timeslot_selection($event_id)) {
            if ('' === $timeslot_id) {
                return new WP_Error('evt_issue_missing_timeslot', __('This ticket is missing the required event timeslot.', 'Event-Tickets-for-Elementor'));
            }
            $slot = $this->timeslots->get_slot($event_id, $timeslot_id);
            if (! $slot) {
                return new WP_Error('evt_issue_invalid_timeslot', __('The event timeslot is no longer valid.', 'Event-Tickets-for-Elementor'));
            }
            $event_start = (string) ($slot['start'] ?? '');
            $event_end   = (string) ($slot['end'] ?? '');
        }

        $token = $this->lock->acquire($event_id, 15);
        if ('' === $token) {
            return new WP_Error('evt_event_locked', __('This event is being booked right now. Please try again in a moment.', 'Event-Tickets-for-Elementor'));
        }

        // Seat availability: tickets + active holds. A paid order's own hold is
        // still counted here and released via Reservation_Service::confirm()
        // AFTER issuance (see Payment_Service::mark_paid()).
        $remaining = $this->reservations->remaining($event_id, $timeslot_id);
        if (PHP_INT_MAX !== $remaining && $remaining < $quantity) {
            $this->lock->release($event_id, $token);
            return new WP_Error('evt_event_sold_out', __('This event is sold out.', 'Event-Tickets-for-Elementor'));
        }

        if ($max_per_email > 0) {
            $current = $this->tickets->count_tickets_for_event_email($event_id, $attendee_email, $normalized);
            if (($current + $quantity) > $max_per_email) {
                $this->lock->release($event_id, $token);
                return new WP_Error('evt_ticket_limit', __('This email already reached the ticket limit for this event.', 'Event-Tickets-for-Elementor'));
            }
        }

        // Exclusivity — single rule, enforced authoritatively at issuance so
        // free, paid-webhook, and WooCommerce paths all behave identically.
        if (Ticket_Rules::enforce_timeslot_exclusivity()) {
            $validator = new Ticket_Timeslot_Exclusivity(Ticket_Rules::timeslot_buffer_minutes());
            $timerange = null;
            if ('' !== $event_start) {
                $timerange = [
                    'start_ts' => (int) strtotime($event_start),
                    'end_ts'   => (int) strtotime($event_end ?: $event_start),
                ];
            }
            $ok = $validator->assert_no_timeslot_conflict($event_id, $attendee_email, $timerange);
            if (is_wp_error($ok)) {
                $this->lock->release($event_id, $token);
                return $ok;
            }
        }

        $ticket_ids = [];
        for ($i = 0; $i < $quantity; $i++) {
            $ticket_id = $this->tickets->create_ticket(
                [
                    'attendee_name'  => $attendee_name,
                    'attendee_email' => $attendee_email,
                    'event_id'       => $event_id,
                    'event_start'    => $event_start,
                    'event_end'      => $event_end,
                    'source'         => isset($args['source']) ? sanitize_key((string) $args['source']) : 'ticket_box',
                    'source_id'      => isset($args['source_id']) ? sanitize_text_field((string) $args['source_id']) : '',
                ]
            );

            if (is_wp_error($ticket_id)) {
                foreach ($ticket_ids as $rollback_id) {
                    wp_delete_post((int) $rollback_id, true);
                }
                $this->lock->release($event_id, $token);
                return new WP_Error('evt_ticket_create_failed', $ticket_id->get_error_message());
            }

            $ticket_id    = (int) $ticket_id;
            $ticket_ids[] = $ticket_id;

            if ('' !== $timeslot_id) {
                update_post_meta($ticket_id, '_ticket_timeslot_id', $timeslot_id);
            }
            if ('' !== $attendee_phone) {
                update_post_meta($ticket_id, '_ticket_phone', $attendee_phone);
            }
            if (($limit_one || $max_per_email > 0) && $normalized) {
                update_post_meta($ticket_id, '_ticket_email_normalized', $normalized);
            }

            if (! empty($args['payment_provider'])) {
                update_post_meta($ticket_id, '_ticket_payment_provider', sanitize_key((string) $args['payment_provider']));
            }
            if (! empty($args['payment_status'])) {
                update_post_meta($ticket_id, '_ticket_payment_status', sanitize_key((string) $args['payment_status']));
            }
            if (! empty($args['order_id'])) {
                update_post_meta($ticket_id, '_ticket_order_id', absint($args['order_id']));
            }
            if (! empty($args['order_item_id'])) {
                update_post_meta($ticket_id, '_ticket_order_item_id', absint($args['order_item_id']));
            }
        }

        $this->lock->release($event_id, $token);

        if (1 === count($ticket_ids)) {
            $this->emails->send_ticket_email((int) $ticket_ids[0]);
        } elseif (! empty($ticket_ids)) {
            $this->emails->send_multi_ticket_email($ticket_ids, $attendee_email, $attendee_name);
        }

        return $ticket_ids;
    }
}
