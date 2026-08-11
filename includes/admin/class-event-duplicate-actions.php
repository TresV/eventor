<?php

namespace EventTicketsElementor\Admin;

use EventTicketsElementor\CPT_Events;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Adds "Duplicate" action for Events CPT.
 *
 * Creates a draft copy and redirects to the edit screen of the new event.
 */
class Event_Duplicate_Actions
{
    private const ACTION = 'evt_duplicate_event';
    private const NONCE_ACTION = 'evt_duplicate_event';

    public function __construct()
    {
        add_filter('post_row_actions', [$this, 'add_row_action'], 10, 2);
        add_action('admin_action_' . self::ACTION, [$this, 'handle']);
    }

    /**
     * @param array<string,string> $actions
     */
    public function add_row_action(array $actions, \WP_Post $post): array
    {
        if (CPT_Events::POST_TYPE !== ($post->post_type ?? '')) {
            return $actions;
        }

        if (! current_user_can('edit_posts')) {
            return $actions;
        }

        $url = wp_nonce_url(
            add_query_arg(
                [
                    'action' => self::ACTION,
                    'post'   => (int) $post->ID,
                ],
                admin_url('admin.php')
            ),
            self::NONCE_ACTION
        );

        $actions['evt_duplicate'] = '<a href="' . esc_url($url) . '">' . esc_html__('Duplicate', 'Event-Tickets-for-Elementor') . '</a>';
        return $actions;
    }

    public function handle(): void
    {
        if (! current_user_can('edit_posts')) {
            wp_die(esc_html__('You do not have permission to duplicate events.', 'Event-Tickets-for-Elementor'));
        }

        check_admin_referer(self::NONCE_ACTION);

        $post_id = isset($_GET['post']) ? absint($_GET['post']) : 0;
        if (! $post_id) {
            wp_safe_redirect(admin_url('edit.php?post_type=' . CPT_Events::POST_TYPE));
            exit;
        }

        $original = get_post($post_id);
        if (! $original || CPT_Events::POST_TYPE !== ($original->post_type ?? '')) {
            wp_safe_redirect(admin_url('edit.php?post_type=' . CPT_Events::POST_TYPE));
            exit;
        }

        $new_id = wp_insert_post(
            [
                'post_type'    => CPT_Events::POST_TYPE,
                'post_status'  => 'draft',
                'post_title'   => $original->post_title ? ($original->post_title . ' ' . __('(Copy)', 'Event-Tickets-for-Elementor')) : __('Event (Copy)', 'Event-Tickets-for-Elementor'),
                'post_content' => $original->post_content,
                'post_excerpt' => $original->post_excerpt,
                'post_author'  => get_current_user_id(),
            ],
            true
        );

        if (is_wp_error($new_id) || ! $new_id) {
            wp_die(esc_html__('Unable to duplicate event.', 'Event-Tickets-for-Elementor'));
        }

        // Copy taxonomies (categories, tags, venues, organizers, etc.).
        $taxes = get_object_taxonomies(CPT_Events::POST_TYPE);
        foreach ($taxes as $tax) {
            $terms = wp_get_object_terms($post_id, $tax, ['fields' => 'ids']);
            if (! is_wp_error($terms) && is_array($terms)) {
                wp_set_object_terms($new_id, $terms, $tax, false);
            }
        }

        // Copy meta.
        $all_meta = get_post_meta($post_id);
        foreach ($all_meta as $key => $values) {
            if (! is_string($key) || '' === $key) {
                continue;
            }

            // Skip internal WP edit locks and revision pointers.
            if (in_array($key, ['_edit_lock', '_edit_last'], true)) {
                continue;
            }

            // Keep series linkage clean: duplicated event should not belong to the same series by default.
            if (in_array($key, [
                '_evt_series_parent',
                '_evt_is_series',
                '_evt_recurrence_enabled',
                '_evt_recurrence_freq',
                '_evt_recurrence_interval',
                '_evt_recurrence_weekdays',
                '_evt_recurrence_end_type',
                '_evt_recurrence_until',
                '_evt_recurrence_count',
                '_evt_is_occurrence',
                '_evt_occurrence_index',
                '_evt_occurrence_original_start',
                '_evt_occurrence_original_end',
                '_evt_occurrence_overridden',
            ], true)) {
                continue;
            }

            foreach ((array) $values as $value) {
                add_post_meta($new_id, $key, maybe_unserialize($value));
            }
        }

        // Copy featured image.
        $thumb_id = get_post_thumbnail_id($post_id);
        if ($thumb_id) {
            set_post_thumbnail($new_id, $thumb_id);
        }

        wp_safe_redirect(admin_url('post.php?action=edit&post=' . (int) $new_id));
        exit;
    }
}
