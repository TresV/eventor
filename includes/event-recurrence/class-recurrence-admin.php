<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Recurring events: admin meta-box rendering/saving and event-list columns/filter/row actions.
 */
class Recurrence_Admin
{
    private Recurrence_Generator $generator;
    private Recurrence_Schedule $schedule;
    private Recurrence_Operations $operations;

    public function __construct(Recurrence_Generator $generator, Recurrence_Schedule $schedule, Recurrence_Operations $operations)
    {
        $this->generator = $generator;
        $this->schedule = $schedule;
        $this->operations = $operations;
    }

    public function render_fields(\WP_Post $post): void
    {
        $event_id = (int) $post->ID;

        if (Event_Recurrence::is_occurrence($event_id)) {
            $this->render_occurrence_editor($post);
            return;
        }

        $settings = $this->generator->get_settings($event_id);
        $preview = $settings['enabled'] ? $this->schedule->build_occurrence_schedule($event_id, $settings) : [];
        $preview = array_slice($preview, 0, 8);
        $occurrence_count = $this->count_occurrences($event_id);
?>
        <div class="evt-event-toggle">
            <label>
                <input type="checkbox" name="evt_recurrence_enabled" value="1" <?php checked(! empty($settings['enabled'])); ?> data-toggle-target="#evt-event-recurrence-target" />
                <strong><?php esc_html_e('Enable recurring event series', 'Event-Tickets-for-Elementor'); ?></strong>
            </label>
            <p class="description">
                <?php esc_html_e('Use this event as the series template and generate real event occurrences that can each have their own tickets, capacity, check-in, and status.', 'Event-Tickets-for-Elementor'); ?>
            </p>
        </div>

        <div id="evt-event-recurrence-target" class="evt-event-toggle-target">
            <div class="evt-event-grid evt-event-grid--2">
                <p class="evt-event-field">
                    <label for="evt_recurrence_freq"><strong><?php esc_html_e('Frequency', 'Event-Tickets-for-Elementor'); ?></strong></label><br />
                    <select id="evt_recurrence_freq" name="evt_recurrence_freq">
                        <option value="daily" <?php selected($settings['freq'], 'daily'); ?>><?php esc_html_e('Daily', 'Event-Tickets-for-Elementor'); ?></option>
                        <option value="weekly" <?php selected($settings['freq'], 'weekly'); ?>><?php esc_html_e('Weekly', 'Event-Tickets-for-Elementor'); ?></option>
                        <option value="monthly" <?php selected($settings['freq'], 'monthly'); ?>><?php esc_html_e('Monthly', 'Event-Tickets-for-Elementor'); ?></option>
                    </select>
                </p>

                <p class="evt-event-field">
                    <label for="evt_recurrence_interval"><strong><?php esc_html_e('Repeat every', 'Event-Tickets-for-Elementor'); ?></strong></label><br />
                    <input type="number" min="1" max="52" id="evt_recurrence_interval" name="evt_recurrence_interval" class="regular-text" value="<?php echo esc_attr((string) $settings['interval']); ?>" />
                </p>
            </div>

            <div class="evt-event-field evt-event-recurrence__weekdays">
                <strong><?php esc_html_e('Weekdays', 'Event-Tickets-for-Elementor'); ?></strong>
                <p class="description"><?php esc_html_e('Used only for weekly recurrence. If none are selected, the weekday of the main event start date is used.', 'Event-Tickets-for-Elementor'); ?></p>
                <div class="evt-event-recurrence__weekday-grid">
                    <?php foreach ($this->weekday_labels() as $weekday => $label) : ?>
                        <label class="evt-event-recurrence__weekday">
                            <input type="checkbox" name="evt_recurrence_weekdays[]" value="<?php echo esc_attr((string) $weekday); ?>" <?php checked(in_array($weekday, $settings['weekdays'], true)); ?> />
                            <span><?php echo esc_html($label); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="evt-event-grid evt-event-grid--2">
                <p class="evt-event-field">
                    <label for="evt_recurrence_end_type"><strong><?php esc_html_e('End condition', 'Event-Tickets-for-Elementor'); ?></strong></label><br />
                    <select id="evt_recurrence_end_type" name="evt_recurrence_end_type">
                        <option value="count" <?php selected($settings['end_type'], 'count'); ?>><?php esc_html_e('After a number of occurrences', 'Event-Tickets-for-Elementor'); ?></option>
                        <option value="until" <?php selected($settings['end_type'], 'until'); ?>><?php esc_html_e('Until a date', 'Event-Tickets-for-Elementor'); ?></option>
                    </select>
                </p>

                <p class="evt-event-field">
                    <label for="evt_recurrence_count"><strong><?php esc_html_e('Occurrence count', 'Event-Tickets-for-Elementor'); ?></strong></label><br />
                    <input type="number" min="1" max="<?php echo esc_attr((string) Recurrence_Generator::MAX_OCCURRENCES); ?>" id="evt_recurrence_count" name="evt_recurrence_count" class="regular-text" value="<?php echo esc_attr((string) $settings['count']); ?>" />
                </p>
            </div>

            <p class="evt-event-field">
                <label for="evt_recurrence_until"><strong><?php esc_html_e('Repeat until', 'Event-Tickets-for-Elementor'); ?></strong></label><br />
                <input type="date" id="evt_recurrence_until" name="evt_recurrence_until" class="regular-text" value="<?php echo esc_attr($settings['until']); ?>" />
                <span class="description"><?php esc_html_e('Required when the end condition uses a date. The plugin will never generate more than the safety limit for one series refresh.', 'Event-Tickets-for-Elementor'); ?></span>
            </p>

            <div class="evt-event-inline-note">
                <?php
                printf(
                    /* translators: %d is the number of generated occurrences. */
                    esc_html__('Current generated occurrences: %d. Saving this event refreshes the series and creates or updates real event posts for each occurrence.', 'Event-Tickets-for-Elementor'),
                    (int) $occurrence_count
                );
                ?>
            </div>

            <div class="evt-event-recurrence__preview">
                <h4><?php esc_html_e('Upcoming occurrences preview', 'Event-Tickets-for-Elementor'); ?></h4>
                <?php if (! empty($preview)) : ?>
                    <ul class="evt-event-recurrence__preview-list">
                        <?php foreach ($preview as $item) : ?>
                            <li>
                                <strong><?php echo esc_html($item['label']); ?></strong>
                                /* translators: %d is the occurrence number. */
                                <span><?php echo esc_html(sprintf(__('Occurrence %d', 'Event-Tickets-for-Elementor'), (int) $item['index'])); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <p class="description"><?php esc_html_e('Save the event with a valid schedule to generate and preview recurring occurrences.', 'Event-Tickets-for-Elementor'); ?></p>
                <?php endif; ?>
            </div>
        </div>
    <?php
    }

