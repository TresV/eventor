<?php

namespace EventTicketsElementor;

use EventTicketsElementor\Tickets\Ticket_Email_Normalizer;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Issues tickets from paid WooCommerce orders.
 */
class WooCommerce_Order_Ticketing
{
    private Ticket_Service $tickets;
    private Email_Service $emails;
    private Event_Capacity $event_capacity;
    private Event_Lock $lock;
    private Event_Timeslots $timeslots;

    public function __construct(
        Ticket_Service $tickets,
        Email_Service $emails,
        Event_Capacity $event_capacity
    ) {
        $this->tickets = $tickets;
        $this->emails = $emails;
        $this->event_capacity = $event_capacity;
        $this->lock = new Event_Lock();
        $this->timeslots = new Event_Timeslots();

        add_action('woocommerce_order_status_processing', [$this, 'handle_paid_order']);
        add_action('woocommerce_order_status_completed', [$this, 'handle_paid_order']);
    }

    public function handle_paid_order(int $order_id): void
    {
        if (! function_exists('wc_get_order')) {
            return;
        }

        $order = wc_get_order($order_id);
        if (! $order) {
            return;
        }

        foreach ($order->get_items() as $item_id => $item) {
            $request = $this->request_from_item($item);
            if (empty($request)) {
                continue;
            }

            if ('issued' === (string) $item->get_meta('_evt_tickets_issue_status', true)) {
                continue;
            }

            $result = $this->issue_tickets_for_item($order, $item_id, $item, $request);
            if (is_wp_error($result)) {
                $item->update_meta_data('_evt_tickets_issue_status', 'failed');
                $item->update_meta_data('_evt_tickets_issue_error', $result->get_error_message());
                $item->save();
                $order->add_order_note(
                    sprintf(
                        /* translators: 1: order item id, 2: error */
                        __('Event Tickets: could not issue tickets for order item %1$d. %2$s', 'Event-Tickets-for-Elementor'),
                        (int) $item_id,
                        $result->get_error_message()
                    )
                );
                continue;
            }

            $ticket_ids = is_array($result) ? $result : [];
            $item->update_meta_data('_evt_tickets_issue_status', 'issued');
            $item->update_meta_data('_evt_tickets_ticket_ids', wp_json_encode($ticket_ids));
            $item->update_meta_data('_evt_tickets_issued_at', current_time('mysql'));
            $item->delete_meta_data('_evt_tickets_issue_error');
            $item->save();
        }
    }

    /**
     * @param \WC_Order                    $order
     * @param int                          $item_id
     * @param \WC_Order_Item_Product       $item
     * @param array<string,mixed>          $request
     * @return array<int,int>|\WP_Error
     */
    private function issue_tickets_for_item($order, int $item_id, $item, array $request)
    {
        $event_id = isset($request['event_id']) ? absint($request['event_id']) : 0;
        $quantity = max(1, (int) $item->get_quantity());
        $timeslot_id = isset($request['timeslot_id']) ? sanitize_key((string) $request['timeslot_id']) : '';
        $attendee_email = isset($request['attendee_email']) ? sanitize_email((string) $request['attendee_email']) : '';
        $attendee_name = isset($request['attendee_name']) ? sanitize_text_field((string) $request['attendee_name']) : '';
        $attendee_phone = isset($request['attendee_phone']) ? sanitize_text_field((string) $request['attendee_phone']) : '';

        if (! $event_id || '' === $attendee_email) {
            return new \WP_Error('evt_woo_missing_request', __('Ticket request metadata is incomplete on this WooCommerce order item.', 'Event-Tickets-for-Elementor'));
        }

        $limit_one = (int) get_post_meta($event_id, Event_Timeslots::META_KEY_LIMIT_ONE_EMAIL, true);
        $max_per_email = (int) get_post_meta($event_id, Event_Timeslots::META_KEY_MAX_PER_EMAIL, true);
        $normalize = (int) get_post_meta($event_id, Event_Timeslots::META_KEY_NORMALIZE_EMAIL, true);
        $normalized = $normalize ? Ticket_Email_Normalizer::normalize($attendee_email) : '';
        if ($limit_one) {
            $max_per_email = 1;
            $quantity = 1;
        }

        $event_start = '';
        $event_end = '';
        if ($this->timeslots->requires_timeslot_selection($event_id)) {
            if ('' === $timeslot_id) {
                return new \WP_Error('evt_woo_missing_timeslot', __('This order item is missing the required event timeslot.', 'Event-Tickets-for-Elementor'));
            }
            $slot = $this->timeslots->get_slot($event_id, $timeslot_id);
            if (! $slot) {
                return new \WP_Error('evt_woo_invalid_timeslot', __('The event timeslot stored on this order item is no longer valid.', 'Event-Tickets-for-Elementor'));
            }
            $event_start = (string) ($slot['start'] ?? '');
            $event_end = (string) ($slot['end'] ?? '');
        }

        $token = $this->lock->acquire($event_id, 15);
        if ('' === $token) {
            return new \WP_Error('evt_woo_event_locked', __('This event is being processed right now. Try the order status again in a moment.', 'Event-Tickets-for-Elementor'));
        }

        $capacity = new Event_Timeslot_Capacity($this->event_capacity, $this->timeslots);
        $remaining_capacity = $capacity->remaining($event_id, $timeslot_id);
        if (PHP_INT_MAX !== $remaining_capacity && $remaining_capacity < $quantity) {
            $this->lock->release($event_id, $token);
            return new \WP_Error('evt_woo_capacity_conflict', __('The event no longer has enough remaining capacity to fulfill this paid order item.', 'Event-Tickets-for-Elementor'));
        }

        if ($max_per_email > 0) {
            $current = $this->tickets->count_tickets_for_event_email($event_id, $attendee_email, $normalized);
            if (($current + $quantity) > $max_per_email) {
                $this->lock->release($event_id, $token);
                return new \WP_Error('evt_woo_ticket_limit', __('Issuing this order item would exceed the per-email ticket limit for the event.', 'Event-Tickets-for-Elementor'));
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
                    'source'         => 'woocommerce_order',
                    'source_id'      => $order->get_id() . ':' . $item_id,
                ]
            );

            if (is_wp_error($ticket_id)) {
                foreach ($ticket_ids as $rollback_ticket_id) {
                    wp_delete_post((int) $rollback_ticket_id, true);
                }
                $this->lock->release($event_id, $token);
                return new \WP_Error('evt_woo_ticket_create_failed', $ticket_id->get_error_message());
            }

            $ticket_id = (int) $ticket_id;
            $ticket_ids[] = $ticket_id;

            update_post_meta($ticket_id, '_ticket_payment_provider', 'woocommerce');
            update_post_meta($ticket_id, '_ticket_payment_status', sanitize_key((string) $order->get_status()));
            update_post_meta($ticket_id, '_ticket_order_id', (int) $order->get_id());
            update_post_meta($ticket_id, '_ticket_order_item_id', $item_id);

            if ('' !== $timeslot_id) {
                update_post_meta($ticket_id, '_ticket_timeslot_id', $timeslot_id);
            }

            if ('' !== $attendee_phone) {
                update_post_meta($ticket_id, '_ticket_phone', $attendee_phone);
            }

            if (($limit_one || $max_per_email > 0) && $normalized) {
                update_post_meta($ticket_id, '_ticket_email_normalized', $normalized);
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

    /**
     * @param \WC_Order_Item_Product $item
     * @return array<string,mixed>
     */
    private function request_from_item($item): array
    {
        $raw = (string) $item->get_meta('_evt_tickets_request_json', true);
        if ('' === $raw) {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
