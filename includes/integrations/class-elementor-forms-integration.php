<?php

namespace EventTicketsElementor\Integrations;

use EventTicketsElementor\Ticket_Service;
use EventTicketsElementor\Settings;
use EventTicketsElementor\Email_Service;
use EventTicketsElementor\CPT_Events;
use EventTicketsElementor\Event_Lock;
use EventTicketsElementor\Plugin;
use EventTicketsElementor\Event_Timeslots;
use EventTicketsElementor\Event_Timeslot_Capacity;
use EventTicketsElementor\Tickets\Ticket_Rules;
use EventTicketsElementor\Tickets\Ticket_Timeslot_Exclusivity;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Elementor Pro Forms integration.
 *
 * Listens to form submissions and creates tickets + sends emails.
 */
class Elementor_Forms_Integration
{

    /** @var Ticket_Service */
    protected $ticket_service;

    /** @var Settings */
    protected $settings;

    /** @var Email_Service */
    protected $email_service;

    public function __construct(
        Ticket_Service $ticket_service,
        Settings $settings,
        Email_Service $email_service
    ) {
        $this->ticket_service = $ticket_service;
        $this->settings       = $settings;
        $this->email_service  = $email_service;

        add_action(
            'elementor_pro/forms/new_record',
            [$this, 'handle_form_submission'],
            10,
            2
        );
    }

