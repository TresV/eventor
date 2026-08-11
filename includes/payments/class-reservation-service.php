<?php

namespace EventTicketsElementor\Payments;

use EventTicketsElementor\CPT_Orders;
use EventTicketsElementor\Event_Capacity;
use EventTicketsElementor\Event_Lock;
use EventTicketsElementor\Event_Timeslot_Capacity;
use EventTicketsElementor\Event_Timeslots;
use WP_Error;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Seat reservation (hold) management for the direct paid path.
 *
 * A pending evt_order holds seats so a customer who is mid-payment cannot be
 * oversold. Holds live as counters on the event post (guarded by Event_Lock so
 * increments are race-free) and are released on failure/expiry (sweep) or
 * converted to tickets on payment (confirm).
 */
class Reservation_Service
{
    private const HOLDS_META = '_evt_event_holds';

    private Event_Capacity $event_capacity;
    private Event_Timeslots $timeslots;
    private Event_Timeslot_Capacity $slot_capacity;
    private Event_Lock $lock;
    private Payment_Order_Service $orders;

    public function __construct(Event_Capacity $event_capacity, Payment_Order_Service $orders)
    {
        $this->event_capacity = $event_capacity;
        $this->orders         = $orders;
        $this->timeslots      = new Event_Timeslots();
        $this->slot_capacity  = new Event_Timeslot_Capacity($event_capacity, $this->timeslots);
        $this->lock           = new Event_Lock();
    }

    /**
     * @return array{event:int, slots:array<string,int>}
     */
    private function holds(int $event_id): array
    {
        $raw = get_post_meta($event_id, self::HOLDS_META, true);
        if (! is_array($raw)) {
            return ['event' => 0, 'slots' => []];
        }
        return [
            'event' => max(0, absint($raw['event'] ?? 0)),
            'slots' => is_array($raw['slots'] ?? null) ? $raw['slots'] : [],
        ];
    }

    /**
     * @param array{event:int, slots:array<string,int>} $holds
     */
    private function save_holds(int $event_id, array $holds): void
    {
        if (0 === $holds['event'] && empty($holds['slots'])) {
            delete_post_meta($event_id, self::HOLDS_META);
            return;
        }
        update_post_meta($event_id, self::HOLDS_META, $holds);
    }

    public function event_holds(int $event_id): int
    {
        return $this->holds($event_id)['event'];
    }

    public function slot_holds(int $event_id, string $timeslot_id): int
    {
        $holds = $this->holds($event_id);
        return max(0, absint($holds['slots'][sanitize_key($timeslot_id)] ?? 0));
    }

    /**
     * Remaining capacity accounting for active holds, mirroring
     * Event_Timeslot_Capacity::remaining() but subtracting holds per dimension.
     *
     * @return int PHP_INT_MAX when unlimited
     */
    public function remaining(int $event_id, string $timeslot_id = ''): int
    {
        $event_id    = absint($event_id);
        $timeslot_id = sanitize_key($timeslot_id);
        $mode        = $this->timeslots->get_capacity_mode($event_id);
        $holds       = $this->holds($event_id);

        if (Event_Timeslots::CAPACITY_MODE_EVENT === $mode || '' === $timeslot_id) {
            return $this->subtract_holds(
                $this->event_capacity->remaining_capacity($event_id),
                $holds['event']
            );
        }

        $slot_remaining = $this->subtract_holds(
            $this->slot_capacity->remaining_for_slot($event_id, $timeslot_id),
            absint($holds['slots'][$timeslot_id] ?? 0)
        );

        if (Event_Timeslots::CAPACITY_MODE_SLOT === $mode) {
            return $slot_remaining;
        }

        // both: tighter of event and slot.
        $event_remaining = $this->subtract_holds(
            $this->event_capacity->remaining_capacity($event_id),
            $holds['event']
        );
        if (PHP_INT_MAX === $event_remaining) {
            return $slot_remaining;
        }
        if (PHP_INT_MAX === $slot_remaining) {
            return $event_remaining;
        }
        return min($event_remaining, $slot_remaining);
    }

