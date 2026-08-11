<?php

namespace EventTicketsElementor;

use EventTicketsElementor\Tickets\Ticket_Rules;
use EventTicketsElementor\Tickets\Ticket_Timeslot_Exclusivity;
use EventTicketsElementor\Tickets\Ticket_Email_Normalizer;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Handles AJAX ticket creation from the Ticket Box widget.
 */
class Ticket_Box_Ajax
{
    private Event_Lock $lock;
    private Event_Timeslots $timeslots;

    public function __construct()
    {
        $this->lock = new Event_Lock();
        $this->timeslots = new Event_Timeslots();
        add_action('wp_ajax_evt_ticket_box_submit', [$this, 'handle']);
        add_action('wp_ajax_nopriv_evt_ticket_box_submit', [$this, 'handle']);
        add_action('wp_ajax_evt_ticket_box_timeslots', [$this, 'handle_timeslots']);
        add_action('wp_ajax_nopriv_evt_ticket_box_timeslots', [$this, 'handle_timeslots']);
    }

    public function handle_timeslots(): void
    {
        check_ajax_referer('evt_ticket_box', 'nonce');

        $event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;
        if (! $event_id) {
            \evt_json_success(['required' => false, 'slots' => [], 'limit_one' => false, 'max_per_email' => 0]);
        }

        $limit_one = ! empty(get_post_meta($event_id, Event_Timeslots::META_KEY_LIMIT_ONE_EMAIL, true));
        $max_per_email = (int) get_post_meta($event_id, Event_Timeslots::META_KEY_MAX_PER_EMAIL, true);
        if ($limit_one) {
            $max_per_email = 1;
        }

        if (! $this->timeslots->requires_timeslot_selection($event_id)) {
            \evt_json_success(['required' => false, 'slots' => [], 'limit_one' => $limit_one, 'max_per_email' => $max_per_email]);
        }

        $slots = $this->timeslots->get_slots($event_id);
        if (empty($slots)) {
            \evt_json_success(['required' => false, 'slots' => [], 'limit_one' => $limit_one, 'max_per_email' => $max_per_email]);
        }

        $capacity = new Event_Timeslot_Capacity(Plugin::instance()->event_capacity, $this->timeslots);
        $mode = $this->timeslots->get_capacity_mode($event_id);

        $out = [];
        foreach ($slots as $slot) {
            $label = $this->timeslots->format_slot_label($slot);

            if (in_array($mode, [Event_Timeslots::CAPACITY_MODE_SLOT, Event_Timeslots::CAPACITY_MODE_BOTH], true) && ! empty($slot['capacity'])) {
                $remaining = $capacity->remaining_for_slot($event_id, (string) $slot['id']);
                if (PHP_INT_MAX !== $remaining) {
                    $label .= ' (' . sprintf(
                        /* translators: %d is remaining tickets */
                        __('%d left', 'Event-Tickets-for-Elementor'),
                        (int) $remaining
                    ) . ')';
                }
            }

            $out[] = [
                'id'    => (string) $slot['id'],
                'label' => $label,
            ];
        }

        \evt_json_success(
            [
                'required' => true,
                'slots'    => $out,
                'limit_one' => $limit_one,
                'max_per_email' => $max_per_email,
            ]
        );
    }

