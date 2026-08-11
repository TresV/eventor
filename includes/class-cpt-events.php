<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/event-admin/class-event-ticket-stats.php';
require_once __DIR__ . '/event-admin/class-event-editor-sections.php';
require_once __DIR__ . '/event-admin/class-event-editor-assets.php';
require_once __DIR__ . '/event-admin/class-event-meta-boxes.php';

/**
 * Custom post type for Events.
 */
class CPT_Events
{

    const POST_TYPE = 'evt_event';
    public const CAP_EDIT_POST = 'edit_evt_event';
    public const CAP_READ_POST = 'read_evt_event';
    public const CAP_DELETE_POST = 'delete_evt_event';
    public const CAP_EDIT_POSTS = 'edit_evt_events';
    public const CAP_EDIT_OTHERS = 'edit_others_evt_events';
    public const CAP_PUBLISH = 'publish_evt_events';
    public const CAP_READ_PRIVATE = 'read_private_evt_events';
    public const CAP_DELETE_POSTS = 'delete_evt_events';
    public const CAP_DELETE_PRIVATE = 'delete_private_evt_events';
    public const CAP_DELETE_PUBLISHED = 'delete_published_evt_events';
    public const CAP_DELETE_OTHERS = 'delete_others_evt_events';
    public const CAP_EDIT_PRIVATE = 'edit_private_evt_events';
    public const CAP_EDIT_PUBLISHED = 'edit_published_evt_events';

    private Event_Meta_Boxes $meta_boxes;
    private Event_Editor_Assets $assets;
    private Event_Ticket_Stats $stats;

