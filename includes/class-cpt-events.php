<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

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

    public function __construct()
    {
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
        add_theme_support('post-thumbnails', [self::POST_TYPE]);
        add_post_type_support(self::POST_TYPE, 'thumbnail');
    }

    /**
     * Add meta box for event details.
     */
    public function add_meta_boxes(): void
    {
        add_meta_box(
            'evt_event_details',
            __('Event Details', 'Event-Tickets-for-Elementor'),
            [$this, 'render_meta_box'],
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    /**
     * Add stats meta box showing linked tickets.
     */
    public function add_stats_meta_box(): void
    {
        add_meta_box(
            'evt_event_ticket_stats',
            __('Tickets', 'Event-Tickets-for-Elementor'),
            [$this, 'render_stats_meta_box'],
            self::POST_TYPE,
            'side',
            'default'
        );
    }

    /**
     * Render meta box fields.
     *
     * @param \WP_Post $post
     */
    public function render_meta_box(\WP_Post $post): void
    {
        wp_nonce_field('evt_save_event_meta', 'evt_event_meta_nonce');

        $start    = get_post_meta($post->ID, '_evt_event_start', true);
        $end      = get_post_meta($post->ID, '_evt_event_end', true);
        $location = get_post_meta($post->ID, '_evt_event_location', true);

        $start_input = $this->format_for_datetime_local((string) $start);
        $end_input   = $this->format_for_datetime_local((string) $end);
?>
        <div class="evt-event-editor">
            <aside class="evt-event-editor__sidebar">
                <div class="evt-event-editor__sidebar-card">
                    <h3><?php esc_html_e('Event Setup', 'Event-Tickets-for-Elementor'); ?></h3>
                    <p class="evt-event-editor__sidebar-copy"><?php esc_html_e('Use this workspace to move through the event setup in a clear order. High-frequency sections stay open; advanced sections are collapsed.', 'Event-Tickets-for-Elementor'); ?></p>
                </div>
                <nav class="evt-event-editor__nav" aria-label="<?php esc_attr_e('Event setup sections', 'Event-Tickets-for-Elementor'); ?>">
                    <?php foreach ($this->editor_sections() as $section) : ?>
                        <a class="evt-event-editor__nav-item" href="#<?php echo esc_attr($section['id']); ?>">
                            <strong><?php echo esc_html($section['title']); ?></strong>
                            <span><?php echo esc_html($section['description']); ?></span>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </aside>

            <div class="evt-event-editor__content">
                <?php
                $this->render_section_card(
                    'evt-event-basics',
                    __('Basics', 'Event-Tickets-for-Elementor'),
                    __('Review the core event content, publishing context, and taxonomy assignments from one place.', 'Event-Tickets-for-Elementor'),
                    function () use ($post) {
                        $this->render_basics_section($post);
                    },
                    true
                );

                $this->render_section_card(
                    'evt-event-schedule',
                    __('Schedule', 'Event-Tickets-for-Elementor'),
                    __('Set the event time window and optionally split the event into multiple bookable timeslots.', 'Event-Tickets-for-Elementor'),
                    function () use ($start_input, $end_input, $post) {
                ?>
                    <p class="description evt-event-details__legend">
                        <strong><?php esc_html_e('Legend:', 'Event-Tickets-for-Elementor'); ?></strong>
                        <span><span class="evt-required-marker">*</span> <?php esc_html_e('required', 'Event-Tickets-for-Elementor'); ?></span>
                        <span><?php esc_html_e('(optional) fields are clearly marked', 'Event-Tickets-for-Elementor'); ?></span>
                    </p>
                    <div class="evt-event-grid evt-event-grid--2">
                        <p class="evt-event-field">
                            <label for="evt_event_start">
                                <strong><?php esc_html_e('Start (date & time)', 'Event-Tickets-for-Elementor'); ?> <span class="evt-required-marker">*</span></strong>
                            </label><br />
                            <input type="text" id="evt_event_start" name="evt_event_start" class="regular-text evt-datetime-field" value="<?php echo esc_attr($start_input); ?>" required />
                        </p>
                        <p class="evt-event-field">
                            <label for="evt_event_end">
                                <strong><?php esc_html_e('End (date & time)', 'Event-Tickets-for-Elementor'); ?> <span class="evt-required-marker">*</span></strong>
                            </label><br />
                            <input type="text" id="evt_event_end" name="evt_event_end" class="regular-text evt-datetime-field" value="<?php echo esc_attr($end_input); ?>" required />
                        </p>
                    </div>
                    <div class="evt-event-inline-note">
                        <?php esc_html_e('Use a real end date and time so calendar feeds, “over” status, and ticket rules behave correctly.', 'Event-Tickets-for-Elementor'); ?>
                    </div>
                <?php
                        do_action('evt_tickets_event_section_schedule', $post);
                    },
                    true
                );

                $this->render_section_card(
                    'evt-event-recurrence',
                    __('Recurrence', 'Event-Tickets-for-Elementor'),
                    __('Repeat this event as a generated series of real occurrences.', 'Event-Tickets-for-Elementor'),
                    function () use ($post) {
                        do_action('evt_tickets_event_section_recurrence', $post);
                    },
                    false
                );

                $this->render_section_card(
                    'evt-event-location',
                    __('Location', 'Event-Tickets-for-Elementor'),
                    __('Set the venue-facing event location, map data, and discovery metadata used in filters and frontend views.', 'Event-Tickets-for-Elementor'),
                    function () use ($location, $post) {
                ?>
                    <p class="evt-event-field">
                        <label for="evt_event_location">
                            <strong><?php esc_html_e('Location', 'Event-Tickets-for-Elementor'); ?> <span class="evt-required-marker">*</span></strong>
                        </label><br />
                        <input type="text" id="evt_event_location" name="evt_event_location" class="regular-text" value="<?php echo esc_attr($location); ?>" placeholder="<?php esc_attr_e('e.g. City, venue name', 'Event-Tickets-for-Elementor'); ?>" required />
                    </p>
                <?php
                        do_action('evt_tickets_event_section_location', $post);
                    },
                    true
                );

                $this->render_section_card(
                    'evt-event-attendance',
                    __('Attendance & Ticketing', 'Event-Tickets-for-Elementor'),
                    __('Control capacity, ticket limits, pricing, and booking rules that affect how tickets are issued.', 'Event-Tickets-for-Elementor'),
                    function () use ($post) {
                        do_action('evt_tickets_event_section_attendance', $post);
                    },
                    true
                );

                $this->render_section_card(
                    'evt-event-delivery',
                    __('Delivery', 'Event-Tickets-for-Elementor'),
                    __('Configure virtual or hybrid access details that attendees may need after registration.', 'Event-Tickets-for-Elementor'),
                    function () use ($post) {
                        do_action('evt_tickets_event_section_delivery', $post);
                    },
                    false
                );

                $this->render_section_card(
                    'evt-event-status-visibility',
                    __('Status & Visibility', 'Event-Tickets-for-Elementor'),
                    __('Manage cancelled/postponed states and optional badge overrides used across widgets and dynamic tags.', 'Event-Tickets-for-Elementor'),
                    function () use ($post) {
                        do_action('evt_tickets_event_section_status_visibility', $post);
                    },
                    false
                );

                $this->render_section_card(
                    'evt-event-ticket-output',
                    __('Ticket Output', 'Event-Tickets-for-Elementor'),
                    __('Choose how event-specific ticket PDFs and ticket codes should be generated.', 'Event-Tickets-for-Elementor'),
                    function () use ($post) {
                        do_action('evt_tickets_event_section_ticket_output', $post);
                    },
                    false
                );

                $this->render_section_card(
                    'evt-event-related',
                    __('Related Records', 'Event-Tickets-for-Elementor'),
                    __('Review ticket counts, exports, and the operational links associated with this event.', 'Event-Tickets-for-Elementor'),
                    function () use ($post) {
                        $this->render_related_section($post);
                    },
                    false
                );
                ?>
            </div>
        </div>
    <?php
    }

    /**
     * @return array<int, array{id:string,title:string,description:string}>
     */
    private function editor_sections(): array
    {
        return [
            [
                'id' => 'evt-event-basics',
                'title' => __('Basics', 'Event-Tickets-for-Elementor'),
                'description' => __('Content overview, status snapshot, and taxonomy summary.', 'Event-Tickets-for-Elementor'),
            ],
            [
                'id' => 'evt-event-schedule',
                'title' => __('Schedule', 'Event-Tickets-for-Elementor'),
                'description' => __('Dates, times, and optional timeslots.', 'Event-Tickets-for-Elementor'),
            ],
            [
                'id' => 'evt-event-recurrence',
                'title' => __('Recurrence', 'Event-Tickets-for-Elementor'),
                'description' => __('Series rules and generated occurrences.', 'Event-Tickets-for-Elementor'),
            ],
            [
                'id' => 'evt-event-location',
                'title' => __('Location', 'Event-Tickets-for-Elementor'),
                'description' => __('Venue-facing location, maps, and discovery fields.', 'Event-Tickets-for-Elementor'),
            ],
            [
                'id' => 'evt-event-attendance',
                'title' => __('Attendance & Ticketing', 'Event-Tickets-for-Elementor'),
                'description' => __('Capacity, pricing, limits, and ticket issuance rules.', 'Event-Tickets-for-Elementor'),
            ],
            [
                'id' => 'evt-event-delivery',
                'title' => __('Delivery', 'Event-Tickets-for-Elementor'),
                'description' => __('Virtual or hybrid attendee access details.', 'Event-Tickets-for-Elementor'),
            ],
            [
                'id' => 'evt-event-status-visibility',
                'title' => __('Status & Visibility', 'Event-Tickets-for-Elementor'),
                'description' => __('Event states and badge presentation overrides.', 'Event-Tickets-for-Elementor'),
            ],
            [
                'id' => 'evt-event-ticket-output',
                'title' => __('Ticket Output', 'Event-Tickets-for-Elementor'),
                'description' => __('PDF template and ticket code configuration.', 'Event-Tickets-for-Elementor'),
            ],
            [
                'id' => 'evt-event-related',
                'title' => __('Related Records', 'Event-Tickets-for-Elementor'),
                'description' => __('Ticket stats and operational shortcuts.', 'Event-Tickets-for-Elementor'),
            ],
        ];
    }

    private function render_section_card(string $id, string $title, string $description, callable $callback, bool $open): void
    {
    ?>
        <details id="<?php echo esc_attr($id); ?>" class="evt-event-editor__section" <?php echo $open ? 'open' : ''; ?>>
            <summary class="evt-event-editor__section-summary">
                <span class="evt-event-editor__section-title-wrap">
                    <strong><?php echo esc_html($title); ?></strong>
                    <span><?php echo esc_html($description); ?></span>
                </span>
            </summary>
            <div class="evt-event-editor__section-body">
                <?php $callback(); ?>
            </div>
        </details>
    <?php
    }

    private function render_basics_section(\WP_Post $post): void
    {
        $status = $this->get_status_snapshot($post->ID);
        $featured_image = has_post_thumbnail($post);
        $editor_mode = $this->is_block_editor_active() ? __('Gutenberg', 'Event-Tickets-for-Elementor') : __('Classic Editor', 'Event-Tickets-for-Elementor');
        $taxonomy_groups = $this->get_taxonomy_summary($post->ID);
    ?>
        <div class="evt-event-overview">
            <div class="evt-event-overview__grid">
                <div class="evt-event-overview__item">
                    <span class="evt-event-overview__label"><?php esc_html_e('Editor Mode', 'Event-Tickets-for-Elementor'); ?></span>
                    <strong><?php echo esc_html($editor_mode); ?></strong>
                </div>
                <div class="evt-event-overview__item">
                    <span class="evt-event-overview__label"><?php esc_html_e('Current Status', 'Event-Tickets-for-Elementor'); ?></span>
                    <strong><?php echo esc_html($status); ?></strong>
                </div>
                <div class="evt-event-overview__item">
                    <span class="evt-event-overview__label"><?php esc_html_e('Featured Image', 'Event-Tickets-for-Elementor'); ?></span>
                    <strong><?php echo esc_html($featured_image ? __('Assigned', 'Event-Tickets-for-Elementor') : __('Not assigned', 'Event-Tickets-for-Elementor')); ?></strong>
                </div>
                <div class="evt-event-overview__item">
                    <span class="evt-event-overview__label"><?php esc_html_e('Event Content', 'Event-Tickets-for-Elementor'); ?></span>
                    <strong><?php echo esc_html('' !== trim((string) $post->post_content) ? __('Description added', 'Event-Tickets-for-Elementor') : __('Description is empty', 'Event-Tickets-for-Elementor')); ?></strong>
                </div>
            </div>

            <div class="evt-event-inline-note">
                <?php
                if ($this->is_block_editor_active()) {
                    esc_html_e('Use the Gutenberg document sidebar for taxonomy editing. This summary keeps your current assignments visible while you work.', 'Event-Tickets-for-Elementor');
                } else {
                    esc_html_e('Use the native side panels for Publish, Featured Image, and taxonomy editing. This summary keeps the current assignments visible in the main workspace.', 'Event-Tickets-for-Elementor');
                }
                ?>
            </div>
        </div>

        <div class="evt-event-taxonomy-summary">
            <?php foreach ($taxonomy_groups as $group) : ?>
                <div class="evt-event-taxonomy-summary__card">
                    <h4><?php echo esc_html($group['label']); ?></h4>
                    <?php if (! empty($group['terms'])) : ?>
                        <div class="evt-event-taxonomy-summary__chips">
                            <?php foreach ($group['terms'] as $term_name) : ?>
                                <span class="evt-event-taxonomy-summary__chip"><?php echo esc_html($term_name); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?>
                        <p class="description"><?php echo esc_html($group['empty']); ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php
        do_action('evt_tickets_event_section_basics', $post);
    }

    private function render_related_section(\WP_Post $post): void
    {
        $counts = $this->get_ticket_counts($post->ID);
        $tickets_url = add_query_arg(
            [
                'post_type'    => CPT_Tickets::POST_TYPE,
                'evt_event_id' => $post->ID,
            ],
            admin_url('edit.php')
        );
        $export_url = wp_nonce_url(
            add_query_arg(
                [
                    'action'   => 'evt_export_tickets',
                    'event_id' => $post->ID,
                ],
                admin_url('admin-post.php')
            ),
            'evt_export_tickets_' . $post->ID
        );
    ?>
        <div class="evt-event-overview__grid">
            <div class="evt-event-overview__item">
                <span class="evt-event-overview__label"><?php esc_html_e('Total Tickets', 'Event-Tickets-for-Elementor'); ?></span>
                <strong><?php echo esc_html((string) $counts['total']); ?></strong>
            </div>
            <div class="evt-event-overview__item">
                <span class="evt-event-overview__label"><?php esc_html_e('Pending', 'Event-Tickets-for-Elementor'); ?></span>
                <strong><?php echo esc_html((string) $counts['pending']); ?></strong>
            </div>
            <div class="evt-event-overview__item">
                <span class="evt-event-overview__label"><?php esc_html_e('Checked In', 'Event-Tickets-for-Elementor'); ?></span>
                <strong><?php echo esc_html((string) $counts['checked_in']); ?></strong>
            </div>
            <div class="evt-event-overview__item">
                <span class="evt-event-overview__label"><?php esc_html_e('Cancelled', 'Event-Tickets-for-Elementor'); ?></span>
                <strong><?php echo esc_html((string) $counts['cancelled']); ?></strong>
            </div>
        </div>
        <div class="evt-actions">
            <a class="button" href="<?php echo esc_url($tickets_url); ?>"><?php esc_html_e('View tickets for this event', 'Event-Tickets-for-Elementor'); ?></a>
            <a class="button" href="<?php echo esc_url($export_url); ?>"><?php esc_html_e('Download CSV', 'Event-Tickets-for-Elementor'); ?></a>
        </div>
        <?php if (0 === (int) $counts['total']) : ?>
            <p class="description"><?php esc_html_e('No tickets have been issued for this event yet.', 'Event-Tickets-for-Elementor'); ?></p>
        <?php endif; ?>
    <?php
        do_action('evt_tickets_event_section_related', $post);
    }

    public function enqueue_admin_assets(string $hook): void
    {
        if (! in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (! $screen || self::POST_TYPE !== (string) $screen->post_type) {
            return;
        }

        // Don't load our admin scripts inside the Elementor editor — they conflict
        // with Elementor's own JS (flatpickr, color picker, etc.) and prevent the
        // editor from initialising.
        if (isset($_GET['action']) && 'elementor' === $_GET['action']) {
            return;
        }

        wp_enqueue_style(
            'evt-tickets-admin-event-details',
            EVT_TICKETS_PLUGIN_URL . 'assets/css/admin-event-details.css',
            [],
            defined('EVT_TICKETS_VERSION') ? \EVT_TICKETS_VERSION : 'dev'
        );

        wp_enqueue_style('wp-color-picker');

        wp_enqueue_script(
            'evt-tickets-admin-event-details',
            EVT_TICKETS_PLUGIN_URL . 'assets/js/admin-event-details.js',
            ['jquery'],
            defined('EVT_TICKETS_VERSION') ? \EVT_TICKETS_VERSION : 'dev',
            true
        );

        wp_enqueue_script(
            'evt-tickets-admin-settings-color',
            EVT_TICKETS_PLUGIN_URL . 'assets/js/admin-settings-color.js',
            ['jquery', 'wp-color-picker'],
            defined('EVT_TICKETS_VERSION') ? \EVT_TICKETS_VERSION : 'dev',
            true
        );

        $version = defined('EVT_TICKETS_VERSION') ? \EVT_TICKETS_VERSION : 'dev';
        wp_enqueue_style(
            'evt-tickets-flatpickr',
            EVT_TICKETS_PLUGIN_URL . 'assets/vendor/flatpickr/flatpickr.min.css',
            [],
            $version
        );
        wp_enqueue_script(
            'evt-tickets-flatpickr',
            EVT_TICKETS_PLUGIN_URL . 'assets/vendor/flatpickr/flatpickr.min.js',
            [],
            $version,
            true
        );

        $locale = function_exists('get_locale') ? (string) get_locale() : '';
        $locale_short = $locale ? strtolower(substr($locale, 0, 2)) : '';
        $flatpickr_l10n_path = EVT_TICKETS_PLUGIN_DIR . 'assets/vendor/flatpickr/l10n/' . rawurlencode($locale_short) . '.js';
        if ($locale_short && file_exists($flatpickr_l10n_path)) {
            wp_enqueue_script(
                'evt-tickets-flatpickr-l10n',
                EVT_TICKETS_PLUGIN_URL . 'assets/vendor/flatpickr/l10n/' . rawurlencode($locale_short) . '.js',
                ['evt-tickets-flatpickr'],
                $version,
                true
            );
        }

        wp_enqueue_script(
            'evt-tickets-admin-event-datetime',
            EVT_TICKETS_PLUGIN_URL . 'assets/js/admin-event-datetime.js',
            ['jquery', 'evt-tickets-flatpickr'],
            $version,
            true
        );

        wp_localize_script(
            'evt-tickets-admin-event-datetime',
            'evtTicketsFlatpickr',
            [
                'locale'    => $locale_short,
                'time_24hr' => true,
            ]
        );
    }

    /**
     * Save event meta.
     */
    public function save_meta(int $post_id, \WP_Post $post): void
    {
        if ($post->post_type !== self::POST_TYPE) {
            return;
        }

        if (
            ! isset($_POST['evt_event_meta_nonce'])
            || ! wp_verify_nonce(wp_unslash($_POST['evt_event_meta_nonce']), 'evt_save_event_meta')
        ) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (! current_user_can('edit_post', $post_id)) {
            return;
        }

        $start    = isset($_POST['evt_event_start']) ? $this->normalize_datetime_input(sanitize_text_field(wp_unslash($_POST['evt_event_start']))) : '';
        $end      = isset($_POST['evt_event_end']) ? $this->normalize_datetime_input(sanitize_text_field(wp_unslash($_POST['evt_event_end']))) : '';
        $location = isset($_POST['evt_event_location']) ? sanitize_text_field(wp_unslash($_POST['evt_event_location'])) : '';

        update_post_meta($post_id, '_evt_event_start', $start);
        update_post_meta($post_id, '_evt_event_end', $end);
        update_post_meta($post_id, '_evt_event_location', $location);

        do_action('evt_tickets_event_details_save', $post_id, $post);
    }

    private function normalize_datetime_input($value): string
    {
        $value = is_string($value) ? trim($value) : '';
        if ('' === $value) {
            return '';
        }

        // Handle HTML5 datetime-local: "YYYY-MM-DDTHH:MM" (or "YYYY-MM-DD HH:MM").
        if (preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/', $value)) {
            $value = str_replace('T', ' ', substr($value, 0, 16));

            $tz = function_exists('wp_timezone') ? wp_timezone() : null;
            if ($tz instanceof \DateTimeZone) {
                $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $value, $tz);
                if ($dt) {
                    return $dt->format('Y-m-d H:i');
                }
            }

            return $value;
        }

        // Fallback: store the sanitized string (still parsed later with strtotime()).
        return sanitize_text_field($value);
    }

    private function format_for_datetime_local(string $value): string
    {
        $value = trim($value);
        if ('' === $value) {
            return '';
        }

        // Already in flatpickr format.
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)) {
            return $value;
        }

        // HTML5 datetime-local format.
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value)) {
            return str_replace('T', ' ', $value);
        }

        $ts = strtotime($value);
        if (! $ts) {
            return '';
        }

        if (function_exists('wp_date')) {
            return wp_date('Y-m-d H:i', $ts);
        }

        return gmdate('Y-m-d H:i', $ts);
    }

    /**
     * Render ticket stats for this event.
     *
     * @param \WP_Post $post
     */
    public function render_stats_meta_box(\WP_Post $post): void
    {
        $event_id = $post->ID;
        $counts = $this->get_ticket_counts($event_id);

        $tickets_url = add_query_arg(
            [
                'post_type'    => CPT_Tickets::POST_TYPE,
                'evt_event_id' => $event_id,
            ],
            admin_url('edit.php')
        );
    ?>
        <p><strong><?php esc_html_e('Total tickets:', 'Event-Tickets-for-Elementor'); ?></strong> <?php echo esc_html($counts['total']); ?></p>
        <ul style="margin:0 0 10px 18px;">
            /* translators: %d is the number of pending tickets. */
            <li><?php printf(esc_html__('Pending: %d', 'Event-Tickets-for-Elementor'), (int) $counts['pending']); ?></li>
            /* translators: %d is the number of checked-in tickets. */
            <li><?php printf(esc_html__('Checked in: %d', 'Event-Tickets-for-Elementor'), (int) $counts['checked_in']); ?></li>
            /* translators: %d is the number of cancelled tickets. */
            <li><?php printf(esc_html__('Cancelled: %d', 'Event-Tickets-for-Elementor'), (int) $counts['cancelled']); ?></li>
        </ul>
        <p><a href="<?php echo esc_url($tickets_url); ?>"><?php esc_html_e('View tickets for this event', 'Event-Tickets-for-Elementor'); ?></a></p>
<?php
    }

    /**
     * @return array{total:int,pending:int,checked_in:int,cancelled:int}
     */
    private function get_ticket_counts(int $event_id): array
    {
        $counts = [
            'total'      => 0,
            'pending'    => 0,
            'checked_in' => 0,
            'cancelled'  => 0,
        ];

        $counts['total'] = $this->count_tickets_for_event_by_meta($event_id, []);

        foreach (['pending', 'checked_in', 'cancelled'] as $status) {
            $counts[$status] = $this->count_tickets_for_event_by_meta(
                $event_id,
                [
                    [
                        'key'   => '_ticket_status',
                        'value' => $status,
                    ],
                ]
            );
        }

        return $counts;
    }

    /**
     * Count tickets for an event matching an optional extra meta_query clause,
     * using found_posts so the database does the counting rather than loading
     * every matching ticket ID.
     *
     * @param int   $event_id
     * @param array $extra_meta
     */
    private function count_tickets_for_event_by_meta(int $event_id, array $extra_meta): int
    {
        $meta_query = [
            [
                'key'   => '_ticket_event_id',
                'value' => $event_id,
            ],
        ];

        if (! empty($extra_meta)) {
            $meta_query = array_merge($meta_query, $extra_meta);
        }

        $query = new \WP_Query(
            [
                'post_type'      => CPT_Tickets::POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'no_found_rows'  => false,
                'meta_query'     => $meta_query,
            ]
        );

        return (int) $query->found_posts;
    }

    /**
     * @return array<int,array{label:string,terms:array<int,string>,empty:string}>
     */
    private function get_taxonomy_summary(int $post_id): array
    {
        $map = [
            Event_Taxonomies::TAX_CATEGORY => [
                'label' => __('Event Categories', 'Event-Tickets-for-Elementor'),
                'empty' => __('No event categories assigned yet.', 'Event-Tickets-for-Elementor'),
            ],
            Event_Taxonomies::TAX_TAG => [
                'label' => __('Event Tags', 'Event-Tickets-for-Elementor'),
                'empty' => __('No event tags assigned yet.', 'Event-Tickets-for-Elementor'),
            ],
            Event_Taxonomies::TAX_VENUE => [
                'label' => __('Venues', 'Event-Tickets-for-Elementor'),
                'empty' => __('No venues assigned yet.', 'Event-Tickets-for-Elementor'),
            ],
            Event_Taxonomies::TAX_ORGANIZER => [
                'label' => __('Organizers', 'Event-Tickets-for-Elementor'),
                'empty' => __('No organizers assigned yet.', 'Event-Tickets-for-Elementor'),
            ],
        ];

        $summary = [];
        foreach ($map as $taxonomy => $config) {
            $terms = get_the_terms($post_id, $taxonomy);
            $summary[] = [
                'label' => $config['label'],
                'empty' => $config['empty'],
                'terms' => is_array($terms) ? array_values(array_map(static function ($term) {
                    return (string) $term->name;
                }, $terms)) : [],
            ];
        }

        return $summary;
    }

    private function get_status_snapshot(int $post_id): string
    {
        if ((bool) get_post_meta($post_id, Event_Status::META_CANCELLED, true)) {
            return __('Cancelled', 'Event-Tickets-for-Elementor');
        }

        if ((bool) get_post_meta($post_id, Event_Status::META_POSTPONED, true)) {
            return __('Postponed', 'Event-Tickets-for-Elementor');
        }

        $end_ts = (int) get_post_meta($post_id, Event_Meta_Timestamps::END_TS_META, true);
        if ($end_ts > 0 && $end_ts < current_time('timestamp')) {
            return __('Over', 'Event-Tickets-for-Elementor');
        }

        return __('Scheduled', 'Event-Tickets-for-Elementor');
    }

    private function is_block_editor_active(): bool
    {
        if (function_exists('get_current_screen')) {
            $screen = get_current_screen();
            if ($screen) {
                if (method_exists($screen, 'is_block_editor')) {
                    return (bool) $screen->is_block_editor();
                }
                if (isset($screen->is_block_editor)) {
                    return (bool) $screen->is_block_editor;
                }
            }
        }

        return function_exists('use_block_editor_for_post_type') && use_block_editor_for_post_type(self::POST_TYPE);
    }
}
