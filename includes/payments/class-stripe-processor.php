<?php

namespace EventTicketsElementor\Payments;

use EventTicketsElementor\Settings;
use WP_Error;
use WP_REST_Request;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Stripe Checkout processor (hosted page, PCI SAQ-A).
 *
 * Plain HTTP against the Stripe API (no SDK — matches the plugin's zero
 * runtime-dependency style). Creates a Checkout Session, verifies webhooks
 * with Stripe's standard HMAC-SHA256 signature scheme (timestamp tolerance
 * ±5 min), refunds via the Refunds API, and supports a server-side session
 * check on the return page.
 */
class Stripe_Processor implements Payment_Processor
{
    private const API_BASE = 'https://api.stripe.com/v1';

    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function name(): string
    {
        return 'stripe';
    }

    public function is_configured(): bool
    {
        return '' !== $this->active_secret();
    }

    public function create_payment(Order_Record $order)
    {
        $secret = $this->active_secret();
        if ('' === $secret) {
            return new WP_Error('evt_stripe_not_configured', __('Stripe is not configured.', 'Event-Tickets-for-Elementor'));
        }

        $unit_amount = intdiv($order->amount_minor(), $order->quantity());
        $event_title = get_the_title($order->event_id());
        if ('' === $event_title) {
            $event_title = 'Event Ticket';
        }

        $success_url = add_query_arg(
            [
                'evt_order' => $order->public_key(),
                'evt_ref'   => '{CHECKOUT_SESSION_ID}',
            ],
            home_url('/')
        );

        $body = http_build_query(
            [
                'mode'                                             => 'payment',
                'success_url'                                      => $success_url,
                'cancel_url'                                       => home_url('/'),
                'client_reference_id'                              => $order->public_key(),
                'customer_email'                                   => $order->attendee_email(),
                'line_items[0][quantity]'                          => $order->quantity(),
                'line_items[0][price_data][currency]'              => strtolower($order->currency()),
                'line_items[0][price_data][unit_amount]'           => $unit_amount,
                'line_items[0][price_data][product_data][name]'    => $event_title,
                'metadata[event_id]'                               => $order->event_id(),
                'metadata[order_id]'                               => $order->id(),
                'metadata[attendee_email]'                         => $order->attendee_email(),
            ]
        );

        $response = wp_remote_post(
            self::API_BASE . '/checkout/sessions',
            [
                'timeout' => 30,
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($secret . ':'),
                    'Content-Type'  => 'application/x-www-form-urlencoded',
                ],
                'body' => $body,
            ]
        );

        if (is_wp_error($response)) {
            return new WP_Error('evt_stripe_session_failed', $response->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code >= 300) {
            return new WP_Error('evt_stripe_session_failed', $this->api_error_message($response, $code));
        }

        $session = json_decode((string) wp_remote_retrieve_body($response));
        if (! is_object($session) || '' === (string) ($session->id ?? '') || '' === (string) ($session->url ?? '')) {
            return new WP_Error('evt_stripe_session_failed', __('Stripe did not return a valid Checkout Session.', 'Event-Tickets-for-Elementor'));
        }

        return [
            'redirect_url' => (string) $session->url,
            'payment_ref'  => (string) $session->id,
        ];
    }

    public function verify_webhook(WP_REST_Request $request)
    {
        $body   = (string) $request->get_body();
        $header = (string) $request->get_header('stripe-signature');
        $secret = $this->active_webhook_secret();

        if ('' === $header || '' === $secret || '' === $body) {
            return new WP_Error('evt_stripe_bad_signature', __('Missing Stripe webhook signature.', 'Event-Tickets-for-Elementor'));
        }

        $timestamp = 0;
        $expected  = '';
        foreach (explode(',', $header) as $part) {
            if (0 === strpos($part, 't=')) {
                $timestamp = (int) substr($part, 2);
            } elseif (0 === strpos($part, 'v1=')) {
                $expected = substr($part, 3);
            }
        }

        if (0 === $timestamp || '' === $expected || abs(time() - $timestamp) > 300) {
            return new WP_Error('evt_stripe_bad_signature', __('Stripe webhook signature is stale or malformed.', 'Event-Tickets-for-Elementor'));
        }

        $computed = hash_hmac('sha256', $timestamp . '.' . $body, $secret);
        if (! hash_equals($expected, $computed)) {
            return new WP_Error('evt_stripe_bad_signature', __('Stripe webhook signature verification failed.', 'Event-Tickets-for-Elementor'));
        }

        $decoded = json_decode($body);
        if (! is_object($decoded)) {
            return new WP_Error('evt_stripe_ignored', 'ignored');
        }

        if ('checkout.session.completed' !== (string) ($decoded->type ?? '')) {
            return new WP_Error('evt_stripe_ignored', 'ignored');
        }

        $session = $decoded->data->object ?? null;
        if (! is_object($session) || 'paid' !== (string) ($session->payment_status ?? '')) {
            return new WP_Error('evt_stripe_ignored', 'ignored');
        }

        $payment_intent = (string) ($session->payment_intent ?? '');

        return new Payment_Event(
            (string) $session->id,
            '' !== $payment_intent ? $payment_intent : (string) $session->id,
            Payment_Event::STATUS_PAID,
            (int) $session->amount_total,
            (string) $session->currency,
            $decoded
        );
    }

