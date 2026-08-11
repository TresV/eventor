<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

use EventTicketsElementor\Email\Email_Template_Sanitizer;

/**
 * Email tab settings sections: content, attachments, template, and advanced email behavior.
 *
 * @param array<string,mixed> $ctx Shared schema context (blogname, admin_email, webhook URLs).
 * @return array<int,array<string,mixed>>
 */
function schema_email(array $ctx): array
{
        $blogname    = $ctx['blogname'];
        $admin_email = $ctx['admin_email'];

        return [
            [
                'id'          => 'evt_tickets_email_section',
                'title'       => __('Email Settings', 'Event-Tickets-for-Elementor'),
                'tab'         => 'email',
                'subtab'      => 'content',
                'description_html' => wp_kses_post(
                    __(
                        'Control how ticket emails are sent to attendees.<br><br><strong>Available tokens:</strong> <code>{event_name}</code>, <code>{event_start}</code>, <code>{event_end}</code>, <code>{event_location}</code>, <code>{event_map_url}</code>, <code>{ticket_code}</code>, <code>{attendee_name}</code>, <code>{attendee_email}</code>, <code>{verify_url}</code>, <code>{ics_url}</code>, <code>{google_cal_url}</code>, <code>{pdf_url}</code>.',
                        'Event-Tickets-for-Elementor'
                    )
                ),
                'fields'      => [
                    [
                        'key'         => 'from_name',
                        'label'       => __('From Name', 'Event-Tickets-for-Elementor'),
                        'description' => __('Name shown as the sender of ticket emails.', 'Event-Tickets-for-Elementor'),
                        'type'        => 'text',
                        'default'     => $blogname,
                    ],
                    [
                        'key'         => 'from_email',
                        'label'       => __('From Email', 'Event-Tickets-for-Elementor'),
                        'description' => __('Email address used as the sender and reply-to for ticket emails.', 'Event-Tickets-for-Elementor'),
                        'type'        => 'email',
                        'default'     => $admin_email,
                    ],
                    [
                        'key'         => 'ticket_email_subject',
                        'label'       => __('Ticket Email Subject', 'Event-Tickets-for-Elementor'),
                        'description' => __('Subject line for ticket emails. Supported tokens: {event_name}, {ticket_code}, {attendee_name}.', 'Event-Tickets-for-Elementor'),
                        'type'        => 'text',
                        'default'     => __('Your ticket for {event_name}', 'Event-Tickets-for-Elementor'),
                    ],
                    [
                        'key'         => 'ticket_email_footer',
                        'label'       => __('Ticket Email Footer', 'Event-Tickets-for-Elementor'),
                        'description' => __('Shown at the bottom of the ticket email. You can include bilingual instructions here.', 'Event-Tickets-for-Elementor'),
                        'type'        => 'textarea',
                        'default'     => __('If you need to make changes or cancel, please contact us by reply to this email.', 'Event-Tickets-for-Elementor'),
                    ],
                    [
                        'key'         => 'email_greeting',
                        'label'       => __('Greeting', 'Event-Tickets-for-Elementor'),
                        'description' => __('Example: Hi {attendee_name},', 'Event-Tickets-for-Elementor'),
                        'type'        => 'text',
                        'default'     => __('Hi {attendee_name},', 'Event-Tickets-for-Elementor'),
                    ],
                    [
                        'key'         => 'email_intro',
                        'label'       => __('Intro Text', 'Event-Tickets-for-Elementor'),
                        'description' => __('Shown below the greeting.', 'Event-Tickets-for-Elementor'),
                        'type'        => 'textarea',
                        'default'     => __('Thank you for registering for {event_name}.', 'Event-Tickets-for-Elementor'),
                    ],
                    [
                        'key'         => 'email_event_details_heading',
                        'label'       => __('Event Details Heading', 'Event-Tickets-for-Elementor'),
                        'type'        => 'text',
                        'default'     => __('Event details', 'Event-Tickets-for-Elementor'),
                    ],
                    [
                        'key'         => 'email_label_event',
                        'label'       => __('Label: Event', 'Event-Tickets-for-Elementor'),
                        'type'        => 'text',
                        'default'     => __('Event:', 'Event-Tickets-for-Elementor'),
                    ],
                    [
                        'key'         => 'email_label_datetime',
                        'label'       => __('Label: Date & Time', 'Event-Tickets-for-Elementor'),
                        'type'        => 'text',
                        'default'     => __('Date & time:', 'Event-Tickets-for-Elementor'),
                    ],
                    [
                        'key'         => 'email_label_end',
                        'label'       => __('Label: End', 'Event-Tickets-for-Elementor'),
                        'type'        => 'text',
                        'default'     => __('End:', 'Event-Tickets-for-Elementor'),
                    ],
                    [
                        'key'         => 'email_label_location',
                        'label'       => __('Label: Location', 'Event-Tickets-for-Elementor'),
                        'type'        => 'text',
                        'default'     => __('Location:', 'Event-Tickets-for-Elementor'),
                    ],
                    [
                        'key'         => 'email_show_location_block',
                        'label'       => __('Show Location/Navigation Block', 'Event-Tickets-for-Elementor'),
                        'description' => __('Adds a section with the event location and a maps link (when available).', 'Event-Tickets-for-Elementor'),
                        'type'        => 'checkbox',
                        'default'     => 1,
                    ],
                    [
                        'key'         => 'email_location_heading',
                        'label'       => __('Location Block Heading', 'Event-Tickets-for-Elementor'),
                        'type'        => 'text',
                        'default'     => __('Getting there', 'Event-Tickets-for-Elementor'),
                    ],
                    [
                        'key'         => 'email_location_body',
                        'label'       => __('Location Block Text', 'Event-Tickets-for-Elementor'),
                        'description' => __('Use tokens like {event_location} and {event_map_url}.', 'Event-Tickets-for-Elementor'),
                        'type'        => 'textarea',
                        'default'     => __("Venue: {event_location}\nOpen in Maps: {event_map_url}", 'Event-Tickets-for-Elementor'),
                    ],
                    [
                        'key'         => 'email_ticket_code_intro',
                        'label'       => __('Ticket Code Intro', 'Event-Tickets-for-Elementor'),
                        'type'        => 'text',
                        'default'     => __('Your ticket code is:', 'Event-Tickets-for-Elementor'),
                    ],
                    [
                        'key'         => 'email_qr_instructions',
                        'label'       => __('QR Instructions', 'Event-Tickets-for-Elementor'),
                        'type'        => 'textarea',
                        'default'     => __('Show the QR code below at the entrance. Our staff will scan it to check you in.', 'Event-Tickets-for-Elementor'),
                    ],
                    [
                        'key'         => 'email_add_to_calendar_heading',
                        'label'       => __('Calendar Block Heading', 'Event-Tickets-for-Elementor'),
                        'type'        => 'text',
                        'default'     => __('Add to calendar:', 'Event-Tickets-for-Elementor'),
                    ],
                    [
                        'key'         => 'email_google_calendar_label',
                        'label'       => __('Google Calendar Button Label', 'Event-Tickets-for-Elementor'),
                        'type'        => 'text',
                        'default'     => __('Google Calendar', 'Event-Tickets-for-Elementor'),
                    ],
                    [
                        'key'         => 'email_ics_label',
                        'label'       => __('.ics Button Label', 'Event-Tickets-for-Elementor'),
                        'type'        => 'text',
                        'default'     => __('Download .ics', 'Event-Tickets-for-Elementor'),
                    ],
                    [
                        'key'         => 'email_pdf_label',
                        'label'       => __('PDF Button Label', 'Event-Tickets-for-Elementor'),
                        'type'        => 'text',
                        'default'     => __('Download Ticket (PDF)', 'Event-Tickets-for-Elementor'),
                    ],
                    [
                        'key'         => 'email_multi_tickets_intro',
                        'label'       => __('Multi-ticket Intro', 'Event-Tickets-for-Elementor'),
                        'description' => __('Shown above the ticket list when multiple tickets are emailed together.', 'Event-Tickets-for-Elementor'),
                        'type'        => 'text',
                        'default'     => __('Here are your tickets:', 'Event-Tickets-for-Elementor'),
                    ],
                    [
                        'key'         => 'email_label_ticket_code',
                        'label'       => __('Label: Ticket Code', 'Event-Tickets-for-Elementor'),
                        'description' => __('Label used inside multi-ticket emails for each ticket code row.', 'Event-Tickets-for-Elementor'),
                        'type'        => 'text',
                        'default'     => __('Ticket code:', 'Event-Tickets-for-Elementor'),
                    ],
                ],
            ],
            [
                'id'          => 'evt_tickets_email_attachments_section',
                'title'       => __('Email Attachments', 'Event-Tickets-for-Elementor'),
                'tab'         => 'email',
                'subtab'      => 'attachments',
                'description' => __('Control whether QR/ICS are embedded in the email body and/or attached.', 'Event-Tickets-for-Elementor'),
                'fields'      => [
                    [
                        'key'         => 'email_qr_mode',
                        'label'       => __('QR Delivery', 'Event-Tickets-for-Elementor'),
                        'description' => __('Choose how the QR code is included for single-ticket emails.', 'Event-Tickets-for-Elementor'),
                        'type'        => 'select',
                        'default'     => 'both',
                        'options'     => [
                            'inline' => __('Inline (in email body)', 'Event-Tickets-for-Elementor'),
                            'attach' => __('Attachment only', 'Event-Tickets-for-Elementor'),
                            'both'   => __('Inline + attachment', 'Event-Tickets-for-Elementor'),
                            'off'    => __('Do not include QR', 'Event-Tickets-for-Elementor'),
                        ],
                    ],
                    [
                        'key'         => 'email_qr_size',
                        'label'       => __('QR Size (px)', 'Event-Tickets-for-Elementor'),
                        'description' => __('Used when generating the email QR image.', 'Event-Tickets-for-Elementor'),
                        'type'        => 'number',
                        'default'     => 300,
                    ],
                    [
                        'key'         => 'email_attach_ics',
                        'label'       => __('Attach .ics File', 'Event-Tickets-for-Elementor'),
                        'description' => __('Attach an .ics file in addition to showing calendar buttons/links.', 'Event-Tickets-for-Elementor'),
                        'type'        => 'checkbox',
                        'default'     => 1,
                    ],
                ],
            ],
            [
                'id'          => 'evt_tickets_email_template_section',
                'title'       => __('Email Template', 'Event-Tickets-for-Elementor'),
                'tab'         => 'email',
                'subtab'      => 'template',
                'description' => __('Customize the default email or provide your own HTML/CSS template.', 'Event-Tickets-for-Elementor'),
                'fields'      => [
                    [
                        'key'         => 'email_custom_css',
                        'label'       => __('Custom Email CSS', 'Event-Tickets-for-Elementor'),
                        'description' => __('Optional. Added inside a <style> block in the email HTML. Use !important to override defaults if needed.', 'Event-Tickets-for-Elementor'),
                        'type'        => 'textarea',
                        'sanitize_callback' => 'sanitize_textarea_field',
                        'default'     => '',
                    ],
                    [
                        'key'         => 'email_use_custom_template',
                        'label'       => __('Use Custom Email Template', 'Event-Tickets-for-Elementor'),
                        'description' => __('Enable to use your own HTML template (advanced).', 'Event-Tickets-for-Elementor'),
                        'type'        => 'checkbox',
                        'default'     => 0,
                    ],
                    [
                        'key'         => 'email_custom_template_css',
                        'label'       => __('Custom Template CSS', 'Event-Tickets-for-Elementor'),
                        'description' => __('Optional. Applied as a <style> block when using a custom template.', 'Event-Tickets-for-Elementor'),
                        'type'        => 'textarea',
                        'sanitize_callback' => 'sanitize_textarea_field',
                        'default'     => '',
                    ],
                    [
                        'key'         => 'email_custom_template_html',
                        'label'       => __('Custom Template HTML', 'Event-Tickets-for-Elementor'),
                        'description' => __('Paste HTML. You can use the same tokens as above. Use {tickets_html} in multi-ticket emails.', 'Event-Tickets-for-Elementor'),
                        'type'        => 'textarea',
                        'sanitize_callback' => [Email_Template_Sanitizer::class, 'sanitize_html'],
                        'default'     => '',
                    ],
                    [
                        'key'   => 'email_custom_template_preview',
                        'label' => __('Template Preview', 'Event-Tickets-for-Elementor'),
                        'type'  => 'html',
                        'html'  => '<div id="evt-email-template-preview" style="margin-top:8px;"><iframe title="Email preview" style="width:100%;min-height:320px;border:1px solid #d1d5db;background:#fff;"></iframe><p class="description" style="margin-top:8px;">Preview uses sample values and updates as you type.</p></div>',
                    ],
                ],
            ],
            [
                'id'          => 'evt_tickets_email_advanced_section',
                'title'       => __('Email Advanced', 'Event-Tickets-for-Elementor'),
                'tab'         => 'email',
                'subtab'      => 'advanced',
                'description' => __('Advanced behaviors for specific email clients.', 'Event-Tickets-for-Elementor'),
                'fields'      => [
                    [
                        'key'         => 'email_microsoft_invite_enabled',
                        'label'       => __('Microsoft Invite Mode', 'Event-Tickets-for-Elementor'),
                        'description' => __('When enabled, Outlook/Hotmail/Live recipients receive a calendar-invite style email body for better “Add to calendar” prompts.', 'Event-Tickets-for-Elementor'),
                        'type'        => 'checkbox',
                        'default'     => 1,
                    ],
                ],
            ],
        ];
}
