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
 * Implements the official ePay WEB API (kb.epay.bg): the payment request is
 * a base64 (RFC 3548, EOL='') block of newline-separated KEY=VALUE fields,
 * checksummed with HMAC-SHA1 over the encoded value keyed by the merchant
 * secret. The buyer is redirected to a local route that renders an
 * auto-submitting form posting PAGE/ENCODED/CHECKSUM/URL_OK/URL_CANCEL to
 * the ePay payment page. IPN notifications (encoded + checksum, with a
 * colon-separated payload) are verified the same way.
 *
 * NOTE: the IPN payload carries INVOICE/STATUS (plus PAY_TIME/STAN/BCODE)
 * but NO amount, so amount is validated against the stored order rather than
 * the event. Multi-INVOICE notifications (rare batching) are not yet handled
 * — one order per invoice is assumed for Phase 1.
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
        // ePay IPN posts `encoded` (base64, colon-separated payload) and
        // `checksum` (HMAC-SHA1 of the encoded value, keyed with the secret).
        // The POST is URL-decoded by WordPress, so `encoded` is raw base64.
        $encoded  = (string) $request->get_param('encoded');
        $checksum = (string) $request->get_param('checksum');
        $secret   = (string) $this->settings->get('epay_secret', '');

        if ('' === $encoded || '' === $checksum || '' === $secret) {
            return new WP_Error('evt_epay_bad_checksum', __('Missing ePay IPN fields.', 'Event-Tickets-for-Elementor'));
        }

        if (! hash_equals(hash_hmac('sha1', $encoded, $secret), $checksum)) {
            return new WP_Error('evt_epay_bad_checksum', __('Invalid ePay IPN checksum.', 'Event-Tickets-for-Elementor'));
        }

        $raw = base64_decode($encoded);
        if (false === $raw) {
            return new WP_Error('evt_epay_bad_checksum', __('Could not decode the ePay IPN payload.', 'Event-Tickets-for-Elementor'));
        }
        $parsed = self::parse_invoice($raw);

        $status  = self::map_status((string) ($parsed['STATUS'] ?? ''));
        $invoice = (string) ($parsed['INVOICE'] ?? '');

        // The IPN carries no AMOUNT/CURRENCY (only INVOICE/STATUS/PAY_TIME/
        // STAN/BCODE). Amount is validated against the stored order in
        // Payment_Service, so mark amount/currency as unknown (0 / '').
        return new Payment_Event($invoice, $invoice, $status, 0, '', $parsed);
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
        return self::extract_invoice($raw);
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
        // Base64 (RFC 3548), EOL='' — no urlencode (the form POST is encoded
        // by the browser automatically). CHECKSUM is HMAC-SHA1 over the
        // encoded value, keyed with the merchant secret (per kb.epay.bg).
        $encoded = base64_encode($invoice);
        $secret  = (string) $settings->get('epay_secret', '');

        // URL_OK/URL_CANCEL are form-level fields (not part of ENCODED) and
        // bring the buyer back to the site with ?evt_order= for the
        // return-page poller.
        $ok_url     = add_query_arg('evt_order', $order->public_key(), home_url('/'));
        $cancel_url = home_url('/');

        return [
            'PAGE'       => 'credit_paydirect',
            'ENCODED'    => $encoded,
            'CHECKSUM'   => hash_hmac('sha1', $encoded, $secret),
            'URL_OK'     => $ok_url,
            'URL_CANCEL' => $cancel_url,
        ];
    }

    /**
     * Build the ePay payment request DATA block (newline-separated KEY=VALUE
     * fields, per the kb.epay.bg sample). EXP_TIME is DD.MM.YYYY[hh:mm[:ss]].
     */
    private static function invoice_string(Order_Record $order, Settings $settings): string
    {
        $ttl_minutes = max(5, absint($settings->get('payment_hold_ttl_minutes', 30)));
        $exp_time    = date('d.m.Y H:i:s', current_time('timestamp') + ($ttl_minutes * 60));

        $title = get_the_title($order->event_id());
        if ('' === $title) {
            $title = 'Event Ticket';
        }
        // DESCR is a single line ≤ 100 chars; newlines would inject extra
        // fields. ENCODING=utf-8 keeps Cyrillic event titles intact.
        $title = (string) preg_replace('/[\r\n]+/', ' ', $title);
        $title = function_exists('mb_substr') ? mb_substr($title, 0, 100) : substr($title, 0, 100);

        $amount = number_format($order->amount_minor() / 100, 2, '.', '');

        return sprintf(
            "MIN=%s\nINVOICE=%d\nAMOUNT=%s\nCURRENCY=%s\nEXP_TIME=%s\nDESCR=%s\nENCODING=utf-8",
            (string) $settings->get('epay_merchant_id', ''),
            $order->id(),
            $amount,
            $order->currency(),
            $exp_time,
            $title
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
