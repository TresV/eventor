<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Event tab settings sections: site-wide event status badges labels and styling.
 *
 * @param array<string,mixed> $ctx Shared schema context (blogname, admin_email, webhook URLs).
 * @return array<int,array<string,mixed>>
 */
function schema_event(array $ctx): array
{
        return [
            [
                'id'          => 'evt_tickets_event_status_badges_section',
                'title'       => __('Event Status Badges', 'Event-Tickets-for-Elementor'),
                'description' => __('Configure site-wide event status labels and badge styling used across dynamic tags and event discovery widgets.', 'Event-Tickets-for-Elementor'),
                'tab'         => 'event',
                'fields'      => [
                    [
                        'key'         => 'event_status_badges_enabled',
                        'label'       => __('Enable Event Status Badges', 'Event-Tickets-for-Elementor'),
                        'description' => __('Master toggle for cancelled, postponed, and over badges.', 'Event-Tickets-for-Elementor'),
                        'type'        => 'checkbox',
                        'default'     => 1,
                    ],
                    [
                        'key'         => 'event_status_badge_show_cancelled',
                        'label'       => __('Show Cancelled Badge', 'Event-Tickets-for-Elementor'),
                        'type'        => 'checkbox',
                        'default'     => 1,
                    ],
                    [
                        'key'         => 'event_status_badge_show_postponed',
                        'label'       => __('Show Postponed Badge', 'Event-Tickets-for-Elementor'),
                        'type'        => 'checkbox',
                        'default'     => 1,
                    ],
                    [
                        'key'         => 'event_status_badge_show_over',
                        'label'       => __('Show Over Badge', 'Event-Tickets-for-Elementor'),
                        'description' => __('The "Over" badge is inferred automatically when an event end time is in the past.', 'Event-Tickets-for-Elementor'),
                        'type'        => 'checkbox',
                        'default'     => 1,
                    ],
                    [
                        'key'         => 'event_status_badge_label_cancelled',
                        'label'       => __('Cancelled Label', 'Event-Tickets-for-Elementor'),
                        'type'        => 'text',
                        'default'     => __('Cancelled', 'Event-Tickets-for-Elementor'),
                    ],
                    [
                        'key'         => 'event_status_badge_label_postponed',
                        'label'       => __('Postponed Label', 'Event-Tickets-for-Elementor'),
                        'type'        => 'text',
                        'default'     => __('Postponed', 'Event-Tickets-for-Elementor'),
                    ],
                    [
                        'key'         => 'event_status_badge_label_over',
                        'label'       => __('Over Label', 'Event-Tickets-for-Elementor'),
                        'type'        => 'text',
                        'default'     => __('Over', 'Event-Tickets-for-Elementor'),
                    ],
                    [
                        'key'         => 'event_status_badge_background',
                        'label'       => __('Badge Background', 'Event-Tickets-for-Elementor'),
                        'type'        => 'color',
                        'default'     => '#f3f4f6',
                        'placeholder' => '#f3f4f6',
                    ],
                    [
                        'key'         => 'event_status_badge_text_color',
                        'label'       => __('Badge Text Color', 'Event-Tickets-for-Elementor'),
                        'type'        => 'color',
                        'default'     => '#111827',
                        'placeholder' => '#111827',
                    ],
                    [
                        'key'         => 'event_status_badge_border_color',
                        'label'       => __('Badge Border Color', 'Event-Tickets-for-Elementor'),
                        'type'        => 'color',
                        'default'     => '#d1d5db',
                        'placeholder' => '#d1d5db',
                    ],
                    [
                        'key'         => 'event_status_badge_border_radius',
                        'label'       => __('Badge Border Radius (px)', 'Event-Tickets-for-Elementor'),
                        'type'        => 'number',
                        'default'     => 999,
                    ],
                    [
                        'key'         => 'event_status_badge_font_size',
                        'label'       => __('Badge Font Size (px)', 'Event-Tickets-for-Elementor'),
                        'type'        => 'number',
                        'default'     => 12,
                    ],
                    [
                        'key'         => 'event_status_badge_font_weight',
                        'label'       => __('Badge Font Weight', 'Event-Tickets-for-Elementor'),
                        'type'        => 'select',
                        'default'     => '600',
                        'options'     => [
                            '400' => '400',
                            '500' => '500',
                            '600' => '600',
                            '700' => '700',
                        ],
                    ],
                    [
                        'key'         => 'event_status_badge_padding_x',
                        'label'       => __('Badge Horizontal Padding (px)', 'Event-Tickets-for-Elementor'),
                        'type'        => 'number',
                        'default'     => 10,
                    ],
                    [
                        'key'         => 'event_status_badge_padding_y',
                        'label'       => __('Badge Vertical Padding (px)', 'Event-Tickets-for-Elementor'),
                        'type'        => 'number',
                        'default'     => 4,
                    ],
                    [
                        'key'         => 'event_status_badge_text_transform',
                        'label'       => __('Badge Text Transform', 'Event-Tickets-for-Elementor'),
                        'type'        => 'select',
                        'default'     => 'uppercase',
                        'options'     => [
                            'none'       => __('None', 'Event-Tickets-for-Elementor'),
                            'uppercase'  => __('Uppercase', 'Event-Tickets-for-Elementor'),
                            'capitalize' => __('Capitalize', 'Event-Tickets-for-Elementor'),
                            'lowercase'  => __('Lowercase', 'Event-Tickets-for-Elementor'),
                        ],
                    ],
                ],
            ],
        ];
}
