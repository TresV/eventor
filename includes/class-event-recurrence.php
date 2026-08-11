<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

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

    private const SAVE_SCOPE_THIS = 'this_occurrence';
    private const SAVE_SCOPE_FUTURE = 'this_and_future';
    private const SAVE_SCOPE_ALL = 'all';
    private const MAX_OCCURRENCES = 365;

    private static bool $syncing = false;

    public function __construct()
    {
        add_action('evt_tickets_event_section_recurrence', [$this, 'render_fields']);
        add_action('evt_tickets_event_details_save', [$this, 'save_fields'], 60, 2);

        add_filter('manage_' . CPT_Events::POST_TYPE . '_posts_columns', [$this, 'add_admin_columns']);
        add_action('manage_' . CPT_Events::POST_TYPE . '_posts_custom_column', [$this, 'render_admin_column'], 10, 2);
        add_action('restrict_manage_posts', [$this, 'render_admin_filter']);
        add_action('pre_get_posts', [$this, 'apply_admin_filter']);
        add_filter('post_row_actions', [$this, 'add_row_actions'], 20, 2);

        add_action('before_delete_post', [$this, 'delete_children_before_parent_delete']);
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
        $event_id = (int) $post->ID;

        if (self::is_occurrence($event_id)) {
            $this->render_occurrence_editor($post);
            return;
        }

        $settings = $this->get_settings($event_id);
        $preview = $settings['enabled'] ? $this->build_occurrence_schedule($event_id, $settings) : [];
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
                    <input type="number" min="1" max="<?php echo esc_attr((string) self::MAX_OCCURRENCES); ?>" id="evt_recurrence_count" name="evt_recurrence_count" class="regular-text" value="<?php echo esc_attr((string) $settings['count']); ?>" />
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
        if (self::$syncing || CPT_Events::POST_TYPE !== $post->post_type) {
            return;
        }

        if (self::is_occurrence($post_id)) {
            $scope = isset($_POST['evt_recurrence_save_scope']) ? sanitize_key((string) wp_unslash($_POST['evt_recurrence_save_scope'])) : self::SAVE_SCOPE_THIS;
            if (! in_array($scope, [self::SAVE_SCOPE_THIS, self::SAVE_SCOPE_FUTURE, self::SAVE_SCOPE_ALL], true)) {
                $scope = self::SAVE_SCOPE_THIS;
            }

            if (self::SAVE_SCOPE_THIS === $scope) {
                update_post_meta($post_id, self::META_OCCURRENCE_OVERRIDDEN, '1');
                return;
            }

            $this->apply_occurrence_scope_changes($post_id, $scope);
            return;
        }

        $settings = $this->sanitize_request_settings();
        $had_series = self::is_series_parent($post_id);

        if (! $settings['enabled']) {
            $this->clear_recurrence_meta($post_id);
            if ($had_series) {
                $this->delete_occurrences_for_series($post_id);
            }
            return;
        }

        $this->store_settings($post_id, $settings);
        update_post_meta($post_id, self::META_IS_SERIES, '1');
        delete_post_meta($post_id, self::META_IS_OCCURRENCE);
        delete_post_meta($post_id, self::META_SERIES_PARENT);
        delete_post_meta($post_id, self::META_OCCURRENCE_INDEX);
        delete_post_meta($post_id, self::META_OCCURRENCE_ORIGINAL_START);
        delete_post_meta($post_id, self::META_OCCURRENCE_ORIGINAL_END);
        delete_post_meta($post_id, self::META_OCCURRENCE_OVERRIDDEN);

        $this->regenerate_series_from_parent($post_id, $settings);
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

        if (self::is_series_parent($post_id)) {
            $count = $this->count_occurrences($post_id);
            echo '<strong>' . esc_html__('Series Parent', 'Event-Tickets-for-Elementor') . '</strong>';
            /* translators: %d is the number of occurrences. */
            echo '<br /><span class="description">' . esc_html(sprintf(__('Occurrences: %d', 'Event-Tickets-for-Elementor'), $count)) . '</span>';
            return;
        }

        if (self::is_occurrence($post_id)) {
            $index = (int) get_post_meta($post_id, self::META_OCCURRENCE_INDEX, true);
            $parent_id = self::series_parent_id($post_id);
            /* translators: %d is the occurrence number. */
            $label = sprintf(__('Occurrence #%d', 'Event-Tickets-for-Elementor'), max(1, $index));
            echo '<strong>' . esc_html($label) . '</strong>';
            if ($parent_id) {
                echo '<br /><span class="description">' . esc_html(get_the_title($parent_id) ?: ('#' . $parent_id)) . '</span>';
            }
            if ((bool) get_post_meta($post_id, self::META_OCCURRENCE_OVERRIDDEN, true)) {
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
                'key'   => self::META_IS_SERIES,
                'value' => '1',
            ];
        } elseif ('occurrence' === $kind) {
            $meta_query[] = [
                'key'   => self::META_IS_OCCURRENCE,
                'value' => '1',
            ];
        } elseif ('standalone' === $kind) {
            $meta_query[] = [
                'relation' => 'AND',
                [
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
                ],
                [
                    'relation' => 'OR',
                    [
                        'key'     => self::META_IS_OCCURRENCE,
                        'compare' => 'NOT EXISTS',
                    ],
                    [
                        'key'     => self::META_IS_OCCURRENCE,
                        'value'   => '1',
                        'compare' => '!=',
                    ],
                ],
            ];
        }

        if ($series_parent_id > 0) {
            $meta_query[] = [
                'key'   => self::META_SERIES_PARENT,
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
        if (self::is_series_parent($post_id)) {
            $url = add_query_arg(
                [
                    'post_type'       => CPT_Events::POST_TYPE,
                    'evt_event_kind'  => 'occurrence',
                    'evt_series_parent_id' => $post_id,
                ],
                admin_url('edit.php')
            );
            $actions['evt_view_occurrences'] = '<a href="' . esc_url($url) . '">' . esc_html__('View occurrences', 'Event-Tickets-for-Elementor') . '</a>';
        } elseif (self::is_occurrence($post_id)) {
            $parent_id = self::series_parent_id($post_id);
            if ($parent_id) {
                $actions['evt_view_series'] = '<a href="' . esc_url(get_edit_post_link($parent_id)) . '">' . esc_html__('View series', 'Event-Tickets-for-Elementor') . '</a>';
            }
        }

        return $actions;
    }

    public function delete_children_before_parent_delete(int $post_id): void
    {
        $post = get_post($post_id);
        if (! $post || CPT_Events::POST_TYPE !== ($post->post_type ?? '')) {
            return;
        }

        if (! self::is_series_parent($post_id)) {
            return;
        }

        foreach ($this->get_occurrence_posts($post_id) as $child) {
            self::$syncing = true;
            wp_delete_post((int) $child->ID, true);
            self::$syncing = false;
        }
    }

    private function render_occurrence_editor(\WP_Post $post): void
    {
        $event_id = (int) $post->ID;
        $parent_id = self::series_parent_id($event_id);
        $index = (int) get_post_meta($event_id, self::META_OCCURRENCE_INDEX, true);
        $overridden = (bool) get_post_meta($event_id, self::META_OCCURRENCE_OVERRIDDEN, true);
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
                <option value="<?php echo esc_attr(self::SAVE_SCOPE_THIS); ?>"><?php esc_html_e('Apply to this occurrence only', 'Event-Tickets-for-Elementor'); ?></option>
                <option value="<?php echo esc_attr(self::SAVE_SCOPE_FUTURE); ?>"><?php esc_html_e('Apply to this and future occurrences', 'Event-Tickets-for-Elementor'); ?></option>
                <option value="<?php echo esc_attr(self::SAVE_SCOPE_ALL); ?>"><?php esc_html_e('Apply to the entire series', 'Event-Tickets-for-Elementor'); ?></option>
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

        $count = isset($_POST['evt_recurrence_count']) ? max(1, min(self::MAX_OCCURRENCES, absint($_POST['evt_recurrence_count']))) : 10;

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
     * @return array{enabled:bool,freq:string,interval:int,weekdays:array<int,int>,end_type:string,until:string,count:int}
     */
    private function get_settings(int $event_id): array
    {
        $weekdays = get_post_meta($event_id, self::META_WEEKDAYS, true);
        if (! is_array($weekdays)) {
            $weekdays = [];
        }
        $weekdays = array_values(array_filter(array_map('intval', $weekdays), static function ($day): bool {
            return $day >= 0 && $day <= 6;
        }));
        sort($weekdays);

        $freq = (string) get_post_meta($event_id, self::META_FREQ, true);
        if (! in_array($freq, ['daily', 'weekly', 'monthly'], true)) {
            $freq = 'weekly';
        }

        $end_type = (string) get_post_meta($event_id, self::META_END_TYPE, true);
        if (! in_array($end_type, ['count', 'until'], true)) {
            $end_type = 'count';
        }

        $until = (string) get_post_meta($event_id, self::META_UNTIL, true);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $until)) {
            $until = '';
        }

        return [
            'enabled'  => (bool) get_post_meta($event_id, self::META_ENABLED, true),
            'freq'     => $freq,
            'interval' => max(1, (int) get_post_meta($event_id, self::META_INTERVAL, true)),
            'weekdays' => $weekdays,
            'end_type' => $end_type,
            'until'    => $until,
            'count'    => max(1, min(self::MAX_OCCURRENCES, (int) get_post_meta($event_id, self::META_COUNT, true) ?: 10)),
        ];
    }

    /**
     * @param array{enabled:bool,freq:string,interval:int,weekdays:array<int,int>,end_type:string,until:string,count:int} $settings
     */
    private function store_settings(int $event_id, array $settings): void
    {
        update_post_meta($event_id, self::META_ENABLED, '1');
        update_post_meta($event_id, self::META_FREQ, $settings['freq']);
        update_post_meta($event_id, self::META_INTERVAL, $settings['interval']);
        update_post_meta($event_id, self::META_WEEKDAYS, $settings['weekdays']);
        update_post_meta($event_id, self::META_END_TYPE, $settings['end_type']);

        if ('' !== $settings['until']) {
            update_post_meta($event_id, self::META_UNTIL, $settings['until']);
        } else {
            delete_post_meta($event_id, self::META_UNTIL);
        }

        update_post_meta($event_id, self::META_COUNT, $settings['count']);
    }

    private function clear_recurrence_meta(int $event_id): void
    {
        foreach ([self::META_ENABLED, self::META_FREQ, self::META_INTERVAL, self::META_WEEKDAYS, self::META_END_TYPE, self::META_UNTIL, self::META_COUNT, self::META_IS_SERIES] as $meta_key) {
            delete_post_meta($event_id, $meta_key);
        }
    }

    /**
     * @param array{enabled:bool,freq:string,interval:int,weekdays:array<int,int>,end_type:string,until:string,count:int}|null $settings
     */
    private function regenerate_series_from_parent(int $parent_id, ?array $settings = null): void
    {
        $settings = $settings ?: $this->get_settings($parent_id);
        $schedule = $this->build_occurrence_schedule($parent_id, $settings);
        $existing = $this->get_occurrence_posts($parent_id);
        $keep_ids = [];

        self::$syncing = true;

        foreach ($schedule as $item) {
            $index = (int) $item['index'];
            $existing_post = isset($existing[$index]) ? $existing[$index] : null;
            if ($existing_post && (bool) get_post_meta((int) $existing_post->ID, self::META_OCCURRENCE_OVERRIDDEN, true)) {
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
            if ((bool) get_post_meta($child_id, self::META_OCCURRENCE_OVERRIDDEN, true)) {
                continue;
            }
            wp_delete_post($child_id, true);
        }

        self::$syncing = false;
    }

    private function apply_occurrence_scope_changes(int $occurrence_id, string $scope): void
    {
        $parent_id = self::series_parent_id($occurrence_id);
        if (! $parent_id) {
            update_post_meta($occurrence_id, self::META_OCCURRENCE_OVERRIDDEN, '1');
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
        $original_start = (string) get_post_meta($occurrence_id, self::META_OCCURRENCE_ORIGINAL_START, true);
        $original_end = (string) get_post_meta($occurrence_id, self::META_OCCURRENCE_ORIGINAL_END, true);

        $this->copy_post_shell($occurrence_id, $parent_id);
        $this->copy_all_parent_meta_from_source($occurrence_id, $parent_id, true);

        $delta_start = $this->timestamp_delta($original_start, $current_start);
        $delta_end = $this->timestamp_delta($original_end, $current_end);

        if (0 !== $delta_start && '' !== $old_parent_start) {
            update_post_meta($parent_id, '_evt_event_start', $this->shift_datetime_string($old_parent_start, $delta_start));
        } else {
            update_post_meta($parent_id, '_evt_event_start', $current_start);
        }

        if (0 !== $delta_end && '' !== $old_parent_end) {
            update_post_meta($parent_id, '_evt_event_end', $this->shift_datetime_string($old_parent_end, $delta_end));
        } else {
            update_post_meta($parent_id, '_evt_event_end', $current_end);
        }

        $this->shift_parent_timeslots($parent_id, $delta_start);
        delete_post_meta($occurrence_id, self::META_OCCURRENCE_OVERRIDDEN);
    }

    private function regenerate_future_from_occurrence(int $occurrence_id, int $parent_id): void
    {
        $settings = $this->get_settings($parent_id);
        $index = max(1, (int) get_post_meta($occurrence_id, self::META_OCCURRENCE_INDEX, true));

        $this->copy_post_shell($occurrence_id, $parent_id);
        $this->copy_all_parent_meta_from_source($occurrence_id, $parent_id, true);

        update_post_meta($occurrence_id, self::META_OCCURRENCE_ORIGINAL_START, (string) get_post_meta($occurrence_id, '_evt_event_start', true));
        update_post_meta($occurrence_id, self::META_OCCURRENCE_ORIGINAL_END, (string) get_post_meta($occurrence_id, '_evt_event_end', true));
        delete_post_meta($occurrence_id, self::META_OCCURRENCE_OVERRIDDEN);

        $existing = $this->get_occurrence_posts($parent_id);
        self::$syncing = true;
        foreach ($existing as $existing_index => $child) {
            $child_id = (int) $child->ID;
            if ($existing_index <= $index) {
                continue;
            }
            if ((bool) get_post_meta($child_id, self::META_OCCURRENCE_OVERRIDDEN, true)) {
                continue;
            }
            wp_delete_post($child_id, true);
        }

        $future_schedule = $this->build_forward_schedule_from_occurrence($occurrence_id, $settings);
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
            self::META_SERIES_PARENT,
            self::META_IS_OCCURRENCE,
            self::META_OCCURRENCE_INDEX,
            self::META_OCCURRENCE_ORIGINAL_START,
            self::META_OCCURRENCE_ORIGINAL_END,
            self::META_OCCURRENCE_OVERRIDDEN,
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
                $slot['start'] = $this->shift_datetime_string((string) $slot['start'], $delta_seconds);
            }
            if (! empty($slot['end'])) {
                $slot['end'] = $this->shift_datetime_string((string) $slot['end'], $delta_seconds);
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

        update_post_meta($occurrence_id, self::META_SERIES_PARENT, $parent_id);
        update_post_meta($occurrence_id, self::META_IS_OCCURRENCE, '1');
        update_post_meta($occurrence_id, self::META_OCCURRENCE_INDEX, (int) $item['index']);
        update_post_meta($occurrence_id, self::META_OCCURRENCE_ORIGINAL_START, $item['start']);
        update_post_meta($occurrence_id, self::META_OCCURRENCE_ORIGINAL_END, $item['end']);
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
                self::META_ENABLED,
                self::META_FREQ,
                self::META_INTERVAL,
                self::META_WEEKDAYS,
                self::META_END_TYPE,
                self::META_UNTIL,
                self::META_COUNT,
                self::META_SERIES_PARENT,
                self::META_IS_SERIES,
                self::META_IS_OCCURRENCE,
                self::META_OCCURRENCE_INDEX,
                self::META_OCCURRENCE_ORIGINAL_START,
                self::META_OCCURRENCE_ORIGINAL_END,
                self::META_OCCURRENCE_OVERRIDDEN,
            ],
            true
        );
    }

    /**
     * @return array<int,array{index:int,start:string,end:string,label:string}>
     */
    private function build_occurrence_schedule(int $event_id, array $settings): array
    {
        $seed_start = (string) get_post_meta($event_id, '_evt_event_start', true);
        $seed_end = (string) get_post_meta($event_id, '_evt_event_end', true);
        $start = $this->parse_datetime($seed_start);
        $end = $this->parse_datetime($seed_end);
        if (! $start || ! $end || $end <= $start) {
            return [];
        }

        return $this->build_schedule_from_anchor($start, $end, $settings, 1);
    }

    /**
     * @return array<int,array{index:int,start:string,end:string,label:string}>
     */
    private function build_forward_schedule_from_occurrence(int $occurrence_id, array $settings): array
    {
        $index = max(1, (int) get_post_meta($occurrence_id, self::META_OCCURRENCE_INDEX, true));
        $start = $this->parse_datetime((string) get_post_meta($occurrence_id, '_evt_event_start', true));
        $end = $this->parse_datetime((string) get_post_meta($occurrence_id, '_evt_event_end', true));
        if (! $start || ! $end || $end <= $start) {
            return [];
        }

        if ('count' === $settings['end_type']) {
            $settings['count'] = max(1, $settings['count'] - $index + 1);
        }

        return $this->build_schedule_from_anchor($start, $end, $settings, $index);
    }

    /**
     * @return array<int,array{index:int,start:string,end:string,label:string}>
     */
    private function build_schedule_from_anchor(\DateTimeImmutable $seed_start, \DateTimeImmutable $seed_end, array $settings, int $index_start): array
    {
        $duration = $seed_end->getTimestamp() - $seed_start->getTimestamp();
        if ($duration <= 0) {
            return [];
        }

        $limit = $settings['count'];
        if ('until' === $settings['end_type'] && '' === $settings['until']) {
            $limit = min($limit, 10);
        }
        $limit = max(1, min(self::MAX_OCCURRENCES, $limit));

        $until_end = null;
        if ('until' === $settings['end_type'] && '' !== $settings['until']) {
            $until_end = $this->parse_datetime($settings['until'] . ' 23:59');
        }

        $items = [];
        if ('daily' === $settings['freq']) {
            for ($i = 0; $i < self::MAX_OCCURRENCES; $i++) {
                $start = $seed_start->modify('+' . ($i * $settings['interval']) . ' day');
                if (! $start) {
                    break;
                }
                if ($until_end && $start > $until_end) {
                    break;
                }
                $end = $start->modify('+' . $duration . ' second');
                $items[] = $this->schedule_item($index_start + $i, $start, $end);
                if ('count' === $settings['end_type'] && count($items) >= $limit) {
                    break;
                }
            }
            return $items;
        }

        if ('monthly' === $settings['freq']) {
            for ($i = 0; $i < self::MAX_OCCURRENCES; $i++) {
                $start = $this->add_months_preserve_day($seed_start, $settings['interval'] * $i);
                if ($until_end && $start > $until_end) {
                    break;
                }
                $end = $start->modify('+' . $duration . ' second');
                $items[] = $this->schedule_item($index_start + $i, $start, $end);
                if ('count' === $settings['end_type'] && count($items) >= $limit) {
                    break;
                }
            }
            return $items;
        }

        $weekdays = ! empty($settings['weekdays']) ? $settings['weekdays'] : [(int) $seed_start->format('w')];
        sort($weekdays);

        $seed_day = $seed_start->setTime(0, 0);
        $seed_week_start = $seed_day->modify('-' . (int) $seed_day->format('w') . ' day');
        $cursor = $seed_day;
        $seen = 0;

        while ($seen < self::MAX_OCCURRENCES) {
            $candidate_week_start = $cursor->modify('-' . (int) $cursor->format('w') . ' day');
            $weeks_diff = (int) floor(($candidate_week_start->getTimestamp() - $seed_week_start->getTimestamp()) / WEEK_IN_SECONDS);
            $weekday = (int) $cursor->format('w');

            if ($weeks_diff >= 0 && 0 === ($weeks_diff % $settings['interval']) && in_array($weekday, $weekdays, true)) {
                $start = $cursor->setTime((int) $seed_start->format('H'), (int) $seed_start->format('i'));
                if ($start >= $seed_start) {
                    if ($until_end && $start > $until_end) {
                        break;
                    }
                    $end = $start->modify('+' . $duration . ' second');
                    $items[] = $this->schedule_item($index_start + count($items), $start, $end);
                    if ('count' === $settings['end_type'] && count($items) >= $limit) {
                        break;
                    }
                }
            }

            $cursor = $cursor->modify('+1 day');
            $seen++;
        }

        return $items;
    }

    /**
     * @return array{index:int,start:string,end:string,label:string}
     */
    private function schedule_item(int $index, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $tz_label = Event_Query::timezone_label();
        $label = sprintf(
            '%s %s - %s (%s)',
            wp_date(get_option('date_format'), $start->getTimestamp()),
            wp_date(get_option('time_format'), $start->getTimestamp()),
            wp_date(get_option('time_format'), $end->getTimestamp()),
            $tz_label
        );

        return [
            'index' => $index,
            'start' => $start->format('Y-m-d H:i'),
            'end'   => $end->format('Y-m-d H:i'),
            'label' => $label,
        ];
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
        $delta = $this->timestamp_delta($parent_start, $occurrence_start);
        if (0 === $delta) {
            return $slots;
        }

        foreach ($slots as &$slot) {
            $slot['start'] = $this->shift_datetime_string((string) $slot['start'], $delta);
            $slot['end'] = $this->shift_datetime_string((string) $slot['end'], $delta);
        }
        unset($slot);

        return $slots;
    }

    private function shift_datetime_string(string $value, int $delta): string
    {
        $date = $this->parse_datetime($value);
        if (! $date || 0 === $delta) {
            return $value;
        }

        return $date->modify(($delta >= 0 ? '+' : '') . $delta . ' second')->format('Y-m-d H:i');
    }

    private function timestamp_delta(string $from, string $to): int
    {
        $from_dt = $this->parse_datetime($from);
        $to_dt = $this->parse_datetime($to);
        if (! $from_dt || ! $to_dt) {
            return 0;
        }

        return $to_dt->getTimestamp() - $from_dt->getTimestamp();
    }

    private function update_timestamp_meta(int $event_id, string $start, string $end): void
    {
        $start_dt = $this->parse_datetime($start);
        $end_dt = $this->parse_datetime($end);

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

    private function parse_datetime(string $value): ?\DateTimeImmutable
    {
        $value = trim($value);
        if ('' === $value) {
            return null;
        }

        $timezone = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone('UTC');
        $date = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $value, $timezone);
        if ($date instanceof \DateTimeImmutable) {
            return $date;
        }

        $fallback = date_create_immutable($value, $timezone);
        return $fallback instanceof \DateTimeImmutable ? $fallback : null;
    }

    private function add_months_preserve_day(\DateTimeImmutable $seed_start, int $months): \DateTimeImmutable
    {
        if ($months <= 0) {
            return $seed_start;
        }

        $year = (int) $seed_start->format('Y');
        $month = (int) $seed_start->format('n') + $months;
        $year += (int) floor(($month - 1) / 12);
        $month = (($month - 1) % 12) + 1;

        $day = (int) $seed_start->format('j');
        $last_day = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $day = min($day, $last_day);

        $timezone = $seed_start->getTimezone();
        return \DateTimeImmutable::createFromFormat(
            'Y-n-j H:i',
            sprintf('%d-%d-%d %02d:%02d', $year, $month, $day, (int) $seed_start->format('H'), (int) $seed_start->format('i')),
            $timezone
        ) ?: $seed_start;
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

    /**
     * @return array<int,\WP_Post>
     */
    private function get_occurrence_posts(int $parent_id): array
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
                'meta_key'       => self::META_OCCURRENCE_INDEX,
                'meta_query'     => [
                    [
                        'key'   => self::META_SERIES_PARENT,
                        'value' => $parent_id,
                    ],
                ],
            ]
        );

        $out = [];
        foreach ((array) $query->posts as $child) {
            $index = (int) get_post_meta($child->ID, self::META_OCCURRENCE_INDEX, true);
            if ($index > 0) {
                $out[$index] = $child;
            }
        }

        return $out;
    }

    private function count_occurrences(int $parent_id): int
    {
        return count($this->get_occurrence_posts($parent_id));
    }

    private function delete_occurrences_for_series(int $parent_id): void
    {
        self::$syncing = true;
        foreach ($this->get_occurrence_posts($parent_id) as $child) {
            wp_delete_post((int) $child->ID, true);
        }
        self::$syncing = false;
    }
}
