<?php

namespace EventTicketsElementor\Elementor_Conditions;

use EventTicketsElementor\Event_Taxonomies;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * In Child Categories condition for Events.
 */
class Event_Child_Categories_Condition extends Event_Condition_Base
{
    public const NAME = 'evt_in_event_child_category';

    public static function get_type(): string
    {
        return Event_Singular_Condition::NAME;
    }

    public static function get_priority(): int
    {
        return 60;
    }

    public function get_name(): string
    {
        return self::NAME;
    }

    public function get_label(): string
    {
        return __('In Child Categories', 'Event-Tickets-for-Elementor');
    }

    public function get_all_label(): string
    {
        return __('Any Event Child Category', 'Event-Tickets-for-Elementor');
    }

    protected function _register_controls()
    {
        $this->add_select_control(
            __('Parent Event Category', 'Event-Tickets-for-Elementor'),
            $this->get_term_options(Event_Taxonomies::TAX_CATEGORY, __('Any Event Child Category', 'Event-Tickets-for-Elementor'))
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

        $event_term_ids = wp_get_post_terms(
            $event_id,
            Event_Taxonomies::TAX_CATEGORY,
            ['fields' => 'ids']
        );

        if (is_wp_error($event_term_ids) || empty($event_term_ids)) {
            return false;
        }

        $selected = $this->get_selected_value(is_array($args) ? $args : []);
        if ('' === $selected || self::ALL_OPTION === $selected) {
            foreach ($event_term_ids as $term_id) {
                $term = get_term((int) $term_id, Event_Taxonomies::TAX_CATEGORY);
                if ($term instanceof \WP_Term && $term->parent > 0) {
                    return true;
                }
            }

            return false;
        }

        $children = get_term_children(absint($selected), Event_Taxonomies::TAX_CATEGORY);
        if (is_wp_error($children) || empty($children)) {
            return false;
        }

        $children = array_map('absint', $children);

        foreach ($event_term_ids as $term_id) {
            if (in_array((int) $term_id, $children, true)) {
                return true;
            }
        }

        return false;
    }
}
