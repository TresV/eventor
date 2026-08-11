<?php

namespace EventTicketsElementor\Elementor_Dynamic_Tags;

use Elementor\Modules\DynamicTags\Module;

if (! defined('ABSPATH')) {
    exit;
}

class Event_Capacity_Tag extends Event_Tag_Base
{
    protected function register_controls()
    {
        parent::register_controls();
    }

    public function get_name()
    {
        return 'evt_event_capacity';
    }

    public function get_title()
    {
        return __('Event Capacity', 'Event-Tickets-for-Elementor');
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

        $value = (string) get_post_meta($event_id, \EventTicketsElementor\Event_Capacity::META_KEY, true);
        $value = trim($value);
        if ('' === $value) {
            return (string) __('Unlimited', 'Event-Tickets-for-Elementor');
        }

        return $value;
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
