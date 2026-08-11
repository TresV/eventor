<?php

namespace EventTicketsElementor\Elementor_Conditions;

use EventTicketsElementor\CPT_Events;
use EventTicketsElementor\Event_Selector;
use ElementorPro\Modules\ThemeBuilder\Conditions\Condition_Base;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Shared helpers for Event-focused Elementor Pro conditions.
 */
abstract class Event_Condition_Base extends Condition_Base
{
    protected const ALL_OPTION = 'all';

    protected function get_current_event_id(): int
    {
        if (! is_singular(CPT_Events::POST_TYPE)) {
            return 0;
        }

        $event_id = (int) get_queried_object_id();
        if ($event_id > 0 && CPT_Events::POST_TYPE === get_post_type($event_id)) {
            return $event_id;
        }

        $post = get_post();
        if ($post instanceof \WP_Post && CPT_Events::POST_TYPE === $post->post_type) {
            return (int) $post->ID;
        }

        return 0;
    }

    protected function event_has_term(int $event_id, int $term_id, string $taxonomy): bool
    {
        if ($event_id <= 0 || $term_id <= 0) {
            return false;
        }

        return has_term($term_id, $taxonomy, $event_id);
    }

    /**
     * @return array<string,string>
     */
    protected function get_event_options(bool $include_all = true): array
    {
        $options = Event_Selector::get_event_options(0);

        if ($include_all) {
            $options = [self::ALL_OPTION => __('All Events', 'Event-Tickets-for-Elementor')] + $options;
        }

        return $options;
    }

    /**
     * @return array<string,string>
     */
    protected function get_term_options(string $taxonomy, string $all_label): array
    {
        $options = [];
        $terms   = get_terms(
            [
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
            ]
        );

        $options[self::ALL_OPTION] = $all_label;

        if (is_wp_error($terms) || empty($terms)) {
            return $options;
        }

        foreach ($terms as $term) {
            if (! $term instanceof \WP_Term) {
                continue;
            }

            $options[(string) $term->term_id] = $term->name;
        }

        return $options;
    }

    /**
     * @return array<string,string>
     */
    protected function get_author_options(bool $include_all = true): array
    {
        $options = [];
        $users   = get_users(
            [
                'fields'              => 'all',
                'has_published_posts' => [CPT_Events::POST_TYPE],
                'orderby'             => 'display_name',
                'order'               => 'ASC',
            ]
        );

        if ($include_all) {
            $options[self::ALL_OPTION] = __('Any Event Author', 'Event-Tickets-for-Elementor');
        }

        if (! is_array($users) || empty($users)) {
            return $options;
        }

        foreach ($users as $user) {
            if (! $user instanceof \WP_User) {
                continue;
            }

            $options[(string) $user->ID] = $user->display_name;
        }

        return $options;
    }

    /**
     * @param array<string,mixed> $args
     */
    protected function get_selected_value(array $args, string $setting_key = 'id'): string
    {
        $selected = $args['id'] ?? $args[$setting_key] ?? null;

        if (is_array($selected)) {
            $selected = reset($selected);
        }

        return is_scalar($selected) ? (string) $selected : '';
    }

    protected function add_select_control(string $label, array $options): void
    {
        $this->add_control(
            'id',
            [
                'section' => 'settings',
                'label'   => $label,
                'type'    => \Elementor\Controls_Manager::SELECT2,
                'options' => $options,
                'default' => self::ALL_OPTION,
            ]
        );
    }
}
