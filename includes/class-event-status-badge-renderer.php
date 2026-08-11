<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Shared normalization and rendering helpers for event status badges.
 */
class Event_Status_Badge_Renderer
{
    public const CSS_VAR_BACKGROUND = '--evt-status-bg';
    public const CSS_VAR_TEXT = '--evt-status-text';
    public const CSS_VAR_BORDER = '--evt-status-border';
    public const CSS_VAR_RADIUS = '--evt-status-radius';
    public const CSS_VAR_FONT_SIZE = '--evt-status-font-size';
    public const CSS_VAR_FONT_WEIGHT = '--evt-status-font-weight';
    public const CSS_VAR_PADDING_X = '--evt-status-padding-x';
    public const CSS_VAR_PADDING_Y = '--evt-status-padding-y';
    public const CSS_VAR_TEXT_TRANSFORM = '--evt-status-text-transform';

    /**
     * @return array<string,string|int>
     */
    public function default_style_values(): array
    {
        return [
            'background'     => '#f3f4f6',
            'text_color'     => '#111827',
            'border_color'   => '#d1d5db',
            'border_radius'  => 999,
            'font_size'      => 12,
            'font_weight'    => '600',
            'padding_x'      => 10,
            'padding_y'      => 4,
            'text_transform' => 'uppercase',
        ];
    }

    /**
     * @param array<string,mixed> $style
     * @return array<string,string|int>
     */
    public function normalize_style_values(array $style): array
    {
        $defaults = $this->default_style_values();

        $background = isset($style['background']) ? sanitize_hex_color((string) $style['background']) : '';
        $text_color = isset($style['text_color']) ? sanitize_hex_color((string) $style['text_color']) : '';
        $border_color = isset($style['border_color']) ? sanitize_hex_color((string) $style['border_color']) : '';

        $font_weight = isset($style['font_weight']) ? (string) $style['font_weight'] : '';
        if (! in_array($font_weight, ['400', '500', '600', '700'], true)) {
            $font_weight = (string) $defaults['font_weight'];
        }

        $text_transform = isset($style['text_transform']) ? (string) $style['text_transform'] : '';
        if (! in_array($text_transform, ['none', 'uppercase', 'capitalize', 'lowercase'], true)) {
            $text_transform = (string) $defaults['text_transform'];
        }

        return [
            'background'     => $background ?: (string) $defaults['background'],
            'text_color'     => $text_color ?: (string) $defaults['text_color'],
            'border_color'   => $border_color ?: (string) $defaults['border_color'],
            'border_radius'  => max(0, (int) ($style['border_radius'] ?? $defaults['border_radius'])),
            'font_size'      => max(10, (int) ($style['font_size'] ?? $defaults['font_size'])),
            'font_weight'    => $font_weight,
            'padding_x'      => max(0, (int) ($style['padding_x'] ?? $defaults['padding_x'])),
            'padding_y'      => max(0, (int) ($style['padding_y'] ?? $defaults['padding_y'])),
            'text_transform' => $text_transform,
        ];
    }

    /**
     * @param array<string,mixed> $style
     * @return array<string,string>
     */
    public function build_css_variables(array $style): array
    {
        $style = $this->normalize_style_values($style);

        return [
            self::CSS_VAR_BACKGROUND     => (string) $style['background'],
            self::CSS_VAR_TEXT           => (string) $style['text_color'],
            self::CSS_VAR_BORDER         => (string) $style['border_color'],
            self::CSS_VAR_RADIUS         => (int) $style['border_radius'] . 'px',
            self::CSS_VAR_FONT_SIZE      => (int) $style['font_size'] . 'px',
            self::CSS_VAR_FONT_WEIGHT    => (string) $style['font_weight'],
            self::CSS_VAR_PADDING_X      => (int) $style['padding_x'] . 'px',
            self::CSS_VAR_PADDING_Y      => (int) $style['padding_y'] . 'px',
            self::CSS_VAR_TEXT_TRANSFORM => (string) $style['text_transform'],
        ];
    }

    public function build_class_name(string $status_key): string
    {
        $status_key = sanitize_key($status_key);
        return 'evt-event-status evt-event-status--' . $status_key;
    }
}
