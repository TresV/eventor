<?php

namespace EventTicketsElementor;

use EventTicketsElementor\Email\Email_Template_Sanitizer;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Centralized settings storage and schema.
 */
class Settings_Store
{
    public const OPTION_KEY = 'evt_tickets_settings';

    /**
     * Sentinel used by secret fields: when a secret field is submitted with
     * this value (or empty), the stored value is kept instead of overwritten.
     */
    public const SECRET_KEEP_SENTINEL = '__KEEP__';

    /**
     * Setting key => wp-config.php constant override.
     * When the constant is defined and truthy it takes precedence over the stored option.
     *
     * @var array<string,string>
     */
    public const SECRET_CONSTANTS = [
        'stripe_secret_key'          => 'EVT_STRIPE_SECRET_KEY',
        'stripe_test_secret_key'     => 'EVT_STRIPE_TEST_SECRET_KEY',
        'stripe_webhook_secret'      => 'EVT_STRIPE_WEBHOOK_SECRET',
        'stripe_test_webhook_secret' => 'EVT_STRIPE_TEST_WEBHOOK_SECRET',
        'epay_merchant_id'           => 'EVT_EPAY_MERCHANT_ID',
        'epay_secret'                => 'EVT_EPAY_SECRET',
    ];

    /**
     * @var array<string,array<string,mixed>>
     */
    private array $fields_index = [];

    /**
     * @var array<int,array<string,mixed>>
     */
    private array $sections = [];

    public function __construct()
    {
        // Lazy-load schema to avoid triggering translation loading too early
        // (WP 6.7+ warns when translations are loaded before `init`).
    }

    /**
     * List all sections with their fields metadata.
     *
     * @return array<int,array<string,mixed>>
     */
    public function sections(): array
    {
        $this->ensure_schema_loaded();
        return $this->sections;
    }

    /**
     * Get a specific field configuration by key.
     *
     * @param string $key
     * @return array<string,mixed>|null
     */
    public function field(string $key): ?array
    {
        $this->ensure_schema_loaded();
        return $this->fields_index[$key] ?? null;
    }

    /**
     * Retrieve a setting with a default.
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public function get(string $key, $default = '')
    {
        $constant = $this->secret_constant_for($key);
        if (null !== $constant && defined($constant)) {
            $override = constant($constant);
            if (! empty($override)) {
                return $override;
            }
        }

        $options = get_option(self::OPTION_KEY, []);
        return $options[$key] ?? $default;
    }

    /**
     * Return the wp-config.php constant name that overrides a setting key, if any.
     *
     * @param string $key
     * @return string|null
     */
    public function secret_constant_for(string $key): ?string
    {
        return self::SECRET_CONSTANTS[$key] ?? null;
    }

    /**
     * Sanitize the incoming settings array using the schema.
     *
     * @param mixed $input
     * @return array<string,mixed>
     */
    public function sanitize($input): array
    {
        $this->ensure_schema_loaded();
        $existing = get_option(self::OPTION_KEY, []);
        if (! is_array($existing)) {
            $existing = [];
        }

        // Keep existing values for fields not present in the submitted form
        // (e.g. when settings UI is split across tabs).
        $output = [];
        foreach ($this->fields_index as $key => $field) {
            if (array_key_exists($key, $existing)) {
                $output[$key] = $existing[$key];
            }
        }

        if (! is_array($input)) {
            return $output;
        }

        foreach ($this->fields_index as $key => $field) {
            if (array_key_exists($key, $input)) {
                // Secret fields keep their stored value unless a new non-empty
                // value was actually submitted (blank/sentinel never wipe it).
                if ('secret' === ($field['type'] ?? '') && $this->should_keep_secret($input[$key])) {
                    continue;
                }
                $output[$key] = $this->sanitize_field($input[$key], $field);
            }
        }

        // Preserve Staff-only settings that are managed outside the main Settings tabs.
        if (class_exists(Staff_Access_Manager::class)) {
            $staff_checkin_key = Staff_Access_Manager::SETTING_CHECKIN_PAGE_URL;

            if (array_key_exists($staff_checkin_key, $existing)) {
                $output[$staff_checkin_key] = Staff_Access_Manager::normalize_checkin_page_url((string) $existing[$staff_checkin_key]);
            }

            if (array_key_exists($staff_checkin_key, $input)) {
                $output[$staff_checkin_key] = Staff_Access_Manager::normalize_checkin_page_url((string) $input[$staff_checkin_key]);
            }
        }

        return $output;
    }

