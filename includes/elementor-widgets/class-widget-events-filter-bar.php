<?php
namespace EventTicketsElementor\Elementor_Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

if (! defined('ABSPATH')) {
    exit;
}


/**
 * Keyword + date filter bar (client-side state, broadcasts changes).
 */
class Widget_Events_Filter_Bar extends Widget_Base {

    public function get_name() {
        return 'evt_events_filter_bar';
    }

    public function get_title() {
        return __( 'Events Filter Bar', 'Event-Tickets-for-Elementor');
    }

    public function get_icon() {
        return 'eicon-search';
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
        return [ 'evt-tickets-filter-bar' ];
    }

    public function get_script_depends() {
        return [ 'evt-tickets-filter-bar' ];
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
            'show_keyword',
            [
                'label'        => __( 'Show keyword input', 'Event-Tickets-for-Elementor'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'show_date_range',
            [
                'label'        => __( 'Show date range', 'Event-Tickets-for-Elementor'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'keyword_placeholder',
            [
                'label'   => __( 'Keyword placeholder', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::TEXT,
                'default' => __( 'Search events…', 'Event-Tickets-for-Elementor'),
            ]
        );

        $this->add_control(
            'enable_saved_views',
            [
                'label'        => __( 'Enable Saved Views', 'Event-Tickets-for-Elementor'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'from_label',
            [
                'label'   => __( 'From label', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::TEXT,
                'default' => __( 'From', 'Event-Tickets-for-Elementor'),
                'condition' => [
                    'show_date_range' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'to_label',
            [
                'label'   => __( 'To label', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::TEXT,
                'default' => __( 'To', 'Event-Tickets-for-Elementor'),
                'condition' => [
                    'show_date_range' => 'yes',
                ],
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
            'saved_label',
            [
                'label'   => __( 'Label: Saved', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::TEXT,
                'default' => __( 'Saved', 'Event-Tickets-for-Elementor'),
                'condition' => [
                    'enable_saved_views' => 'yes',
                ],
            ]
        );
        $this->add_control(
            'saved_placeholder',
            [
                'label'   => __( 'Placeholder: Saved select', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::TEXT,
                'default' => __( 'Select…', 'Event-Tickets-for-Elementor'),
                'condition' => [
                    'enable_saved_views' => 'yes',
                ],
            ]
        );
        $this->add_control(
            'save_label',
            [
                'label'   => __( 'Button: Save', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::TEXT,
                'default' => __( 'Save', 'Event-Tickets-for-Elementor'),
                'condition' => [
                    'enable_saved_views' => 'yes',
                ],
            ]
        );
        $this->add_control(
            'reset_label',
            [
                'label'   => __( 'Button: Reset', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::TEXT,
                'default' => __( 'Reset', 'Event-Tickets-for-Elementor'),
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $show_keyword = ('yes' === ($settings['show_keyword'] ?? 'yes'));
        $show_dates   = ('yes' === ($settings['show_date_range'] ?? 'yes'));
        $saved_views  = ('yes' === ($settings['enable_saved_views'] ?? 'yes'));

        $saved_label = is_string($settings['saved_label'] ?? '') && trim((string) $settings['saved_label']) !== '' ? trim((string) $settings['saved_label']) : __('Saved', 'Event-Tickets-for-Elementor');
        $saved_placeholder = is_string($settings['saved_placeholder'] ?? '') && trim((string) $settings['saved_placeholder']) !== '' ? trim((string) $settings['saved_placeholder']) : __('Select…', 'Event-Tickets-for-Elementor');
        $save_label = is_string($settings['save_label'] ?? '') && trim((string) $settings['save_label']) !== '' ? trim((string) $settings['save_label']) : __('Save', 'Event-Tickets-for-Elementor');
        $reset_label = is_string($settings['reset_label'] ?? '') && trim((string) $settings['reset_label']) !== '' ? trim((string) $settings['reset_label']) : __('Reset', 'Event-Tickets-for-Elementor');
?>
        <div class="evt-filter-bar"
            data-show-keyword="<?php echo esc_attr($show_keyword ? '1' : '0'); ?>"
            data-show-dates="<?php echo esc_attr($show_dates ? '1' : '0'); ?>"
            data-keyword-placeholder="<?php echo esc_attr((string) ($settings['keyword_placeholder'] ?? '')); ?>"
            data-saved-views="<?php echo esc_attr($saved_views ? '1' : '0'); ?>">
            <form class="evt-filter-bar__form">
                <?php if ($saved_views) : ?>
                    <label class="evt-filter-bar__label">
                        <span><?php echo esc_html($saved_label); ?></span>
                        <select class="evt-filter-bar__saved" name="evt_saved_view">
                            <option value=""><?php echo esc_html($saved_placeholder); ?></option>
                        </select>
                    </label>
                    <button type="button" class="button evt-filter-bar__save"><?php echo esc_html($save_label); ?></button>
                <?php endif; ?>

                <?php if ($show_keyword) : ?>
                    <input type="search" name="evt_keyword" class="evt-filter-bar__keyword" placeholder="<?php echo esc_attr((string) ($settings['keyword_placeholder'] ?? '')); ?>" />
                <?php endif; ?>

                <?php if ($show_dates) : ?>
                    <label class="evt-filter-bar__label">
                        <span><?php echo esc_html((string) ($settings['from_label'] ?? 'From')); ?></span>
                        <input type="date" name="evt_from" class="evt-filter-bar__date" />
                    </label>
                    <label class="evt-filter-bar__label">
                        <span><?php echo esc_html((string) ($settings['to_label'] ?? 'To')); ?></span>
                        <input type="date" name="evt_to" class="evt-filter-bar__date" />
                    </label>
                <?php endif; ?>

                <button type="reset" class="button evt-filter-bar__reset"><?php echo esc_html($reset_label); ?></button>
            </form>
        </div>
<?php
    }
}
