<?php

namespace EventTicketsElementor\Elementor_Dynamic_Tags;

use Elementor\Modules\DynamicTags\Module;
use EventTicketsElementor\Event_Gallery_Meta;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Event Gallery dynamic tag (for Elementor Pro gallery/carousel controls).
 */
class Event_Gallery_Tag extends Event_Data_Tag_Base
{
    protected function register_controls()
    {
        parent::register_controls();
    }

    public function get_name()
    {
        return 'evt_event_gallery';
    }

    public function get_title()
    {
        return __('Event Gallery', 'Event-Tickets-for-Elementor');
    }

    public function get_group()
    {
        return 'evt-tickets';
    }

    public function get_categories()
    {
        return [Module::GALLERY_CATEGORY];
    }

    public function get_value(array $options = [])
    {
        $event_id = $this->get_event_id();
        if (! $event_id) {
            return [];
        }

        $ids = get_post_meta($event_id, Event_Gallery_Meta::META_KEY, true);
        if (! is_array($ids)) {
            return [];
        }

        $items = [];
        foreach ($ids as $id) {
            $id = absint($id);
            if (! $id) {
                continue;
            }

            $src = wp_get_attachment_image_src($id, 'full');
            if (! $src || empty($src[0])) {
                continue;
            }

            $items[] = [
                'id'  => $id,
                'url' => (string) $src[0],
            ];
        }

        return $items;
    }
}

