<?php

namespace EventTicketsElementor;

use Elementor\Core\DynamicTags\Manager;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Registers Elementor Dynamic Tags for Event fields.
 */
class Elementor_Dynamic_Tags_Loader
{
    public function __construct()
    {
        add_action('elementor/dynamic_tags/register_tags', [$this, 'register_tags']);
    }

    public function register_tags(Manager $dynamic_tags): void
    {
        // Elementor is active if this hook fires, but keep this defensive.
        if (! defined('EVT_TICKETS_PLUGIN_DIR')) {
            return;
        }

        // Load tag classes lazily to avoid fatal errors when Elementor isn't installed/active.
        require_once EVT_TICKETS_PLUGIN_DIR . 'includes/elementor-dynamic-tags/class-event-tag-base.php';
        require_once EVT_TICKETS_PLUGIN_DIR . 'includes/elementor-dynamic-tags/class-event-data-tag-base.php';
        require_once EVT_TICKETS_PLUGIN_DIR . 'includes/elementor-dynamic-tags/class-event-title-tag.php';
        require_once EVT_TICKETS_PLUGIN_DIR . 'includes/elementor-dynamic-tags/class-event-description-tag.php';
        require_once EVT_TICKETS_PLUGIN_DIR . 'includes/elementor-dynamic-tags/class-event-date-tag.php';
        require_once EVT_TICKETS_PLUGIN_DIR . 'includes/elementor-dynamic-tags/class-event-venue-tag.php';
        require_once EVT_TICKETS_PLUGIN_DIR . 'includes/elementor-dynamic-tags/class-event-featured-image-tag.php';
        require_once EVT_TICKETS_PLUGIN_DIR . 'includes/elementor-dynamic-tags/class-event-gallery-tag.php';
        require_once EVT_TICKETS_PLUGIN_DIR . 'includes/elementor-dynamic-tags/class-event-capacity-tag.php';
        require_once EVT_TICKETS_PLUGIN_DIR . 'includes/elementor-dynamic-tags/class-event-status-tag.php';
        require_once EVT_TICKETS_PLUGIN_DIR . 'includes/elementor-dynamic-tags/class-event-ticket-link-tag.php';

        $dynamic_tags->register_group(
            'evt-tickets',
            [
                'title' => __('Event Tickets', 'Event-Tickets-for-Elementor'),
            ]
        );

        $dynamic_tags->register_tag(new \EventTicketsElementor\Elementor_Dynamic_Tags\Event_Title_Tag());
        $dynamic_tags->register_tag(new \EventTicketsElementor\Elementor_Dynamic_Tags\Event_Description_Tag());
        $dynamic_tags->register_tag(new \EventTicketsElementor\Elementor_Dynamic_Tags\Event_Date_Tag('start'));
        $dynamic_tags->register_tag(new \EventTicketsElementor\Elementor_Dynamic_Tags\Event_Date_Tag('end'));
        $dynamic_tags->register_tag(new \EventTicketsElementor\Elementor_Dynamic_Tags\Event_Venue_Tag());
        $dynamic_tags->register_tag(new \EventTicketsElementor\Elementor_Dynamic_Tags\Event_Featured_Image_Tag());
        $dynamic_tags->register_tag(new \EventTicketsElementor\Elementor_Dynamic_Tags\Event_Gallery_Tag());
        $dynamic_tags->register_tag(new \EventTicketsElementor\Elementor_Dynamic_Tags\Event_Capacity_Tag());
        $dynamic_tags->register_tag(new \EventTicketsElementor\Elementor_Dynamic_Tags\Event_Status_Tag());
        $dynamic_tags->register_tag(new \EventTicketsElementor\Elementor_Dynamic_Tags\Event_Ticket_Link_Tag());
    }
}
