<?php

namespace EventTicketsElementor\Payments;

use EventTicketsElementor\CPT_Orders;
use EventTicketsElementor\Event_Discovery_Meta;
use EventTicketsElementor\Event_Timeslots;
use EventTicketsElementor\Plugin;
use EventTicketsElementor\Settings;
use EventTicketsElementor\Tickets\Ticket_Rules;
use EventTicketsElementor\Tickets\Ticket_Timeslot_Exclusivity;
use WP_Error;
use WP_REST_Request;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Facade for the direct paid-ticket checkout path.
 *
 * Replaces the WooCommerce-only "payments" seam for sites that enable a direct
 * processor (Stripe / ePay.bg). Orchestrates: order creation → seat hold →
 * hosted session → webhook verification → ticket issuance. The WooCommerce
 * bridge remains available as a fallback processor.
 *
 * Hooks:
 * - evt_payments_sweep  (cron) → expire stale holds
 * - cron_schedules      → adds the 5-minute sweep interval
 */
class Payment_Service
{
    public const MODE_NONE         = 'none';
    public const MODE_DIRECT       = 'direct';
    public const MODE_WOOCOMMERCE  = 'woocommerce';

    private Settings $settings;
    private Payment_Order_Service $orders;
    private Reservation_Service $reservations;
    private Ticket_Issuance_Service $issuance;
    private Refund_Service $refunds;
    /** @var array<string, Payment_Processor> */
    private array $processors = [];

    public function __construct(
        Settings $settings,
        Payment_Order_Service $orders,
        Reservation_Service $reservations,
        Ticket_Issuance_Service $issuance,
        Refund_Service $refunds
    ) {
        $this->settings     = $settings;
        $this->orders       = $orders;
        $this->reservations = $reservations;
        $this->issuance     = $issuance;
        $this->refunds      = $refunds;

        // Direct processors are provided by the payments namespace; guard with
        // class_exists so the facade degrades gracefully before they load.
        if (class_exists(__NAMESPACE__ . '\Stripe_Processor')) {
            $this->register_processor(new Stripe_Processor($settings));
            $this->refunds->register_processor(new Stripe_Processor($settings));
        }
        if (class_exists(__NAMESPACE__ . '\Epay_Processor')) {
            $this->register_processor(new Epay_Processor($settings));
            $this->refunds->register_processor(new Epay_Processor($settings));
        }

        add_action('evt_payments_sweep', [$this, 'cron_sweep']);
        add_filter('cron_schedules', [$this, 'add_sweep_interval']);
    }

    /**
     * @param array<string,array{interval:int,display:string}> $schedules
     * @return array<string,array{interval:int,display:string}>
     */
    public function add_sweep_interval(array $schedules): array
    {
        $schedules['evt_5min'] = [
            'interval' => 300,
            'display'  => __('Every 5 minutes', 'Event-Tickets-for-Elementor'),
        ];
        return $schedules;
    }

    public function register_processor(Payment_Processor $processor): void
    {
        $this->processors[$processor->name()] = $processor;
    }

    public function processor(string $name): ?Payment_Processor
    {
        return $this->processors[sanitize_key($name)] ?? null;
    }

    /**
     * Name of the active, configured direct processor ('stripe' | 'epay') or ''.
     */
    public function active_processor(): string
    {
        $selected = sanitize_key((string) $this->settings->get('payment_processor', ''));
        $processor = $this->processor($selected);
        if ($processor && $processor->is_configured()) {
            return $selected;
        }
        return '';
    }

    /**
     * Which checkout mode paid events should use.
     */
    public function mode(): string
    {
        $selected = (string) $this->settings->get('payment_processor', '');
        if (in_array($selected, ['stripe', 'epay'], true) && $this->active_processor()) {
            return self::MODE_DIRECT;
        }
        if ('woocommerce' === $selected || ('' === $selected && $this->wc_is_connected())) {
            return self::MODE_WOOCOMMERCE;
        }
        return self::MODE_NONE;
    }

    /**
     * Whether the direct path is currently usable.
     */
    public function is_connected(): bool
    {
        return '' !== $this->active_processor();
    }

    /**
     * Whether the WooCommerce fallback bridge is usable.
     */
    public function wc_is_connected(): bool
    {
        return Plugin::instance()->payments()->is_connected();
    }

    public function is_event_paid(int $event_id): bool
    {
        if ($event_id <= 0) {
            return false;
        }
        $paid = (bool) get_post_meta($event_id, Event_Discovery_Meta::PAID_ENABLED_META, true);
        if (! $paid) {
            return false;
        }
        return $this->event_unit_amount($event_id) > 0;
    }