    /**
     * Handle Elementor Pro form submission.
     *
     * @param \ElementorPro\Modules\Forms\Classes\Form_Record  $record
     * @param \ElementorPro\Modules\Forms\Classes\Ajax_Handler $ajax_handler
     */
    public function handle_form_submission($record, $ajax_handler): void
    {
        if (! is_object($record) || ! method_exists($record, 'get_form_settings')) {
            return;
        }

        // Settings: form name + field ID mappings.
        $expected_form_name = trim((string) $this->settings->get('elementor_form_name', 'Event Signup'));
        $name_field_id      = (string) $this->settings->get('elementor_name_field_id', 'name');
        $email_field_id     = (string) $this->settings->get('elementor_email_field_id', 'email');
        $event_field_id     = (string) $this->settings->get('elementor_event_field_id', 'event');
        $event_dt_field_id  = (string) $this->settings->get('elementor_event_datetime_field_id', 'event_date_time');
        $event_loc_field_id = (string) $this->settings->get('elementor_event_location_field_id', 'event_location');

        $form_name = (string) $record->get_form_settings('form_name');
        if ($expected_form_name && $form_name !== $expected_form_name) {
            return;
        }

        $fields = $record->get('fields');
        if (! is_array($fields) || empty($fields)) {
            return;
        }

        $attendee_name      = $this->lookup_field($fields, $name_field_id);
        $attendee_email     = $this->lookup_field($fields, $email_field_id);
        $event_name_raw     = $this->lookup_field($fields, $event_field_id);
        $event_start_raw    = $this->lookup_field($fields, $event_dt_field_id);
        $event_location_raw = $this->lookup_field($fields, $event_loc_field_id);

        $attendee_email = sanitize_email($attendee_email);

        if (empty($attendee_name) || empty($attendee_email)) {
            return;
        }

        $event_name     = trim((string) $event_name_raw);
        $event_start    = trim((string) $event_start_raw);
        $event_end      = '';
        $event_location = trim((string) $event_location_raw);

        // Best-effort link: resolve event_id by title (if an Event CPT exists with this name).
        $event_id = 0;
        if ($event_name) {
            global $wpdb;
            $event_id = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' AND post_title = %s LIMIT 1",
                    CPT_Events::POST_TYPE,
                    $event_name
                )
            );
        }

        // No linked event and no snapshot details: cannot issue tickets.
        if (! $event_id && ('' === $event_name || '' === $event_start)) {
            return;
        }

        $timeslot_id = '';
        $timeslots = new Event_Timeslots();

        if ($event_id && $timeslots->requires_timeslot_selection($event_id)) {
            $normalized = $timeslots->normalize_datetime($event_start);
            if ('' === $normalized) {
                $normalized = $timeslots->normalize_datetime((string) get_post_meta($event_id, '_evt_event_start', true));
            }

            $slot = null;
            foreach ($timeslots->get_slots($event_id) as $candidate) {
                if (isset($candidate['start']) && (string) $candidate['start'] === $normalized) {
                    $slot = $candidate;
                    break;
                }
            }

            if (! $slot) {
                if (is_object($ajax_handler) && method_exists($ajax_handler, 'add_error_message')) {
                    $ajax_handler->add_error_message(__('This event requires a valid timeslot selection.', 'Event-Tickets-for-Elementor'));
                }
                return;
            }

            $timeslot_id = (string) ($slot['id'] ?? '');
            $event_start = (string) ($slot['start'] ?? $event_start);
            $event_end   = (string) ($slot['end'] ?? $event_end);
        }

        if ($event_id && Ticket_Rules::enforce_timeslot_exclusivity()) {
            $validator = new Ticket_Timeslot_Exclusivity(Ticket_Rules::timeslot_buffer_minutes());
            $timerange = null;
            if ('' !== $event_start) {
                $timerange = [
                    'start_ts' => (int) strtotime($event_start),
                    'end_ts'   => (int) strtotime($event_end ? $event_end : $event_start),
                ];
            }

            $ok        = $validator->assert_no_timeslot_conflict($event_id, $attendee_email, $timerange);
            if (is_wp_error($ok)) {
                if (is_object($ajax_handler) && method_exists($ajax_handler, 'add_error_message')) {
                    $ajax_handler->add_error_message($ok->get_error_message());
                }
                return;
            }
        }

        if ($event_id) {
            // Capacity enforcement + concurrency guard.
            $lock  = new Event_Lock();
            $token = $lock->acquire($event_id, 10);
            if ('' === $token) {
                return;
            }

            $capacity = new Event_Timeslot_Capacity(Plugin::instance()->event_capacity, $timeslots);
            $remaining = $capacity->remaining($event_id, $timeslot_id);
            if (PHP_INT_MAX !== $remaining && $remaining <= 0) {
                $lock->release($event_id, $token);
                return;
            }
        }

        // Snapshot of the submission for later reference.
        $snapshot = [
            'form_name' => $form_name,
            'fields'    => [],
        ];

        foreach ($fields as $id => $field) {
            if (! is_array($field)) {
                continue;
            }

            $snapshot['fields'][] = [
                'id'    => isset($field['id']) ? $field['id'] : (string) $id,
                'label' => isset($field['title']) ? $field['title'] : '',
                'value' => isset($field['value']) ? $field['value'] : '',
            ];
        }

        $ticket_id = $this->ticket_service->create_ticket(
            [
                'attendee_name'   => $attendee_name,
                'attendee_email'  => $attendee_email,
                'event_name'      => $event_name,
                'event_id'        => $event_id,
                'event_start'     => $event_start,
                'event_end'       => $event_end,
                'event_location'  => $event_location,
                'form_snapshot'   => $snapshot,
            ]
        );

        if ($event_id) {
            $lock->release($event_id, $token);
        }

        if ($ticket_id && '' !== $timeslot_id) {
            update_post_meta((int) $ticket_id, '_ticket_timeslot_id', $timeslot_id);
        }

        if ($ticket_id) {
            $this->email_service->send_ticket_email($ticket_id);
        } elseif (defined('WP_DEBUG') && WP_DEBUG && function_exists('wp_trigger_error')) {
            wp_trigger_error(
                '',
                'Event Tickets for Elementor: Failed to create ticket from form "' . $form_name . '" for email ' . $attendee_email,
                E_USER_NOTICE
            );
        }
    }

    /**
     * Look up a value from Elementor fields by ID or custom ID.
     *
     * @param array  $fields
     * @param string $id
     *
     * @return string
     */
    protected function lookup_field(array $fields, string $id): string
    {
        if (isset($fields[$id]) && is_array($fields[$id])) {
            if (isset($fields[$id]['value'])) {
                return (string) $fields[$id]['value'];
            }
        }

        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            if (
                (isset($field['id']) && $field['id'] === $id) ||
                (isset($field['custom_id']) && $field['custom_id'] === $id)
            ) {
                if (isset($field['value'])) {
                    return (string) $field['value'];
                }
            }
        }

        return '';
    }
}