    public function handle(): void
    {
        check_ajax_referer('evt_ticket_box', 'nonce');

        // Honeypot: silently drop bots that fill the hidden field.
        if (isset($_POST['evt_website']) && '' !== trim((string) sanitize_text_field(wp_unslash($_POST['evt_website'])))) {
            \evt_json_success(['message' => __('Ticket created and emailed.', 'Event-Tickets-for-Elementor')]);
            return;
        }

        // Coarse anti-flood brake per client IP.
        if (evt_rate_limit_hit('ticket_box:ip:' . evt_client_ip(), 20, 900)) {
            \evt_json_error('evt_rate_limited', __('Too many requests. Please try again in a few minutes.', 'Event-Tickets-for-Elementor'), 429);
        }

        $event_id       = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;
        $timeslot_id    = isset($_POST['timeslot_id']) ? sanitize_key((string) wp_unslash($_POST['timeslot_id'])) : '';
        $quantity       = isset($_POST['quantity']) ? max(1, min(10, (int) $_POST['quantity'])) : 1;
        $requested_quantity = $quantity;
        $attendee_name  = isset($_POST['attendee_name']) ? sanitize_text_field(wp_unslash($_POST['attendee_name'])) : '';
        $attendee_phone = isset($_POST['attendee_phone']) ? sanitize_text_field(wp_unslash($_POST['attendee_phone'])) : '';
        $attendee_email = isset($_POST['attendee_email']) ? sanitize_email(wp_unslash($_POST['attendee_email'])) : '';
        $current_url = isset($_POST['current_url']) ? esc_url_raw((string) wp_unslash($_POST['current_url'])) : '';

        if (! $attendee_email) {
            \evt_json_error('evt_missing_email', __('Please provide an email address.', 'Event-Tickets-for-Elementor'), 400);
        }

        if (! $event_id) {
            \evt_json_error('evt_missing_event', __('Please select an event.', 'Event-Tickets-for-Elementor'), 400);
        }

        // Prevent mail-bombing a single address: limit batches per email.
        if (evt_rate_limit_hit('ticket_box:email:' . strtolower($attendee_email), 3, 900)) {
            \evt_json_error('evt_rate_limited', __('Too many tickets requested for this email. Please try again in a few minutes.', 'Event-Tickets-for-Elementor'), 429);
        }

        $limit_one = (int) get_post_meta($event_id, Event_Timeslots::META_KEY_LIMIT_ONE_EMAIL, true);
        $normalize = (int) get_post_meta($event_id, Event_Timeslots::META_KEY_NORMALIZE_EMAIL, true);
        $max_per_email = (int) get_post_meta($event_id, Event_Timeslots::META_KEY_MAX_PER_EMAIL, true);
        if ($limit_one) {
            $max_per_email = 1;
        }
        $normalized = $normalize ? Ticket_Email_Normalizer::normalize($attendee_email) : '';

        $email_limit_adjusted = false;
        if ($max_per_email > 0) {
            $current = Plugin::instance()->ticket_service->count_tickets_for_event_email($event_id, $attendee_email, $normalized);
            $remaining_for_email = max(0, $max_per_email - $current);
            if ($remaining_for_email <= 0) {
                \evt_json_error(
                    'evt_ticket_limit',
                    __('You have reached the ticket limit for this event.', 'Event-Tickets-for-Elementor'),
                    409,
                    ['remaining' => 0]
                );
            }
            if ($quantity > $remaining_for_email) {
                $quantity = $remaining_for_email;
                $email_limit_adjusted = true;
            }
        }

        $event_start = '';
        $event_end   = '';

        if ($this->timeslots->requires_timeslot_selection($event_id)) {
            if ('' === $timeslot_id) {
                \evt_json_error('evt_missing_timeslot', __('Please select a timeslot.', 'Event-Tickets-for-Elementor'), 400);
            }

            $slot = $this->timeslots->get_slot($event_id, $timeslot_id);
            if (! $slot) {
                \evt_json_error('evt_invalid_timeslot', __('Selected timeslot is invalid.', 'Event-Tickets-for-Elementor'), 400);
            }

            $event_start = (string) ($slot['start'] ?? '');
            $event_end   = (string) ($slot['end'] ?? '');
        }

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
                $data = $ok->get_error_data();
                \evt_json_error(
                    'evt_timeslot_conflict',
                    $ok->get_error_message(),
                    409,
                    is_array($data) ? $data : []
                );
            }
        }

        $ticket_service = Plugin::instance()->ticket_service;
        $email_service  = Plugin::instance()->email_service;
        $payment_service = Plugin::instance()->payments();
        $capacity       = new Event_Timeslot_Capacity(Plugin::instance()->event_capacity, $this->timeslots);

        $ticket_ids = [];

        $remaining = $capacity->remaining($event_id, $timeslot_id);
        if (PHP_INT_MAX !== $remaining) {
            if ($remaining <= 0) {
                \evt_json_error('evt_event_sold_out', __('This event is sold out.', 'Event-Tickets-for-Elementor'), 409);
            }
            if ($quantity > $remaining) {
                $quantity = $remaining;
            }
        }

        if ($payment_service->is_event_paid($event_id)) {
            if (! $payment_service->is_connected()) {
                \evt_json_error(
                    'evt_woocommerce_not_ready',
                    __('Paid ticket checkout is not available right now. Please contact the organizer.', 'Event-Tickets-for-Elementor'),
                    503
                );
            }

            $checkout = $payment_service->begin_checkout(
                [
                    'event_id'       => $event_id,
                    'timeslot_id'    => $timeslot_id,
                    'quantity'       => $limit_one ? 1 : $quantity,
                    'attendee_name'  => $attendee_name,
                    'attendee_phone' => $attendee_phone,
                    'attendee_email' => $attendee_email,
                    'return_url'     => $current_url,
                ]
            );

            if (is_wp_error($checkout)) {
                \evt_json_error('evt_checkout_failed', $checkout->get_error_message(), 400);
            }

            $response = [
                'redirect_url' => (string) $checkout['redirect_url'],
                'message'      => __('Redirecting to WooCommerce checkout…', 'Event-Tickets-for-Elementor'),
            ];
            if (isset($remaining_for_email)) {
                $response['remaining'] = (int) $remaining_for_email;
            }

            \evt_json_success($response);
        }

        // Basic concurrency guard to reduce oversells.
        $token = $this->lock->acquire($event_id, 15);
        if ('' === $token) {
            \evt_json_error('evt_event_locked', __('This event is being booked right now. Please try again in a moment.', 'Event-Tickets-for-Elementor'), 409);
        }

        $issue_count = $limit_one ? 1 : $quantity;

        for ($i = 0; $i < $issue_count; $i++) {
            // Re-check before each ticket to reduce race-window.
            $remaining_now = $capacity->remaining($event_id, $timeslot_id);
            if (PHP_INT_MAX !== $remaining_now && $remaining_now <= 0) {
                break;
            }

            $ticket_id = $ticket_service->create_ticket([
                'attendee_name'  => $attendee_name,
                'attendee_email' => $attendee_email,
                'event_id'       => $event_id,
                'event_start'    => $event_start,
                'event_end'      => $event_end,
                'source'         => 'ticket_box',
                'source_id'      => 'widget_' . time(),
            ]);

            if (is_wp_error($ticket_id)) {
                $this->lock->release($event_id, $token);
                \evt_json_error('evt_ticket_create_failed', $ticket_id->get_error_message(), 400);
            }

            $ticket_ids[] = $ticket_id;

            if ('' !== $timeslot_id) {
                update_post_meta((int) $ticket_id, '_ticket_timeslot_id', $timeslot_id);
            }

            if ('' !== $attendee_phone) {
                update_post_meta((int) $ticket_id, '_ticket_phone', $attendee_phone);
            }

            if (($limit_one || $max_per_email > 0) && $normalized) {
                update_post_meta((int) $ticket_id, '_ticket_email_normalized', $normalized);
            }
        }

        $this->lock->release($event_id, $token);

        if (! empty($ticket_ids)) {
            if (1 === count($ticket_ids)) {
                $email_service->send_ticket_email((int) $ticket_ids[0]);
            } else {
                $email_service->send_multi_ticket_email($ticket_ids, $attendee_email, $attendee_name);
            }
        }

        $message = '';
        if ($email_limit_adjusted) {
            $message = sprintf(
                /* translators: %d is the number of tickets created after applying the per-email limit */
                _n(
                    'Your request exceeded the per-email limit for this event. We created and emailed %d ticket.',
                    'Your request exceeded the per-email limit for this event. We created and emailed %d tickets.',
                    count($ticket_ids),
                    'Event-Tickets-for-Elementor'),
                count($ticket_ids)
            );
        } elseif (count($ticket_ids) === $issue_count) {
            $message = sprintf(
                /* translators: %d is quantity */
                _n('%d ticket created and emailed.', '%d tickets created and emailed.', count($ticket_ids), 'Event-Tickets-for-Elementor'),
                count($ticket_ids)
            );
        } else {
            $message = sprintf(
                /* translators: 1: created count, 2: requested count */
                __('Only %1$d of %2$d requested tickets were available. We created and emailed %1$d.', 'Event-Tickets-for-Elementor'),
                count($ticket_ids),
                $requested_quantity
            );
        }

        $response = [
            'tickets' => $ticket_ids,
            'message' => $message,
        ];
        if (isset($remaining_for_email)) {
            $response['remaining'] = (int) $remaining_for_email;
        }

        \evt_json_success($response);
    }
}
