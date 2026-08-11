<?php

namespace EventTicketsElementor\Elementor_Conditions;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Events by author condition.
 */
class Event_Author_Condition extends Event_Condition_Base
{
    public const NAME = 'evt_events_by_author';

    public static function get_type(): string
    {
        return Event_Singular_Condition::NAME;
    }

    public static function get_priority(): int
    {
        return 80;
    }

    public function get_name(): string
    {
        return self::NAME;
    }

    public function get_label(): string
    {
        return __('Events by author', 'Event-Tickets-for-Elementor');
    }

    public function get_all_label(): string
    {
        return __('Any Event Author', 'Event-Tickets-for-Elementor');
    }

    protected function _register_controls()
    {
        $this->add_select_control(
            __('Event Author', 'Event-Tickets-for-Elementor'),
            $this->get_author_options(true)
        );
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
        $author_id = (int) get_post_field('post_author', $event_id);

        if ('' === $selected || self::ALL_OPTION === $selected) {
            return $author_id > 0;
        }

        return $author_id === absint($selected);
    }
}
