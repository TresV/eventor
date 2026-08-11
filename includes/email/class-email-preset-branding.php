<?php

namespace EventTicketsElementor\Email;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Stores branding configuration used by email presets (colors + logo).
 *
 * Kept separate from Settings_Store to avoid expanding large settings schema files.
 */
class Email_Preset_Branding
{
    public const OPTION_KEY = 'evt_tickets_email_preset_branding';

    /**
     * @return array<string,mixed>
     */
    public static function get_all(): array
    {
        $options = get_option(self::OPTION_KEY, []);
        return is_array($options) ? $options : [];
    }

    public static function primary_color(): string
    {
        $options = self::get_all();
        $color = isset($options['primary_color']) ? (string) $options['primary_color'] : '#111827';
        return self::sanitize_hex($color, '#111827');
    }

    public static function accent_color(): string
    {
        $options = self::get_all();
        $color = isset($options['accent_color']) ? (string) $options['accent_color'] : '#7C3AED';
        return self::sanitize_hex($color, '#7C3AED');
    }

    public static function background_color(): string
    {
        $options = self::get_all();
        $color = isset($options['background_color']) ? (string) $options['background_color'] : '#F3F4F6';
        return self::sanitize_hex($color, '#F3F4F6');
    }

    public static function show_logo(): bool
    {
        $options = self::get_all();
        return ! empty($options['show_logo']);
    }

    public static function logo_url(): string
    {
        if (! self::show_logo()) {
            return '';
        }

        $custom_logo_id = (int) get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            $url = wp_get_attachment_image_url($custom_logo_id, 'full');
            if (is_string($url) && '' !== trim($url)) {
                return (string) esc_url_raw($url);
            }
        }

        $icon = get_site_icon_url(256);
        if (is_string($icon) && '' !== trim($icon)) {
            return (string) esc_url_raw($icon);
        }

        return '';
    }

    public static function logo_display(): string
    {
        return self::logo_url() ? 'block' : 'none';
    }

    private static function sanitize_hex(string $value, string $fallback): string
    {
        $value = trim($value);
        if ('' === $value) {
            return $fallback;
        }

        $san = sanitize_hex_color($value);
        return $san ? $san : $fallback;
    }
}

