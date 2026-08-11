<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * PDF tab settings sections: PDF ticket layout and email attachments.
 *
 * @param array<string,mixed> $ctx Shared schema context (blogname, admin_email, webhook URLs).
 * @return array<int,array<string,mixed>>
 */
function schema_pdf(array $ctx): array
{
        return [
            [
                'id'          => 'evt_tickets_pdf_section',
                'title'       => __('PDF Tickets', 'Event-Tickets-for-Elementor'),
                'description' => __('Control the PDF ticket layout and email attachments.', 'Event-Tickets-for-Elementor'),
                'tab'         => 'pdf',
                'fields'      => [
                    [
                        'key'         => 'pdf_template_preset',
                        'label'       => __('Global PDF Template', 'Event-Tickets-for-Elementor'),
                        'description' => __('Default PDF template for all events.', 'Event-Tickets-for-Elementor'),
                        'type'        => 'select',
                        'default'     => 'classic',
                        'options'     => [
                            'classic' => __('Classic', 'Event-Tickets-for-Elementor'),
                            'minimal' => __('Minimal', 'Event-Tickets-for-Elementor'),
                            'dark'    => __('Dark', 'Event-Tickets-for-Elementor'),
                        ],
                    ],
                    [
                        'key'         => 'pdf_allow_event_override',
                        'label'       => __('Allow Per-Event PDF Template Override', 'Event-Tickets-for-Elementor'),
                        'description' => __('When enabled, each event can override the global PDF template in the Event editor.', 'Event-Tickets-for-Elementor'),
                        'type'        => 'checkbox',
                        'default'     => 1,
                    ],
                    [
                        'key'         => 'pdf_show_logo',
                        'label'       => __('Show Logo', 'Event-Tickets-for-Elementor'),
                        'description' => __('Include the site logo (or site icon) in the PDF header when available.', 'Event-Tickets-for-Elementor'),
                        'type'        => 'checkbox',
                        'default'     => 1,
                    ],
                    [
                        'key'         => 'pdf_accent_color',
                        'label'       => __('Accent Color', 'Event-Tickets-for-Elementor'),
                        'description' => __('Hex color used for the PDF accent bar.', 'Event-Tickets-for-Elementor'),
                        'type'        => 'color',
                        'default'     => '#2563eb',
                        'placeholder' => '#2563eb',
                    ],
                    [
                        'key'         => 'pdf_footer_text',
                        'label'       => __('Footer Text', 'Event-Tickets-for-Elementor'),
                        'description' => __('Optional footer text shown at the bottom of the PDF.', 'Event-Tickets-for-Elementor'),
                        'type'        => 'textarea',
                        'default'     => '',
                    ],
                    [
                        'key'         => 'pdf_attach_to_emails',
                        'label'       => __('Attach PDF to Emails', 'Event-Tickets-for-Elementor'),
                        'description' => __('Attach a PDF ticket to outgoing ticket emails. Disable if email size is a concern.', 'Event-Tickets-for-Elementor'),
                        'type'        => 'checkbox',
                        'default'     => 1,
                    ],
                ],
            ],
        ];
}
