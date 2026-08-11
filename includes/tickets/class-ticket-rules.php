<?php

namespace EventTicketsElementor\Tickets;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Ticket-level behavior flags that are saved as a separate option array.
 *
 * Kept separate from the main settings store to avoid expanding large settings files.
 */
class Ticket_Rules
{
    public const OPTION_KEY = 'evt_tickets_ticket_rules';

    /**
     * @return array<string,mixed>
     */
    public static function get_all(): array
    {
        $options = get_option(self::OPTION_KEY, []);
        return is_array($options) ? $options : [];
    }

    public static function enforce_timeslot_exclusivity(): bool
    {
        $options = self::get_all();
        return ! empty($options['enforce_timeslot_exclusivity']);
    }

    public static function timeslot_buffer_minutes(): int
    {
        $options = self::get_all();
        $value = isset($options['timeslot_buffer_minutes']) ? (int) $options['timeslot_buffer_minutes'] : 0;
        return max(0, $value);
    }
}