    /**
     * Per-ticket price in minor units.
     */
    public function event_unit_amount(int $event_id): int
    {
        $raw = (string) get_post_meta($event_id, Event_Discovery_Meta::COST_META, true);
        if ('' === trim($raw) || ! is_numeric($raw)) {
            return 0;
        }
        $amount = (float) $raw;
        if ($amount <= 0) {
            return 0;
        }
        return (int) round($amount * 100);
    }

    /**
     * Order currency (default EUR).
     */
    public function currency(): string
    {
        $currency = (string) $this->settings->get('payment_currency', 'EUR');
        return '' !== trim($currency) ? strtoupper(trim($currency)) : 'EUR';
    }

    /**
     * Seat-hold TTL in seconds (default 30 minutes).
     */
    public function hold_ttl_seconds(): int
    {
        $minutes = max(5, absint($this->settings->get('payment_hold_ttl_minutes', 30)));
        return $minutes * 60;
    }

    /**
     * Begin a direct checkout for a paid ticket request.
     *
     * Creates the pending order, holds seats, and opens a hosted payment
     * session.
     *
     * @param array $payload {event_id, timeslot_id, quantity, attendee_name,
     *                       attendee_email, attendee_phone}
     * @return array{redirect_url:string, order_key:string, hold_minutes:int, message:string}|\WP_Error
     */
    public function begin_payment(array $payload)
    {
        $event_id       = isset($payload['event_id']) ? absint($payload['event_id']) : 0;
        $quantity       = isset($payload['quantity']) ? max(1, (int) $payload['quantity']) : 1;
        $timeslot_id    = isset($payload['timeslot_id']) ? sanitize_key((string) $payload['timeslot_id']) : '';
        $attendee_email = isset($payload['attendee_email']) ? sanitize_email((string) $payload['attendee_email']) : '';
        $attendee_name  = isset($payload['attendee_name']) ? sanitize_text_field((string) $payload['attendee_name']) : '';
        $attendee_phone = isset($payload['attendee_phone']) ? sanitize_text_field((string) $payload['attendee_phone']) : '';

        if (! $event_id || '' === $attendee_email) {
            return new WP_Error('evt_checkout_invalid', __('Missing checkout payload values.', 'Event-Tickets-for-Elementor'));
        }

        $unit = $this->event_unit_amount($event_id);
        if ($unit <= 0) {
            return new WP_Error('evt_checkout_invalid_price', __('This event does not have a valid paid ticket price.', 'Event-Tickets-for-Elementor'));
        }

        $processor_name = $this->active_processor();
        $processor      = $processor_name ? $this->processor($processor_name) : null;
        if (! $processor) {
            return new WP_Error('evt_checkout_not_ready', __('Paid ticket checkout is not available right now. Please contact the organizer.', 'Event-Tickets-for-Elementor'));
        }

        // Fail fast on exclusivity before taking money (issuance re-checks).
        $exclusivity = $this->check_exclusivity($event_id, $timeslot_id, $attendee_email);
        if (is_wp_error($exclusivity)) {
            return $exclusivity;
        }

        $order_id = $this->orders->create(
            [
                'event_id'       => $event_id,
                'timeslot_id'    => $timeslot_id,
                'quantity'       => $quantity,
                'attendee_name'  => $attendee_name,
                'attendee_email' => $attendee_email,
                'attendee_phone' => $attendee_phone,
                'amount_minor'   => $unit * $quantity,
                'currency'       => $this->currency(),
                'processor'      => $processor_name,
            ]
        );
        if (is_wp_error($order_id)) {
            return $order_id;
        }

        $ttl = $this->hold_ttl_seconds();
        $this->orders->set_hold_expiry($order_id, $ttl);

        $reserved = $this->reservations->reserve($event_id, $timeslot_id, $quantity);
        if (is_wp_error($reserved)) {
            $this->orders->set_status($order_id, CPT_Orders::STATUS_FAILED);
            return $reserved;
        }

        $order = $this->orders->load($order_id);
        if (! $order) {
            return new WP_Error('evt_checkout_order_lost', __('Could not initialize the payment order.', 'Event-Tickets-for-Elementor'));
        }

        $session = $processor->create_payment($order);
        if (is_wp_error($session)) {
            $this->reservations->release($event_id, $timeslot_id, $quantity);
            $this->orders->set_status($order_id, CPT_Orders::STATUS_FAILED);
            update_post_meta($order_id, '_evt_order_error', $session->get_error_message());
            return new WP_Error('evt_checkout_session_failed', $session->get_error_message());
        }

        $this->orders->store_session($order_id, (string) ($session['payment_ref'] ?? ''));

        return [
            'redirect_url' => (string) ($session['redirect_url'] ?? ''),
            'order_key'    => $order->public_key(),
            'hold_minutes' => (int) round($ttl / 60),
            'message'      => __('Redirecting to secure payment…', 'Event-Tickets-for-Elementor'),
        ];
    }

