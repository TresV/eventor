<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Elementor tab settings sections: Elementor Pro form field mapping.
 *
 * @param array<string,mixed> $ctx Shared schema context (blogname, admin_email, webhook URLs).
 * @return array<int,array<string,mixed>>
 */
function schema_elementor(array $ctx): array
{
        return [
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
        ];
}
