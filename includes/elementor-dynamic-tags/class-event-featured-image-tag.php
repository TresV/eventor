<?php

namespace EventTicketsElementor\Elementor_Dynamic_Tags;

use Elementor\Modules\DynamicTags\Module;

if (! defined('ABSPATH')) {
    exit;
}

class Event_Featured_Image_Tag extends Event_Data_Tag_Base
{
    protected function register_controls()
    {
        parent::register_controls();
    }

    public function get_name()
    {
        return 'evt_event_featured_image';
    }

    public function get_title()
    {
        return __('Event Featured Image', 'Event-Tickets-for-Elementor');
    }

    public function get_group()
    {
        return 'evt-tickets';
    }

    public function get_categories()
    {
        return [Module::IMAGE_CATEGORY];
    }

    public function get_value(array $options = [])
    {
        $event_id = $this->get_event_id();
        if (! $event_id) {
            return [
                'id'  => 0,
                'url' => '',
            ];
        }

        $image_id = get_post_thumbnail_id($event_id);
        if (! $image_id) {
            return [
                'id'  => 0,
                'url' => '',
            ];
        }

        $image = wp_get_attachment_image_src($image_id, 'full');
        if (! $image) {
            return [
                'id'  => 0,
                'url' => '',
            ];
        }

        return [
            'id'  => $image_id,
            'url' => $image[0],
        ];
    }
}