    public function save_fields(int $post_id, \WP_Post $post): void
    {
        if (Recurrence_Generator::$syncing || CPT_Events::POST_TYPE !== $post->post_type) {
            return;
        }

        if (Event_Recurrence::is_occurrence($post_id)) {
            $scope = isset($_POST['evt_recurrence_save_scope']) ? sanitize_key((string) wp_unslash($_POST['evt_recurrence_save_scope'])) : Recurrence_Generator::SAVE_SCOPE_THIS;
            if (! in_array($scope, [Recurrence_Generator::SAVE_SCOPE_THIS, Recurrence_Generator::SAVE_SCOPE_FUTURE, Recurrence_Generator::SAVE_SCOPE_ALL], true)) {
                $scope = Recurrence_Generator::SAVE_SCOPE_THIS;
            }

            if (Recurrence_Generator::SAVE_SCOPE_THIS === $scope) {
                update_post_meta($post_id, Event_Recurrence::META_OCCURRENCE_OVERRIDDEN, '1');
                return;
            }

            $this->generator->apply_occurrence_scope_changes($post_id, $scope);
            return;
        }

        $settings = $this->sanitize_request_settings();
        $had_series = Event_Recurrence::is_series_parent($post_id);

        if (! $settings['enabled']) {
            $this->clear_recurrence_meta($post_id);
            if ($had_series) {
                $this->operations->delete_occurrences_for_series($post_id);
            }
            return;
        }

        $this->store_settings($post_id, $settings);
        update_post_meta($post_id, Event_Recurrence::META_IS_SERIES, '1');
        delete_post_meta($post_id, Event_Recurrence::META_IS_OCCURRENCE);
        delete_post_meta($post_id, Event_Recurrence::META_SERIES_PARENT);
        delete_post_meta($post_id, Event_Recurrence::META_OCCURRENCE_INDEX);
        delete_post_meta($post_id, Event_Recurrence::META_OCCURRENCE_ORIGINAL_START);
        delete_post_meta($post_id, Event_Recurrence::META_OCCURRENCE_ORIGINAL_END);
        delete_post_meta($post_id, Event_Recurrence::META_OCCURRENCE_OVERRIDDEN);

        $this->generator->regenerate_series_from_parent($post_id, $settings);
    }

