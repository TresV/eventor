<?php

namespace EventTicketsElementor\Payments;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Normalized payment outcome produced by a processor's webhook/IPN
 * verification (or return-page check).
 *
 * Processors map their own payloads into this shape; services act only on
 * these fields so processor-specific logic stays inside the processor classes.
 */
class Payment_Event
{
    public const STATUS_PAID     = 'paid';
    public const STATUS_FAILED   = 'failed';
    public const STATUS_REFUNDED = 'refunded';

    /** @var string Processor reference set at create time (session id / invoice no). */
    public string $payment_ref;

    /** @var string Unique processor payment id — used as the idempotency key. */
    public string $transaction_id;

    /** @var string One of the STATUS_* constants. */
    public string $status;

    /** @var int Amount in minor units (cents). */
    public int $amount_minor;

    /** @var string ISO-4217 currency code, uppercase. */
    public string $currency;

    /** @var mixed Raw (already verified) processor payload, for logging/debug only. */
    public $raw;

    /**
     * @param string $payment_ref
     * @param string $transaction_id
     * @param string $status
     * @param int    $amount_minor
     * @param string $currency
     * @param mixed  $raw
     */
    public function __construct(
        string $payment_ref,
        string $transaction_id,
        string $status,
        int $amount_minor,
        string $currency,
        $raw = null
    ) {
        $this->payment_ref    = $payment_ref;
        $this->transaction_id = $transaction_id;
        $this->status         = $status;
        $this->amount_minor   = absint($amount_minor);
        $this->currency       = strtoupper($currency);
        $this->raw            = $raw;
    }
}
