<?php

namespace EventTicketsElementor\Elementor_Dynamic_Tags;

use Elementor\Controls_Manager;
use Elementor\Modules\DynamicTags\Module;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Dynamic tag: Event Ticket Link (appends ?evt_event_id=ID).
 */
class Event_Ticket_Link_Tag extends Event_Tag_Base
{
    protected function register_controls()
    {
        parent::register_controls();

        $this->add_control(
            'base_url',
            [
                'label'       => __('Ticket Page URL', 'Event-Tickets-for-Elementor'),
                'type'        => Controls_Manager::TEXT,
                'placeholder' => site_url('/tickets/'),
                'description' => __('If empty, uses the current page URL.', 'Event-Tickets-for-Elementor'),
            ]
        );

        $this->add_control(
            'param_name',
            [
                'label'       => __('URL Param', 'Event-Tickets-for-Elementor'),
                'type'        => Controls_Manager::TEXT,
                'default'     => 'evt_event_id',
                'placeholder' => 'evt_event_id',
            ]
        );

        $this->add_control(
            'include_param',
            [
                'label'        => __('Include Event ID Param', 'Event-Tickets-for-Elementor'),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => __('Yes', 'Event-Tickets-for-Elementor'),
                'label_off'    => __('No', 'Event-Tickets-for-Elementor'),
                'return_value' => 'yes',
                'default'      => 'yes',
                'description'  => __('Turn off to link to the event page without a query string (use with inline ticket box).', 'Event-Tickets-for-Elementor'),
            ]
        );

        $this->add_control(
            'anchor',
            [
                'label'       => __('Anchor (optional)', 'Event-Tickets-for-Elementor'),
                'type'        => Controls_Manager::TEXT,
                'placeholder' => 'evt-ticket-box',
                'description' => __('Appends a #anchor to jump to the inline ticket box.', 'Event-Tickets-for-Elementor'),
            ]
        );

        $this->add_control(
            'open_in_new_tab',
            [
                'label'        => __('Open in new tab', 'Event-Tickets-for-Elementor'),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => __('Yes', 'Event-Tickets-for-Elementor'),
                'label_off'    => __('No', 'Event-Tickets-for-Elementor'),
                'return_value' => 'yes',
                'default'      => '',
            ]
        );
    }

    public function get_name()
    {
        return 'evt_event_ticket_link';
    }

    public function get_title()
    {
        return __('Event Ticket Link', 'Event-Tickets-for-Elementor');
    }

    public function get_group()
    {
        return 'evt-tickets';
    }

    public function get_categories()
    {
        return [Module::URL_CATEGORY];
    }

    public function get_value(array $options = [])
    {
        $event_id = $this->get_event_id();
        if (! $event_id) {
            return '';
        }

        $base = trim((string) $this->get_settings('base_url'));
        if ('' === $base) {
            $current_id = get_queried_object_id();
            if ($current_id) {
                $base = (string) get_permalink($current_id);
            }
        }

        if ('' === $base) {
            $base = (string) get_permalink($event_id);
        }

        if ('' === $base) {
            return '';
        }

        $include_param = ('yes' === (string) $this->get_settings('include_param'));
        if ($include_param) {
            $param = sanitize_key((string) $this->get_settings('param_name'));
            if ('' === $param) {
                $param = 'evt_event_id';
            }
            $url = (string) add_query_arg($param, $event_id, $base);
        } else {
            $url = $base;
        }

        $anchor = trim((string) $this->get_settings('anchor'));
        if ($anchor !== '') {
            $anchor = sanitize_title($anchor);
            if ($anchor !== '') {
                $url .= '#' . $anchor;
            }
        }

        $is_external = ('yes' === (string) $this->get_settings('open_in_new_tab'));

        return [
            'url'         => $url,
            'is_external' => $is_external,
        ];
    }

    public function render()
    {
        $value = $this->get_value();
        $url = '';
        if (is_array($value)) {
            $url = (string) ($value['url'] ?? '');
        } else {
            $url = (string) $value;
        }
        if ('' === $url) {
            return;
        }

        echo esc_url($url);
    }
}
