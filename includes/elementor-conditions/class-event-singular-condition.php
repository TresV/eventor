<?php

namespace EventTicketsElementor\Elementor_Conditions;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Singular Events condition root under Elementor Pro's "Singular" tree.
 */
class Event_Singular_Condition extends Event_Condition_Base
{
    public const NAME = 'evt_event';

    public static function get_type(): string
    {
        return 'singular';
    }

    public static function get_priority(): int
    {
        return 41;
    }

    public function get_name(): string
    {
        return self::NAME;
    }

    public function get_label(): string
    {
        return __('Event', 'Event-Tickets-for-Elementor');
    }

    public function get_all_label(): string
    {
        return __('All Events', 'Event-Tickets-for-Elementor');
    }

    public function register_sub_conditions(): void
    {
        $this->register_sub_condition(new Event_Posts_Condition());
        $this->register_sub_condition(new Event_Category_Condition());
        $this->register_sub_condition(new Event_Child_Categories_Condition());
        $this->register_sub_condition(new Event_Tag_Condition());
        $this->register_sub_condition(new Event_Author_Condition());
    }

    public function check($args): bool
    {
        return $this->get_current_event_id() > 0;
    }
}
