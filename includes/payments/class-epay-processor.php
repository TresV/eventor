<?php

namespace EventTicketsElementor\Payments;

use EventTicketsElementor\Plugin;
use EventTicketsElementor\Settings;
use WP_Error;
use WP_REST_Request;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * ePay.bg processor (hosted page, Bulgarian card schemes / BORICA).
 *
 * Builds a signed invoice string, then redirects the customer to a local route
 * that renders an auto-submitting form posting ENCODED + CHECKSUM to the ePay
 * payment page. IPN notifications are verified with ePay's sha1 checksum
 * scheme (sha1 of the encoded value concatenated with the secret).
 *
 * ePay has no public refund API — refunds are processed from the merchant
 * panel, so refund() returns a manual-refund WP_Error and verify_return()
 * returns null (the return page relies on IPN + polling).
 */
class Epay_Processor implements Payment_Processor
{
    private const LIVE_URL = 'https://www.epay.bg/';
    private const TEST_URL = 'https://demo.epay.bg/';

    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function name(): string
    {
        return 'epay';
    }

    public function is_configured(): bool
    {
        return '' !== trim((string) $this->settings->get('epay_merchant_id', ''))
            && '' !== trim((string) $this->settings->get('epay_secret', ''));
    }

    public function create_payment(Order_Record $order)
    {
        if (! $this->is_configured()) {
            return new WP_Error('evt_epay_not_configured', __('The ePay.bg processor is not configured.', 'Event-Tickets-for-Elementor'));
        }

        // ePay requires a form POST (not a plain redirect), so redirect to the
        // local auto-submit route that posts ENCODED/CHECKSUM to ePay.
        $redirect_url = add_query_arg(
            ['order_id' => $order->id()],
            rest_url('evt/v1/payments/epay/start')
        );

        return [
            'redirect_url' => $redirect_url,
            'payment_ref'  => (string) $order->id(),
            'form_fields'  => self::build_fields($order, $this->settings),
        ];
    }

    public function verify_webhook(WP_REST_Request $request)
    {
        $encoded  = (string) $request->get_param('encoded');
        $checksum = (string) $request->get_param('checksum');
        $secret   = (string) $this->settings->get('epay_secret', '');

        if ('' === $encoded || '' === $checksum || '' === $secret) {
            return new WP_Error('evt_epay_bad_checksum', __('Missing ePay IPN fields.', 'Event-Tickets-for-Elementor'));
        }

        if (! hash_equals(sha1($encoded . $secret), $checksum)) {
            return new WP_Error('evt_epay_bad_checksum', __('Invalid ePay IPN checksum.', 'Event-Tickets-for-Elementor'));
        }

        $raw = base64_decode($encoded);
        if (false === $raw) {
            return new WP_Error('evt_epay_bad_checksum', __('Could not decode the ePay IPN payload.', 'Event-Tickets-for-Elementor'));
        }
        $raw    = urldecode($raw);
        $parsed = self::parse_invoice($raw);

        $status = self::map_status((string) ($parsed['STATUS'] ?? ''));
        $invoice = (string) ($parsed['INVOICE'] ?? '');

        $amount_minor = 0;
        if (isset($parsed['AMOUNT']) && is_numeric($parsed['AMOUNT'])) {
            $amount_minor = (int) round((float) $parsed['AMOUNT'] * 100);
        }

        $currency = strtoupper((string) ($parsed['CURRENCY'] ?? 'EUR'));

        return new Payment_Event($invoice, $invoice, $status, $amount_minor, $currency, $parsed);
    }

    public function refund(string $transaction_id, int $amount_minor, string $currency)
    {
        return new WP_Error('evt_epay_manual_refund', 'ePay.bg refunds are processed from the ePay merchant panel.');
    }

    public function verify_return(string $payment_ref)
    {
        return null;
    }

    /**
     * Extract the INVOICE id from an ePay IPN request (same decode the
     * processor uses) so the webhook controller can reply with the plaintext
     * INVOICE=...:STATUS=OK|ERR body ePay expects.
     */
    public static function invoice_from_request(WP_REST_Request $request): string
    {
        $encoded = (string) $request->get_param('encoded');
        if ('' === $encoded) {
            return '';
        }
        $raw = base64_decode($encoded);
        if (false === $raw) {
            return '';
        }
        return self::extract_invoice(urldecode($raw));
    }

