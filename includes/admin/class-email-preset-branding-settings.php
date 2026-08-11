<?php

namespace EventTicketsElementor\Admin;

use EventTicketsElementor\Email\Email_Preset_Branding;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Branding settings used by email presets (logo + colors).
 */
class Email_Preset_Branding_Settings
{
    public function __construct()
    {
        add_action('admin_init', [$this, 'register'], 25);
    }

    public function register(): void
    {
        register_setting(
            'evt_tickets_settings_group',
            Email_Preset_Branding::OPTION_KEY,
            [
                'type'              => 'array',
                'sanitize_callback' => [$this, 'sanitize'],
                'default'           => [],
            ]
        );

        add_settings_section(
            'evt_tickets_email_preset_branding_section',
            __('Preset Branding', 'Event-Tickets-for-Elementor'),
            function () {
                echo '<p>' . esc_html__(
                    'Branding options for the built-in email presets (logo + colors).',
                    'Event-Tickets-for-Elementor') . '</p>';
            },
            'evt-tickets-settings-email-content'
        );

        add_settings_field(
            'show_logo',
            __('Show Logo', 'Event-Tickets-for-Elementor'),
            [$this, 'render_show_logo'],
            'evt-tickets-settings-email-content',
            'evt_tickets_email_preset_branding_section'
        );

        add_settings_field(
            'primary_color',
            __('Primary Color', 'Event-Tickets-for-Elementor'),
            [$this, 'render_primary_color'],
            'evt-tickets-settings-email-content',
            'evt_tickets_email_preset_branding_section'
        );

        add_settings_field(
            'accent_color',
            __('Accent Color', 'Event-Tickets-for-Elementor'),
            [$this, 'render_accent_color'],
            'evt-tickets-settings-email-content',
            'evt_tickets_email_preset_branding_section'
        );

        add_settings_field(
            'background_color',
            __('Background Color', 'Event-Tickets-for-Elementor'),
            [$this, 'render_background_color'],
            'evt-tickets-settings-email-content',
            'evt_tickets_email_preset_branding_section'
        );
    }

    /**
     * @param mixed $input
     * @return array<string,mixed>
     */
    public function sanitize($input): array
    {
        if (! is_array($input)) {
            $input = [];
        }

        return [
            'show_logo'        => empty($input['show_logo']) ? 0 : 1,
            'primary_color'    => sanitize_hex_color((string) ($input['primary_color'] ?? '')) ?: '#111827',
            'accent_color'     => sanitize_hex_color((string) ($input['accent_color'] ?? '')) ?: '#7C3AED',
            'background_color' => sanitize_hex_color((string) ($input['background_color'] ?? '')) ?: '#F3F4F6',
        ];
    }

    public function render_show_logo(): void
    {
        $options = Email_Preset_Branding::get_all();
        $value = empty($options['show_logo']) ? 0 : 1;
        $name = Email_Preset_Branding::OPTION_KEY . '[show_logo]';

        printf(
            '<input type="hidden" name="%1$s" value="0" /><label><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label><p class="description">%4$s</p>',
            esc_attr($name),
            checked($value, 1, false),
            esc_html__('Enabled', 'Event-Tickets-for-Elementor'),
            esc_html__('Uses your site logo (Appearance → Customize → Site Identity) or the Site Icon as fallback.', 'Event-Tickets-for-Elementor')
        );
    }

    public function render_primary_color(): void
    {
        $name = Email_Preset_Branding::OPTION_KEY . '[primary_color]';
        printf(
            '<input type="text" class="evt-email-color-field" name="%1$s" value="%2$s" data-default-color="%3$s" />',
            esc_attr($name),
            esc_attr(Email_Preset_Branding::primary_color()),
            esc_attr('#111827')
        );
    }

    public function render_accent_color(): void
    {
        $name = Email_Preset_Branding::OPTION_KEY . '[accent_color]';
        printf(
            '<input type="text" class="evt-email-color-field" name="%1$s" value="%2$s" data-default-color="%3$s" />',
            esc_attr($name),
            esc_attr(Email_Preset_Branding::accent_color()),
            esc_attr('#7C3AED')
        );
    }

    public function render_background_color(): void
    {
        $name = Email_Preset_Branding::OPTION_KEY . '[background_color]';
        printf(
            '<input type="text" class="evt-email-color-field" name="%1$s" value="%2$s" data-default-color="%3$s" />',
            esc_attr($name),
            esc_attr(Email_Preset_Branding::background_color()),
            esc_attr('#F3F4F6')
        );
    }
}

