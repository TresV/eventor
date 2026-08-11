<?php

namespace EventTicketsElementor\Elementor_Dynamic_Tags;

use Elementor\Modules\DynamicTags\Module;

if (! defined('ABSPATH')) {
    exit;
}

class Event_Title_Tag extends Event_Tag_Base
{
    protected function register_controls()
    {
        parent::register_controls();
    }

    public function get_name()
    {
        return 'evt_event_title';
    }

    public function get_title()
    {
        return __('Event Title', 'Event-Tickets-for-Elementor');
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

        $title = get_post_field('post_title', $event_id);
        if (! is_string($title) || '' === $title) {
            $title = (string) get_the_title($event_id);
        }

        return (string) $title;
    }

    public function render()
    {
        $value = $this->get_value();
        if ('' === (string) $value) {
            return;
        }

        echo esc_html((string) $value);
    }
}
