<?php

namespace EventTicketsElementor\Elementor_Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use EventTicketsElementor\Plugin;

if (! defined('ABSPATH')) {
    exit;
}

require_once EVT_TICKETS_PLUGIN_DIR . 'includes/elementor-widgets/concerns/trait-events-list-style-controls.php';

use EventTicketsElementor\Elementor_Widgets\Concerns\Events_List_Style_Controls;

/**
 * Simple upcoming events list widget.
 */
class Widget_Events_List extends Widget_Base
{
    use Events_List_Style_Controls;

    public function get_name()
    {
        return 'evt_events_list';
    }

    public function get_title()
    {
        return __('Events List', 'Event-Tickets-for-Elementor');
    }

    public function get_icon()
    {
        return 'eicon-nav-menu';
    }

    public function get_categories()
    {
        return ['evt-tickets'];
    }

    public function get_style_depends()
    {
        return ['evt-tickets-events-list'];
    }

    /**
     * Legacy widget kept for backward compatibility.
     * Use Events Browser for new pages.
     */
    public function show_in_panel(): bool
    {
        return false;
    }

    protected function register_controls()
    {
        $this->start_controls_section(
            'section_content',
            [
                'label' => __('Content', 'Event-Tickets-for-Elementor'),
                'tab'   => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'heading',
            [
                'label'   => __('Heading', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::TEXT,
                'default' => '',
            ]
        );

        $this->add_control(
            'limit',
            [
                'label'   => __('Number of events', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::NUMBER,
                'default' => 5,
            ]
        );

        $this->add_control(
            'hide_cancelled',
            [
                'label'        => __('Hide Cancelled', 'Event-Tickets-for-Elementor'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'only_capacity',
            [
                'label'        => __('Only Events With Capacity', 'Event-Tickets-for-Elementor'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => '',
            ]
        );

        $this->add_control(
            'labels_heading',
            [
                'label'     => __('Labels / Text', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::RAW_HTML,
                'separator' => 'before',
                'raw'       => '<strong>' . esc_html__('Labels / Text', 'Event-Tickets-for-Elementor') . '</strong>',
            ]
        );
        $this->add_control(
            'text_empty',
            [
                'label'   => __('Empty state', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::TEXT,
                'default' => __('No upcoming events.', 'Event-Tickets-for-Elementor'),
            ]
        );
        $this->end_controls_section();

        $this->register_events_list_style_controls();
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();
        $heading = is_string($settings['heading'] ?? '') ? trim((string) $settings['heading']) : '';
        $text_empty = is_string($settings['text_empty'] ?? '') && trim((string) $settings['text_empty']) !== ''
            ? trim((string) $settings['text_empty'])
            : __('No upcoming events.', 'Event-Tickets-for-Elementor');
        $events = Plugin::instance()->event_query->get_feed([
            'limit'             => (int) $settings['limit'],
            'order'             => 'ASC',
            'hide_cancelled'    => ('yes' === $settings['hide_cancelled']),
            'only_with_capacity' => ('yes' === $settings['only_capacity']),
            'start_ts'          => current_time('timestamp'),
        ]);
?>
        <div class="evt-events-list">
            <?php if ($heading) : ?>
                <h3 class="evt-events-list__heading"><?php echo esc_html($heading); ?></h3>
            <?php endif; ?>
            <?php if (empty($events)) : ?>
                <p><?php echo esc_html($text_empty); ?></p>
            <?php else : ?>
                <ul class="evt-events-list__items">
                    <?php foreach ($events as $event) : ?>
                        <li class="evt-events-list__item">
                            <a class="evt-events-list__link" href="<?php echo esc_url($event['permalink']); ?>"><?php echo esc_html($event['title']); ?></a>
                            <?php if (! empty($event['start_ts'])) : ?>
                                <span class="evt-events-list__date"><?php echo esc_html(date_i18n(get_option('date_format'), (int) $event['start_ts'])); ?><?php if (! empty($event['timezone'])) : ?> <span class="evt-events-list__tz">(<?php echo esc_html($event['timezone']); ?>)</span><?php endif; ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
<?php
    }
}