    /**
     * @param array<string,string> $columns
     * @return array<string,string>
     */
    public function add_admin_columns(array $columns): array
    {
        $out = [];
        foreach ($columns as $key => $label) {
            $out[$key] = $label;
            if ('title' === $key) {
                $out['evt_recurrence_type'] = __('Recurrence', 'Event-Tickets-for-Elementor');
            }
        }
        return $out;
    }

    public function render_admin_column(string $column, int $post_id): void
    {
        if ('evt_recurrence_type' !== $column) {
            return;
        }

        if (Event_Recurrence::is_series_parent($post_id)) {
            $count = $this->count_occurrences($post_id);
            echo '<strong>' . esc_html__('Series Parent', 'Event-Tickets-for-Elementor') . '</strong>';
            /* translators: %d is the number of occurrences. */
            echo '<br /><span class="description">' . esc_html(sprintf(__('Occurrences: %d', 'Event-Tickets-for-Elementor'), $count)) . '</span>';
            return;
        }

        if (Event_Recurrence::is_occurrence($post_id)) {
            $index = (int) get_post_meta($post_id, Event_Recurrence::META_OCCURRENCE_INDEX, true);
            $parent_id = Event_Recurrence::series_parent_id($post_id);
            /* translators: %d is the occurrence number. */
            $label = sprintf(__('Occurrence #%d', 'Event-Tickets-for-Elementor'), max(1, $index));
            echo '<strong>' . esc_html($label) . '</strong>';
            if ($parent_id) {
                echo '<br /><span class="description">' . esc_html(get_the_title($parent_id) ?: ('#' . $parent_id)) . '</span>';
            }
            if ((bool) get_post_meta($post_id, Event_Recurrence::META_OCCURRENCE_OVERRIDDEN, true)) {
                echo '<br /><span class="description">' . esc_html__('Overridden', 'Event-Tickets-for-Elementor') . '</span>';
            }
            return;
        }

        echo '<span class="description">' . esc_html__('Standalone', 'Event-Tickets-for-Elementor') . '</span>';
    }

