<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Per-event badge overrides shown in the Event editor.
 */
class Event_Status_Badge_Meta
{
    public function __construct()
    {
        add_action('evt_tickets_event_section_status_visibility', [$this, 'render_fields'], 20);
        add_action('evt_tickets_event_details_save', [$this, 'save_fields'], 20, 2);
    }

    public function render_fields(\WP_Post $post): void
    {
        $visibility = (string) get_post_meta($post->ID, Event_Status_Badge_Resolver::META_BADGE_VISIBILITY, true);
        $label = (string) get_post_meta($post->ID, Event_Status_Badge_Resolver::META_BADGE_LABEL, true);
        $bg = (string) get_post_meta($post->ID, Event_Status_Badge_Resolver::META_BADGE_BG, true);
        $text = (string) get_post_meta($post->ID, Event_Status_Badge_Resolver::META_BADGE_TEXT, true);
        $border = (string) get_post_meta($post->ID, Event_Status_Badge_Resolver::META_BADGE_BORDER, true);
        $radius = (string) get_post_meta($post->ID, Event_Status_Badge_Resolver::META_BADGE_RADIUS, true);
        $font_size = (string) get_post_meta($post->ID, Event_Status_Badge_Resolver::META_BADGE_FONT_SIZE, true);
        $font_weight = (string) get_post_meta($post->ID, Event_Status_Badge_Resolver::META_BADGE_FONT_WEIGHT, true);
        $padding_x = (string) get_post_meta($post->ID, Event_Status_Badge_Resolver::META_BADGE_PADDING_X, true);
        $padding_y = (string) get_post_meta($post->ID, Event_Status_Badge_Resolver::META_BADGE_PADDING_Y, true);
        $text_transform = (string) get_post_meta($post->ID, Event_Status_Badge_Resolver::META_BADGE_TEXT_TRANSFORM, true);
        ?>
        <details class="evt-event-inline-accordion">
            <summary><?php esc_html_e('Badge Style Overrides', 'Event-Tickets-for-Elementor'); ?></summary>
            <div class="evt-event-grid evt-event-grid--2 evt-event-inline-accordion__body">
            <p class="evt-event-field">
                <label for="evt_status_badge_visibility"><strong><?php esc_html_e('Badge Visibility Override', 'Event-Tickets-for-Elementor'); ?></strong></label><br />
                <select id="evt_status_badge_visibility" name="evt_status_badge_visibility">
                    <option value="" <?php selected($visibility, ''); ?>><?php esc_html_e('Use global settings', 'Event-Tickets-for-Elementor'); ?></option>
                    <option value="show" <?php selected($visibility, 'show'); ?>><?php esc_html_e('Force show badge', 'Event-Tickets-for-Elementor'); ?></option>
                    <option value="hide" <?php selected($visibility, 'hide'); ?>><?php esc_html_e('Hide badge for this event', 'Event-Tickets-for-Elementor'); ?></option>
                </select>
            </p>

            <p class="evt-event-field">
                <label for="evt_status_badge_label"><strong><?php esc_html_e('Custom Badge Label', 'Event-Tickets-for-Elementor'); ?></strong></label><br />
                <input type="text" id="evt_status_badge_label" name="evt_status_badge_label" class="regular-text" value="<?php echo esc_attr($label); ?>" />
            </p>

            <p class="evt-event-field">
                <label for="evt_status_badge_bg"><strong><?php esc_html_e('Badge Background', 'Event-Tickets-for-Elementor'); ?></strong></label><br />
                <input type="text" id="evt_status_badge_bg" name="evt_status_badge_bg" class="regular-text evt-color-field" value="<?php echo esc_attr($bg); ?>" />
            </p>

            <p class="evt-event-field">
                <label for="evt_status_badge_text"><strong><?php esc_html_e('Badge Text Color', 'Event-Tickets-for-Elementor'); ?></strong></label><br />
                <input type="text" id="evt_status_badge_text" name="evt_status_badge_text" class="regular-text evt-color-field" value="<?php echo esc_attr($text); ?>" />
            </p>

            <p class="evt-event-field">
                <label for="evt_status_badge_border"><strong><?php esc_html_e('Badge Border Color', 'Event-Tickets-for-Elementor'); ?></strong></label><br />
                <input type="text" id="evt_status_badge_border" name="evt_status_badge_border" class="regular-text evt-color-field" value="<?php echo esc_attr($border); ?>" />
            </p>

            <p class="evt-event-field">
                <label for="evt_status_badge_radius"><strong><?php esc_html_e('Badge Border Radius (px)', 'Event-Tickets-for-Elementor'); ?></strong></label><br />
                <input type="number" id="evt_status_badge_radius" name="evt_status_badge_radius" class="regular-text" value="<?php echo esc_attr($radius); ?>" min="0" />
            </p>

            <p class="evt-event-field">
                <label for="evt_status_badge_font_size"><strong><?php esc_html_e('Badge Font Size (px)', 'Event-Tickets-for-Elementor'); ?></strong></label><br />
                <input type="number" id="evt_status_badge_font_size" name="evt_status_badge_font_size" class="regular-text" value="<?php echo esc_attr($font_size); ?>" min="10" />
            </p>

            <p class="evt-event-field">
                <label for="evt_status_badge_font_weight"><strong><?php esc_html_e('Badge Font Weight', 'Event-Tickets-for-Elementor'); ?></strong></label><br />
                <select id="evt_status_badge_font_weight" name="evt_status_badge_font_weight">
                    <option value="" <?php selected($font_weight, ''); ?>><?php esc_html_e('Use global settings', 'Event-Tickets-for-Elementor'); ?></option>
                    <option value="400" <?php selected($font_weight, '400'); ?>>400</option>
                    <option value="500" <?php selected($font_weight, '500'); ?>>500</option>
                    <option value="600" <?php selected($font_weight, '600'); ?>>600</option>
                    <option value="700" <?php selected($font_weight, '700'); ?>>700</option>
                </select>
            </p>

            <p class="evt-event-field">
                <label for="evt_status_badge_padding_x"><strong><?php esc_html_e('Badge Horizontal Padding (px)', 'Event-Tickets-for-Elementor'); ?></strong></label><br />
                <input type="number" id="evt_status_badge_padding_x" name="evt_status_badge_padding_x" class="regular-text" value="<?php echo esc_attr($padding_x); ?>" min="0" />
            </p>

            <p class="evt-event-field">
                <label for="evt_status_badge_padding_y"><strong><?php esc_html_e('Badge Vertical Padding (px)', 'Event-Tickets-for-Elementor'); ?></strong></label><br />
                <input type="number" id="evt_status_badge_padding_y" name="evt_status_badge_padding_y" class="regular-text" value="<?php echo esc_attr($padding_y); ?>" min="0" />
            </p>

            <p class="evt-event-field">
                <label for="evt_status_badge_text_transform"><strong><?php esc_html_e('Badge Text Transform', 'Event-Tickets-for-Elementor'); ?></strong></label><br />
                <select id="evt_status_badge_text_transform" name="evt_status_badge_text_transform">
                    <option value="" <?php selected($text_transform, ''); ?>><?php esc_html_e('Use global settings', 'Event-Tickets-for-Elementor'); ?></option>
                    <option value="none" <?php selected($text_transform, 'none'); ?>><?php esc_html_e('None', 'Event-Tickets-for-Elementor'); ?></option>
                    <option value="uppercase" <?php selected($text_transform, 'uppercase'); ?>><?php esc_html_e('Uppercase', 'Event-Tickets-for-Elementor'); ?></option>
                    <option value="capitalize" <?php selected($text_transform, 'capitalize'); ?>><?php esc_html_e('Capitalize', 'Event-Tickets-for-Elementor'); ?></option>
                    <option value="lowercase" <?php selected($text_transform, 'lowercase'); ?>><?php esc_html_e('Lowercase', 'Event-Tickets-for-Elementor'); ?></option>
                </select>
            </p>
            </div>
            <p class="description"><?php esc_html_e('These optional overrides affect badge output only for this event. Event status precedence still follows cancelled, postponed, then over.', 'Event-Tickets-for-Elementor'); ?></p>
        </details>
        <?php
    }

