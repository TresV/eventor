<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Virtual/Hybrid event fields stored on the Event CPT.
 */
class Event_Virtual_Meta
{
    public const ENABLED_META       = '_evt_event_virtual_enabled';
    public const MEETING_URL_META   = '_evt_event_meeting_url';
    public const LIVESTREAM_URL_META = '_evt_event_livestream_url';

    public function __construct()
    {
        add_action('evt_tickets_event_section_delivery', [$this, 'render_fields']);
        add_action('evt_tickets_event_details_save', [$this, 'save_fields'], 10, 2);
    }

    public function render_fields(\WP_Post $post): void
    {
        $enabled = (bool) get_post_meta($post->ID, self::ENABLED_META, true);
        $meeting = (string) get_post_meta($post->ID, self::MEETING_URL_META, true);
        $stream  = (string) get_post_meta($post->ID, self::LIVESTREAM_URL_META, true);
        ?>
        <div class="evt-event-toggle">
            <label>
                <input type="checkbox" name="evt_event_virtual_enabled" value="1" <?php checked($enabled, true); ?> data-toggle-target="#evt-event-virtual-target" />
                <strong><?php esc_html_e('Enable virtual / hybrid details', 'Event-Tickets-for-Elementor'); ?></strong>
            </label>
        </div>
        <div id="evt-event-virtual-target" class="evt-event-toggle-target">
            <p class="evt-event-field">
                <label for="evt_event_meeting_url"><strong><?php esc_html_e('Meeting URL', 'Event-Tickets-for-Elementor'); ?> (<?php esc_html_e('optional', 'Event-Tickets-for-Elementor'); ?>)</strong></label><br />
                <input
                    type="url"
                    id="evt_event_meeting_url"
                    name="evt_event_meeting_url"
                    class="regular-text"
                    value="<?php echo esc_attr($meeting); ?>"
                    placeholder="<?php esc_attr_e('https://…', 'Event-Tickets-for-Elementor'); ?>" />
                <span class="description"><?php esc_html_e('Zoom/Teams/Meet link.', 'Event-Tickets-for-Elementor'); ?></span>
            </p>
            <p class="evt-event-field">
                <label for="evt_event_livestream_url"><strong><?php esc_html_e('Livestream URL', 'Event-Tickets-for-Elementor'); ?> (<?php esc_html_e('optional', 'Event-Tickets-for-Elementor'); ?>)</strong></label><br />
                <input
                    type="url"
                    id="evt_event_livestream_url"
                    name="evt_event_livestream_url"
                    class="regular-text"
                    value="<?php echo esc_attr($stream); ?>"
                    placeholder="<?php esc_attr_e('https://…', 'Event-Tickets-for-Elementor'); ?>" />
                <span class="description"><?php esc_html_e('YouTube/Vimeo/live page URL.', 'Event-Tickets-for-Elementor'); ?></span>
            </p>
        </div>
        <?php
    }

    public function save_fields(int $post_id, \WP_Post $post): void
    {
        if ($post->post_type !== CPT_Events::POST_TYPE) {
            return;
        }

        $enabled = isset($_POST['evt_event_virtual_enabled']) && '1' === sanitize_text_field(wp_unslash($_POST['evt_event_virtual_enabled']));
        $meeting = isset($_POST['evt_event_meeting_url']) ? esc_url_raw(wp_unslash($_POST['evt_event_meeting_url'])) : '';
        $stream  = isset($_POST['evt_event_livestream_url']) ? esc_url_raw(wp_unslash($_POST['evt_event_livestream_url'])) : '';

        if ($enabled) {
            update_post_meta($post_id, self::ENABLED_META, '1');
        } else {
            delete_post_meta($post_id, self::ENABLED_META);
        }

        if ($enabled && $meeting) {
            update_post_meta($post_id, self::MEETING_URL_META, $meeting);
        } else {
            delete_post_meta($post_id, self::MEETING_URL_META);
        }

        if ($enabled && $stream) {
            update_post_meta($post_id, self::LIVESTREAM_URL_META, $stream);
        } else {
            delete_post_meta($post_id, self::LIVESTREAM_URL_META);
        }
    }
}