    public function render_admin_filter(): void
    {
        global $typenow;
        if (CPT_Events::POST_TYPE !== $typenow) {
            return;
        }

        $selected = isset($_GET['evt_event_kind']) ? sanitize_key((string) wp_unslash($_GET['evt_event_kind'])) : '';
    ?>
        <label for="filter_evt_event_kind" class="screen-reader-text"><?php esc_html_e('Filter by event type', 'Event-Tickets-for-Elementor'); ?></label>
        <select id="filter_evt_event_kind" name="evt_event_kind">
            <option value=""><?php esc_html_e('All event types', 'Event-Tickets-for-Elementor'); ?></option>
            <option value="standalone" <?php selected($selected, 'standalone'); ?>><?php esc_html_e('Standalone', 'Event-Tickets-for-Elementor'); ?></option>
            <option value="series" <?php selected($selected, 'series'); ?>><?php esc_html_e('Series parents', 'Event-Tickets-for-Elementor'); ?></option>
            <option value="occurrence" <?php selected($selected, 'occurrence'); ?>><?php esc_html_e('Occurrences', 'Event-Tickets-for-Elementor'); ?></option>
        </select>
    <?php
    }

    public function apply_admin_filter(\WP_Query $query): void
    {
        if (! is_admin() || ! $query->is_main_query() || CPT_Events::POST_TYPE !== $query->get('post_type')) {
            return;
        }

        $series_parent_id = isset($_GET['evt_series_parent_id']) ? absint(wp_unslash($_GET['evt_series_parent_id'])) : 0;
        $kind = isset($_GET['evt_event_kind']) ? sanitize_key((string) wp_unslash($_GET['evt_event_kind'])) : '';
        if ('' === $kind && ! $series_parent_id) {
            return;
        }

        $meta_query = (array) $query->get('meta_query');
        if ('series' === $kind) {
            $meta_query[] = [
                'key'   => Event_Recurrence::META_IS_SERIES,
                'value' => '1',
            ];
        } elseif ('occurrence' === $kind) {
            $meta_query[] = [
                'key'   => Event_Recurrence::META_IS_OCCURRENCE,
                'value' => '1',
            ];
        } elseif ('standalone' === $kind) {
            $meta_query[] = [
                'relation' => 'AND',
                [
                    'relation' => 'OR',
                    [
                        'key'     => Event_Recurrence::META_IS_SERIES,
                        'compare' => 'NOT EXISTS',
                    ],
                    [
                        'key'     => Event_Recurrence::META_IS_SERIES,
                        'value'   => '1',
                        'compare' => '!=',
                    ],
                ],
                [
                    'relation' => 'OR',
                    [
                        'key'     => Event_Recurrence::META_IS_OCCURRENCE,
                        'compare' => 'NOT EXISTS',
                    ],
                    [
                        'key'     => Event_Recurrence::META_IS_OCCURRENCE,
                        'value'   => '1',
                        'compare' => '!=',
                    ],
                ],
            ];
        }

        if ($series_parent_id > 0) {
            $meta_query[] = [
                'key'   => Event_Recurrence::META_SERIES_PARENT,
                'value' => $series_parent_id,
            ];
        }

        $query->set('meta_query', $meta_query);
    }

    /**
     * @param array<string,string> $actions
     * @return array<string,string>
     */
    public function add_row_actions(array $actions, \WP_Post $post): array
    {
        if (CPT_Events::POST_TYPE !== ($post->post_type ?? '')) {
            return $actions;
        }

        $post_id = (int) $post->ID;
        if (Event_Recurrence::is_series_parent($post_id)) {
            $url = add_query_arg(
                [
                    'post_type'       => CPT_Events::POST_TYPE,
                    'evt_event_kind'  => 'occurrence',
                    'evt_series_parent_id' => $post_id,
                ],
                admin_url('edit.php')
            );
            $actions['evt_view_occurrences'] = '<a href="' . esc_url($url) . '">' . esc_html__('View occurrences', 'Event-Tickets-for-Elementor') . '</a>';
        } elseif (Event_Recurrence::is_occurrence($post_id)) {
            $parent_id = Event_Recurrence::series_parent_id($post_id);
            if ($parent_id) {
                $actions['evt_view_series'] = '<a href="' . esc_url(get_edit_post_link($parent_id)) . '">' . esc_html__('View series', 'Event-Tickets-for-Elementor') . '</a>';
            }
        }

        return $actions;
    }

