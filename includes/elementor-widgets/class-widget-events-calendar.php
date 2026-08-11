<?php
namespace EventTicketsElementor\Elementor_Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use EventTicketsElementor\Plugin;

if (! defined('ABSPATH')) {
    exit;
}

require_once EVT_TICKETS_PLUGIN_DIR . 'includes/elementor-widgets/concerns/trait-calendar-style-controls.php';

use EventTicketsElementor\Elementor_Widgets\Concerns\Calendar_Style_Controls;

/**
 * Calendar view widget with switchable views (month/week/day/list).
 */
class Widget_Events_Calendar extends Widget_Base {
    use Calendar_Style_Controls;

    public function get_name() {
        return 'evt_events_calendar';
    }

    public function get_title() {
        return __( 'Events Calendar', 'Event-Tickets-for-Elementor');
    }

    public function get_icon() {
        return 'eicon-calendar';
    }

    public function get_categories() {
        return [ 'evt-tickets' ];
    }

    /**
     * Legacy widget kept for backward compatibility.
     * Use Events Browser for new pages.
     */
    public function show_in_panel(): bool {
        return false;
    }

    public function get_style_depends() {
        return [ 'evt-tickets-events-calendar' ];
    }

    public function get_script_depends() {
        return [ 'evt-tickets-events-calendar' ];
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            [
                'label' => __( 'Content', 'Event-Tickets-for-Elementor'),
                'tab'   => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'initial_view',
            [
                'label'   => __( 'Initial View', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::SELECT,
                'default' => 'month',
                'options' => [
                    'month' => __( 'Month', 'Event-Tickets-for-Elementor'),
                    'week'  => __( 'Week', 'Event-Tickets-for-Elementor'),
                    'day'   => __( 'Day', 'Event-Tickets-for-Elementor'),
                    'list'  => __( 'List', 'Event-Tickets-for-Elementor'),
                ],
            ]
        );

        $this->add_control(
            'allow_toggle',
            [
                'label'        => __( 'Allow View Toggle', 'Event-Tickets-for-Elementor'),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => __( 'Yes', 'Event-Tickets-for-Elementor'),
                'label_off'    => __( 'No', 'Event-Tickets-for-Elementor'),
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'show_navigation',
            [
                'label'        => __( 'Show Navigation (prev/next/today)', 'Event-Tickets-for-Elementor'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'hide_cancelled',
            [
                'label'        => __( 'Hide Cancelled Events', 'Event-Tickets-for-Elementor'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'only_capacity',
            [
                'label'        => __( 'Only Events With Capacity', 'Event-Tickets-for-Elementor'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => '',
            ]
        );

        $this->add_control(
            'items_per_list',
            [
                'label'   => __( 'Items in List View', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::NUMBER,
                'default' => 10,
            ]
        );

        $this->add_control(
            'list_range_days',
            [
                'label'       => __( 'List View Range (days)', 'Event-Tickets-for-Elementor'),
                'type'        => Controls_Manager::NUMBER,
                'default'     => 90,
                'min'         => 1,
                'max'         => 3650,
                'description' => __( 'How many days ahead to show in the List view.', 'Event-Tickets-for-Elementor'),
            ]
        );

        $this->add_control(
            'show_filter_bar',
            [
                'label'        => __( 'Show Filter Bar', 'Event-Tickets-for-Elementor'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => '',
            ]
        );

        $this->add_control(
            'timeline_start_hour',
            [
                'label'   => __( 'Timeline Start Hour (week/day)', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::NUMBER,
                'default' => 7,
                'min'     => 0,
                'max'     => 23,
            ]
        );

        $this->add_control(
            'timeline_end_hour',
            [
                'label'   => __( 'Timeline End Hour (week/day)', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::NUMBER,
                'default' => 21,
                'min'     => 1,
                'max'     => 24,
            ]
        );

        $this->add_control(
            'labels_heading',
            [
                'label'     => __( 'Labels / Text', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::RAW_HTML,
                'separator' => 'before',
                'raw'       => '<strong>' . esc_html__( 'Labels / Text', 'Event-Tickets-for-Elementor') . '</strong>',
            ]
        );

        $this->add_control('label_prev', ['label' => __( 'Label: Prev', 'Event-Tickets-for-Elementor'), 'type' => Controls_Manager::TEXT, 'default' => __( 'Prev', 'Event-Tickets-for-Elementor')]);
        $this->add_control('label_next', ['label' => __( 'Label: Next', 'Event-Tickets-for-Elementor'), 'type' => Controls_Manager::TEXT, 'default' => __( 'Next', 'Event-Tickets-for-Elementor')]);
        $this->add_control('label_today', ['label' => __( 'Label: Today', 'Event-Tickets-for-Elementor'), 'type' => Controls_Manager::TEXT, 'default' => __( 'Today', 'Event-Tickets-for-Elementor')]);
        $this->add_control('label_month', ['label' => __( 'Label: Month', 'Event-Tickets-for-Elementor'), 'type' => Controls_Manager::TEXT, 'default' => __( 'Month', 'Event-Tickets-for-Elementor')]);
        $this->add_control('label_week', ['label' => __( 'Label: Week', 'Event-Tickets-for-Elementor'), 'type' => Controls_Manager::TEXT, 'default' => __( 'Week', 'Event-Tickets-for-Elementor')]);
        $this->add_control('label_day', ['label' => __( 'Label: Day', 'Event-Tickets-for-Elementor'), 'type' => Controls_Manager::TEXT, 'default' => __( 'Day', 'Event-Tickets-for-Elementor')]);
        $this->add_control('label_list', ['label' => __( 'Label: List', 'Event-Tickets-for-Elementor'), 'type' => Controls_Manager::TEXT, 'default' => __( 'List', 'Event-Tickets-for-Elementor')]);
        $this->add_control('text_loading', ['label' => __( 'Loading text', 'Event-Tickets-for-Elementor'), 'type' => Controls_Manager::TEXT, 'default' => __( 'Loading events…', 'Event-Tickets-for-Elementor')]);
        $this->add_control('text_no_events', ['label' => __( 'Empty state', 'Event-Tickets-for-Elementor'), 'type' => Controls_Manager::TEXT, 'default' => __( 'No events.', 'Event-Tickets-for-Elementor')]);
        $this->add_control('label_more', ['label' => __( 'Label: “more” suffix', 'Event-Tickets-for-Elementor'), 'type' => Controls_Manager::TEXT, 'default' => __( 'more', 'Event-Tickets-for-Elementor')]);
        $this->add_control('title_upcoming', ['label' => __( 'Title: Upcoming list', 'Event-Tickets-for-Elementor'), 'type' => Controls_Manager::TEXT, 'default' => __( 'Upcoming Events', 'Event-Tickets-for-Elementor')]);

        $this->add_control('fb_saved', ['label' => __( 'Filter bar: Saved', 'Event-Tickets-for-Elementor'), 'type' => Controls_Manager::TEXT, 'default' => __( 'Saved', 'Event-Tickets-for-Elementor'), 'condition' => ['show_filter_bar' => 'yes']]);
        $this->add_control('fb_select', ['label' => __( 'Filter bar: Select placeholder', 'Event-Tickets-for-Elementor'), 'type' => Controls_Manager::TEXT, 'default' => __( 'Select…', 'Event-Tickets-for-Elementor'), 'condition' => ['show_filter_bar' => 'yes']]);
        $this->add_control('fb_save', ['label' => __( 'Filter bar: Save button', 'Event-Tickets-for-Elementor'), 'type' => Controls_Manager::TEXT, 'default' => __( 'Save', 'Event-Tickets-for-Elementor'), 'condition' => ['show_filter_bar' => 'yes']]);
        $this->add_control('fb_reset', ['label' => __( 'Filter bar: Reset button', 'Event-Tickets-for-Elementor'), 'type' => Controls_Manager::TEXT, 'default' => __( 'Reset', 'Event-Tickets-for-Elementor'), 'condition' => ['show_filter_bar' => 'yes']]);
        $this->add_control('fb_from', ['label' => __( 'Filter bar: From', 'Event-Tickets-for-Elementor'), 'type' => Controls_Manager::TEXT, 'default' => __( 'From', 'Event-Tickets-for-Elementor'), 'condition' => ['show_filter_bar' => 'yes']]);
        $this->add_control('fb_to', ['label' => __( 'Filter bar: To', 'Event-Tickets-for-Elementor'), 'type' => Controls_Manager::TEXT, 'default' => __( 'To', 'Event-Tickets-for-Elementor'), 'condition' => ['show_filter_bar' => 'yes']]);
        $this->add_control('fb_placeholder', ['label' => __( 'Filter bar: Keyword placeholder', 'Event-Tickets-for-Elementor'), 'type' => Controls_Manager::TEXT, 'default' => __( 'Search events…', 'Event-Tickets-for-Elementor'), 'condition' => ['show_filter_bar' => 'yes']]);

        $this->end_controls_section();

        $this->register_calendar_style_controls();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $initial_view = $settings['initial_view'];
        $hide_cancelled = ('yes' === $settings['hide_cancelled']);
        $only_capacity  = ('yes' === $settings['only_capacity']);
        $items_per_list = max(1, (int) ($settings['items_per_list'] ?? 10));
        $list_range_days = max(1, (int) ($settings['list_range_days'] ?? 90));
        $timeline_start = isset($settings['timeline_start_hour']) ? (int) $settings['timeline_start_hour'] : 7;
        $timeline_end   = isset($settings['timeline_end_hour']) ? (int) $settings['timeline_end_hour'] : 21;
        $show_filter_bar = ('yes' === ($settings['show_filter_bar'] ?? ''));

        $labels = [
            'prev'        => is_string($settings['label_prev'] ?? '') && trim((string) $settings['label_prev']) !== '' ? trim((string) $settings['label_prev']) : __('Prev', 'Event-Tickets-for-Elementor'),
            'next'        => is_string($settings['label_next'] ?? '') && trim((string) $settings['label_next']) !== '' ? trim((string) $settings['label_next']) : __('Next', 'Event-Tickets-for-Elementor'),
            'today'       => is_string($settings['label_today'] ?? '') && trim((string) $settings['label_today']) !== '' ? trim((string) $settings['label_today']) : __('Today', 'Event-Tickets-for-Elementor'),
            'month'       => is_string($settings['label_month'] ?? '') && trim((string) $settings['label_month']) !== '' ? trim((string) $settings['label_month']) : __('Month', 'Event-Tickets-for-Elementor'),
            'week'        => is_string($settings['label_week'] ?? '') && trim((string) $settings['label_week']) !== '' ? trim((string) $settings['label_week']) : __('Week', 'Event-Tickets-for-Elementor'),
            'day'         => is_string($settings['label_day'] ?? '') && trim((string) $settings['label_day']) !== '' ? trim((string) $settings['label_day']) : __('Day', 'Event-Tickets-for-Elementor'),
            'list'        => is_string($settings['label_list'] ?? '') && trim((string) $settings['label_list']) !== '' ? trim((string) $settings['label_list']) : __('List', 'Event-Tickets-for-Elementor'),
            'loading'     => is_string($settings['text_loading'] ?? '') && trim((string) $settings['text_loading']) !== '' ? trim((string) $settings['text_loading']) : __('Loading events…', 'Event-Tickets-for-Elementor'),
            'no_events'   => is_string($settings['text_no_events'] ?? '') && trim((string) $settings['text_no_events']) !== '' ? trim((string) $settings['text_no_events']) : __('No events.', 'Event-Tickets-for-Elementor'),
            'more'        => is_string($settings['label_more'] ?? '') && trim((string) $settings['label_more']) !== '' ? trim((string) $settings['label_more']) : __('more', 'Event-Tickets-for-Elementor'),
            'upcoming'    => is_string($settings['title_upcoming'] ?? '') && trim((string) $settings['title_upcoming']) !== '' ? trim((string) $settings['title_upcoming']) : __('Upcoming Events', 'Event-Tickets-for-Elementor'),
            'fb_saved'    => is_string($settings['fb_saved'] ?? '') && trim((string) $settings['fb_saved']) !== '' ? trim((string) $settings['fb_saved']) : __('Saved', 'Event-Tickets-for-Elementor'),
            'fb_select'   => is_string($settings['fb_select'] ?? '') && trim((string) $settings['fb_select']) !== '' ? trim((string) $settings['fb_select']) : __('Select…', 'Event-Tickets-for-Elementor'),
            'fb_save'     => is_string($settings['fb_save'] ?? '') && trim((string) $settings['fb_save']) !== '' ? trim((string) $settings['fb_save']) : __('Save', 'Event-Tickets-for-Elementor'),
            'fb_reset'    => is_string($settings['fb_reset'] ?? '') && trim((string) $settings['fb_reset']) !== '' ? trim((string) $settings['fb_reset']) : __('Reset', 'Event-Tickets-for-Elementor'),
            'fb_from'     => is_string($settings['fb_from'] ?? '') && trim((string) $settings['fb_from']) !== '' ? trim((string) $settings['fb_from']) : __('From', 'Event-Tickets-for-Elementor'),
            'fb_to'       => is_string($settings['fb_to'] ?? '') && trim((string) $settings['fb_to']) !== '' ? trim((string) $settings['fb_to']) : __('To', 'Event-Tickets-for-Elementor'),
            'fb_placeholder' => is_string($settings['fb_placeholder'] ?? '') && trim((string) $settings['fb_placeholder']) !== '' ? trim((string) $settings['fb_placeholder']) : __('Search events…', 'Event-Tickets-for-Elementor'),
        ];

        if ($show_filter_bar) {
            wp_enqueue_style('evt-tickets-filter-bar');
            wp_enqueue_script('evt-tickets-filter-bar');
        }

        $anchor_ts = current_time('timestamp');
        $range_start = 0;
        $range_end   = 0;

        if ('month' === $initial_view) {
            $range_start = (int) mktime(0, 0, 0, (int) wp_date('n', $anchor_ts), 1, (int) wp_date('Y', $anchor_ts));
            $range_end   = (int) mktime(23, 59, 59, (int) wp_date('n', $anchor_ts), (int) wp_date('t', $anchor_ts), (int) wp_date('Y', $anchor_ts));
        } elseif ('week' === $initial_view) {
            $start_of_week = (int) get_option('start_of_week', 1);
            $anchor_w = (int) wp_date('w', $anchor_ts);
            $diff = ($anchor_w - $start_of_week + 7) % 7;
            $start_ts = strtotime('-' . $diff . ' days', $anchor_ts);
            $range_start = (int) mktime(0, 0, 0, (int) wp_date('n', $start_ts), (int) wp_date('j', $start_ts), (int) wp_date('Y', $start_ts));
            $range_end   = $range_start + WEEK_IN_SECONDS - 1;
        } elseif ('day' === $initial_view) {
            $range_start = (int) mktime(0, 0, 0, (int) wp_date('n', $anchor_ts), (int) wp_date('j', $anchor_ts), (int) wp_date('Y', $anchor_ts));
            $range_end   = $range_start + DAY_IN_SECONDS - 1;
        } else { // list
            $range_start = $anchor_ts;
            $range_end   = $anchor_ts + ($list_range_days * DAY_IN_SECONDS);
        }

        $query_args = [
            'limit'             => ('list' === $initial_view) ? $items_per_list : -1,
            'order'             => 'ASC',
            'hide_cancelled'    => $hide_cancelled,
            'only_with_capacity'=> $only_capacity,
        ];

        if ($range_start) {
            $query_args['start_ts'] = $range_start;
        }
        if ($range_end) {
            $query_args['end_ts'] = $range_end;
        }

        $feed = Plugin::instance()->event_query->get_feed($query_args);

        $events_payload = array_map(
            function (array $evt) {
                return [
                    'id'       => $evt['id'],
                    'title'    => $evt['title'],
                    'start'    => $evt['start_ts'],
                    'end'      => $evt['end_ts'],
                    'location' => $evt['location'],
                    'url'      => $evt['permalink'],
                ];
            },
            $feed
        );

        $today = current_time('timestamp');
        $year  = (int) wp_date('Y', $today);
        $month = (int) wp_date('n', $today);
        $day   = (int) wp_date('j', $today);
?>
        <div class="evt-events-calendar evt-cal"
            data-initial-view="<?php echo esc_attr($initial_view); ?>"
            data-hide-cancelled="<?php echo esc_attr($hide_cancelled ? '1' : '0'); ?>"
            data-only-capacity="<?php echo esc_attr($only_capacity ? '1' : '0'); ?>"
            data-year="<?php echo esc_attr($year); ?>"
            data-month="<?php echo esc_attr($month); ?>"
            data-day="<?php echo esc_attr($day); ?>"
            data-list-limit="<?php echo esc_attr($items_per_list); ?>"
            data-list-days="<?php echo esc_attr($list_range_days); ?>"
            data-timeline-start="<?php echo esc_attr($timeline_start); ?>"
            data-timeline-end="<?php echo esc_attr($timeline_end); ?>"
            data-labels="<?php echo esc_attr(wp_json_encode($labels)); ?>">
            <div class="evt-cal__header">
                <div class="evt-cal__title"></div>
                <?php if ('yes' === ($settings['show_navigation'] ?? 'yes')) : ?>
                    <div class="evt-cal__nav">
                        <button type="button" class="evt-cal__btn" data-nav="prev"><?php echo esc_html($labels['prev']); ?></button>
                        <button type="button" class="evt-cal__btn" data-nav="today"><?php echo esc_html($labels['today']); ?></button>
                        <button type="button" class="evt-cal__btn" data-nav="next"><?php echo esc_html($labels['next']); ?></button>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($show_filter_bar) : ?>
                <div style="margin: 10px 0;">
                    <div class="evt-filter-bar" data-show-keyword="1" data-show-dates="1" data-keyword-placeholder="<?php echo esc_attr($labels['fb_placeholder']); ?>" data-saved-views="1">
                        <form class="evt-filter-bar__form">
                            <label class="evt-filter-bar__label">
                                <span><?php echo esc_html($labels['fb_saved']); ?></span>
                                <select class="evt-filter-bar__saved" name="evt_saved_view">
                                    <option value=""><?php echo esc_html($labels['fb_select']); ?></option>
                                </select>
                            </label>
                            <button type="button" class="button evt-filter-bar__save"><?php echo esc_html($labels['fb_save']); ?></button>
                            <input type="search" name="evt_keyword" class="evt-filter-bar__keyword" placeholder="<?php echo esc_attr($labels['fb_placeholder']); ?>" />
                            <label class="evt-filter-bar__label">
                                <span><?php echo esc_html($labels['fb_from']); ?></span>
                                <input type="date" name="evt_from" class="evt-filter-bar__date" />
                            </label>
                            <label class="evt-filter-bar__label">
                                <span><?php echo esc_html($labels['fb_to']); ?></span>
                                <input type="date" name="evt_to" class="evt-filter-bar__date" />
                            </label>
                            <button type="reset" class="button evt-filter-bar__reset"><?php echo esc_html($labels['fb_reset']); ?></button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ('yes' === $settings['allow_toggle']) : ?>
                <div class="evt-calendar-toggle evt-cal__toggle">
                    <button type="button" data-view="month" class="evt-cal__btn"><?php echo esc_html($labels['month']); ?></button>
                    <button type="button" data-view="week" class="evt-cal__btn"><?php echo esc_html($labels['week']); ?></button>
                    <button type="button" data-view="day" class="evt-cal__btn"><?php echo esc_html($labels['day']); ?></button>
                    <button type="button" data-view="list" class="evt-cal__btn"><?php echo esc_html($labels['list']); ?></button>
                </div>
            <?php endif; ?>

            <div class="evt-calendar-body evt-cal__body" data-events='<?php echo wp_json_encode($events_payload); ?>'>
                <p><?php echo esc_html($labels['loading']); ?></p>
            </div>
        </div>
<?php
    }
}
