<?php

namespace EventTicketsElementor\Elementor_Conditions;

use EventTicketsElementor\Event_Taxonomies;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * In Category condition for Events.
 */
class Event_Category_Condition extends Event_Condition_Base
{
    public const NAME = 'evt_in_event_category';

    public static function get_type(): string
    {
        return Event_Singular_Condition::NAME;
    }

    public static function get_priority(): int
    {
        return 50;
    }

    public function get_name(): string
    {
        return self::NAME;
    }

    public function get_label(): string
    {
        return __('In Category', 'Event-Tickets-for-Elementor');
    }

    public function get_all_label(): string
    {
        return __('Any Event Category', 'Event-Tickets-for-Elementor');
    }

    protected function _register_controls()
    {
        $this->add_select_control(
            __('Event Category', 'Event-Tickets-for-Elementor'),
            $this->get_term_options(Event_Taxonomies::TAX_CATEGORY, __('Any Event Category', 'Event-Tickets-for-Elementor'))
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
        if ('' === $selected || self::ALL_OPTION === $selected) {
            $term_ids = wp_get_post_terms(
                $event_id,
                Event_Taxonomies::TAX_CATEGORY,
                ['fields' => 'ids']
            );

            return ! is_wp_error($term_ids) && ! empty($term_ids);
        }

        return $this->event_has_term($event_id, absint($selected), Event_Taxonomies::TAX_CATEGORY);
    }
}
