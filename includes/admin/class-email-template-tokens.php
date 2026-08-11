<?php

namespace EventTicketsElementor\Admin;

use EventTicketsElementor\Email\Email_Links;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Adds a token legend + small link settings under Email Settings → Template.
 */
class Email_Template_Tokens
{
    public function __construct()
    {
        add_action('admin_init', [$this, 'register'], 20);
    }

    public function register(): void
    {
        register_setting(
            'evt_tickets_settings_group',
            Email_Links::OPTION_KEY,
            [
                'type'              => 'array',
                'sanitize_callback' => [$this, 'sanitize'],
                'default'           => [],
            ]
        );

        add_settings_section(
            'evt_tickets_email_template_tokens_section',
            __('Template Tokens', 'Event-Tickets-for-Elementor'),
            function () {
                echo '<p>' . wp_kses_post(
                    __('Use these tokens in email fields and templates. They will be replaced per ticket.', 'Event-Tickets-for-Elementor')
                ) . '</p>';
                echo '<p><strong>' . esc_html__('Available tokens:', 'Event-Tickets-for-Elementor') . '</strong> ';
                echo '<code>{event_name}</code>, <code>{event_start}</code>, <code>{event_end}</code>, <code>{event_location}</code>, <code>{event_map_url}</code>, ';
                echo '<code>{ticket_code}</code>, <code>{attendee_name}</code>, <code>{attendee_email}</code>, ';
                echo '<code>{verify_url}</code>, <code>{ics_url}</code>, <code>{google_cal_url}</code>, <code>{pdf_url}</code>, <code>{cancel_url}</code>.';
                echo '</p>';
                echo '<p class="description" style="margin-top:6px;">';
                echo esc_html__('Preset branding tokens:', 'Event-Tickets-for-Elementor') . ' ';
                echo '<code>{site_name}</code>, <code>{logo_url}</code>, <code>{primary_color}</code>, <code>{accent_color}</code>, <code>{background_color}</code>.';
                echo '</p>';
                echo '<p class="description" style="margin-top:6px;">' . esc_html__(
                    'QR codes are not included in emails. Use the PDF attachment for the scannable ticket.',
                    'Event-Tickets-for-Elementor') . '</p>';
                echo '<p class="description">' . esc_html__('For custom templates, you can also use {tickets_html} (multi-ticket emails).', 'Event-Tickets-for-Elementor') . '</p>';
            },
            'evt-tickets-settings-email-template'
        );

        // Cancel link fields are more useful on Email Settings → Content (even when not using templates).
        add_settings_section(
            'evt_tickets_email_cancel_link_section',
            __('Cancellation Link', 'Event-Tickets-for-Elementor'),
            function () {
                echo '<p>' . esc_html__(
                    'Configure the cancel link used in ticket emails.',
                    'Event-Tickets-for-Elementor') . '</p>';
            },
            'evt-tickets-settings-email-content'
        );

        add_settings_field(
            'cancel_page_url',
            __('Cancel Page URL', 'Event-Tickets-for-Elementor'),
            [$this, 'render_cancel_page_url'],
            'evt-tickets-settings-email-content',
            'evt_tickets_email_cancel_link_section'
        );

        add_settings_field(
            'cancel_link_label',
            __('Cancel Link Label', 'Event-Tickets-for-Elementor'),
            [$this, 'render_cancel_link_label'],
            'evt-tickets-settings-email-content',
            'evt_tickets_email_cancel_link_section'
        );

        add_settings_field(
            'include_cancel_email',
            __('Prefill email in cancel link', 'Event-Tickets-for-Elementor'),
            [$this, 'render_include_cancel_email'],
            'evt-tickets-settings-email-content',
            'evt_tickets_email_cancel_link_section'
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
            'cancel_page_url'   => isset($input['cancel_page_url']) ? esc_url_raw((string) $input['cancel_page_url']) : '',
            'cancel_link_label' => isset($input['cancel_link_label']) ? sanitize_text_field((string) $input['cancel_link_label']) : '',
            'include_cancel_email' => empty($input['include_cancel_email']) ? 0 : 1,
        ];
    }

    public function render_cancel_page_url(): void
    {
        $options = Email_Links::get_all();
        $value = isset($options['cancel_page_url']) ? (string) $options['cancel_page_url'] : '';
        $name = Email_Links::OPTION_KEY . '[cancel_page_url]';

        printf(
            '<input type="text" class="regular-text" name="%1$s" value="%2$s" placeholder="%3$s" />',
            esc_attr($name),
            esc_attr($value),
            esc_attr(site_url('/ticket-cancel/'))
        );

        echo '<p class="description">' . esc_html__(
            'URL of the page where you placed the “Cancel Ticket” widget. Used to build {cancel_url}.',
            'Event-Tickets-for-Elementor') . '</p>';
    }

    public function render_cancel_link_label(): void
    {
        $options = Email_Links::get_all();
        $value = isset($options['cancel_link_label']) ? (string) $options['cancel_link_label'] : '';
        $name = Email_Links::OPTION_KEY . '[cancel_link_label]';

        printf(
            '<input type="text" class="regular-text" name="%1$s" value="%2$s" placeholder="%3$s" />',
            esc_attr($name),
            esc_attr($value),
            esc_attr(__('Cancel ticket', 'Event-Tickets-for-Elementor'))
        );
    }

    public function render_include_cancel_email(): void
    {
        $options = Email_Links::get_all();
        $value = empty($options['include_cancel_email']) ? 0 : 1;
        $name = Email_Links::OPTION_KEY . '[include_cancel_email]';

        printf(
            '<input type="hidden" name="%1$s" value="0" /><label><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label><p class="description">%4$s</p>',
            esc_attr($name),
            checked($value, 1, false),
            esc_html__('Enabled', 'Event-Tickets-for-Elementor'),
            esc_html__('Adds the attendee email to the cancel URL for convenience. Note: email will be visible in the link.', 'Event-Tickets-for-Elementor')
        );
    }
}
