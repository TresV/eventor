<?php

namespace EventTicketsElementor\Payments;

use EventTicketsElementor\CPT_Orders;
use WP_Error;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Creates and mutates evt_order payment records.
 *
 * Owns the status transition rules (pending → paid/failed/expired/refunded),
 * the unguessable public key used by the return-page status endpoint, and the
 * refunded-amount ledger.
 */
class Payment_Order_Service
{
    /**
     * Create a pending payment order.
     *
     * @param array $data {
     *   event_id:int, timeslot_id:string, quantity:int, attendee_name:string,
     *   attendee_email:string, attendee_phone:string, amount_minor:int,
     *   currency:string, processor:string
     * }
     * @return int|\WP_Error evt_order post id
     */
    public function create(array $data)
    {
        $event_id = isset($data['event_id']) ? absint($data['event_id']) : 0;
        $email    = isset($data['attendee_email']) ? sanitize_email((string) $data['attendee_email']) : '';
        if (! $event_id || '' === $email) {
            return new WP_Error('evt_order_invalid', __('Missing event or attendee email for the payment order.', 'Event-Tickets-for-Elementor'));
        }

        $amount = isset($data['amount_minor']) ? absint($data['amount_minor']) : 0;
        if ($amount <= 0) {
            return new WP_Error('evt_order_invalid_amount', __('The payment order has no amount.', 'Event-Tickets-for-Elementor'));
        }

        $post_id = wp_insert_post(
            [
                'post_type'   => CPT_Orders::POST_TYPE,
                'post_status' => CPT_Orders::STATUS_PENDING,
                'post_title'  => $this->title_for($data),
            ],
            true
        );

        if (is_wp_error($post_id) || ! $post_id) {
            return $post_id ?: new WP_Error('evt_order_create_failed', __('Could not create the payment order.', 'Event-Tickets-for-Elementor'));
        }

        update_post_meta($post_id, '_evt_order_event_id', $event_id);
        update_post_meta($post_id, '_evt_order_timeslot_id', isset($data['timeslot_id']) ? sanitize_key((string) $data['timeslot_id']) : '');
        update_post_meta($post_id, '_evt_order_quantity', max(1, isset($data['quantity']) ? absint($data['quantity']) : 1));
        update_post_meta($post_id, '_evt_order_attendee_name', isset($data['attendee_name']) ? sanitize_text_field((string) $data['attendee_name']) : '');
        update_post_meta($post_id, '_evt_order_attendee_email', $email);
        update_post_meta($post_id, '_evt_order_attendee_phone', isset($data['attendee_phone']) ? sanitize_text_field((string) $data['attendee_phone']) : '');
        update_post_meta($post_id, '_evt_order_amount', $amount);
        update_post_meta($post_id, '_evt_order_currency', isset($data['currency']) ? strtoupper(sanitize_text_field((string) $data['currency'])) : 'EUR');
        update_post_meta($post_id, '_evt_order_processor', isset($data['processor']) ? sanitize_key((string) $data['processor']) : '');
        update_post_meta($post_id, '_evt_order_public_key', wp_generate_uuid4());
        update_post_meta($post_id, '_evt_order_refunded_amount', 0);

        return $post_id;
    }

    /**
     * @param array<string,mixed> $data
     */
    private function title_for(array $data): string
    {
        $event = get_the_title(isset($data['event_id']) ? absint($data['event_id']) : 0);
        $name  = isset($data['attendee_name']) && '' !== trim((string) $data['attendee_name'])
            ? sanitize_text_field((string) $data['attendee_name'])
            : sanitize_email((string) ($data['attendee_email'] ?? ''));

        return sprintf(
            /* translators: 1: attendee name/email, 2: event name */
            __('Order for %1$s — %2$s', 'Event-Tickets-for-Elementor'),
            $name ?: __('Attendee', 'Event-Tickets-for-Elementor'),
            $event ?: __('Event', 'Event-Tickets-for-Elementor')
        );
    }

    public function load(int $order_id): ?Order_Record
    {
        $order_id = absint($order_id);
        if (! $order_id || CPT_Orders::POST_TYPE !== get_post_type($order_id)) {
            return null;
        }
        return new Order_Record($order_id);
    }

