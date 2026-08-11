<?php

namespace EventTicketsElementor\Payments;

use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Value object for an evt_order post used by payment processors.
 *
 * Immutable wrapper over order meta so processors and services share one
 * shape. Never exposes secrets and never echoes user-supplied data unescaped.
 */
class Order_Record
{
    private int $id;
    /** @var array<string, array<int, mixed>> */
    private array $meta;

    public function __construct(int $order_id)
    {
        $this->id   = absint($order_id);
        $this->meta = get_post_meta($this->id);
    }

    public static function from_post(int $order_id): self
    {
        return new self($order_id);
    }

    public function id(): int
    {
        return $this->id;
    }

    public function status(): string
    {
        $post = get_post($this->id);
        return $post ? (string) $post->post_status : '';
    }

    public function event_id(): int
    {
        return absint($this->get('_evt_order_event_id'));
    }

    public function timeslot_id(): string
    {
        return sanitize_key((string) $this->get('_evt_order_timeslot_id'));
    }

    public function quantity(): int
    {
        return max(1, absint($this->get('_evt_order_quantity')));
    }

    public function attendee_name(): string
    {
        return (string) $this->get('_evt_order_attendee_name');
    }

    public function attendee_email(): string
    {
        return sanitize_email((string) $this->get('_evt_order_attendee_email'));
    }

    public function attendee_phone(): string
    {
        return (string) $this->get('_evt_order_attendee_phone');
    }

    public function amount_minor(): int
    {
        return absint($this->get('_evt_order_amount'));
    }

    public function currency(): string
    {
        $currency = (string) $this->get('_evt_order_currency');
        return '' !== $currency ? strtoupper($currency) : 'EUR';
    }

    public function processor(): string
    {
        return sanitize_key((string) $this->get('_evt_order_processor'));
    }

    public function transaction_id(): string
    {
        return (string) $this->get('_evt_order_transaction_id');
    }

    public function payment_ref(): string
    {
        return (string) $this->get('_evt_order_payment_ref');
    }

    public function public_key(): string
    {
        return (string) $this->get('_evt_order_public_key');
    }

    public function hold_expires_at(): int
    {
        return absint($this->get('_evt_order_hold_expires_at'));
    }

    public function refunded_amount_minor(): int
    {
        return absint($this->get('_evt_order_refunded_amount'));
    }

    /**
     * @return array<int,int>
     */
    public function ticket_ids(): array
    {
        $raw = $this->get('_evt_order_ticket_ids');
        if (is_array($raw)) {
            return array_values(array_map('absint', $raw));
        }
        $decoded = json_decode((string) $raw, true);
        return is_array($decoded) ? array_values(array_map('absint', $decoded)) : [];
    }

    /**
     * @param string $key
     * @return mixed
     */
    private function get(string $key)
    {
        return $this->meta[$key][0] ?? '';
    }
}
