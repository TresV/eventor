<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Short-lived lock per event to reduce race conditions (DB-backed via options).
 */
class Event_Lock
{
    private const KEY_PREFIX = 'evt_lock_event_';

    /**
     * Acquire a lock for an event. Returns a token on success, empty string on failure.
     */
    public function acquire(int $event_id, int $ttl_seconds = 15): string
    {
        $event_id = absint($event_id);
        if (! $event_id) {
            return '';
        }

        $key   = self::KEY_PREFIX . $event_id;
        $now   = time();
        $token = wp_generate_password(12, false, false);

        // If stale lock exists, clear it.
        $existing = get_option($key, null);
        if (is_array($existing) && isset($existing['expires_at']) && (int) $existing['expires_at'] < $now) {
            delete_option($key);
        }

        $value = [
            'token'      => $token,
            'expires_at' => $now + max(5, $ttl_seconds),
        ];

        // Atomic acquire using unique option name.
        $acquired = add_option($key, $value, '', 'no');
        if ($acquired) {
            return $token;
        }

        return '';
    }

    public function release(int $event_id, string $token): void
    {
        $event_id = absint($event_id);
        if (! $event_id || '' === $token) {
            return;
        }

        $key = self::KEY_PREFIX . $event_id;
        $existing = get_option($key, null);

        if (is_array($existing) && ($existing['token'] ?? '') === $token) {
            delete_option($key);
        }
    }
}

