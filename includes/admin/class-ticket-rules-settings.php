<?php

namespace EventTicketsElementor\Admin;

use EventTicketsElementor\Tickets\Ticket_Rules;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Registers "Ticket Rules" settings on the existing settings page.
 */
class Ticket_Rules_Settings
{
    public function __construct()
    {
        add_action('admin_init', [$this, 'register'], 20);
    }

    public function register(): void
    {
        register_setting(
            'evt_tickets_settings_group',
            Ticket_Rules::OPTION_KEY,
            [
                'type'              => 'array',
                'sanitize_callback' => [$this, 'sanitize'],
                'default'           => [],
            ]
        );

        add_settings_section(
            'evt_tickets_ticket_rules_section',
            __('Ticket Rules', 'Event-Tickets-for-Elementor'),
            function () {
                echo '<p>' . esc_html__('Control ticket validation rules applied during ticket creation.', 'Event-Tickets-for-Elementor') . '</p>';
            },
            'evt-tickets-settings-elementor'
        );

        add_settings_field(
            'enforce_timeslot_exclusivity',
            __('Prevent timeslot conflicts', 'Event-Tickets-for-Elementor'),
            [$this, 'render_timeslot_exclusivity_field'],
            'evt-tickets-settings-elementor',
            'evt_tickets_ticket_rules_section'
        );

        add_settings_field(
            'timeslot_buffer_minutes',
            __('Buffer minutes', 'Event-Tickets-for-Elementor'),
            [$this, 'render_timeslot_buffer_field'],
            'evt-tickets-settings-elementor',
            'evt_tickets_ticket_rules_section'
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
            'enforce_timeslot_exclusivity' => empty($input['enforce_timeslot_exclusivity']) ? 0 : 1,
            'timeslot_buffer_minutes'      => max(0, (int) ($input['timeslot_buffer_minutes'] ?? 0)),
        ];
    }

    public function render_timeslot_exclusivity_field(): void
    {
        $options = Ticket_Rules::get_all();
        $value = empty($options['enforce_timeslot_exclusivity']) ? 0 : 1;

        $name = Ticket_Rules::OPTION_KEY . '[enforce_timeslot_exclusivity]';
        printf(
            '<input type="hidden" name="%1$s" value="0" /><label><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label><p class="description">%4$s</p>',
            esc_attr($name),
            checked($value, 1, false),
            esc_html__('Enabled', 'Event-Tickets-for-Elementor'),
            esc_html__('Blocks creating a ticket when the attendee email already has an active ticket for another event that overlaps in time.', 'Event-Tickets-for-Elementor')
        );
    }

    public function render_timeslot_buffer_field(): void
    {
        $options = Ticket_Rules::get_all();
        $value = isset($options['timeslot_buffer_minutes']) ? (int) $options['timeslot_buffer_minutes'] : 0;

        $name = Ticket_Rules::OPTION_KEY . '[timeslot_buffer_minutes]';
        printf(
            '<input type="number" min="0" step="1" class="small-text" name="%1$s" value="%2$s" /> <span class="description">%3$s</span>',
            esc_attr($name),
            esc_attr((string) max(0, $value)),
            esc_html__('Optional. Adds a buffer to both sides of the event time range when detecting overlaps.', 'Event-Tickets-for-Elementor')
        );
    }
}