    public function refund(string $transaction_id, int $amount_minor, string $currency)
    {
        $secret = $this->active_secret();
        if ('' === $secret || '' === $transaction_id) {
            return new WP_Error('evt_stripe_refund_failed', __('Stripe is not configured or the transaction id is missing.', 'Event-Tickets-for-Elementor'));
        }

        $response = wp_remote_post(
            self::API_BASE . '/refunds',
            [
                'timeout' => 30,
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($secret . ':'),
                    'Content-Type'  => 'application/x-www-form-urlencoded',
                ],
                'body' => http_build_query(
                    [
                        'payment_intent' => $transaction_id,
                        'amount'         => $amount_minor,
                    ]
                ),
            ]
        );

        if (is_wp_error($response)) {
            return new WP_Error('evt_stripe_refund_failed', $response->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code >= 300) {
            return new WP_Error('evt_stripe_refund_failed', $this->api_error_message($response, $code));
        }

        $refund = json_decode((string) wp_remote_retrieve_body($response));
        if (! is_object($refund) || '' === (string) ($refund->id ?? '')) {
            return new WP_Error('evt_stripe_refund_failed', __('Stripe did not confirm the refund.', 'Event-Tickets-for-Elementor'));
        }

        return ['refund_id' => (string) $refund->id];
    }

    public function verify_return(string $payment_ref)
    {
        $secret = $this->active_secret();
        if ('' === $secret || '' === $payment_ref) {
            return null;
        }

        $response = wp_remote_get(
            self::API_BASE . '/checkout/sessions/' . rawurlencode($payment_ref),
            [
                'timeout' => 30,
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($secret . ':'),
                ],
            ]
        );

        if (is_wp_error($response)) {
            return null;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code >= 300) {
            return null;
        }

        $session = json_decode((string) wp_remote_retrieve_body($response));
        if (! is_object($session) || 'paid' !== (string) ($session->payment_status ?? '')) {
            return null;
        }

        $payment_intent = (string) ($session->payment_intent ?? '');

        return new Payment_Event(
            (string) $session->id,
            '' !== $payment_intent ? $payment_intent : (string) $session->id,
            Payment_Event::STATUS_PAID,
            (int) $session->amount_total,
            (string) $session->currency,
            $session
        );
    }

    /**
     * Active mode key: 'live' | 'test' (defaults to test).
     */
    private function mode(): string
    {
        return 'live' === (string) $this->settings->get('stripe_mode', 'test') ? 'live' : 'test';
    }

    private function active_secret(): string
    {
        $key = 'live' === $this->mode() ? 'stripe_secret_key' : 'stripe_test_secret_key';
        return trim((string) $this->settings->get($key, ''));
    }

    private function active_webhook_secret(): string
    {
        $key = 'live' === $this->mode() ? 'stripe_webhook_secret' : 'stripe_test_webhook_secret';
        return trim((string) $this->settings->get($key, ''));
    }

    /**
     * Extract the human-readable message from a Stripe error response.
     *
     * @param mixed $response wp_remote_* response (already known not to be a WP_Error).
     */
    private function api_error_message($response, int $code): string
    {
        $data = json_decode((string) wp_remote_retrieve_body($response), true);
        $message = is_array($data) && isset($data['error']['message']) ? (string) $data['error']['message'] : '';
        if ('' !== $message) {
            return $message;
        }
        return sprintf(
            /* translators: %d: HTTP status code */
            __('Stripe API error (HTTP %d).', 'Event-Tickets-for-Elementor'),
            $code
        );
    }
}