    private function ensure_schema_loaded(): void
    {
        if (! empty($this->sections) || ! empty($this->fields_index)) {
            return;
        }

        $this->sections = $this->build_sections();
        $this->fields_index = $this->index_fields($this->sections);
    }

    /**
     * Build settings sections + fields metadata.
     *
     * @return array<int,array<string,mixed>>
     */
    private function build_sections(): array
    {
        $blogname    = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
        $admin_email = get_option('admin_email');
        $stripe_webhook_url = rest_url('evt/v1/payments/stripe/webhook');
        $epay_webhook_url   = rest_url('evt/v1/payments/epay/webhook');

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
            [
                'id'          => 'evt_tickets_elementor_section',
                'title'       => __('Elementor Form Mapping', 'Event-Tickets-for-Elementor'),
                'description' => __('Map Elementor Pro form fields to ticket data. Useful if your form uses custom field IDs.', 'Event-Tickets-for-Elementor'),
                'tab'         => 'elementor',
                'fields'      => [
                    [
                        'key'         => 'elementor_form_name',
                        'label'       => __('Form Name', 'Event-Tickets-for-Elementor'),
                        'description' => __('Elementor Pro "Form Name" that should create tickets (case-sensitive).', 'Event-Tickets-for-Elementor'),
                        'type'        => 'text',
                        'default'     => 'Event Signup',
                    ],
                    [
                        'key'         => 'elementor_name_field_id',
                        'label'       => __('Name Field ID', 'Event-Tickets-for-Elementor'),
                        'description' => __('Field ID used for attendee name (e.g. "name").', 'Event-Tickets-for-Elementor'),
                        'type'        => 'text',
                        'default'     => 'name',
                    ],
                    [
                        'key'         => 'elementor_email_field_id',
                        'label'       => __('Email Field ID', 'Event-Tickets-for-Elementor'),
                        'description' => __('Field ID used for attendee email (e.g. "email").', 'Event-Tickets-for-Elementor'),
                        'type'        => 'text',
                        'default'     => 'email',
                    ],
                    [
                        'key'         => 'elementor_event_field_id',
                        'label'       => __('Event Field ID', 'Event-Tickets-for-Elementor'),
                        'description' => __('Field ID used for event name (e.g. "event").', 'Event-Tickets-for-Elementor'),
                        'type'        => 'text',
                        'default'     => 'event',
                    ],
                    [
                        'key'         => 'elementor_event_datetime_field_id',
                        'label'       => __('Event Date/Time Field ID', 'Event-Tickets-for-Elementor'),
                        'description' => __('Field ID used for event date & time (e.g. "event_date_time").', 'Event-Tickets-for-Elementor'),
                        'type'        => 'text',
                        'default'     => 'event_date_time',
                    ],
                    [
                        'key'         => 'elementor_event_location_field_id',
                        'label'       => __('Event Location Field ID', 'Event-Tickets-for-Elementor'),
                        'description' => __('Field ID used for event location (e.g. "event_location").', 'Event-Tickets-for-Elementor'),
                        'type'        => 'text',
                        'default'     => 'event_location',
                    ],
                ],
            ],
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
            [
                'id'          => 'evt_tickets_payments_woocommerce_section',
                'title'       => __('WooCommerce Checkout', 'Event-Tickets-for-Elementor'),
                'description' => __('Configure how paid event requests are sent to WooCommerce cart and checkout.', 'Event-Tickets-for-Elementor'),
                'tab'         => 'payments',
                'fields'      => [
                    [
                        'key'         => 'woocommerce_product_id',
                        'label'       => __('WooCommerce Product ID', 'Event-Tickets-for-Elementor'),
                        'description' => __('Product used as the paid ticket carrier in WooCommerce. Use a simple hidden/virtual product for ticket checkout.', 'Event-Tickets-for-Elementor'),
                        'type'        => 'number',
                        'default'     => 0,
                    ],
                    [
                        'key'         => 'woocommerce_checkout_redirect',
                        'label'       => __('Redirect After Add To Cart', 'Event-Tickets-for-Elementor'),
                        'description' => __('Choose whether paid ticket requests should go to cart or directly to checkout.', 'Event-Tickets-for-Elementor'),
                        'type'        => 'select',
                        'default'     => 'checkout',
                        'options'     => [
                            'checkout' => __('Checkout', 'Event-Tickets-for-Elementor'),
                            'cart'     => __('Cart', 'Event-Tickets-for-Elementor'),
                        ],
                    ],
                    [
                        'key'   => 'woocommerce_paid_events_note',
                        'label' => __('Paid Event Flow', 'Event-Tickets-for-Elementor'),
                        'type'  => 'html',
                        'html'  => '<p class="description">' . esc_html__('Paid events use the Ticket Box to add a WooCommerce item to cart with attendee, event, and timeslot metadata. Tickets are issued only after the WooCommerce order reaches a paid status.', 'Event-Tickets-for-Elementor') . '</p>',
                    ],
                ],
            ],
            [
                'id'          => 'evt_tickets_payments_processors_section',
                'title'       => __('Payment Processors (Direct Checkout)', 'Event-Tickets-for-Elementor'),
                'description' => __('Paid ticket requests can go directly to a hosted payment page (Stripe for global cards, ePay.bg for Bulgarian bank cards) without WooCommerce.', 'Event-Tickets-for-Elementor'),
                'tab'         => 'payments',
                'fields'      => [
                    [
                        'key'         => 'payment_processor',
                        'label'       => __('Active Processor', 'Event-Tickets-for-Elementor'),
                        'description' => __('Choose where paid ticket requests are sent. myPOS support arrives in Phase 2.', 'Event-Tickets-for-Elementor'),
                        'type'        => 'select',
                        'default'     => '',
                        'options'     => [
                            ''            => __('Auto — WooCommerce if connected', 'Event-Tickets-for-Elementor'),
                            'woocommerce' => __('WooCommerce (fallback)', 'Event-Tickets-for-Elementor'),
                            'stripe'      => __('Stripe — Global cards', 'Event-Tickets-for-Elementor'),
                            'epay'        => __('ePay.bg — Bulgaria', 'Event-Tickets-for-Elementor'),
                        ],
                    ],
                    [
                        'key'         => 'payment_currency',
                        'label'       => __('Currency', 'Event-Tickets-for-Elementor'),
                        'description' => __('ISO-4217 code used for direct checkout amounts (e.g. EUR).', 'Event-Tickets-for-Elementor'),
                        'type'        => 'text',
                        'default'     => 'EUR',
                    ],
                    [
                        'key'         => 'payment_hold_ttl_minutes',
                        'label'       => __('Seat Hold (minutes)', 'Event-Tickets-for-Elementor'),
                        'description' => __('How long a pending payment holds the seats before they are released.', 'Event-Tickets-for-Elementor'),
                        'type'        => 'number',
                        'default'     => 30,
                    ],
                    [
                        'key'         => 'stripe_mode',
                        'label'       => __('Stripe Mode', 'Event-Tickets-for-Elementor'),
                        'type'        => 'select',
                        'default'     => 'test',
                        'options'     => [
                            'live' => __('Live', 'Event-Tickets-for-Elementor'),
                            'test' => __('Test', 'Event-Tickets-for-Elementor'),
                        ],
                    ],
                    [
                        'key'         => 'stripe_secret_key',
                        'label'       => __('Stripe Secret Key (live)', 'Event-Tickets-for-Elementor'),
                        'type'        => 'secret',
                    ],
                    [
                        'key'         => 'stripe_test_secret_key',
                        'label'       => __('Stripe Secret Key (test)', 'Event-Tickets-for-Elementor'),
                        'type'        => 'secret',
                    ],
                    [
                        'key'         => 'stripe_webhook_secret',
                        'label'       => __('Stripe Webhook Secret (live)', 'Event-Tickets-for-Elementor'),
                        'description' => sprintf(
                            /* translators: %s is the Stripe webhook URL. */
                            __('The whsec_... value from the Stripe dashboard; the webhook URL is %s.', 'Event-Tickets-for-Elementor'),
                            $stripe_webhook_url
                        ),
                        'type'        => 'secret',
                    ],
                    [
                        'key'         => 'stripe_test_webhook_secret',
                        'label'       => __('Stripe Webhook Secret (test)', 'Event-Tickets-for-Elementor'),
                        'type'        => 'secret',
                    ],
                    [
                        'key'         => 'epay_merchant_id',
                        'label'       => __('ePay.bg Merchant ID (MIN)', 'Event-Tickets-for-Elementor'),
                        'type'        => 'text',
                    ],
                    [
                        'key'         => 'epay_secret',
                        'label'       => __('ePay.bg Secret', 'Event-Tickets-for-Elementor'),
                        'description' => sprintf(
                            /* translators: %s is the ePay.bg IPN URL. */
                            __('The IPN URL is %s.', 'Event-Tickets-for-Elementor'),
                            $epay_webhook_url
                        ),
                        'type'        => 'secret',
                    ],
                    [
                        'key'         => 'epay_test_mode',
                        'label'       => __('ePay.bg Demo mode', 'Event-Tickets-for-Elementor'),
                        'type'        => 'checkbox',
                        'default'     => 1,
                    ],
                ],
            ],
        ];
    }

    /**
     * Flatten fields for quicker lookup.
     *
     * @param array<int,array<string,mixed>> $sections
     * @return array<string,array<string,mixed>>
     */
    private function index_fields(array $sections): array
    {
        $fields = [];

        foreach ($sections as $section) {
            if (empty($section['fields']) || ! is_array($section['fields'])) {
                continue;
            }

            foreach ($section['fields'] as $field) {
                $fields[$field['key']] = $field;
            }
        }

        return $fields;
    }

    /**
     * Whether a secret field submission should keep the stored value.
     * Empty values and the sentinel both mean "leave the stored secret unchanged".
     *
     * @param mixed $value
     * @return bool
     */
    private function should_keep_secret($value): bool
    {
        if (self::SECRET_KEEP_SENTINEL === $value) {
            return true;
        }
        return '' === trim((string) $value);
    }

    /**
     * Apply the correct sanitizer per field.
     *
     * @param mixed $value
     * @param array<string,mixed> $field
     * @return mixed
     */
    private function sanitize_field($value, array $field)
    {
        if (isset($field['sanitize_callback']) && is_callable($field['sanitize_callback'])) {
            return call_user_func($field['sanitize_callback'], $value);
        }

        switch ($field['type'] ?? 'text') {
            case 'checkbox':
            case 'checkbox_matrix':
                return ! empty($value) ? 1 : 0;
            case 'number':
                return absint($value);
            case 'select':
                $value = sanitize_text_field((string) $value);
                $options = isset($field['options']) && is_array($field['options']) ? $field['options'] : [];
                if (isset($options[$value])) {
                    return $value;
                }
                return $field['default'] ?? '';
            case 'color':
                $value = sanitize_hex_color((string) $value);
                return $value ? $value : '';
            case 'email':
                return sanitize_email((string) $value);
            case 'secret':
                return sanitize_text_field((string) $value);
            case 'textarea':
                return wp_kses_post((string) $value);
            case 'text':
            default:
                return sanitize_text_field((string) $value);
        }
    }
}
