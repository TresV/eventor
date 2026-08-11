<?php

namespace EventTicketsElementor\Elementor_Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use EventTicketsElementor\Plugin;

if (! defined('ABSPATH')) {
    exit;
}

require_once EVT_TICKETS_PLUGIN_DIR . 'includes/elementor-widgets/concerns/trait-events-summary-style-controls.php';

use EventTicketsElementor\Elementor_Widgets\Concerns\Events_Summary_Style_Controls;

/**
 * Summary / Photo view widget (list or grid with images).
 */
class Widget_Events_Summary extends Widget_Base
{
    use Events_Summary_Style_Controls;

    public function get_name()
    {
        return 'evt_events_summary';
    }

    public function get_title()
    {
        return __('Events Summary / Photo', 'Event-Tickets-for-Elementor');
    }

    public function get_icon()
    {
        return 'eicon-gallery-group';
    }

    public function get_categories()
    {
        return ['evt-tickets'];
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
            'layout',
            [
                'label'   => __('Layout', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::SELECT,
                'default' => 'summary',
                'options' => [
                    'summary' => __('Summary List', 'Event-Tickets-for-Elementor'),
                    'photo'   => __('Photo Grid', 'Event-Tickets-for-Elementor'),
                ],
            ]
        );

        $this->add_control(
            'limit',
            [
                'label'   => __('Number of events', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::NUMBER,
                'default' => 6,
            ]
        );

        $this->add_control(
            'columns',
            [
                'label'   => __('Columns (photo view)', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::NUMBER,
                'default' => 3,
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

        $this->register_events_summary_style_controls();
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();
        $heading = is_string($settings['heading'] ?? '') ? trim((string) $settings['heading']) : '';
        $text_empty = is_string($settings['text_empty'] ?? '') && trim((string) $settings['text_empty']) !== ''
            ? trim((string) $settings['text_empty'])
            : __('No upcoming events.', 'Event-Tickets-for-Elementor');
        $events = Plugin::instance()->event_query->get_feed([
            'limit'          => (int) $settings['limit'],
            'hide_cancelled' => ('yes' === $settings['hide_cancelled']),
            'start_ts'       => current_time('timestamp'),
        ]);

        $layout = $settings['layout'];
        $columns = max(1, (int) $settings['columns']);
?>
        <div class="evt-events-summary evt-events-summary--<?php echo esc_attr($layout); ?>" data-columns="<?php echo esc_attr($columns); ?>">
            <?php if ($heading) : ?>
                <h3 class="evt-events-summary__heading"><?php echo esc_html($heading); ?></h3>
            <?php endif; ?>
            <?php if (empty($events)) : ?>
                <p><?php echo esc_html($text_empty); ?></p>
            <?php else : ?>
                <?php foreach ($events as $event) : ?>
                    <?php
                    $start_ts = (int) ($event['start_ts'] ?? 0);
                    $thumb    = (string) ($event['image_url'] ?? '');
                    ?>
                    <article class="evt-events-summary__item">
                        <?php if ($thumb && 'photo' === $layout) : ?>
                            <div class="evt-events-summary__image" style="background-image:url('<?php echo esc_url($thumb); ?>');"></div>
                        <?php endif; ?>
                        <div class="evt-events-summary__body">
                            <h4 class="evt-events-summary__title"><a href="<?php echo esc_url($event['permalink']); ?>"><?php echo esc_html($event['title']); ?></a></h4>
                            <?php if ($start_ts) : ?>
                                <p class="evt-events-summary__date"><?php echo esc_html(date_i18n(get_option('date_format'), $start_ts)); ?><?php if (! empty($event['timezone'])) : ?> <span class="evt-events-summary__tz">(<?php echo esc_html($event['timezone']); ?>)</span><?php endif; ?></p>
                            <?php endif; ?>
                            <p class="evt-events-summary__excerpt"><?php echo esc_html(wp_trim_words((string) get_post_field('post_content', (int) $event['id']), 20)); ?></p>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
<?php
    }
}
