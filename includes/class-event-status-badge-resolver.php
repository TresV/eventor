<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Resolves site-wide and per-event badge output for event statuses.
 */
class Event_Status_Badge_Resolver
{
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_POSTPONED = 'postponed';
    public const STATUS_OVER = 'over';
    public const STATUS_SCHEDULED = 'scheduled';

    public const META_BADGE_VISIBILITY = '_evt_status_badge_visibility';
    public const META_BADGE_LABEL = '_evt_status_badge_label';
    public const META_BADGE_BG = '_evt_status_badge_bg';
    public const META_BADGE_TEXT = '_evt_status_badge_text';
    public const META_BADGE_BORDER = '_evt_status_badge_border';
    public const META_BADGE_RADIUS = '_evt_status_badge_radius';
    public const META_BADGE_FONT_SIZE = '_evt_status_badge_font_size';
    public const META_BADGE_FONT_WEIGHT = '_evt_status_badge_font_weight';
    public const META_BADGE_PADDING_X = '_evt_status_badge_padding_x';
    public const META_BADGE_PADDING_Y = '_evt_status_badge_padding_y';
    public const META_BADGE_TEXT_TRANSFORM = '_evt_status_badge_text_transform';

    private Settings $settings;
    private Event_Status_Badge_Renderer $renderer;

    public function __construct(Settings $settings, Event_Status_Badge_Renderer $renderer)
    {
        $this->settings = $settings;
        $this->renderer = $renderer;
    }

    /**
     * @param array<string,mixed> $context
     * @return array{status_key:string,status_label:string,status_badge:array<string,mixed>}
     */
    public function resolve_for_event(int $event_id, array $context = []): array
    {
        $status_key = $this->resolve_status_key($event_id, $context);
        $status_label = $this->label_for_status($status_key);
        $badge_enabled = $this->badge_enabled_for_status($event_id, $status_key);
        $style_values = $this->resolve_style_values($event_id);
        $custom_label = trim((string) get_post_meta($event_id, self::META_BADGE_LABEL, true));

        if ('' !== $custom_label && self::STATUS_SCHEDULED !== $status_key) {
            $status_label = $custom_label;
        }

        return [
            'status_key'   => $status_key,
            'status_label' => $status_label,
            'status_badge' => [
                'enabled'    => $badge_enabled,
                'label'      => $status_label,
                'class_name' => $this->renderer->build_class_name($status_key),
                'style'      => $this->renderer->build_css_variables($style_values),
            ],
        ];
    }

    /**
     * @param array<string,mixed> $context
     */
    private function resolve_status_key(int $event_id, array $context = []): string
    {
        if ((bool) get_post_meta($event_id, Event_Status::META_CANCELLED, true)) {
            return self::STATUS_CANCELLED;
        }

        if ((bool) get_post_meta($event_id, Event_Status::META_POSTPONED, true)) {
            return self::STATUS_POSTPONED;
        }

        $end_ts = isset($context['end_ts']) ? (int) $context['end_ts'] : 0;
        if (! $end_ts) {
            $end_ts = (int) get_post_meta($event_id, Event_Meta_Timestamps::END_TS_META, true);
        }

        if (! $end_ts) {
            $end_str = (string) get_post_meta($event_id, '_evt_event_end', true);
            if ('' !== $end_str) {
                $dt = date_create_immutable($end_str, wp_timezone());
                $end_ts = $dt ? (int) $dt->getTimestamp() : 0;
            }
        }

        if ($end_ts > 0 && $end_ts < current_time('timestamp')) {
            return self::STATUS_OVER;
        }

        return self::STATUS_SCHEDULED;
    }

    private function label_for_status(string $status_key): string
    {
        switch ($status_key) {
            case self::STATUS_CANCELLED:
                return (string) $this->settings->get('event_status_badge_label_cancelled', __('Cancelled', 'Event-Tickets-for-Elementor'));
            case self::STATUS_POSTPONED:
                return (string) $this->settings->get('event_status_badge_label_postponed', __('Postponed', 'Event-Tickets-for-Elementor'));
            case self::STATUS_OVER:
                return (string) $this->settings->get('event_status_badge_label_over', __('Over', 'Event-Tickets-for-Elementor'));
            case self::STATUS_SCHEDULED:
            default:
                return (string) __('Scheduled', 'Event-Tickets-for-Elementor');
        }
    }

    private function badge_enabled_for_status(int $event_id, string $status_key): bool
    {
        if (self::STATUS_SCHEDULED === $status_key) {
            return false;
        }

        $global_enabled = (bool) $this->settings->get('event_status_badges_enabled', 1);
        $status_setting = $this->status_visibility_setting_key($status_key);
        if ($status_setting) {
            $global_enabled = $global_enabled && (bool) $this->settings->get($status_setting, 1);
        }

        $override = (string) get_post_meta($event_id, self::META_BADGE_VISIBILITY, true);
        if ('show' === $override) {
            return true;
        }
        if ('hide' === $override) {
            return false;
        }

        return $global_enabled;
    }

    private function status_visibility_setting_key(string $status_key): string
    {
        switch ($status_key) {
            case self::STATUS_CANCELLED:
                return 'event_status_badge_show_cancelled';
            case self::STATUS_POSTPONED:
                return 'event_status_badge_show_postponed';
            case self::STATUS_OVER:
                return 'event_status_badge_show_over';
            default:
                return '';
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function resolve_style_values(int $event_id): array
    {
        $style = [
            'background'     => $this->settings->get('event_status_badge_background', '#f3f4f6'),
            'text_color'     => $this->settings->get('event_status_badge_text_color', '#111827'),
            'border_color'   => $this->settings->get('event_status_badge_border_color', '#d1d5db'),
            'border_radius'  => $this->settings->get('event_status_badge_border_radius', 999),
            'font_size'      => $this->settings->get('event_status_badge_font_size', 12),
            'font_weight'    => $this->settings->get('event_status_badge_font_weight', '600'),
            'padding_x'      => $this->settings->get('event_status_badge_padding_x', 10),
            'padding_y'      => $this->settings->get('event_status_badge_padding_y', 4),
            'text_transform' => $this->settings->get('event_status_badge_text_transform', 'uppercase'),
        ];

        $meta_map = [
            'background'     => self::META_BADGE_BG,
            'text_color'     => self::META_BADGE_TEXT,
            'border_color'   => self::META_BADGE_BORDER,
            'border_radius'  => self::META_BADGE_RADIUS,
            'font_size'      => self::META_BADGE_FONT_SIZE,
            'font_weight'    => self::META_BADGE_FONT_WEIGHT,
            'padding_x'      => self::META_BADGE_PADDING_X,
            'padding_y'      => self::META_BADGE_PADDING_Y,
            'text_transform' => self::META_BADGE_TEXT_TRANSFORM,
        ];

        foreach ($meta_map as $style_key => $meta_key) {
            $value = get_post_meta($event_id, $meta_key, true);
            if ('' !== (string) $value && null !== $value) {
                $style[$style_key] = $value;
            }
        }

        return $this->renderer->normalize_style_values($style);
    }
}
