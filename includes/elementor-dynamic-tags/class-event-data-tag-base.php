<?php

namespace EventTicketsElementor\Elementor_Dynamic_Tags;

use EventTicketsElementor\CPT_Events;
use EventTicketsElementor\Event_Selector;
use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Data_Tag;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Base helper for Event "data" dynamic tags (e.g. image tags).
 */
abstract class Event_Data_Tag_Base extends Data_Tag
{
    protected function register_controls()
    {
        $this->add_control(
            'event_source',
            [
                'label'   => __('Event Source', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::SELECT,
                'default' => 'current',
                'options' => [
                    'current'  => __('Current Event (template context)', 'Event-Tickets-for-Elementor'),
                    'specific' => __('Specific Event (select below)', 'Event-Tickets-for-Elementor'),
                ],
            ]
        );

        $options = Event_Selector::get_event_options(Event_Selector::DEFAULT_EDITOR_LIMIT);

        $this->add_control(
            'event_id',
            [
                'label'     => __('Event', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::SELECT2,
                'options'   => $options,
                'default'   => '',
                'condition' => [
                    'event_source' => 'specific',
                ],
            ]
        );

        $this->add_control(
            'event_id_fallback',
            [
                'label'       => __('Event ID (fallback)', 'Event-Tickets-for-Elementor'),
                'type'        => Controls_Manager::NUMBER,
                'description' => __('Use when the dropdown cannot list your event (large sites).', 'Event-Tickets-for-Elementor'),
                'min'         => 0,
                'default'     => 0,
                'condition'   => [
                    'event_source' => 'specific',
                ],
            ]
        );
    }

    protected function get_event_id(): int
    {
        $source = (string) $this->get_settings('event_source');
        if ('specific' === $source) {
            $event_id = absint($this->get_settings('event_id'));
            if (! $event_id) {
                $event_id = absint($this->get_settings('event_id_fallback'));
            }

            if ($event_id) {
                $post = get_post($event_id);
                if ($post && CPT_Events::POST_TYPE === $post->post_type && ! \EventTicketsElementor\Event_Recurrence::is_series_parent((int) $post->ID)) {
                    return (int) $event_id;
                }
            }
        }

        $post = get_post();
        if ($post && CPT_Events::POST_TYPE === $post->post_type && ! \EventTicketsElementor\Event_Recurrence::is_series_parent((int) $post->ID)) {
            return (int) $post->ID;
        }

        $queried_id = get_queried_object_id();
        if ($queried_id) {
            $qpost = get_post($queried_id);
            if ($qpost && CPT_Events::POST_TYPE === $qpost->post_type && ! \EventTicketsElementor\Event_Recurrence::is_series_parent((int) $qpost->ID)) {
                return (int) $qpost->ID;
            }
        }

        return 0;
    }
}
