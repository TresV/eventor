<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Per-event ticket code pattern selector.
 *
 * This replaces the global "Ticket Code Pattern" setting.
 */
class Event_Ticket_Code_Pattern_Meta
{
    public const META_KEY = '_evt_event_ticket_code_pattern';

    private const DEFAULT_PATTERN = 'EVT-{RANDOM:10}';

    public function __construct()
    {
        add_action('evt_tickets_event_section_ticket_output', [$this, 'render_event_details_fields'], 20);
        add_action('evt_tickets_event_details_save', [$this, 'save_event_details_fields'], 10, 2);
    }

    public function render_event_details_fields(\WP_Post $post): void
    {
        $value = (string) get_post_meta($post->ID, self::META_KEY, true);
        $value = trim($value);
        if ('' === $value) {
            $value = self::DEFAULT_PATTERN;
        }
        ?>
        <div class="evt-event-subsection">
        <p>
            <label for="evt_event_ticket_code_pattern"><strong><?php esc_html_e('Ticket Code Pattern', 'Event-Tickets-for-Elementor'); ?></strong></label><br />
            <input
                type="text"
                id="evt_event_ticket_code_pattern"
                name="evt_event_ticket_code_pattern"
                class="regular-text"
                value="<?php echo esc_attr($value); ?>"
                placeholder="<?php echo esc_attr(self::DEFAULT_PATTERN); ?>" />
            <br />
            <span class="description">
                <?php esc_html_e('Pattern supports: {RANDOM} or {RANDOM:n}, {ID}, {EVENT_SLUG}, {DATEYMD}.', 'Event-Tickets-for-Elementor'); ?>
            </span>
        </p>
        </div>
        <?php
    }

    public function save_event_details_fields(int $post_id, \WP_Post $post): void
    {
        if (CPT_Events::POST_TYPE !== $post->post_type) {
            return;
        }

        $pattern = isset($_POST['evt_event_ticket_code_pattern'])
            ? sanitize_text_field(wp_unslash($_POST['evt_event_ticket_code_pattern']))
            : '';

        $pattern = trim($pattern);
        if ('' === $pattern) {
            $pattern = self::DEFAULT_PATTERN;
        }

        update_post_meta($post_id, self::META_KEY, $pattern);
    }
}
