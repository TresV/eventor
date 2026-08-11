<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Shared event selector utilities for admin/Elementor controls.
 */
class Event_Selector
{
    public const DEFAULT_EDITOR_LIMIT = 50;

    public static function is_elementor_editor_context(): bool
    {
        if (! class_exists('\Elementor\Plugin')) {
            return false;
        }

        $plugin = \Elementor\Plugin::$instance ?? null;
        if (! $plugin) {
            return false;
        }

        if (isset($plugin->editor) && method_exists($plugin->editor, 'is_edit_mode') && $plugin->editor->is_edit_mode()) {
            return true;
        }

        if (isset($plugin->preview) && method_exists($plugin->preview, 'is_preview_mode') && $plugin->preview->is_preview_mode()) {
            return true;
        }

        return false;
    }

    /**
     * @param array<string,mixed> $args
     * @return array<string,mixed>
     */
    public static function query_args(array $args = [], int $limit = 0): array
    {
        $args = Event_Recurrence::event_selector_query_args($args);

        if ($limit > 0) {
            $args['numberposts'] = $limit;
            $args['posts_per_page'] = $limit;
            $args['no_found_rows'] = true;
        }

        return $args;
    }

    /**
     * @return \WP_Post[]
     */
    public static function get_event_posts(int $limit = 0, int $include_event_id = 0): array
    {
        $posts = get_posts(
            self::query_args(
                [
                    'post_type'   => CPT_Events::POST_TYPE,
                    'post_status' => 'publish',
                    'orderby'     => 'title',
                    'order'       => 'ASC',
                ],
                $limit
            )
        );

        if (! is_array($posts)) {
            $posts = [];
        }

        if ($include_event_id > 0) {
            $included = false;
            foreach ($posts as $post) {
                if ((int) $post->ID === $include_event_id) {
                    $included = true;
                    break;
                }
            }

            if (! $included && self::is_selectable_event($include_event_id)) {
                $include_post = get_post($include_event_id);
                if ($include_post instanceof \WP_Post) {
                    $posts[] = $include_post;
                }
            }
        }

        usort(
            $posts,
            static function (\WP_Post $a, \WP_Post $b): int {
                return strcasecmp($a->post_title, $b->post_title);
            }
        );

        return $posts;
    }

    /**
     * @return array<string,string>
     */
    public static function get_event_options(int $limit = self::DEFAULT_EDITOR_LIMIT, int $include_event_id = 0): array
    {
        $options = [];
        foreach (self::get_event_posts($limit, $include_event_id) as $post) {
            $options[(string) $post->ID] = $post->post_title ?: ('#' . $post->ID);
        }

        return $options;
    }

    public static function is_selectable_event(int $event_id): bool
    {
        if ($event_id <= 0) {
            return false;
        }

        $post = get_post($event_id);

        return $post instanceof \WP_Post
            && CPT_Events::POST_TYPE === $post->post_type
            && ! Event_Recurrence::is_series_parent((int) $post->ID);
    }
}
