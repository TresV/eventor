<?php

namespace EventTicketsElementor\Elementor_Dynamic_Tags;

use Elementor\Modules\DynamicTags\Module;

if (! defined('ABSPATH')) {
    exit;
}

class Event_Description_Tag extends Event_Tag_Base
{
    protected function register_controls()
    {
        parent::register_controls();
    }

    public function get_name()
    {
        return 'evt_event_description';
    }

    public function get_title()
    {
        return __('Event Description', 'Event-Tickets-for-Elementor');
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

        $content = get_post_field('post_content', $event_id);
        if (! $content) {
            return '';
        }

        return wp_kses_post(apply_filters('the_content', (string) $content));
    }

    public function render()
    {
        $value = $this->get_value();
        if ('' === trim((string) $value)) {
            return;
        }

        // Description can include basic HTML formatting.
        echo wp_kses_post((string) $value);
    }
}
