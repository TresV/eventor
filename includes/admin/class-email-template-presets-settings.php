<?php

namespace EventTicketsElementor\Admin;

use EventTicketsElementor\Email\Email_Template_Presets;
use EventTicketsElementor\Email\Email_Preset_Branding;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Adds a "Preset" selector with previews under Email Settings → Template.
 */
class Email_Template_Presets_Settings
{
    public function __construct()
    {
        add_action('admin_init', [$this, 'register'], 20);
    }

    public function register(): void
    {
        register_setting(
            'evt_tickets_settings_group',
            Email_Template_Presets::OPTION_KEY,
            [
                'type'              => 'string',
                'sanitize_callback' => [Email_Template_Presets::class, 'sanitize_preset'],
                'default'           => Email_Template_Presets::PRESET_CLASSIC,
            ]
        );

        add_settings_section(
            'evt_tickets_email_presets_section',
            __('Email Presets', 'Event-Tickets-for-Elementor'),
            function () {
                echo '<p>' . esc_html__(
                    'Choose one of the built-in email templates. Custom templates (if enabled) override this choice.',
                    'Event-Tickets-for-Elementor'
                ) . '</p>';
            },
            'evt-tickets-settings-email-content'
        );

        add_settings_field(
            Email_Template_Presets::OPTION_KEY,
            __('Preset', 'Event-Tickets-for-Elementor'),
            [$this, 'render_field'],
            'evt-tickets-settings-email-content',
            'evt_tickets_email_presets_section'
        );
    }

    public function render_field(): void
    {
        $current = Email_Template_Presets::get_current();

        $templates = [];
        foreach (Email_Template_Presets::presets() as $key => $_label) {
            $tpl = Email_Template_Presets::single($key);
            $templates[$key] = [
                'css'  => (string) ($tpl['css'] ?? ''),
                'html' => (string) ($tpl['html'] ?? ''),
            ];
        }

        $sample = [
            'event_name'      => 'Sample Event',
            'event_start'     => '2026-06-24 10:00',
            'event_end'       => '2026-06-24 18:00',
            'event_location'  => 'Sample Venue, Sample City',
            'event_map_url'   => 'https://maps.google.com/?q=Sample+Venue',
            'ticket_code'     => 'EVT-ABCDEFG123',
            'attendee_name'   => 'Viktor',
            'attendee_email'  => 'viktor@example.com',
            'verify_url'      => 'https://example.com/ticket-checkin/?code=EVT-ABCDEFG123',
            'ics_url'         => 'https://example.com/event.ics',
            'google_cal_url'  => 'https://www.google.com/calendar/render?action=TEMPLATE',
            'pdf_url'         => 'https://example.com/ticket.pdf',
            'cancel_url'      => 'https://example.com/ticket-cancel/?ticket_code=EVT-ABCDEFG123',
            'greeting'        => 'Hi Viktor,',
            'intro'           => 'Thank you for registering for Sample Event.',
            'tickets_html'    => '<div style="margin:12px 0;padding:12px;border:1px solid #e5e7eb;border-radius:10px;"><strong>Sample Event</strong> — EVT-ABCDEFG123</div>',
            'site_name'       => wp_specialchars_decode(get_option('blogname'), ENT_QUOTES),
            'logo_url'        => Email_Preset_Branding::logo_url(),
        ];

        $data = [
            'templates' => $templates,
            'sample'    => $sample,
            'defaults'  => [
                'primary_color'    => Email_Preset_Branding::primary_color(),
                'accent_color'     => Email_Preset_Branding::accent_color(),
                'background_color' => Email_Preset_Branding::background_color(),
            ],
        ];

        echo '<script type="application/json" id="evt-email-presets-data">' . wp_json_encode($data) . '</script>';

        echo '<div style="max-width:1100px;">';
        foreach (Email_Template_Presets::presets() as $key => $label) {
            $checked = checked($current, $key, false);
            $open = ($current === $key) ? ' open' : '';

            echo '<details class="evt-email-preset-item" data-preset="' . esc_attr($key) . '"' . esc_attr($open) . ' style="border:1px solid #d1d5db;border-radius:12px;padding:8px 10px;background:#fff;margin-bottom:12px;">';
            echo '<summary style="cursor:pointer;display:flex;align-items:center;gap:10px;list-style:none;">';
            echo '<input type="radio" name="' . esc_attr(Email_Template_Presets::OPTION_KEY) . '" value="' . esc_attr($key) . '" ' . esc_attr($checked) . ' />';
            echo '<strong style="font-size:14px;">' . esc_html($label) . '</strong>';
            echo '<span class="description" style="margin-left:auto;">' . esc_html__('Preview', 'Event-Tickets-for-Elementor') . '</span>';
            echo '</summary>';
            echo '<div style="margin-top:10px;">';
            echo '<iframe class="evt-email-preset-preview" data-preset="' . esc_attr($key) . '" title="' . esc_attr($label) . '" style="width:100%;height:520px;border:1px solid #e5e7eb;border-radius:10px;background:#fff;" srcdoc=""></iframe>';
            echo '</div>';
            echo '</details>';
        }
        echo '</div>';

        echo '<p class="description" style="max-width:900px;">' . esc_html__(
            'Preview uses sample values. Actual emails will use your email content settings and ticket data.',
            'Event-Tickets-for-Elementor'
        ) . '</p>';
    }
}