    /**
     * Process a verified webhook/IPN for a processor.
     *
     * @param string          $processor_name
     * @param WP_REST_Request $request
     * @return array<string,mixed> {
     *   processed:bool, error?:string, message?:string, duplicate?:bool,
     *   status?:string, order_id?:int, ticket_count?:int, auto_refunded?:bool
     * }
     */
    public function handle_webhook(string $processor_name, WP_REST_Request $request): array
    {
        $processor = $this->processor($processor_name);
        if (! $processor) {
            return ['processed' => false, 'error' => 'unknown_processor'];
        }

        $event = $processor->verify_webhook($request);
        if (is_wp_error($event)) {
            return ['processed' => false, 'error' => $event->get_error_code(), 'message' => $event->get_error_message()];
        }

        $order = $this->orders->find_by_payment_ref($event->payment_ref);
        if (! $order) {
            return ['processed' => false, 'error' => 'order_not_found'];
        }

        // Idempotency: never re-process the same transaction.
        $existing = $order->transaction_id();
        if ('' !== $existing && $existing === $event->transaction_id) {
            return ['processed' => true, 'duplicate' => true, 'status' => $order->status(), 'order_id' => $order->id()];
        }

        // Amount + currency verification before anything else. ePay's IPN
        // carries no amount, so only enforce when the processor reports one
        // (Stripe) — ePay relies on signature + invoice lookup instead.
        if (
            ($event->amount_minor > 0 && $event->amount_minor !== $order->amount_minor())
            || ('' !== $event->currency && strtoupper($event->currency) !== strtoupper($order->currency()))
        ) {
            $this->orders->set_status($order->id(), CPT_Orders::STATUS_FAILED);
            update_post_meta($order->id(), '_evt_order_error', 'amount_mismatch');
            return ['processed' => false, 'error' => 'amount_mismatch'];
        }

        if (Payment_Event::STATUS_PAID === $event->status) {
            return $this->mark_paid($order, $event);
        }

        if (Payment_Event::STATUS_FAILED === $event->status) {
            if (CPT_Orders::STATUS_PENDING === $order->status()) {
                $this->orders->set_status($order->id(), CPT_Orders::STATUS_FAILED);
                $this->reservations->release($order->event_id(), $order->timeslot_id(), $order->quantity());
            }
            return ['processed' => true, 'status' => CPT_Orders::STATUS_FAILED, 'order_id' => $order->id()];
        }

        if (Payment_Event::STATUS_REFUNDED === $event->status) {
            $this->refunds->mark_order_refunded($order, $event->amount_minor);
            return ['processed' => true, 'status' => CPT_Orders::STATUS_REFUNDED, 'order_id' => $order->id()];
        }

        return ['processed' => true, 'status' => $order->status(), 'order_id' => $order->id()];
    }

