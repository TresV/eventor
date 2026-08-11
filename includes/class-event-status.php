<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Adds cancelled/postponed status toggles for events.
 */
class Event_Status
{
    public const META_CANCELLED = '_evt_event_cancelled';
    public const META_POSTPONED = '_evt_event_postponed';

    public function __construct()
    {
        add_action('evt_tickets_event_section_status_visibility', [$this, 'render_event_details_fields'], 10);
        add_action('evt_tickets_event_details_save', [$this, 'save_event_details_fields'], 10, 2);
    }

    public function render_event_details_fields(\WP_Post $post): void
    {
        $cancelled = (bool) get_post_meta($post->ID, self::META_CANCELLED, true);
        $postponed = (bool) get_post_meta($post->ID, self::META_POSTPONED, true);
?>
        <p style="margin: 12px 0 8px;">
            <label>
                <input type="checkbox" name="evt_event_cancelled" value="1" <?php checked($cancelled, true); ?> />
                <strong><?php esc_html_e('Mark event as cancelled', 'Event-Tickets-for-Elementor'); ?></strong>
            </label>
        </p>
        <p style="margin: 8px 0 8px;">
            <label>
                <input type="checkbox" name="evt_event_postponed" value="1" <?php checked($postponed, true); ?> />
                <strong><?php esc_html_e('Mark event as postponed', 'Event-Tickets-for-Elementor'); ?></strong>
            </label>
        </p>
        <p class="description"><?php esc_html_e('These flags drive event status labels, badges, and filtering behavior across the plugin.', 'Event-Tickets-for-Elementor'); ?></p>
<?php
    }

    public function save_event_details_fields(int $post_id, \WP_Post $post): void
    {
        if ($post->post_type !== CPT_Events::POST_TYPE) {
            return;
        }

        $cancelled = isset($_POST['evt_event_cancelled']) && '1' === $_POST['evt_event_cancelled'];
        $postponed = isset($_POST['evt_event_postponed']) && '1' === $_POST['evt_event_postponed'];

        if ($cancelled) {
            update_post_meta($post_id, self::META_CANCELLED, '1');
        } else {
            delete_post_meta($post_id, self::META_CANCELLED);
        }

        if ($postponed) {
            update_post_meta($post_id, self::META_POSTPONED, '1');
        } else {
            delete_post_meta($post_id, self::META_POSTPONED);
        }
    }
}
