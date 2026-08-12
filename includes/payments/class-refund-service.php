<?php

namespace EventTicketsElementor\Payments;

use EventTicketsElementor\CPT_Orders;
use EventTicketsElementor\Tickets\Ticket_Cancellation_Service;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Moves money when a refund is requested on a direct-path paid ticket.
 *
 * Listens to the existing `evt_ticket_refund_requested` action (which nothing
 * listens to today) and drives the processor refund + order/ticket status
 * updates. Phase 1: full-order refunds only (the per-order refunded-amount
 * ledger is in place so per-ticket partials can be added in Phase 2).
 */
class Refund_Service
{
    private Payment_Order_Service $orders;
    /** @var array<string, Payment_Processor> */
    private array $processors = [];

    public function __construct(Payment_Order_Service $orders)
    {
        $this->orders = $orders;

        add_action('evt_ticket_refund_requested', [$this, 'on_refund_requested'], 10, 2);
        add_action('evt_order_refund_requested', [$this, 'refund_order'], 10, 1);
    }

    public function register_processor(Payment_Processor $processor): void
    {
        $this->processors[$processor->name()] = $processor;
    }

    /**
     * @param int   $ticket_id
     * @param array $context
     */
    public function on_refund_requested(int $ticket_id, array $context = []): void
    {
        $ticket = get_post($ticket_id);
        if (! $ticket) {
            return;
        }

        $provider = (string) get_post_meta($ticket_id, '_ticket_payment_provider', true);
        // Only direct-processor tickets route refunds through here.
        if ('' === $provider) {
            return;
        }
        if (! isset($this->processors[$provider])) {
            return;
        }

        $order_id = absint(get_post_meta($ticket_id, '_ticket_order_id', true));
        if ($order_id) {
            $this->refund_order($order_id);
        }
    }

    /**
     * Refund an evt_order (full remaining amount) via its processor.
     */
    public function refund_order(int $order_id): void
    {
        $order = $this->orders->load($order_id);
        if ($order) {
            $this->refund($order);
        }
    }

    /**
     * Run a processor refund for an order.
     *
     * @return bool true when the processor confirmed the refund
     */
    public function refund(Order_Record $order): bool
    {
        $processor = $this->processors[$order->processor()] ?? null;
        if (! $processor) {
            return false;
        }

        $transaction_id = $order->transaction_id();
        if ('' === $transaction_id) {
            return false;
        }

        $amount_minor = $order->amount_minor() - $order->refunded_amount_minor();
        if ($amount_minor <= 0) {
            return false;
        }

        $result = $processor->refund($transaction_id, $amount_minor, $order->currency());
        if (is_wp_error($result)) {
            $this->flag_tickets($order, Ticket_Cancellation_Service::REFUND_DECLINED);
            return false;
        }

        $this->mark_order_refunded($order, $amount_minor);
        return true;
    }

    /**
     * Mark an order (and its tickets) refunded after a processor refund.
     *
     * Used both by the local refund flow and by processor-initiated refund
     * notifications (Payment_Event::STATUS_REFUNDED).
     */
    public function mark_order_refunded(Order_Record $order, int $amount_minor): void
    {
        $ledger = $order->refunded_amount_minor() + absint($amount_minor);
        update_post_meta($order->id(), '_evt_order_refunded_amount', $ledger);
        update_post_meta($order->id(), '_evt_order_refunded_at', current_time('mysql'));

        if ($ledger >= $order->amount_minor()) {
            $this->orders->set_status($order->id(), CPT_Orders::STATUS_REFUNDED);
        }

        $this->flag_tickets($order, Ticket_Cancellation_Service::REFUND_REFUNDED);
    }

    private function flag_tickets(Order_Record $order, string $status): void
    {
        foreach ($order->ticket_ids() as $ticket_id) {
            update_post_meta((int) $ticket_id, '_ticket_refund_status', sanitize_key($status));
        }
    }
}