    public function save_fields(int $post_id, \WP_Post $post): void
    {
        if (CPT_Events::POST_TYPE !== $post->post_type) {
            return;
        }

        $text_fields = [
            Event_Status_Badge_Resolver::META_BADGE_VISIBILITY => isset($_POST['evt_status_badge_visibility']) ? sanitize_key(wp_unslash($_POST['evt_status_badge_visibility'])) : '',
            Event_Status_Badge_Resolver::META_BADGE_LABEL => isset($_POST['evt_status_badge_label']) ? sanitize_text_field(wp_unslash($_POST['evt_status_badge_label'])) : '',
            Event_Status_Badge_Resolver::META_BADGE_FONT_WEIGHT => isset($_POST['evt_status_badge_font_weight']) ? sanitize_text_field(wp_unslash($_POST['evt_status_badge_font_weight'])) : '',
            Event_Status_Badge_Resolver::META_BADGE_TEXT_TRANSFORM => isset($_POST['evt_status_badge_text_transform']) ? sanitize_text_field(wp_unslash($_POST['evt_status_badge_text_transform'])) : '',
        ];

        foreach ($text_fields as $meta_key => $value) {
            if ('' === $value) {
                delete_post_meta($post_id, $meta_key);
            } else {
                update_post_meta($post_id, $meta_key, $value);
            }
        }

        $color_fields = [
            Event_Status_Badge_Resolver::META_BADGE_BG => isset($_POST['evt_status_badge_bg']) ? sanitize_hex_color(wp_unslash($_POST['evt_status_badge_bg'])) : '',
            Event_Status_Badge_Resolver::META_BADGE_TEXT => isset($_POST['evt_status_badge_text']) ? sanitize_hex_color(wp_unslash($_POST['evt_status_badge_text'])) : '',
            Event_Status_Badge_Resolver::META_BADGE_BORDER => isset($_POST['evt_status_badge_border']) ? sanitize_hex_color(wp_unslash($_POST['evt_status_badge_border'])) : '',
        ];

        foreach ($color_fields as $meta_key => $value) {
            if ('' === (string) $value) {
                delete_post_meta($post_id, $meta_key);
            } else {
                update_post_meta($post_id, $meta_key, $value);
            }
        }

        $number_fields = [
            'evt_status_badge_radius' => Event_Status_Badge_Resolver::META_BADGE_RADIUS,
            'evt_status_badge_font_size' => Event_Status_Badge_Resolver::META_BADGE_FONT_SIZE,
            'evt_status_badge_padding_x' => Event_Status_Badge_Resolver::META_BADGE_PADDING_X,
            'evt_status_badge_padding_y' => Event_Status_Badge_Resolver::META_BADGE_PADDING_Y,
        ];

        foreach ($number_fields as $input_key => $meta_key) {
            $raw = isset($_POST[$input_key]) ? trim(sanitize_text_field(wp_unslash($_POST[$input_key]))) : '';
            if ('' === $raw) {
                delete_post_meta($post_id, $meta_key);
            } else {
                update_post_meta($post_id, $meta_key, absint($raw));
            }
        }
    }
}