    private function render_occurrence_editor(\WP_Post $post): void
    {
        $event_id = (int) $post->ID;
        $parent_id = Event_Recurrence::series_parent_id($event_id);
        $index = (int) get_post_meta($event_id, Event_Recurrence::META_OCCURRENCE_INDEX, true);
        $overridden = (bool) get_post_meta($event_id, Event_Recurrence::META_OCCURRENCE_OVERRIDDEN, true);
    ?>
        <div class="evt-event-overview__grid">
            <div class="evt-event-overview__item">
                <span class="evt-event-overview__label"><?php esc_html_e('Series Parent', 'Event-Tickets-for-Elementor'); ?></span>
                <strong>
                    <?php if ($parent_id) : ?>
                        <a href="<?php echo esc_url(get_edit_post_link($parent_id)); ?>"><?php echo esc_html(get_the_title($parent_id) ?: ('#' . $parent_id)); ?></a>
                    <?php else : ?>
                        <?php esc_html_e('Unknown', 'Event-Tickets-for-Elementor'); ?>
                    <?php endif; ?>
                </strong>
            </div>
            <div class="evt-event-overview__item">
                <span class="evt-event-overview__label"><?php esc_html_e('Occurrence Index', 'Event-Tickets-for-Elementor'); ?></span>
                <strong><?php echo esc_html((string) max(1, $index)); ?></strong>
            </div>
            <div class="evt-event-overview__item">
                <span class="evt-event-overview__label"><?php esc_html_e('Mode', 'Event-Tickets-for-Elementor'); ?></span>
                <strong><?php echo esc_html($overridden ? __('Overridden occurrence', 'Event-Tickets-for-Elementor') : __('Inherited from series', 'Event-Tickets-for-Elementor')); ?></strong>
            </div>
            <div class="evt-event-overview__item">
                <span class="evt-event-overview__label"><?php esc_html_e('Update Scope', 'Event-Tickets-for-Elementor'); ?></span>
                <strong><?php esc_html_e('Choose below before saving', 'Event-Tickets-for-Elementor'); ?></strong>
            </div>
        </div>

        <p class="evt-event-field">
            <label for="evt_recurrence_save_scope"><strong><?php esc_html_e('When saving changes to this occurrence', 'Event-Tickets-for-Elementor'); ?></strong></label><br />
            <select id="evt_recurrence_save_scope" name="evt_recurrence_save_scope">
                <option value="<?php echo esc_attr(Recurrence_Generator::SAVE_SCOPE_THIS); ?>"><?php esc_html_e('Apply to this occurrence only', 'Event-Tickets-for-Elementor'); ?></option>
                <option value="<?php echo esc_attr(Recurrence_Generator::SAVE_SCOPE_FUTURE); ?>"><?php esc_html_e('Apply to this and future occurrences', 'Event-Tickets-for-Elementor'); ?></option>
                <option value="<?php echo esc_attr(Recurrence_Generator::SAVE_SCOPE_ALL); ?>"><?php esc_html_e('Apply to the entire series', 'Event-Tickets-for-Elementor'); ?></option>
            </select>
        </p>
        <p class="description">
            <?php esc_html_e('Occurrences are real event posts. Choosing a broader scope refreshes the generated series while keeping ticketing, check-in, and capacity occurrence-based.', 'Event-Tickets-for-Elementor'); ?>
        </p>
<?php
    }

