<?php

namespace EventTicketsElementor\Payments;

use WP_REST_Request;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Contract for a payment processor (hosted-page style, PCI SAQ-A).
 *
 * Implementations never touch card data: they create a hosted session, the
 * customer pays on the processor's page, and the processor notifies this
 * plugin via webhook/IPN (verified here) or the return page.
 */
interface Payment_Processor
{
    /**
     * Human/UI processor key, e.g. 'stripe' | 'epay' | 'mypos'.
     */
    public function name(): string;

    /**
     * Whether this processor has enough configuration to take payments.
     */
    public function is_configured(): bool;

    /**
     * Create a hosted payment session for an order.
     *
     * @param Order_Record $order
     * @return array{redirect_url:string, payment_ref:string}|\WP_Error
     */
    public function create_payment(Order_Record $order);

    /**
     * Verify an incoming webhook/IPN request.
     *
     * Returns a normalized Payment_Event when the signature is valid, or
     * WP_Error when invalid/unrecognized (the caller maps that to a 4xx).
     *
     * @param WP_REST_Request $request
     * @return Payment_Event|\WP_Error
     */
    public function verify_webhook(WP_REST_Request $request);

    /**
     * Refund a payment. Phase 1: full amount only.
     *
     * @param string $transaction_id
     * @param int    $amount_minor
     * @param string $currency
     * @return array{refund_id:string}|\WP_Error
     */
    public function refund(string $transaction_id, int $amount_minor, string $currency);

    /**
     * Optional server-side status check on the return page (Stripe only).
     *
     * @param string $payment_ref
     * @return Payment_Event|null Null when unsupported or not yet paid.
     */
    public function verify_return(string $payment_ref);
}
