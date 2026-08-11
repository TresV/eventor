<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Event capacity management (meta box + helpers).
 */
class Event_Capacity
{
    public const META_KEY = '_evt_event_capacity';
    private const CACHE_KEY_PREFIX = 'evt_capacity_';

    private function should_enforce_event_capacity(int $event_id): bool
    {
        $event_id = absint($event_id);
        if (! $event_id) {
            return false;
        }

        // If the event uses timeslots and is configured for slot-only capacity,
        // skip event-level capacity enforcement.
        $timeslots = new Event_Timeslots();
        if (
            $timeslots->has_slots($event_id)
            && Event_Timeslots::TICKETING_MODE_SLOT === $timeslots->get_ticketing_mode($event_id)
            && Event_Timeslots::CAPACITY_MODE_SLOT === $timeslots->get_capacity_mode($event_id)
        ) {
            return false;
        }

        return true;
    }

    public function __construct()
    {
        add_action('evt_tickets_event_section_attendance', [$this, 'render_event_details_fields'], 10);
        add_action('evt_tickets_event_details_save', [$this, 'save_event_details_fields'], 10, 2);
    }

    public function render_event_details_fields(\WP_Post $post): void
    {
        $capacity  = (int) get_post_meta($post->ID, self::META_KEY, true);
        $remaining = $this->remaining_capacity($post->ID);
?>
        <p class="evt-event-field">
            <label for="evt_event_capacity_field"><strong><?php esc_html_e('Max tickets', 'Event-Tickets-for-Elementor'); ?> (<?php esc_html_e('optional', 'Event-Tickets-for-Elementor'); ?>)</strong></label><br />
            <input
                type="number"
                id="evt_event_capacity_field"
                name="evt_event_capacity"
                min="0"
                step="1"
                class="regular-text"
                value="<?php echo esc_attr($capacity > 0 ? $capacity : ''); ?>"
                placeholder="<?php esc_attr_e('Unlimited', 'Event-Tickets-for-Elementor'); ?>" />
        </p>
        <p class="description">
            <?php esc_html_e('Leave blank for unlimited. Cancelled tickets do not count toward capacity.', 'Event-Tickets-for-Elementor'); ?>
        </p>
        <?php if ($capacity > 0) : ?>
            <p><strong><?php esc_html_e('Remaining:', 'Event-Tickets-for-Elementor'); ?></strong> <?php echo esc_html(max(0, $remaining)); ?></p>
        <?php endif; ?>
<?php
    }

    public function save_event_details_fields(int $post_id, \WP_Post $post): void
    {
        if ($post->post_type !== CPT_Events::POST_TYPE) {
            return;
        }

        $capacity = isset($_POST['evt_event_capacity']) ? absint($_POST['evt_event_capacity']) : 0;

        if ($capacity > 0) {
            update_post_meta($post_id, self::META_KEY, $capacity);
        } else {
            delete_post_meta($post_id, self::META_KEY);
        }
    }

    /**
     * Check if event is at or over capacity.
     */
    public function is_full(int $event_id): bool
    {
        if (! $this->should_enforce_event_capacity($event_id)) {
            return false;
        }

        $capacity = $this->get_capacity($event_id);
        if ($capacity <= 0) {
            return false;
        }

        return $this->tickets_count($event_id) >= $capacity;
    }

    /**
     * Remaining slots (returns large number if unlimited).
     */
    public function remaining_capacity(int $event_id): int
    {
        if (! $this->should_enforce_event_capacity($event_id)) {
            return PHP_INT_MAX;
        }

        $capacity = $this->get_capacity($event_id);
        if ($capacity <= 0) {
            return PHP_INT_MAX;
        }

        $used = $this->tickets_count($event_id);
        return max(0, $capacity - $used);
    }

    public function get_capacity(int $event_id): int
    {
        return (int) get_post_meta($event_id, self::META_KEY, true);
    }

    /**
     * Count active tickets for an event (excludes cancelled).
     */
    public function tickets_count(int $event_id): int
    {
        $event_id = absint($event_id);
        if (! $event_id) {
            return 0;
        }

        $cache_key = self::CACHE_KEY_PREFIX . $event_id;
        $cached = get_transient($cache_key);
        if (false !== $cached) {
            return (int) $cached;
        }

        $ticket_service = Plugin::instance()->tickets();
        if (! $ticket_service) {
            return 0;
        }

        $capacity = $this->get_capacity($event_id);
        $limit = $capacity > 0 ? $capacity : null;

        $count = (int) $ticket_service->count_active_tickets_for_event($event_id, $limit);
        set_transient($cache_key, $count, 60);

        return $count;
    }
}