    /**
     * @return array{enabled:bool,freq:string,interval:int,weekdays:array<int,int>,end_type:string,until:string,count:int}
     */
    private function sanitize_request_settings(): array
    {
        $enabled = ! empty($_POST['evt_recurrence_enabled']);
        $freq = isset($_POST['evt_recurrence_freq']) ? sanitize_key((string) wp_unslash($_POST['evt_recurrence_freq'])) : 'weekly';
        if (! in_array($freq, ['daily', 'weekly', 'monthly'], true)) {
            $freq = 'weekly';
        }

        $interval = isset($_POST['evt_recurrence_interval']) ? max(1, min(52, absint(wp_unslash($_POST['evt_recurrence_interval'])))) : 1;

        $weekdays = [];
        if (isset($_POST['evt_recurrence_weekdays']) && is_array($_POST['evt_recurrence_weekdays'])) {
            foreach ((array) wp_unslash($_POST['evt_recurrence_weekdays']) as $day) {
                $value = (int) $day;
                if ($value >= 0 && $value <= 6) {
                    $weekdays[] = $value;
                }
            }
        }
        $weekdays = array_values(array_unique($weekdays));
        sort($weekdays);

        $end_type = isset($_POST['evt_recurrence_end_type']) ? sanitize_key((string) wp_unslash($_POST['evt_recurrence_end_type'])) : 'count';
        if (! in_array($end_type, ['count', 'until'], true)) {
            $end_type = 'count';
        }

        $until = isset($_POST['evt_recurrence_until']) ? sanitize_text_field((string) wp_unslash($_POST['evt_recurrence_until'])) : '';
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $until)) {
            $until = '';
        }

        $count = isset($_POST['evt_recurrence_count']) ? max(1, min(Recurrence_Generator::MAX_OCCURRENCES, absint($_POST['evt_recurrence_count']))) : 10;

        return [
            'enabled'  => $enabled,
            'freq'     => $freq,
            'interval' => $interval,
            'weekdays' => $weekdays,
            'end_type' => $end_type,
            'until'    => $until,
            'count'    => $count,
        ];
    }

    /**
     * @param array{enabled:bool,freq:string,interval:int,weekdays:array<int,int>,end_type:string,until:string,count:int} $settings
     */
    private function store_settings(int $event_id, array $settings): void
    {
        update_post_meta($event_id, Event_Recurrence::META_ENABLED, '1');
        update_post_meta($event_id, Event_Recurrence::META_FREQ, $settings['freq']);
        update_post_meta($event_id, Event_Recurrence::META_INTERVAL, $settings['interval']);
        update_post_meta($event_id, Event_Recurrence::META_WEEKDAYS, $settings['weekdays']);
        update_post_meta($event_id, Event_Recurrence::META_END_TYPE, $settings['end_type']);

        if ('' !== $settings['until']) {
            update_post_meta($event_id, Event_Recurrence::META_UNTIL, $settings['until']);
        } else {
            delete_post_meta($event_id, Event_Recurrence::META_UNTIL);
        }

        update_post_meta($event_id, Event_Recurrence::META_COUNT, $settings['count']);
    }

    private function clear_recurrence_meta(int $event_id): void
    {
        foreach ([Event_Recurrence::META_ENABLED, Event_Recurrence::META_FREQ, Event_Recurrence::META_INTERVAL, Event_Recurrence::META_WEEKDAYS, Event_Recurrence::META_END_TYPE, Event_Recurrence::META_UNTIL, Event_Recurrence::META_COUNT, Event_Recurrence::META_IS_SERIES] as $meta_key) {
            delete_post_meta($event_id, $meta_key);
        }
    }

    /**
     * @return array<int,string>
     */
    private function weekday_labels(): array
    {
        return [
            0 => __('Sun', 'Event-Tickets-for-Elementor'),
            1 => __('Mon', 'Event-Tickets-for-Elementor'),
            2 => __('Tue', 'Event-Tickets-for-Elementor'),
            3 => __('Wed', 'Event-Tickets-for-Elementor'),
            4 => __('Thu', 'Event-Tickets-for-Elementor'),
            5 => __('Fri', 'Event-Tickets-for-Elementor'),
            6 => __('Sat', 'Event-Tickets-for-Elementor'),
        ];
    }

    private function count_occurrences(int $parent_id): int
    {
        return count($this->generator->get_occurrence_posts($parent_id));
    }
}
