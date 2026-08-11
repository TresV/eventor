<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Renders and saves the Event editor meta boxes.
 */
class Event_Meta_Boxes
{
    private Event_Ticket_Stats $stats;
    private Event_Editor_Sections $sections;

    public function __construct(Event_Ticket_Stats $stats)
    {
        $this->stats = $stats;
        $this->sections = new Event_Editor_Sections($stats);
    }

    public function enable_thumbnails(): void
    {
        add_theme_support('post-thumbnails', [CPT_Events::POST_TYPE]);
        add_post_type_support(CPT_Events::POST_TYPE, 'thumbnail');
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
            CPT_Events::POST_TYPE,
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
            CPT_Events::POST_TYPE,
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
                        $this->sections->render_basics_section($post);
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
                        $this->sections->render_related_section($post);
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

    /**
     * Save event meta.
     */
    public function save_meta(int $post_id, \WP_Post $post): void
    {
        if ($post->post_type !== CPT_Events::POST_TYPE) {
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
        $counts = $this->stats->get_ticket_counts($event_id);

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
}
