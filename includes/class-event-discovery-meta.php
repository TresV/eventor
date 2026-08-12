<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Adds discovery-related fields for events (cost + country/city).
 */
class Event_Discovery_Meta
{
    public const COST_META    = '_evt_event_cost';
    public const COST_ENABLED_META = '_evt_event_cost_enabled';
    public const PAID_ENABLED_META = '_evt_event_paid_enabled';
    public const COUNTRY_META = '_evt_event_country';
    public const CITY_META    = '_evt_event_city';

    public function __construct()
    {
        add_action('evt_tickets_event_section_location', [$this, 'render_location_fields'], 30);
        add_action('evt_tickets_event_section_attendance', [$this, 'render_ticketing_fields'], 30);
        add_action('evt_tickets_event_details_save', [$this, 'save_fields'], 10, 2);
    }

    public function render_location_fields(\WP_Post $post): void
    {
        $country = (string) get_post_meta($post->ID, self::COUNTRY_META, true);
        $city    = (string) get_post_meta($post->ID, self::CITY_META, true);
        ?>
        <div class="evt-event-grid evt-event-grid--2">
            <p class="evt-event-field">
                <label for="evt_event_country"><strong><?php esc_html_e('Country', 'Event-Tickets-for-Elementor'); ?> (<?php esc_html_e('optional', 'Event-Tickets-for-Elementor'); ?>)</strong></label><br />
                <input
                    type="text"
                    id="evt_event_country"
                    name="evt_event_country"
                    class="regular-text"
                    value="<?php echo esc_attr($country); ?>"
                    placeholder="<?php esc_attr_e('e.g. Bulgaria', 'Event-Tickets-for-Elementor'); ?>" />
            </p>
            <p class="evt-event-field">
                <label for="evt_event_city"><strong><?php esc_html_e('City', 'Event-Tickets-for-Elementor'); ?> (<?php esc_html_e('optional', 'Event-Tickets-for-Elementor'); ?>)</strong></label><br />
                <input
                    type="text"
                    id="evt_event_city"
                    name="evt_event_city"
                    class="regular-text"
                    value="<?php echo esc_attr($city); ?>"
                    placeholder="<?php esc_attr_e('e.g. Varna', 'Event-Tickets-for-Elementor'); ?>" />
            </p>
        </div>
        <?php
    }

    public function render_ticketing_fields(\WP_Post $post): void
    {
        $cost    = (string) get_post_meta($post->ID, self::COST_META, true);
        $enabled = (bool) get_post_meta($post->ID, self::COST_ENABLED_META, true);
        $paid_enabled = (bool) get_post_meta($post->ID, self::PAID_ENABLED_META, true);
        ?>
        <div class="evt-event-toggle">
            <label>
                <input type="checkbox" name="evt_event_cost_enabled" value="1" <?php checked($enabled, true); ?> data-toggle-target="#evt-event-cost-target" />
                <strong><?php esc_html_e('Enable ticket price', 'Event-Tickets-for-Elementor'); ?></strong>
            </label>
        </div>
        <div id="evt-event-cost-target" class="evt-event-toggle-target">
            <p class="evt-event-field">
                <label for="evt_event_cost"><strong><?php esc_html_e('Cost', 'Event-Tickets-for-Elementor'); ?> (<?php esc_html_e('optional', 'Event-Tickets-for-Elementor'); ?>)</strong></label><br />
                <input
                    type="number"
                    id="evt_event_cost"
                    name="evt_event_cost"
                    class="regular-text"
                    step="0.01"
                    min="0"
                    value="<?php echo esc_attr($cost); ?>"
                    placeholder="<?php esc_attr_e('0.00', 'Event-Tickets-for-Elementor'); ?>" />
            </p>
            <p class="description">
                <?php esc_html_e('Used for event pricing and the direct checkout amount when paid tickets are enabled.', 'Event-Tickets-for-Elementor'); ?>
            </p>
        </div>
        <div class="evt-event-toggle">
            <label>
                <input type="checkbox" name="evt_event_paid_enabled" value="1" <?php checked($paid_enabled, true); ?> />
                <strong><?php esc_html_e('Require payment for ticket requests', 'Event-Tickets-for-Elementor'); ?></strong>
            </label>
            <p class="description">
                <?php esc_html_e('When enabled, the ticket form routes requests through the configured payment processor. Tickets are issued only after the payment is confirmed.', 'Event-Tickets-for-Elementor'); ?>
            </p>
        </div>
        <?php
    }

    public function save_fields(int $post_id, \WP_Post $post): void
    {
        if ($post->post_type !== CPT_Events::POST_TYPE) {
            return;
        }

        $cost = isset($_POST['evt_event_cost']) ? sanitize_text_field(wp_unslash($_POST['evt_event_cost'])) : '';
        $cost_enabled = isset($_POST['evt_event_cost_enabled']) && '1' === sanitize_text_field(wp_unslash($_POST['evt_event_cost_enabled']));
        $paid_enabled = isset($_POST['evt_event_paid_enabled']) && '1' === sanitize_text_field(wp_unslash($_POST['evt_event_paid_enabled']));
        $country = isset($_POST['evt_event_country']) ? sanitize_text_field(wp_unslash($_POST['evt_event_country'])) : '';
        $city = isset($_POST['evt_event_city']) ? sanitize_text_field(wp_unslash($_POST['evt_event_city'])) : '';

        $cost = trim((string) $cost);
        $country = trim((string) $country);
        $city = trim((string) $city);

        if ($cost_enabled) {
            update_post_meta($post_id, self::COST_ENABLED_META, '1');
        } else {
            delete_post_meta($post_id, self::COST_ENABLED_META);
        }

        if ($cost_enabled && '' !== $cost && is_numeric($cost)) {
            update_post_meta($post_id, self::COST_META, (string) (float) $cost);
        } else {
            delete_post_meta($post_id, self::COST_META);
        }

        if ($paid_enabled) {
            update_post_meta($post_id, self::PAID_ENABLED_META, '1');
        } else {
            delete_post_meta($post_id, self::PAID_ENABLED_META);
        }

        if ('' !== $country) {
            update_post_meta($post_id, self::COUNTRY_META, $country);
        } else {
            delete_post_meta($post_id, self::COUNTRY_META);
        }

        if ('' !== $city) {
            update_post_meta($post_id, self::CITY_META, $city);
        } else {
            delete_post_meta($post_id, self::CITY_META);
        }
    }
}