    /**
     * Handle a confirmed "paid" webhook event.
     *
     * @return array<string,mixed>
     */
    private function mark_paid(Order_Record $order, Payment_Event $event): array
    {
        // Edge: payment landed after the seat-hold expired → money moved but no
        // seat was held → auto-refund (honest behavior).
        if (CPT_Orders::STATUS_EXPIRED === $order->status()) {
            $processor = $this->processor($order->processor());
            if ($processor) {
                $this->orders->record_payment($order->id(), $event);
                $result = $processor->refund($event->transaction_id, $order->amount_minor(), $order->currency());
                if (! is_wp_error($result)) {
                    $this->refunds->mark_order_refunded($order, $order->amount_minor());
                    return ['processed' => true, 'status' => CPT_Orders::STATUS_REFUNDED, 'order_id' => $order->id(), 'auto_refunded' => true];
                }
            }
            return ['processed' => false, 'error' => 'expired_auto_refund_failed'];
        }

        if (CPT_Orders::STATUS_PAID === $order->status()) {
            return ['processed' => true, 'duplicate' => true, 'status' => CPT_Orders::STATUS_PAID, 'order_id' => $order->id()];
        }

        if (CPT_Orders::STATUS_PENDING !== $order->status()) {
            return ['processed' => false, 'error' => 'unexpected_order_status'];
        }

        $this->orders->record_payment($order->id(), $event);
        $this->orders->set_status($order->id(), CPT_Orders::STATUS_PAID);

        $issued = $this->issuance->issue(
            [
                'event_id'         => $order->event_id(),
                'timeslot_id'      => $order->timeslot_id(),
                'quantity'         => $order->quantity(),
                'attendee_name'    => $order->attendee_name(),
                'attendee_email'   => $order->attendee_email(),
                'attendee_phone'   => $order->attendee_phone(),
                'source'           => 'payment_order',
                'source_id'        => (string) $order->id(),
                'payment_provider' => $order->processor(),
                'payment_status'   => 'paid',
                'order_id'         => $order->id(),
            ]
        );

        if (is_wp_error($issued)) {
            // Money moved but we could not issue: flag for admin and best-effort
            // auto-refund so the customer is not left without a seat.
            update_post_meta($order->id(), '_evt_order_error', $issued->get_error_message());
            $processor = $this->processor($order->processor());
            if ($processor) {
                $processor->refund($order->transaction_id(), $order->amount_minor(), $order->currency());
                $this->refunds->mark_order_refunded($order, $order->amount_minor());
            } else {
                $this->orders->set_status($order->id(), CPT_Orders::STATUS_FAILED);
            }
            return ['processed' => true, 'status' => CPT_Orders::STATUS_REFUNDED, 'order_id' => $order->id(), 'error' => $issued->get_error_message()];
        }

        // Release the hold AFTER tickets are created (hold was still counted in
        // capacity during issuance; tickets now cover the seats).
        $this->reservations->confirm($order->event_id(), $order->timeslot_id(), $order->quantity());

        $this->orders->record_tickets($order->id(), is_array($issued) ? $issued : []);
        delete_post_meta($order->id(), '_evt_order_error');

        return ['processed' => true, 'status' => CPT_Orders::STATUS_PAID, 'order_id' => $order->id(), 'ticket_count' => count($issued)];
    }

    /**
     * Optional server-side status check on the return page (Stripe).
     *
     * @param string $processor_name
     * @param string $payment_ref
     * @return array{status:string, ticket_count:int}|null
     */
    public function verify_return(string $processor_name, string $payment_ref): ?array
    {
        $processor = $this->processor($processor_name);
        if (! $processor) {
            return null;
        }
        $event = $processor->verify_return($payment_ref);
        if (! $event) {
            return null;
        }

        $order = $this->orders->find_by_payment_ref($event->payment_ref);
        if (! $order) {
            return null;
        }

        if (Payment_Event::STATUS_PAID === $event->status && CPT_Orders::STATUS_PENDING === $order->status()) {
            $this->mark_paid($order, $event);
            $order = $this->orders->load($order->id());
        }

        return $order ? ['status' => $order->status(), 'ticket_count' => count($order->ticket_ids())] : null;
    }

    /**
     * Public status for the return-page poller.
     *
     * @param string $public_key
     * @return array{status:string, ticket_count:int}|null
     */
    public function public_status(string $public_key): ?array
    {
        return $this->orders->public_status($public_key);
    }

    /**
     * Cron sweep: expire stale holds.
     */
    public function cron_sweep(): void
    {
        $this->reservations->expire_stale();
    }

    /**
     * Schedule the 5-minute sweep cron if it is not already running.
     */
    public function maybe_schedule_sweep(): void
    {
        if (! wp_next_scheduled('evt_payments_sweep')) {
            wp_schedule_event(time() + 300, 'evt_5min', 'evt_payments_sweep');
        }
    }

    /**
     * Fail-fast exclusivity check before taking money.
     *
     * @return true|\WP_Error
     */
    private function check_exclusivity(int $event_id, string $timeslot_id, string $attendee_email)
    {
        if (! Ticket_Rules::enforce_timeslot_exclusivity()) {
            return true;
        }

        $event_start = '';
        $event_end   = '';
        if ('' !== $timeslot_id) {
            $slot = (new Event_Timeslots())->get_slot($event_id, $timeslot_id);
            if ($slot) {
                $event_start = (string) ($slot['start'] ?? '');
                $event_end   = (string) ($slot['end'] ?? '');
            }
        } else {
            $event_start = (string) get_post_meta($event_id, '_evt_event_start', true);
            $event_end   = (string) get_post_meta($event_id, '_evt_event_end', true);
        }

        $validator = new Ticket_Timeslot_Exclusivity(Ticket_Rules::timeslot_buffer_minutes());
        $timerange = null;
        if ('' !== $event_start) {
            $timerange = [
                'start_ts' => (int) strtotime($event_start),
                'end_ts'   => (int) strtotime($event_end ?: $event_start),
            ];
        }
        $ok = $validator->assert_no_timeslot_conflict($event_id, $attendee_email, $timerange);
        return is_wp_error($ok) ? $ok : true;
    }
}
