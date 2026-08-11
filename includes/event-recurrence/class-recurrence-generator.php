<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Recurring events: reads series settings and materializes/synchronizes occurrence posts.
 */
class Recurrence_Generator
{
    public const SAVE_SCOPE_THIS = 'this_occurrence';
    public const SAVE_SCOPE_FUTURE = 'this_and_future';
    public const SAVE_SCOPE_ALL = 'all';
    public const MAX_OCCURRENCES = 365;

    public static bool $syncing = false;

    private Recurrence_Schedule $schedule;

    public function __construct(Recurrence_Schedule $schedule)
    {
        $this->schedule = $schedule;
    }

    /**
     * @return array{enabled:bool,freq:string,interval:int,weekdays:array<int,int>,end_type:string,until:string,count:int}
     */
    public function get_settings(int $event_id): array
    {
        $weekdays = get_post_meta($event_id, Event_Recurrence::META_WEEKDAYS, true);
        if (! is_array($weekdays)) {
            $weekdays = [];
        }
        $weekdays = array_values(array_filter(array_map('intval', $weekdays), static function ($day): bool {
            return $day >= 0 && $day <= 6;
        }));
        sort($weekdays);

        $freq = (string) get_post_meta($event_id, Event_Recurrence::META_FREQ, true);
        if (! in_array($freq, ['daily', 'weekly', 'monthly'], true)) {
            $freq = 'weekly';
        }

        $end_type = (string) get_post_meta($event_id, Event_Recurrence::META_END_TYPE, true);
        if (! in_array($end_type, ['count', 'until'], true)) {
            $end_type = 'count';
        }

        $until = (string) get_post_meta($event_id, Event_Recurrence::META_UNTIL, true);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $until)) {
            $until = '';
        }

        return [
            'enabled'  => (bool) get_post_meta($event_id, Event_Recurrence::META_ENABLED, true),
            'freq'     => $freq,
            'interval' => max(1, (int) get_post_meta($event_id, Event_Recurrence::META_INTERVAL, true)),
            'weekdays' => $weekdays,
            'end_type' => $end_type,
            'until'    => $until,
            'count'    => max(1, min(self::MAX_OCCURRENCES, (int) get_post_meta($event_id, Event_Recurrence::META_COUNT, true) ?: 10)),
        ];
    }

    /**
     * @param array{enabled:bool,freq:string,interval:int,weekdays:array<int,int>,end_type:string,until:string,count:int}|null $settings
     */
    public function regenerate_series_from_parent(int $parent_id, ?array $settings = null): void
    {
        $settings = $settings ?: $this->get_settings($parent_id);
        $schedule = $this->schedule->build_occurrence_schedule($parent_id, $settings);
        $existing = $this->get_occurrence_posts($parent_id);
        $keep_ids = [];

        self::$syncing = true;

        foreach ($schedule as $item) {
            $index = (int) $item['index'];
            $existing_post = isset($existing[$index]) ? $existing[$index] : null;
            if ($existing_post && (bool) get_post_meta((int) $existing_post->ID, Event_Recurrence::META_OCCURRENCE_OVERRIDDEN, true)) {
                $keep_ids[] = (int) $existing_post->ID;
                continue;
            }

            $occurrence_id = $existing_post ? (int) $existing_post->ID : 0;
            $occurrence_id = $this->upsert_occurrence($parent_id, $occurrence_id, $item);
            if ($occurrence_id > 0) {
                $keep_ids[] = $occurrence_id;
            }
        }

        foreach ($existing as $index => $child) {
            $child_id = (int) $child->ID;
            if (in_array($child_id, $keep_ids, true)) {
                continue;
            }
            if ((bool) get_post_meta($child_id, Event_Recurrence::META_OCCURRENCE_OVERRIDDEN, true)) {
                continue;
            }
            wp_delete_post($child_id, true);
        }

        self::$syncing = false;
    }

    public function apply_occurrence_scope_changes(int $occurrence_id, string $scope): void
    {
        $parent_id = Event_Recurrence::series_parent_id($occurrence_id);
        if (! $parent_id) {
            update_post_meta($occurrence_id, Event_Recurrence::META_OCCURRENCE_OVERRIDDEN, '1');
            return;
        }

        if (self::SAVE_SCOPE_ALL === $scope) {
            $this->apply_occurrence_to_parent_all($occurrence_id, $parent_id);
            $this->regenerate_series_from_parent($parent_id);
            return;
        }

        $this->regenerate_future_from_occurrence($occurrence_id, $parent_id);
    }

    private function apply_occurrence_to_parent_all(int $occurrence_id, int $parent_id): void
    {
        $old_parent_start = (string) get_post_meta($parent_id, '_evt_event_start', true);
        $old_parent_end = (string) get_post_meta($parent_id, '_evt_event_end', true);
        $current_start = (string) get_post_meta($occurrence_id, '_evt_event_start', true);
        $current_end = (string) get_post_meta($occurrence_id, '_evt_event_end', true);
        $original_start = (string) get_post_meta($occurrence_id, Event_Recurrence::META_OCCURRENCE_ORIGINAL_START, true);
        $original_end = (string) get_post_meta($occurrence_id, Event_Recurrence::META_OCCURRENCE_ORIGINAL_END, true);

        $this->copy_post_shell($occurrence_id, $parent_id);
        $this->copy_all_parent_meta_from_source($occurrence_id, $parent_id, true);

        $delta_start = $this->schedule->timestamp_delta($original_start, $current_start);
        $delta_end = $this->schedule->timestamp_delta($original_end, $current_end);

        if (0 !== $delta_start && '' !== $old_parent_start) {
            update_post_meta($parent_id, '_evt_event_start', $this->schedule->shift_datetime_string($old_parent_start, $delta_start));
        } else {
            update_post_meta($parent_id, '_evt_event_start', $current_start);
        }

        if (0 !== $delta_end && '' !== $old_parent_end) {
            update_post_meta($parent_id, '_evt_event_end', $this->schedule->shift_datetime_string($old_parent_end, $delta_end));
        } else {
            update_post_meta($parent_id, '_evt_event_end', $current_end);
        }

        $this->shift_parent_timeslots($parent_id, $delta_start);
        delete_post_meta($occurrence_id, Event_Recurrence::META_OCCURRENCE_OVERRIDDEN);
    }

    private function regenerate_future_from_occurrence(int $occurrence_id, int $parent_id): void
    {
        $settings = $this->get_settings($parent_id);
        $index = max(1, (int) get_post_meta($occurrence_id, Event_Recurrence::META_OCCURRENCE_INDEX, true));

        $this->copy_post_shell($occurrence_id, $parent_id);
        $this->copy_all_parent_meta_from_source($occurrence_id, $parent_id, true);

        update_post_meta($occurrence_id, Event_Recurrence::META_OCCURRENCE_ORIGINAL_START, (string) get_post_meta($occurrence_id, '_evt_event_start', true));
        update_post_meta($occurrence_id, Event_Recurrence::META_OCCURRENCE_ORIGINAL_END, (string) get_post_meta($occurrence_id, '_evt_event_end', true));
        delete_post_meta($occurrence_id, Event_Recurrence::META_OCCURRENCE_OVERRIDDEN);

        $existing = $this->get_occurrence_posts($parent_id);
        self::$syncing = true;
        foreach ($existing as $existing_index => $child) {
            $child_id = (int) $child->ID;
            if ($existing_index <= $index) {
                continue;
            }
            if ((bool) get_post_meta($child_id, Event_Recurrence::META_OCCURRENCE_OVERRIDDEN, true)) {
                continue;
            }
            wp_delete_post($child_id, true);
        }

        $future_schedule = $this->schedule->build_forward_schedule_from_occurrence($occurrence_id, $settings);
        foreach ($future_schedule as $item) {
            if ((int) $item['index'] <= $index) {
                continue;
            }
            $existing_post = isset($existing[(int) $item['index']]) ? $existing[(int) $item['index']] : null;
            $existing_id = $existing_post ? (int) $existing_post->ID : 0;
            $this->upsert_occurrence($parent_id, $existing_id, $item);
        }
        self::$syncing = false;
    }

    private function copy_post_shell(int $source_id, int $target_id): void
    {
        $source = get_post($source_id);
        if (! $source) {
            return;
        }

        wp_update_post(
            [
                'ID'           => $target_id,
                'post_title'   => $source->post_title,
                'post_content' => $source->post_content,
                'post_excerpt' => $source->post_excerpt,
            ]
        );

        $taxonomies = get_object_taxonomies(CPT_Events::POST_TYPE);
        foreach ($taxonomies as $taxonomy) {
            $terms = wp_get_object_terms($source_id, $taxonomy, ['fields' => 'ids']);
            if (! is_wp_error($terms) && is_array($terms)) {
                wp_set_object_terms($target_id, $terms, $taxonomy, false);
            }
        }

        $thumb_id = get_post_thumbnail_id($source_id);
        if ($thumb_id) {
            set_post_thumbnail($target_id, $thumb_id);
        } else {
            delete_post_thumbnail($target_id);
        }
    }

    private function copy_all_parent_meta_from_source(int $source_id, int $target_id, bool $preserve_parent_schedule): void
    {
        $all_meta = get_post_meta($source_id);
        foreach ($all_meta as $key => $values) {
            if (! is_string($key) || '' === $key || $this->should_skip_meta_for_parent_copy($key, $preserve_parent_schedule)) {
                continue;
            }

            delete_post_meta($target_id, $key);
            foreach ((array) $values as $value) {
                add_post_meta($target_id, $key, maybe_unserialize($value));
            }
        }
    }

    private function should_skip_meta_for_parent_copy(string $meta_key, bool $preserve_parent_schedule): bool
    {
        $skip = [
            '_edit_lock',
            '_edit_last',
            Event_Meta_Timestamps::START_TS_META,
            Event_Meta_Timestamps::END_TS_META,
            Event_Recurrence::META_SERIES_PARENT,
            Event_Recurrence::META_IS_OCCURRENCE,
            Event_Recurrence::META_OCCURRENCE_INDEX,
            Event_Recurrence::META_OCCURRENCE_ORIGINAL_START,
            Event_Recurrence::META_OCCURRENCE_ORIGINAL_END,
            Event_Recurrence::META_OCCURRENCE_OVERRIDDEN,
        ];

        if ($preserve_parent_schedule) {
            $skip[] = '_evt_event_start';
            $skip[] = '_evt_event_end';
            $skip[] = Event_Timeslots::META_KEY_SLOTS;
        }

        return in_array($meta_key, $skip, true);
    }

    private function shift_parent_timeslots(int $parent_id, int $delta_seconds): void
    {
        if (0 === $delta_seconds) {
            return;
        }

        $slots = get_post_meta($parent_id, Event_Timeslots::META_KEY_SLOTS, true);
        if (! is_array($slots)) {
            return;
        }

        foreach ($slots as &$slot) {
            if (! is_array($slot)) {
                continue;
            }
            if (! empty($slot['start'])) {
                $slot['start'] = $this->schedule->shift_datetime_string((string) $slot['start'], $delta_seconds);
            }
            if (! empty($slot['end'])) {
                $slot['end'] = $this->schedule->shift_datetime_string((string) $slot['end'], $delta_seconds);
            }
        }
        unset($slot);

        update_post_meta($parent_id, Event_Timeslots::META_KEY_SLOTS, $slots);
    }

    /**
     * @param array{index:int,start:string,end:string,label:string} $item
     */
    private function upsert_occurrence(int $parent_id, int $occurrence_id, array $item): int
    {
        $parent = get_post($parent_id);
        if (! $parent) {
            return 0;
        }

        $payload = [
            'post_type'    => CPT_Events::POST_TYPE,
            'post_status'  => $parent->post_status,
            'post_title'   => $parent->post_title,
            'post_content' => $parent->post_content,
            'post_excerpt' => $parent->post_excerpt,
            'post_author'  => $parent->post_author,
            'post_parent'  => $parent_id,
        ];

        if ($occurrence_id > 0) {
            $payload['ID'] = $occurrence_id;
            $result = wp_update_post($payload, true);
        } else {
            $result = wp_insert_post($payload, true);
        }

        if (is_wp_error($result) || ! $result) {
            return 0;
        }

        $occurrence_id = (int) $result;
        $this->sync_occurrence_from_parent($parent_id, $occurrence_id, $item);
        return $occurrence_id;
    }

    /**
     * @param array{index:int,start:string,end:string,label:string} $item
     */
    private function sync_occurrence_from_parent(int $parent_id, int $occurrence_id, array $item): void
    {
        $this->copy_post_shell($parent_id, $occurrence_id);

        $all_meta = get_post_meta($parent_id);
        foreach ($all_meta as $key => $values) {
            if (! is_string($key) || '' === $key || $this->should_skip_parent_meta_for_occurrence($key)) {
                continue;
            }

            delete_post_meta($occurrence_id, $key);
            foreach ((array) $values as $value) {
                add_post_meta($occurrence_id, $key, maybe_unserialize($value));
            }
        }

        update_post_meta($occurrence_id, Event_Recurrence::META_SERIES_PARENT, $parent_id);
        update_post_meta($occurrence_id, Event_Recurrence::META_IS_OCCURRENCE, '1');
        update_post_meta($occurrence_id, Event_Recurrence::META_OCCURRENCE_INDEX, (int) $item['index']);
        update_post_meta($occurrence_id, Event_Recurrence::META_OCCURRENCE_ORIGINAL_START, $item['start']);
        update_post_meta($occurrence_id, Event_Recurrence::META_OCCURRENCE_ORIGINAL_END, $item['end']);
        update_post_meta($occurrence_id, '_evt_event_start', $item['start']);
        update_post_meta($occurrence_id, '_evt_event_end', $item['end']);

        $shifted_slots = $this->shift_slots_for_occurrence($parent_id, $item['start']);
        if (! empty($shifted_slots)) {
            update_post_meta($occurrence_id, Event_Timeslots::META_KEY_SLOTS, $shifted_slots);
        } else {
            delete_post_meta($occurrence_id, Event_Timeslots::META_KEY_SLOTS);
        }

        $this->update_timestamp_meta($occurrence_id, $item['start'], $item['end']);
    }

    private function should_skip_parent_meta_for_occurrence(string $meta_key): bool
    {
        return in_array(
            $meta_key,
            [
                '_edit_lock',
                '_edit_last',
                Event_Meta_Timestamps::START_TS_META,
                Event_Meta_Timestamps::END_TS_META,
                Event_Recurrence::META_ENABLED,
                Event_Recurrence::META_FREQ,
                Event_Recurrence::META_INTERVAL,
                Event_Recurrence::META_WEEKDAYS,
                Event_Recurrence::META_END_TYPE,
                Event_Recurrence::META_UNTIL,
                Event_Recurrence::META_COUNT,
                Event_Recurrence::META_SERIES_PARENT,
                Event_Recurrence::META_IS_SERIES,
                Event_Recurrence::META_IS_OCCURRENCE,
                Event_Recurrence::META_OCCURRENCE_INDEX,
                Event_Recurrence::META_OCCURRENCE_ORIGINAL_START,
                Event_Recurrence::META_OCCURRENCE_ORIGINAL_END,
                Event_Recurrence::META_OCCURRENCE_OVERRIDDEN,
            ],
            true
        );
    }

    /**
     * @return array<int,array{id:string,start:string,end:string,capacity:int}>
     */
    private function shift_slots_for_occurrence(int $parent_id, string $occurrence_start): array
    {
        $timeslots = new Event_Timeslots();
        $slots = $timeslots->get_slots($parent_id);
        if (empty($slots)) {
            return [];
        }

        $parent_start = (string) get_post_meta($parent_id, '_evt_event_start', true);
        $delta = $this->schedule->timestamp_delta($parent_start, $occurrence_start);
        if (0 === $delta) {
            return $slots;
        }

        foreach ($slots as &$slot) {
            $slot['start'] = $this->schedule->shift_datetime_string((string) $slot['start'], $delta);
            $slot['end'] = $this->schedule->shift_datetime_string((string) $slot['end'], $delta);
        }
        unset($slot);

        return $slots;
    }

    private function update_timestamp_meta(int $event_id, string $start, string $end): void
    {
        $start_dt = $this->schedule->parse_datetime($start);
        $end_dt = $this->schedule->parse_datetime($end);

        if ($start_dt) {
            update_post_meta($event_id, Event_Meta_Timestamps::START_TS_META, $start_dt->getTimestamp());
        } else {
            delete_post_meta($event_id, Event_Meta_Timestamps::START_TS_META);
        }

        if ($end_dt) {
            update_post_meta($event_id, Event_Meta_Timestamps::END_TS_META, $end_dt->getTimestamp());
        } else {
            delete_post_meta($event_id, Event_Meta_Timestamps::END_TS_META);
        }
    }

    /**
     * @return array<int,\WP_Post>
     */
    public function get_occurrence_posts(int $parent_id): array
    {
        $query = new \WP_Query(
            [
                'post_type'      => CPT_Events::POST_TYPE,
                'post_status'    => ['publish', 'draft', 'future', 'private', 'pending', 'trash'],
                // A single series can legitimately grow large, but guard
                // against pathological unbounded result sets.
                'posts_per_page' => 500,
                'orderby'        => 'meta_value_num',
                'order'          => 'ASC',
                'meta_key'       => Event_Recurrence::META_OCCURRENCE_INDEX,
                'meta_query'     => [
                    [
                        'key'   => Event_Recurrence::META_SERIES_PARENT,
                        'value' => $parent_id,
                    ],
                ],
            ]
        );

        $out = [];
        foreach ((array) $query->posts as $child) {
            $index = (int) get_post_meta($child->ID, Event_Recurrence::META_OCCURRENCE_INDEX, true);
            if ($index > 0) {
                $out[$index] = $child;
            }
        }

        return $out;
    }
}
