<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Recurring events: bulk cleanup and deletion of generated occurrence posts.
 */
class Recurrence_Operations
{
    private Recurrence_Generator $generator;

    public function __construct(Recurrence_Generator $generator)
    {
        $this->generator = $generator;
    }

    public function delete_children_before_parent_delete(int $post_id): void
    {
        $post = get_post($post_id);
        if (! $post || CPT_Events::POST_TYPE !== ($post->post_type ?? '')) {
            return;
        }

        if (! Event_Recurrence::is_series_parent($post_id)) {
            return;
        }

        foreach ($this->generator->get_occurrence_posts($post_id) as $child) {
            Recurrence_Generator::$syncing = true;
            wp_delete_post((int) $child->ID, true);
            Recurrence_Generator::$syncing = false;
        }
    }

    public function delete_occurrences_for_series(int $parent_id): void
    {
        Recurrence_Generator::$syncing = true;
        foreach ($this->generator->get_occurrence_posts($parent_id) as $child) {
            wp_delete_post((int) $child->ID, true);
        }
        Recurrence_Generator::$syncing = false;
    }
}
