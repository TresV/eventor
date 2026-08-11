<?php

/**
 * Helpers for lightweight rate limiting and client-IP detection.
 *
 * @package EventTicketsElementor
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Best-effort client IP for rate limiting.
 *
 * Prefers the Cloudflare-provided header (only present when the site is really
 * behind Cloudflare); otherwise falls back to the server-visible address. This
 * is an anti-abuse heuristic, not a security boundary.
 *
 * @return string
 */
function evt_client_ip(): string
{
    if (! empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip = sanitize_text_field(wp_unslash((string) $_SERVER['HTTP_CF_CONNECTING_IP']));
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }

    $ip = sanitize_text_field(wp_unslash((string) ($_SERVER['REMOTE_ADDR'] ?? '')));
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
}

/**
 * Non-blocking per-key rate limiter backed by transients.
 *
 * @param string $bucket  Logical bucket key (e.g. 'ticket_box:ip:1.2.3.4').
 * @param int    $limit   Maximum allowed hits within the window. 0 disables.
 * @param int    $window  Window length in seconds.
 * @return bool True when the caller has exceeded the limit (should be rejected).
 */
function evt_rate_limit_hit(string $bucket, int $limit, int $window = 900): bool
{
    if ($limit <= 0 || '' === $bucket) {
        return false;
    }

    $key   = 'evt_rl_' . md5($bucket);
    $count = (int) get_transient($key);
    if ($count >= $limit) {
        return true;
    }

    set_transient($key, $count + 1, $window);
    return false;
}
