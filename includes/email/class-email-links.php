<?php

namespace EventTicketsElementor\Email;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Stores small email-related link settings outside the main Settings_Store,
 * to keep the large settings schema file stable.
 */
class Email_Links
{
    public const OPTION_KEY = 'evt_tickets_email_links';

    /**
     * @return array<string,mixed>
     */
    public static function get_all(): array
    {
        $options = get_option(self::OPTION_KEY, []);
        return is_array($options) ? $options : [];
    }

    public static function cancel_page_url(): string
    {
        $options = self::get_all();
        $url = isset($options['cancel_page_url']) ? (string) $options['cancel_page_url'] : '';
        $url = trim($url);

        if ('' === $url) {
            return (string) site_url('/ticket-cancel/');
        }

        return (string) esc_url_raw($url);
    }

    public static function cancel_link_label(): string
    {
        $options = self::get_all();
        $label = isset($options['cancel_link_label']) ? (string) $options['cancel_link_label'] : '';
        $label = trim($label);

        if ('' === $label) {
            return (string) __('Cancel ticket', 'Event-Tickets-for-Elementor');
        }

        return $label;
    }

    public static function include_email_in_cancel_link(): bool
    {
        $options = self::get_all();
        return ! empty($options['include_cancel_email']);
    }
}
