<?php

namespace EventTicketsElementor\Tickets;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Signed cancellation links for tickets.
 *
 * Avoids exposing attendee email in URLs and allows self-service cancellation
 * from an email link, while keeping requests non-enumerable.
 */
class Ticket_Cancel_Link
{
    private const SIG_SALT_CONTEXT = 'evt_ticket_cancel';

    public static function build_signed_url(string $ticket_code, int $ticket_id, string $base_url, ?int $ts = null): string
    {
        $ticket_code = trim((string) $ticket_code);
        $ticket_id = absint($ticket_id);
        $base_url = trim((string) $base_url);

        if ('' === $ticket_code || ! $ticket_id || '' === $base_url) {
            return '';
        }

        $ts = $ts ? (int) $ts : time();
        $sig = self::sign($ticket_code, $ticket_id, $ts);

        return (string) add_query_arg(
            [
                'ticket_code' => rawurlencode($ticket_code),
                'ts'          => (string) $ts,
                'sig'         => $sig,
            ],
            $base_url
        );
    }

    /**
     * Validates a signed cancel link.
     */
    public static function validate(string $ticket_code, int $ticket_id, int $ts, string $sig, int $max_age_seconds = 7776000): bool
    {
        $ticket_code = trim((string) $ticket_code);
        $ticket_id = absint($ticket_id);
        $ts = (int) $ts;
        $sig = trim((string) $sig);

        if ('' === $ticket_code || ! $ticket_id || ! $ts || '' === $sig) {
            return false;
        }

        $age = time() - $ts;
        if ($age < 0 || $age > $max_age_seconds) {
            return false;
        }

        $expected = self::sign($ticket_code, $ticket_id, $ts);
        return hash_equals($expected, $sig);
    }

    private static function sign(string $ticket_code, int $ticket_id, int $ts): string
    {
        $payload = $ticket_code . '|' . $ticket_id . '|' . $ts;
        $raw = hash_hmac('sha256', $payload, wp_salt(self::SIG_SALT_CONTEXT), true);
        return self::base64url_encode($raw);
    }

    private static function base64url_encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}

