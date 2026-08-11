<?php

namespace EventTicketsElementor;

use EventTicketsElementor\Payments\Payment_Order_Service;
use EventTicketsElementor\Payments\Reservation_Service;
use EventTicketsElementor\Payments\Ticket_Issuance_Service;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Issues tickets from paid WooCommerce orders.
 *
 * All issuance logic now lives in Ticket_Issuance_Service (the single source
 * of truth shared with the direct processor path); this class only feeds order
 * item metadata into it.
 */
class WooCommerce_Order_Ticketing
{
    private Ticket_Service $tickets;
    private Email_Service $emails;
    private Event_Capacity $event_capacity;
    private Ticket_Issuance_Service $issuance;

    public function __construct(
        Ticket_Service $tickets,
        Email_Service $emails,
        Event_Capacity $event_capacity,
        ?Ticket_Issuance_Service $issuance = null
    ) {
        $this->tickets = $tickets;
        $this->emails = $emails;
        $this->event_capacity = $event_capacity;

        if ($issuance) {
            $this->issuance = $issuance;
        } else {
            // Safety fallback so the class still works if constructed standalone.
            $orders       = new Payment_Order_Service();
            $reservations = new Reservation_Service($event_capacity, $orders);
            $this->issuance = new Ticket_Issuance_Service($tickets, $emails, $reservations);
        }

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
        $event_id       = isset($request['event_id']) ? absint($request['event_id']) : 0;
        $timeslot_id    = isset($request['timeslot_id']) ? sanitize_key((string) $request['timeslot_id']) : '';
        $attendee_email = isset($request['attendee_email']) ? sanitize_email((string) $request['attendee_email']) : '';
        $attendee_name  = isset($request['attendee_name']) ? sanitize_text_field((string) $request['attendee_name']) : '';
        $attendee_phone = isset($request['attendee_phone']) ? sanitize_text_field((string) $request['attendee_phone']) : '';
        $quantity       = max(1, (int) $item->get_quantity());

        if (! $event_id || '' === $attendee_email) {
            return new \WP_Error('evt_woo_missing_request', __('Ticket request metadata is incomplete on this WooCommerce order item.', 'Event-Tickets-for-Elementor'));
        }

        $issued = $this->issuance->issue(
            [
                'event_id'         => $event_id,
                'timeslot_id'      => $timeslot_id,
                'quantity'         => $quantity,
                'attendee_name'    => $attendee_name,
                'attendee_email'   => $attendee_email,
                'attendee_phone'   => $attendee_phone,
                'source'           => 'woocommerce_order',
                'source_id'        => $order->get_id() . ':' . $item_id,
                'payment_provider' => 'woocommerce',
                'payment_status'   => sanitize_key((string) $order->get_status()),
                'order_id'         => (int) $order->get_id(),
                'order_item_id'    => $item_id,
            ]
        );

        if (is_wp_error($issued)) {
            return new \WP_Error('evt_woo_ticket_issue_failed', $issued->get_error_message());
        }

        return is_array($issued) ? $issued : [];
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