    public function __construct()
    {
        $this->stats = new Event_Ticket_Stats();
        $this->meta_boxes = new Event_Meta_Boxes($this->stats);
        $this->assets = new Event_Editor_Assets();

        // Register early so Elementor/Elementor Pro can discover the CPT
        // (e.g. Theme Builder preview/settings dropdowns).
        add_action('init', [$this, 'register'], 0);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post', [$this, 'save_meta'], 10, 2);

        add_action('add_meta_boxes', [$this, 'add_stats_meta_box']);

        // Ensure "Featured image" UI is available for Events (for photo/grid widgets).
        add_action('after_setup_theme', [$this, 'enable_thumbnails']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    /**
     * Register the Events CPT.
     */
    public function register(): void
    {
        $labels = [
            'name'               => __('Events', 'Event-Tickets-for-Elementor'),
            'singular_name'      => __('Event', 'Event-Tickets-for-Elementor'),
            'menu_name'          => __('Events', 'Event-Tickets-for-Elementor'),
            'name_admin_bar'     => __('Event', 'Event-Tickets-for-Elementor'),
            'add_new'            => __('Add New', 'Event-Tickets-for-Elementor'),
            'add_new_item'       => __('Add New Event', 'Event-Tickets-for-Elementor'),
            'edit_item'          => __('Edit Event', 'Event-Tickets-for-Elementor'),
            'new_item'           => __('New Event', 'Event-Tickets-for-Elementor'),
            'view_item'          => __('View Event', 'Event-Tickets-for-Elementor'),
            'search_items'       => __('Search Events', 'Event-Tickets-for-Elementor'),
            'not_found'          => __('No events found.', 'Event-Tickets-for-Elementor'),
            'not_found_in_trash' => __('No events found in Trash.', 'Event-Tickets-for-Elementor'),
            'all_items'          => __('Events', 'Event-Tickets-for-Elementor'),
        ];

        $args = [
            'labels'             => $labels,
            // Events must be publicly queryable so Elementor can preview "Single" templates
            // and so event permalinks work when clicking events in widgets.
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            // Keep it under our "Event Tickets" top-level menu, while still being discoverable
            // as a real CPT by other plugins (Elementor preview/settings, etc.).
            'show_in_menu'       => 'evt-tickets',
            // Keep REST enabled so builders/blocks can read the CPT when needed.
            'show_in_rest'       => true,
            'supports'           => ['title', 'editor', 'thumbnail'],
            // Events get their own capability type so that regular users with
            // post/publish caps cannot edit or create events. Administrators
            // and Editors receive these caps via Staff_Access_Manager::sync_*.
            'capability_type'    => ['evt_event', 'evt_events'],
            'capabilities'       => [
                'edit_post'              => self::CAP_EDIT_POST,
                'read_post'              => self::CAP_READ_POST,
                'delete_post'            => self::CAP_DELETE_POST,
                'edit_posts'             => self::CAP_EDIT_POSTS,
                'edit_others_posts'      => self::CAP_EDIT_OTHERS,
                'publish_posts'          => self::CAP_PUBLISH,
                'read_private_posts'     => self::CAP_READ_PRIVATE,
                'delete_posts'           => self::CAP_DELETE_POSTS,
                'delete_private_posts'   => self::CAP_DELETE_PRIVATE,
                'delete_published_posts' => self::CAP_DELETE_PUBLISHED,
                'delete_others_posts'    => self::CAP_DELETE_OTHERS,
                'edit_private_posts'     => self::CAP_EDIT_PRIVATE,
                'edit_published_posts'   => self::CAP_EDIT_PUBLISHED,
                'create_posts'           => self::CAP_EDIT_POSTS,
            ],
            'map_meta_cap'       => true,
            'has_archive'        => false,
            'exclude_from_search' => true,
            'rewrite'            => [
                'slug'       => 'events',
                'with_front' => false,
            ],
            'menu_position'      => null,
        ];

        register_post_type(self::POST_TYPE, $args);

        $this->register_meta();
    }

    /**
     * Register event post meta with centralized sanitization so every write path
     * (save handlers, REST, integrations) is covered consistently.
     */
    private function register_meta(): void
    {
        foreach ($this->meta_schema() as $key => $config) {
            register_post_meta(
                self::POST_TYPE,
                $key,
                [
                    'type'              => $config['type'],
                    'single'            => true,
                    'show_in_rest'      => false,
                    'sanitize_callback' => $config['sanitize'] ?? null,
                ]
            );
        }
    }

    /**
     * @return array<string,array{type:string,sanitize?:callable}>
     */
    private function meta_schema(): array
    {
        return [
            // Date/time.
            '_evt_event_start'                  => ['type' => 'string', 'sanitize' => 'sanitize_text_field'],
            '_evt_event_end'                    => ['type' => 'string', 'sanitize' => 'sanitize_text_field'],
            '_evt_event_start_ts'               => ['type' => 'integer', 'sanitize' => 'absint'],
            '_evt_event_end_ts'                 => ['type' => 'integer', 'sanitize' => 'absint'],
            // Location.
            '_evt_event_location'               => ['type' => 'string', 'sanitize' => 'sanitize_text_field'],
            '_evt_event_lat'                    => ['type' => 'string', 'sanitize' => 'sanitize_text_field'],
            '_evt_event_lng'                    => ['type' => 'string', 'sanitize' => 'sanitize_text_field'],
            '_evt_event_map_url'                => ['type' => 'string', 'sanitize' => 'esc_url_raw'],
            '_evt_event_geo_lat'                => ['type' => 'string', 'sanitize' => 'sanitize_text_field'],
            '_evt_event_geo_lng'                => ['type' => 'string', 'sanitize' => 'sanitize_text_field'],
            '_evt_event_geo_at'                 => ['type' => 'string', 'sanitize' => 'sanitize_text_field'],
            // Capacity / ticketing.
            '_evt_event_capacity'               => ['type' => 'integer', 'sanitize' => 'absint'],
            '_evt_event_capacity_mode'          => ['type' => 'string', 'sanitize' => 'sanitize_key'],
            '_evt_event_ticketing_mode'         => ['type' => 'string', 'sanitize' => 'sanitize_key'],
            '_evt_event_limit_one_email'        => ['type' => 'boolean', 'sanitize' => $this->bool_cb()],
            '_evt_event_normalize_email'        => ['type' => 'boolean', 'sanitize' => $this->bool_cb()],
            '_evt_event_max_tickets_per_email'  => ['type' => 'integer', 'sanitize' => 'absint'],
            '_evt_event_timeslots'              => ['type' => 'array'],
            // Taxonomy links.
            '_evt_event_venue_id'               => ['type' => 'integer', 'sanitize' => 'absint'],
            '_evt_event_organizer_id'           => ['type' => 'integer', 'sanitize' => 'absint'],
            // Discovery.
            '_evt_event_cost'                   => ['type' => 'string', 'sanitize' => 'sanitize_text_field'],
            '_evt_event_cost_enabled'           => ['type' => 'boolean', 'sanitize' => $this->bool_cb()],
            '_evt_event_paid_enabled'           => ['type' => 'boolean', 'sanitize' => $this->bool_cb()],
            '_evt_event_country'                => ['type' => 'string', 'sanitize' => 'sanitize_text_field'],
            '_evt_event_city'                   => ['type' => 'string', 'sanitize' => 'sanitize_text_field'],
            // Virtual.
            '_evt_event_virtual_enabled'        => ['type' => 'boolean', 'sanitize' => $this->bool_cb()],
            '_evt_event_meeting_url'            => ['type' => 'string', 'sanitize' => 'esc_url_raw'],
            '_evt_event_livestream_url'         => ['type' => 'string', 'sanitize' => 'esc_url_raw'],
            // Status.
            '_evt_event_cancelled'              => ['type' => 'boolean', 'sanitize' => $this->bool_cb()],
            '_evt_event_postponed'              => ['type' => 'boolean', 'sanitize' => $this->bool_cb()],
            // PDF / code pattern / gallery.
            '_evt_event_pdf_preset'             => ['type' => 'string', 'sanitize' => 'sanitize_key'],
            '_evt_event_ticket_code_pattern'    => ['type' => 'string', 'sanitize' => 'sanitize_text_field'],
            '_evt_event_gallery_ids'            => ['type' => 'array'],
        ];
    }

    private function bool_cb(): callable
    {
        return static function ($value) {
            return $value ? 1 : 0;
        };
    }

    public function enable_thumbnails(): void
    {
        $this->meta_boxes->enable_thumbnails();
    }

    /**
     * Add meta box for event details.
     */
    public function add_meta_boxes(): void
    {
        $this->meta_boxes->add_meta_boxes();
    }

    /**
     * Add stats meta box showing linked tickets.
     */
    public function add_stats_meta_box(): void
    {
        $this->meta_boxes->add_stats_meta_box();
    }

    /**
     * Render meta box fields.
     *
     * @param \WP_Post $post
     */
    public function render_meta_box(\WP_Post $post): void
    {
        $this->meta_boxes->render_meta_box($post);
    }

    public function enqueue_admin_assets(string $hook): void
    {
        $this->assets->enqueue_admin_assets($hook);
    }

    /**
     * Save event meta.
     */
    public function save_meta(int $post_id, \WP_Post $post): void
    {
        $this->meta_boxes->save_meta($post_id, $post);
    }

    /**
     * Render ticket stats for this event.
     *
     * @param \WP_Post $post
     */
    public function render_stats_meta_box(\WP_Post $post): void
    {
        $this->meta_boxes->render_stats_meta_box($post);
    }
}
