<?php
namespace EventTicketsElementor\Elementor_Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;

if (! defined('ABSPATH')) {
    exit;
}


/**
 * AJAX-driven month calendar widget (Sun–Sat grid).
 */
class Widget_Events_Month_Calendar extends Widget_Base {

    public function get_name() {
        return 'evt_events_month_calendar';
    }

    public function get_title() {
        return __( 'Events Month Calendar', 'Event-Tickets-for-Elementor');
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
        return [ 'evt-tickets-month-calendar' ];
    }

    public function get_script_depends() {
        return [ 'evt-tickets-month-calendar' ];
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
            'hide_cancelled',
            [
                'label'        => __( 'Hide Cancelled Events', 'Event-Tickets-for-Elementor'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'hide_past',
            [
                'label'        => __( 'Hide Past Events', 'Event-Tickets-for-Elementor'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => '',
            ]
        );

        $this->add_control(
            'max_events_per_day',
            [
                'label'   => __( 'Max events per day', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::NUMBER,
                'default' => 3,
                'min'     => 1,
                'max'     => 10,
            ]
        );

        $this->add_control(
            'show_more',
            [
                'label'        => __( 'Show “+X more”', 'Event-Tickets-for-Elementor'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => 'yes',
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
        $this->add_control(
            'label_prev',
            [
                'label'   => __( 'Label: Prev', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::TEXT,
                'default' => __( 'Prev', 'Event-Tickets-for-Elementor'),
            ]
        );
        $this->add_control(
            'label_next',
            [
                'label'   => __( 'Label: Next', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::TEXT,
                'default' => __( 'Next', 'Event-Tickets-for-Elementor'),
            ]
        );
        $this->add_control(
            'label_more',
            [
                'label'   => __( 'Label: “more” suffix', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::TEXT,
                'default' => __( 'more', 'Event-Tickets-for-Elementor'),
            ]
        );
        $this->end_controls_section();

        $this->start_controls_section(
            'section_style',
            [
                'label' => __( 'Style', 'Event-Tickets-for-Elementor'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'event_bg',
            [
                'label' => __( 'Event Background', 'Event-Tickets-for-Elementor'),
                'type'  => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-month-cal__event' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'event_text',
            [
                'label' => __( 'Event Text', 'Event-Tickets-for-Elementor'),
                'type'  => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-month-cal__event' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'event_typography',
                'selector' => '{{WRAPPER}} .evt-month-cal__event',
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $hide_cancelled = ('yes' === ($settings['hide_cancelled'] ?? 'yes'));
        $hide_past      = ('yes' === ($settings['hide_past'] ?? ''));
        $max_per_day    = max(1, (int) ($settings['max_events_per_day'] ?? 3));
        $show_more      = ('yes' === ($settings['show_more'] ?? 'yes'));

        $labels = [
            'prev' => is_string($settings['label_prev'] ?? '') && trim((string) $settings['label_prev']) !== '' ? trim((string) $settings['label_prev']) : __('Prev', 'Event-Tickets-for-Elementor'),
            'next' => is_string($settings['label_next'] ?? '') && trim((string) $settings['label_next']) !== '' ? trim((string) $settings['label_next']) : __('Next', 'Event-Tickets-for-Elementor'),
            'more' => is_string($settings['label_more'] ?? '') && trim((string) $settings['label_more']) !== '' ? trim((string) $settings['label_more']) : __('more', 'Event-Tickets-for-Elementor'),
        ];

        $today = current_time('timestamp');
        $year  = (int) wp_date('Y', $today);
        $month = (int) wp_date('n', $today);
?>
        <div class="evt-month-cal"
            data-year="<?php echo esc_attr($year); ?>"
            data-month="<?php echo esc_attr($month); ?>"
            data-hide-cancelled="<?php echo esc_attr($hide_cancelled ? '1' : '0'); ?>"
            data-hide-past="<?php echo esc_attr($hide_past ? '1' : '0'); ?>"
            data-max-per-day="<?php echo esc_attr($max_per_day); ?>"
            data-show-more="<?php echo esc_attr($show_more ? '1' : '0'); ?>"
            data-labels="<?php echo esc_attr(wp_json_encode($labels)); ?>">
            <div class="evt-month-cal__header">
                <button type="button" class="button" data-nav="prev"><?php echo esc_html($labels['prev']); ?></button>
                <div class="evt-month-cal__title"></div>
                <button type="button" class="button" data-nav="next"><?php echo esc_html($labels['next']); ?></button>
            </div>
            <div class="evt-month-cal__grid" aria-live="polite"></div>
        </div>
<?php
    }
}