    /**
     * Build the auto-submitting ePay payment form data for an order.
     *
     * @return array{action:string, fields:array<string,string>}
     */
    public static function build_payment_form(Order_Record $order): array
    {
        $settings = Plugin::instance()->settings;
        return [
            'action' => self::payment_page_url($settings),
            'fields' => self::build_fields($order, $settings),
        ];
    }

    /**
     * The ePay payment page for the current mode.
     */
    private static function payment_page_url(Settings $settings): string
    {
        $test_mode = (bool) $settings->get('epay_test_mode', 0);
        return $test_mode ? self::TEST_URL : self::LIVE_URL;
    }

    /**
     * Build the ENCODED/CHECKSUM form fields for an order.
     *
     * @return array<string,string>
     */
    private static function build_fields(Order_Record $order, Settings $settings): array
    {
        $invoice = self::invoice_string($order, $settings);
        $encoded = urlencode(base64_encode($invoice));
        $secret  = (string) $settings->get('epay_secret', '');

        return [
            'PAGE'     => 'paylogin',
            'ENCODED'  => $encoded,
            'CHECKSUM' => sha1($encoded . $secret),
        ];
    }

    /**
     * Build the ePay invoice data string (fields separated by ':').
     */
    private static function invoice_string(Order_Record $order, Settings $settings): string
    {
        $ttl_minutes = max(5, absint($settings->get('payment_hold_ttl_minutes', 30)));
        $exp_time    = date('Y-m-d H:i:s', current_time('timestamp') + ($ttl_minutes * 60));

        $title = get_the_title($order->event_id());
        if ('' === $title) {
            $title = 'Event Ticket';
        }
        // Keep the invoice parseable: ':' and '=' would break the KEY=VALUE
        // fields, and ePay limits DESCR to 120 chars.
        $title = str_replace([':', '='], ' ', $title);
        $title = function_exists('mb_substr') ? mb_substr($title, 0, 120) : substr($title, 0, 120);

        $amount = number_format($order->amount_minor() / 100, 2, '.', '');

        // Return the buyer to the site after payment so the return-page
        // poller (assets/js/payment-return.js) can pick up the order status.
        // URL_OK/URL_CANCEL must be URL-encoded: ePay splits fields on ': '
        // and a raw scheme (https://) would break parsing.
        $ok_url     = add_query_arg('evt_order', $order->public_key(), home_url('/'));
        $cancel_url = home_url('/');

        return sprintf(
            'INVOICE=%d:AMOUNT=%s:CURRENCY=%s:EXP_TIME=%s:DESCR=%s:MIN=%s:URL_OK=%s:URL_CANCEL=%s',
            $order->id(),
            $amount,
            $order->currency(),
            $exp_time,
            $title,
            (string) $settings->get('epay_merchant_id', ''),
            rawurlencode($ok_url),
            rawurlencode($cancel_url)
        );
    }

    /**
     * Parse an ePay KEY=VALUE payload split by ':'.
     *
     * @return array<string,string>
     */
    private static function parse_invoice(string $raw): array
    {
        $parsed = [];
        foreach (explode(':', $raw) as $pair) {
            $pos = strpos($pair, '=');
            if (false === $pos) {
                continue;
            }
            $key   = trim(substr($pair, 0, $pos));
            $value = trim(substr($pair, $pos + 1));
            if ('' !== $key) {
                $parsed[strtoupper($key)] = $value;
            }
        }
        return $parsed;
    }

    private static function extract_invoice(string $raw): string
    {
        $parsed = self::parse_invoice($raw);
        return (string) ($parsed['INVOICE'] ?? '');
    }

    /**
     * Map an ePay IPN status to a normalized Payment_Event status.
     */
    private static function map_status(string $status): string
    {
        $status = strtoupper(trim($status));
        if ('PAID' === $status) {
            return Payment_Event::STATUS_PAID;
        }
        if ('DENIED' === $status || 'EXPIRED' === $status) {
            return Payment_Event::STATUS_FAILED;
        }
        if ('REFUND' === $status || 'REFUNDED' === $status) {
            return Payment_Event::STATUS_REFUNDED;
        }
        // Unknown statuses are treated as non-paid.
        return Payment_Event::STATUS_FAILED;
    }
}