    /**
     * Find an order by the processor reference stored at create time.
     */
    public function find_by_payment_ref(string $payment_ref): ?Order_Record
    {
        $payment_ref = sanitize_text_field($payment_ref);
        if ('' === $payment_ref) {
            return null;
        }

        $found = get_posts(
            [
                'post_type'      => CPT_Orders::POST_TYPE,
                'post_status'    => 'any',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'meta_query'     => [
                    [
                        'key'   => '_evt_order_payment_ref',
                        'value' => $payment_ref,
                    ],
                ],
            ]
        );

        return empty($found) ? null : new Order_Record((int) $found[0]);
    }

    /**
     * Find an order by its transaction id (used for idempotency).
     */
    public function find_by_transaction_id(string $transaction_id): ?Order_Record
    {
        $transaction_id = sanitize_text_field($transaction_id);
        if ('' === $transaction_id) {
            return null;
        }

        $found = get_posts(
            [
                'post_type'      => CPT_Orders::POST_TYPE,
                'post_status'    => 'any',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'meta_query'     => [
                    'relation' => 'OR',
                    [
                        'key'   => '_evt_order_idempotency_key',
                        'value' => $transaction_id,
                    ],
                    [
                        'key'   => '_evt_order_transaction_id',
                        'value' => $transaction_id,
                    ],
                ],
            ]
        );

        return empty($found) ? null : new Order_Record((int) $found[0]);
    }

    /**
     * Transition an order to one of the lifecycle statuses.
     */
    public function set_status(int $order_id, string $status): void
    {
        $allowed = [
            CPT_Orders::STATUS_PENDING,
            CPT_Orders::STATUS_PAID,
            CPT_Orders::STATUS_FAILED,
            CPT_Orders::STATUS_EXPIRED,
            CPT_Orders::STATUS_REFUNDED,
        ];
        if (! in_array($status, $allowed, true)) {
            return;
        }
        wp_update_post(['ID' => absint($order_id), 'post_status' => $status]);
    }

    /**
     * Store the processor's session/reference created for this order.
     */
    public function store_session(int $order_id, string $payment_ref): void
    {
        update_post_meta($order_id, '_evt_order_payment_ref', sanitize_text_field($payment_ref));
    }

    /**
     * Record a verified payment outcome (transaction id + idempotency key).
     */
    public function record_payment(int $order_id, Payment_Event $event): void
    {
        update_post_meta($order_id, '_evt_order_transaction_id', sanitize_text_field($event->transaction_id));
        update_post_meta($order_id, '_evt_order_idempotency_key', sanitize_text_field($event->transaction_id));
    }

    /**
     * Store the issued ticket ids on the order.
     *
     * @param array<int,int> $ticket_ids
     */
    public function record_tickets(int $order_id, array $ticket_ids): void
    {
        $ids = array_values(array_map('absint', $ticket_ids));
        update_post_meta($order_id, '_evt_order_ticket_ids', $ids);
        if (! empty($ids)) {
            update_post_meta($order_id, '_evt_order_issued_at', current_time('mysql'));
        }
    }

    /**
     * Set the seat-hold expiry for a pending order.
     */
    public function set_hold_expiry(int $order_id, int $ttl_seconds): void
    {
        update_post_meta($order_id, '_evt_order_hold_expires_at', time() + max(60, $ttl_seconds));
    }

    public function is_hold_active(Order_Record $order): bool
    {
        return CPT_Orders::STATUS_PENDING === $order->status() && time() < $order->hold_expires_at();
    }

    /**
     * Public (unauthenticated) status for the return-page poller.
     *
     * @param string $public_key
     * @return array{status:string, ticket_count:int}|null
     */
    public function public_status(string $public_key): ?array
    {
        $public_key = sanitize_text_field($public_key);
        if ('' === $public_key) {
            return null;
        }

        $found = get_posts(
            [
                'post_type'      => CPT_Orders::POST_TYPE,
                'post_status'    => 'any',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'meta_query'     => [
                    [
                        'key'   => '_evt_order_public_key',
                        'value' => $public_key,
                    ],
                ],
            ]
        );

        if (empty($found)) {
            return null;
        }

        $order = new Order_Record((int) $found[0]);
        return [
            'status'       => $order->status(),
            'ticket_count' => count($order->ticket_ids()),
        ];
    }
}