    private function subtract_holds(int $base, int $holds): int
    {
        if (PHP_INT_MAX === $base) {
            return PHP_INT_MAX;
        }
        return max(0, $base - max(0, $holds));
    }

    /**
     * Reserve seats for a pending order.
     *
     * @return true|\WP_Error 409-style error when there is no room
     */
    public function reserve(int $event_id, string $timeslot_id, int $quantity)
    {
        $event_id    = absint($event_id);
        $timeslot_id = sanitize_key($timeslot_id);
        $quantity    = max(1, $quantity);

        $token = $this->lock->acquire($event_id, 15);
        if ('' === $token) {
            return new WP_Error('evt_event_locked', __('This event is being booked right now. Please try again in a moment.', 'Event-Tickets-for-Elementor'));
        }

        $remaining = $this->remaining($event_id, $timeslot_id);
        if (PHP_INT_MAX !== $remaining && $remaining < $quantity) {
            $this->lock->release($event_id, $token);
            return new WP_Error('evt_event_sold_out', __('This event is sold out.', 'Event-Tickets-for-Elementor'));
        }

        $holds = $this->holds($event_id);
        $holds['event'] += $quantity;
        if ('' !== $timeslot_id) {
            $holds['slots'][$timeslot_id] = absint($holds['slots'][$timeslot_id] ?? 0) + $quantity;
        }
        $this->save_holds($event_id, $holds);
        delete_transient('evt_capacity_' . $event_id);

        $this->lock->release($event_id, $token);
        return true;
    }

    /**
     * Release held seats (order failed / expired / user abandoned).
     */
    public function release(int $event_id, string $timeslot_id, int $quantity): void
    {
        $event_id    = absint($event_id);
        $timeslot_id = sanitize_key($timeslot_id);
        $quantity    = max(1, $quantity);

        $token = $this->lock->acquire($event_id, 15);
        if ('' === $token) {
            return;
        }

        $holds = $this->holds($event_id);
        $holds['event'] = max(0, $holds['event'] - $quantity);
        if ('' !== $timeslot_id) {
            $key = $timeslot_id;
            $holds['slots'][$key] = max(0, absint($holds['slots'][$key] ?? 0) - $quantity);
            if (0 === $holds['slots'][$key]) {
                unset($holds['slots'][$key]);
            }
        }
        $this->save_holds($event_id, $holds);
        delete_transient('evt_capacity_' . $event_id);

        $this->lock->release($event_id, $token);
    }

    /**
     * Convert a hold into tickets once payment is confirmed.
     *
     * Must be called AFTER tickets are issued (the hold is still counted in
     * capacity during issuance, then decremented here — net effect stays 1:1).
     */
    public function confirm(int $event_id, string $timeslot_id, int $quantity): void
    {
        $this->release($event_id, $timeslot_id, $quantity);
    }

    /**
     * Expire stale pending orders and release their holds.
     *
     * @return int count of orders expired
     */
    public function expire_stale(): int
    {
        $now = time();
        $expired = get_posts(
            [
                'post_type'      => CPT_Orders::POST_TYPE,
                'post_status'    => CPT_Orders::STATUS_PENDING,
                'posts_per_page' => 100,
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'meta_query'     => [
                    [
                        'key'     => '_evt_order_hold_expires_at',
                        'value'   => $now,
                        'compare' => '<',
                        'type'    => 'NUMERIC',
                    ],
                ],
            ]
        );

        $count = 0;
        foreach ($expired as $order_id) {
            $order = $this->orders->load((int) $order_id);
            if (! $order || CPT_Orders::STATUS_PENDING !== $order->status()) {
                continue;
            }
            $this->orders->set_status($order_id, CPT_Orders::STATUS_EXPIRED);
            $this->release($order->event_id(), $order->timeslot_id(), $order->quantity());
            $count++;
        }

        return $count;
    }
}
