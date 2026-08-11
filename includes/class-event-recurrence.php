<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/event-recurrence/class-recurrence-schedule.php';
require_once __DIR__ . '/event-recurrence/class-recurrence-generator.php';
require_once __DIR__ . '/event-recurrence/class-recurrence-operations.php';
require_once __DIR__ . '/event-recurrence/class-recurrence-admin.php';

/**
 * Recurring events: stores series rules and materializes occurrences as real event posts.
 */
class Event_Recurrence
{
    public const META_ENABLED = '_evt_recurrence_enabled';
    public const META_FREQ = '_evt_recurrence_freq';
    public const META_INTERVAL = '_evt_recurrence_interval';
    public const META_WEEKDAYS = '_evt_recurrence_weekdays';
    public const META_END_TYPE = '_evt_recurrence_end_type';
    public const META_UNTIL = '_evt_recurrence_until';
    public const META_COUNT = '_evt_recurrence_count';

    public const META_SERIES_PARENT = '_evt_series_parent';
    public const META_IS_SERIES = '_evt_is_series';
    public const META_IS_OCCURRENCE = '_evt_is_occurrence';
    public const META_OCCURRENCE_INDEX = '_evt_occurrence_index';
    public const META_OCCURRENCE_ORIGINAL_START = '_evt_occurrence_original_start';
    public const META_OCCURRENCE_ORIGINAL_END = '_evt_occurrence_original_end';
    public const META_OCCURRENCE_OVERRIDDEN = '_evt_occurrence_overridden';

    private Recurrence_Schedule $schedule;
    private Recurrence_Generator $generator;
    private Recurrence_Operations $operations;
    private Recurrence_Admin $admin;

    public function __construct()
    {
        $this->schedule = new Recurrence_Schedule();
        $this->generator = new Recurrence_Generator($this->schedule);
        $this->operations = new Recurrence_Operations($this->generator);
        $this->admin = new Recurrence_Admin($this->generator, $this->schedule, $this->operations);

        add_action('evt_tickets_event_section_recurrence', [$this->admin, 'render_fields']);
        add_action('evt_tickets_event_details_save', [$this->admin, 'save_fields'], 60, 2);

        add_filter('manage_' . CPT_Events::POST_TYPE . '_posts_columns', [$this->admin, 'add_admin_columns']);
        add_action('manage_' . CPT_Events::POST_TYPE . '_posts_custom_column', [$this->admin, 'render_admin_column'], 10, 2);
        add_action('restrict_manage_posts', [$this->admin, 'render_admin_filter']);
        add_action('pre_get_posts', [$this->admin, 'apply_admin_filter']);
        add_filter('post_row_actions', [$this->admin, 'add_row_actions'], 20, 2);

        add_action('before_delete_post', [$this->operations, 'delete_children_before_parent_delete']);
    }

    public static function event_selector_query_args(array $args = []): array
    {
        $meta_query = isset($args['meta_query']) && is_array($args['meta_query']) ? $args['meta_query'] : [];
        $meta_query[] = [
            'relation' => 'OR',
            [
                'key'     => self::META_IS_SERIES,
                'compare' => 'NOT EXISTS',
            ],
            [
                'key'     => self::META_IS_SERIES,
                'value'   => '1',
                'compare' => '!=',
            ],
        ];

        $args['meta_query'] = $meta_query;
        return $args;
    }

    public static function is_series_parent(int $event_id): bool
    {
        return (bool) get_post_meta($event_id, self::META_IS_SERIES, true);
    }

    public static function is_occurrence(int $event_id): bool
    {
        return (bool) get_post_meta($event_id, self::META_IS_OCCURRENCE, true);
    }

    public static function series_parent_id(int $event_id): int
    {
        return (int) get_post_meta($event_id, self::META_SERIES_PARENT, true);
    }

    public function render_fields(\WP_Post $post): void
    {
        $this->admin->render_fields($post);
    }

    public function save_fields(int $post_id, \WP_Post $post): void
    {
        $this->admin->save_fields($post_id, $post);
    }

    /**
     * @param array<string,string> $columns
     * @return array<string,string>
     */
    public function add_admin_columns(array $columns): array
    {
        return $this->admin->add_admin_columns($columns);
    }

    public function render_admin_column(string $column, int $post_id): void
    {
        $this->admin->render_admin_column($column, $post_id);
    }

    public function render_admin_filter(): void
    {
        $this->admin->render_admin_filter();
    }

    public function apply_admin_filter(\WP_Query $query): void
    {
        $this->admin->apply_admin_filter($query);
    }

    /**
     * @param array<string,string> $actions
     * @return array<string,string>
     */
    public function add_row_actions(array $actions, \WP_Post $post): array
    {
        return $this->admin->add_row_actions($actions, $post);
    }

    public function delete_children_before_parent_delete(int $post_id): void
    {
        $this->operations->delete_children_before_parent_delete($post_id);
    }
}
