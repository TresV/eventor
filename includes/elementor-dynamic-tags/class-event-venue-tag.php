<?php

namespace EventTicketsElementor\Elementor_Dynamic_Tags;

use Elementor\Modules\DynamicTags\Module;

if (! defined('ABSPATH')) {
    exit;
}

class Event_Venue_Tag extends Event_Tag_Base
{
    protected function register_controls()
    {
        parent::register_controls();
    }

    public function get_name()
    {
        return 'evt_event_venue';
    }

    public function get_title()
    {
        return __('Event Venue', 'Event-Tickets-for-Elementor');
    }

    public function get_group()
    {
        return 'evt-tickets';
    }

    public function get_categories()
    {
        return [Module::TEXT_CATEGORY];
    }

    public function get_value(array $options = [])
    {
        $event_id = $this->get_event_id();
        if (! $event_id) {
            return '';
        }

        $venue = (string) get_post_meta($event_id, '_evt_event_location', true);
        if ('' === trim($venue)) {
            return '';
        }

        return $venue;
    }

    public function render()
    {
        $value = $this->get_value();
        if ('' === trim((string) $value)) {
            return;
        }

        echo esc_html((string) $value);
    }
}
