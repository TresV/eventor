<?php

namespace EventTicketsElementor\Elementor_Conditions;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Specific/all Events condition.
 */
class Event_Posts_Condition extends Event_Condition_Base
{
    public const NAME = 'evt_events';

    public static function get_type(): string
    {
        return Event_Singular_Condition::NAME;
    }

    public static function get_priority(): int
    {
        return 40;
    }

    public function get_name(): string
    {
        return self::NAME;
    }

    public function get_label(): string
    {
        return __('Events', 'Event-Tickets-for-Elementor');
    }

    public function get_all_label(): string
    {
        return __('All Events', 'Event-Tickets-for-Elementor');
    }

    protected function _register_controls()
    {
        $this->add_select_control(__('Event', 'Event-Tickets-for-Elementor'), $this->get_event_options(true));
    }

    public function register_sub_conditions(): void
    {
    }

    public function check($args): bool
    {
        $event_id = $this->get_current_event_id();
        if ($event_id <= 0) {
            return false;
        }

        $selected = $this->get_selected_value(is_array($args) ? $args : []);

        if ('' === $selected || self::ALL_OPTION === $selected) {
            return true;
        }

        return $event_id === absint($selected);
    }
}
