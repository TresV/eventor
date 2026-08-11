<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Registers discovery-related taxonomies for Events.
 */
class Event_Taxonomies
{
    public const TAX_CATEGORY  = 'evt_event_category';
    public const TAX_TAG       = 'evt_event_tag';
    public const TAX_VENUE     = 'evt_event_venue';
    public const TAX_ORGANIZER = 'evt_event_organizer';

    public function __construct()
    {
        add_action('init', [$this, 'register']);
    }

    public function register(): void
    {
        $this->register_category();
        $this->register_tag();
        $this->register_venue();
        $this->register_organizer();
    }

    private function register_category(): void
    {
        register_taxonomy(
            self::TAX_CATEGORY,
            [CPT_Events::POST_TYPE],
            [
                'labels' => [
                    'name'          => __('Event Categories', 'Event-Tickets-for-Elementor'),
                    'singular_name' => __('Event Category', 'Event-Tickets-for-Elementor'),
                ],
                'public'            => false,
                'show_ui'           => true,
                'hierarchical'      => true,
                'show_admin_column' => true,
                'rewrite'           => false,
            ]
        );
    }

    private function register_tag(): void
    {
        register_taxonomy(
            self::TAX_TAG,
            [CPT_Events::POST_TYPE],
            [
                'labels' => [
                    'name'          => __('Event Tags', 'Event-Tickets-for-Elementor'),
                    'singular_name' => __('Event Tag', 'Event-Tickets-for-Elementor'),
                ],
                'public'            => false,
                'show_ui'           => true,
                'hierarchical'      => false,
                'show_admin_column' => true,
                'rewrite'           => false,
            ]
        );
    }

    private function register_venue(): void
    {
        register_taxonomy(
            self::TAX_VENUE,
            [CPT_Events::POST_TYPE],
            [
                'labels' => [
                    'name'          => __('Venues', 'Event-Tickets-for-Elementor'),
                    'singular_name' => __('Venue', 'Event-Tickets-for-Elementor'),
                ],
                'public'            => false,
                'show_ui'           => true,
                'hierarchical'      => false,
                'show_admin_column' => true,
                'rewrite'           => false,
            ]
        );
    }

    private function register_organizer(): void
    {
        register_taxonomy(
            self::TAX_ORGANIZER,
            [CPT_Events::POST_TYPE],
            [
                'labels' => [
                    'name'          => __('Organizers', 'Event-Tickets-for-Elementor'),
                    'singular_name' => __('Organizer', 'Event-Tickets-for-Elementor'),
                ],
                'public'            => false,
                'show_ui'           => true,
                'hierarchical'      => false,
                'show_admin_column' => true,
                'rewrite'           => false,
            ]
        );
    }
}
