<?php
namespace EventTicketsElementor\Elementor_Widgets;

use Elementor\Controls_Manager;

if (! defined('ABSPATH')) {
    exit;
}

require_once EVT_TICKETS_PLUGIN_DIR . 'includes/elementor-widgets/concerns/trait-widget-text-controls.php';

use EventTicketsElementor\Elementor_Widgets\Concerns\Widget_Text_Controls;

/**
 * Events Browser widget with per-instance text overrides.
 *
 * This wrapper exists to keep changes modular (the base widget file is >500 LOC).
 */
class Widget_Events_Browser_Labeled extends Widget_Events_Browser
{
    use Widget_Text_Controls;

    public function get_name()
    {
        // Keep the same widget name so existing pages keep working.
        return 'evt_events_browser';
    }

    protected function register_controls()
    {
        parent::register_controls();

        $inject_supported = method_exists($this, 'start_injection') && method_exists($this, 'end_injection');
        if ($inject_supported) {
            // Inject label controls into the existing "Content" section (Elementor Form-like UX).
            $this->start_injection(
                [
                    'of' => 'grid_columns',
                    'at' => 'after',
                ]
            );
        } else {
            // Fallback: separate accordion within the Content tab.
            $this->start_controls_section(
                'section_labels',
                [
                    'label' => __('Labels / Text', 'Event-Tickets-for-Elementor'),
                    'tab'   => Controls_Manager::TAB_CONTENT,
                ]
            );
        }

        // Header / search / sidebar.
        $this->add_text_control('lbl_today', __('Today button', 'Event-Tickets-for-Elementor'), __('Today', 'Event-Tickets-for-Elementor'));
        $this->add_text_control('lbl_search_placeholder', __('Search placeholder', 'Event-Tickets-for-Elementor'), __('Search events…', 'Event-Tickets-for-Elementor'));
        $this->add_text_control('lbl_search_button', __('Search button', 'Event-Tickets-for-Elementor'), __('Search', 'Event-Tickets-for-Elementor'));
        $this->add_text_control('lbl_filters_title', __('Filters title', 'Event-Tickets-for-Elementor'), __('Filters', 'Event-Tickets-for-Elementor'));
        $this->add_text_control('lbl_your_selections', __('Your selections', 'Event-Tickets-for-Elementor'), __('Your selections', 'Event-Tickets-for-Elementor'));
        $this->add_text_control('lbl_clear', __('Clear button', 'Event-Tickets-for-Elementor'), __('Clear', 'Event-Tickets-for-Elementor'));

        // View names.
        $this->add_text_control('lbl_view_month', __('View: Month', 'Event-Tickets-for-Elementor'), __('Month', 'Event-Tickets-for-Elementor'));
        $this->add_text_control('lbl_view_week', __('View: Week', 'Event-Tickets-for-Elementor'), __('Week', 'Event-Tickets-for-Elementor'));
        $this->add_text_control('lbl_view_day', __('View: Day', 'Event-Tickets-for-Elementor'), __('Day', 'Event-Tickets-for-Elementor'));
        $this->add_text_control('lbl_view_list', __('View: List', 'Event-Tickets-for-Elementor'), __('List', 'Event-Tickets-for-Elementor'));
        $this->add_text_control('lbl_view_grid', __('View: Grid', 'Event-Tickets-for-Elementor'), __('Grid', 'Event-Tickets-for-Elementor'));
        $this->add_text_control('lbl_view_map', __('View: Map', 'Event-Tickets-for-Elementor'), __('Map', 'Event-Tickets-for-Elementor'));
        $this->add_text_control('lbl_view_summary', __('View: Summary', 'Event-Tickets-for-Elementor'), __('Summary', 'Event-Tickets-for-Elementor'));

        // Accordion headings.
        $this->add_text_control('lbl_acc_category', __('Filter: Event Category', 'Event-Tickets-for-Elementor'), __('Event Category', 'Event-Tickets-for-Elementor'));
        $this->add_text_control('lbl_acc_cost', __('Filter: Cost', 'Event-Tickets-for-Elementor'), __('Cost', 'Event-Tickets-for-Elementor'));
        $this->add_text_control('lbl_acc_tags', __('Filter: Tags', 'Event-Tickets-for-Elementor'), __('Tags', 'Event-Tickets-for-Elementor'));
        $this->add_text_control('lbl_acc_venues', __('Filter: Venues', 'Event-Tickets-for-Elementor'), __('Venues', 'Event-Tickets-for-Elementor'));
        $this->add_text_control('lbl_acc_organizers', __('Filter: Organizers', 'Event-Tickets-for-Elementor'), __('Organizers', 'Event-Tickets-for-Elementor'));
        $this->add_text_control('lbl_acc_day', __('Filter: Day', 'Event-Tickets-for-Elementor'), __('Day', 'Event-Tickets-for-Elementor'));
        $this->add_text_control('lbl_acc_country_city', __('Filter: Country / City', 'Event-Tickets-for-Elementor'), __('Country / City', 'Event-Tickets-for-Elementor'));
        $this->add_text_control('lbl_acc_status', __('Filter: Event Status', 'Event-Tickets-for-Elementor'), __('Event Status', 'Event-Tickets-for-Elementor'));
        $this->add_text_control('lbl_acc_virtual', __('Filter: Virtual Events', 'Event-Tickets-for-Elementor'), __('Virtual Events', 'Event-Tickets-for-Elementor'));
        $this->add_text_control('lbl_acc_date', __('Filter: Date Range', 'Event-Tickets-for-Elementor'), __('Date Range', 'Event-Tickets-for-Elementor'));

        // Common list text.
        $this->add_text_control('lbl_loading', __('Loading text', 'Event-Tickets-for-Elementor'), __('Loading…', 'Event-Tickets-for-Elementor'));
        $this->add_text_control('lbl_no_events', __('Empty state', 'Event-Tickets-for-Elementor'), __('No events.', 'Event-Tickets-for-Elementor'));
        $this->add_text_control('lbl_more', __('“more” suffix', 'Event-Tickets-for-Elementor'), __('more', 'Event-Tickets-for-Elementor'));
        $this->add_text_control('lbl_this_month', __('Month view: This Month', 'Event-Tickets-for-Elementor'), __('This Month', 'Event-Tickets-for-Elementor'));
        $this->add_text_control('lbl_get_tickets', __('CTA: Get Tickets', 'Event-Tickets-for-Elementor'), __('Get Tickets', 'Event-Tickets-for-Elementor'));
        $this->add_text_control('lbl_free', __('Label: Free', 'Event-Tickets-for-Elementor'), __('Free', 'Event-Tickets-for-Elementor'));

        // Filter UI labels.
        $this->add_text_control('lbl_any', __('Filter: Any', 'Event-Tickets-for-Elementor'), __('Any', 'Event-Tickets-for-Elementor'));
        $this->add_text_control('lbl_country', __('Filter: Country', 'Event-Tickets-for-Elementor'), __('Country', 'Event-Tickets-for-Elementor'));
        $this->add_text_control('lbl_city', __('Filter: City', 'Event-Tickets-for-Elementor'), __('City', 'Event-Tickets-for-Elementor'));
        $this->add_text_control('lbl_hide_cancelled', __('Filter: Hide cancelled', 'Event-Tickets-for-Elementor'), __('Hide cancelled', 'Event-Tickets-for-Elementor'));
        $this->add_text_control('lbl_hide_postponed', __('Filter: Hide postponed', 'Event-Tickets-for-Elementor'), __('Hide postponed', 'Event-Tickets-for-Elementor'));
        $this->add_text_control('lbl_virtual', __('Filter: Virtual', 'Event-Tickets-for-Elementor'), __('Virtual', 'Event-Tickets-for-Elementor'));
        $this->add_text_control('lbl_cost_prefix', __('Filter: Cost prefix', 'Event-Tickets-for-Elementor'), __('Cost ($):', 'Event-Tickets-for-Elementor'));
        $this->add_text_control('lbl_from', __('Filter: From', 'Event-Tickets-for-Elementor'), __('From', 'Event-Tickets-for-Elementor'));
        $this->add_text_control('lbl_to', __('Filter: To', 'Event-Tickets-for-Elementor'), __('To', 'Event-Tickets-for-Elementor'));
        $this->add_text_control('lbl_remove', __('Pill button: Remove', 'Event-Tickets-for-Elementor'), __('Remove', 'Event-Tickets-for-Elementor'));

        $this->add_text_control('lbl_virtual_all', __('Virtual: Show all', 'Event-Tickets-for-Elementor'), __('Show all', 'Event-Tickets-for-Elementor'));
        $this->add_text_control('lbl_virtual_only', __('Virtual: Show only virtual', 'Event-Tickets-for-Elementor'), __('Show only virtual', 'Event-Tickets-for-Elementor'));
        $this->add_text_control('lbl_virtual_hide', __('Virtual: Hide virtual', 'Event-Tickets-for-Elementor'), __('Hide virtual', 'Event-Tickets-for-Elementor'));

        // Days (short).
        $this->add_text_control('lbl_dow_sun', __('Day: Sun', 'Event-Tickets-for-Elementor'), __('Sun', 'Event-Tickets-for-Elementor'));
        $this->add_text_control('lbl_dow_mon', __('Day: Mon', 'Event-Tickets-for-Elementor'), __('Mon', 'Event-Tickets-for-Elementor'));
        $this->add_text_control('lbl_dow_tue', __('Day: Tue', 'Event-Tickets-for-Elementor'), __('Tue', 'Event-Tickets-for-Elementor'));
        $this->add_text_control('lbl_dow_wed', __('Day: Wed', 'Event-Tickets-for-Elementor'), __('Wed', 'Event-Tickets-for-Elementor'));
        $this->add_text_control('lbl_dow_thu', __('Day: Thu', 'Event-Tickets-for-Elementor'), __('Thu', 'Event-Tickets-for-Elementor'));
        $this->add_text_control('lbl_dow_fri', __('Day: Fri', 'Event-Tickets-for-Elementor'), __('Fri', 'Event-Tickets-for-Elementor'));
        $this->add_text_control('lbl_dow_sat', __('Day: Sat', 'Event-Tickets-for-Elementor'), __('Sat', 'Event-Tickets-for-Elementor'));

        if ($inject_supported) {
            $this->end_injection();
        } else {
            $this->end_controls_section();
        }
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();

        $labels = [
            'today'            => $this->get_text_setting($settings, 'lbl_today', __('Today', 'Event-Tickets-for-Elementor')),
            'search_placeholder'=> $this->get_text_setting($settings, 'lbl_search_placeholder', __('Search events…', 'Event-Tickets-for-Elementor')),
            'search_button'     => $this->get_text_setting($settings, 'lbl_search_button', __('Search', 'Event-Tickets-for-Elementor')),
            'filters_title'     => $this->get_text_setting($settings, 'lbl_filters_title', __('Filters', 'Event-Tickets-for-Elementor')),
            'your_selections'   => $this->get_text_setting($settings, 'lbl_your_selections', __('Your selections', 'Event-Tickets-for-Elementor')),
            'clear'            => $this->get_text_setting($settings, 'lbl_clear', __('Clear', 'Event-Tickets-for-Elementor')),
            'views' => [
                'month'   => $this->get_text_setting($settings, 'lbl_view_month', __('Month', 'Event-Tickets-for-Elementor')),
                'week'    => $this->get_text_setting($settings, 'lbl_view_week', __('Week', 'Event-Tickets-for-Elementor')),
                'day'     => $this->get_text_setting($settings, 'lbl_view_day', __('Day', 'Event-Tickets-for-Elementor')),
                'list'    => $this->get_text_setting($settings, 'lbl_view_list', __('List', 'Event-Tickets-for-Elementor')),
                'grid'    => $this->get_text_setting($settings, 'lbl_view_grid', __('Grid', 'Event-Tickets-for-Elementor')),
                'map'     => $this->get_text_setting($settings, 'lbl_view_map', __('Map', 'Event-Tickets-for-Elementor')),
                'summary' => $this->get_text_setting($settings, 'lbl_view_summary', __('Summary', 'Event-Tickets-for-Elementor')),
            ],
            'acc' => [
                'category'    => $this->get_text_setting($settings, 'lbl_acc_category', __('Event Category', 'Event-Tickets-for-Elementor')),
                'cost'        => $this->get_text_setting($settings, 'lbl_acc_cost', __('Cost', 'Event-Tickets-for-Elementor')),
                'tags'        => $this->get_text_setting($settings, 'lbl_acc_tags', __('Tags', 'Event-Tickets-for-Elementor')),
                'venues'      => $this->get_text_setting($settings, 'lbl_acc_venues', __('Venues', 'Event-Tickets-for-Elementor')),
                'organizers'  => $this->get_text_setting($settings, 'lbl_acc_organizers', __('Organizers', 'Event-Tickets-for-Elementor')),
                'day'         => $this->get_text_setting($settings, 'lbl_acc_day', __('Day', 'Event-Tickets-for-Elementor')),
                'country_city'=> $this->get_text_setting($settings, 'lbl_acc_country_city', __('Country / City', 'Event-Tickets-for-Elementor')),
                'status'      => $this->get_text_setting($settings, 'lbl_acc_status', __('Event Status', 'Event-Tickets-for-Elementor')),
                'virtual'     => $this->get_text_setting($settings, 'lbl_acc_virtual', __('Virtual Events', 'Event-Tickets-for-Elementor')),
                'date'        => $this->get_text_setting($settings, 'lbl_acc_date', __('Date Range', 'Event-Tickets-for-Elementor')),
            ],
            'loading'        => $this->get_text_setting($settings, 'lbl_loading', __('Loading…', 'Event-Tickets-for-Elementor')),
            'no_events'      => $this->get_text_setting($settings, 'lbl_no_events', __('No events.', 'Event-Tickets-for-Elementor')),
            'more'           => $this->get_text_setting($settings, 'lbl_more', __('more', 'Event-Tickets-for-Elementor')),
            'this_month'     => $this->get_text_setting($settings, 'lbl_this_month', __('This Month', 'Event-Tickets-for-Elementor')),
            'get_tickets'    => $this->get_text_setting($settings, 'lbl_get_tickets', __('Get Tickets', 'Event-Tickets-for-Elementor')),
            'free'           => $this->get_text_setting($settings, 'lbl_free', __('Free', 'Event-Tickets-for-Elementor')),
            'any'            => $this->get_text_setting($settings, 'lbl_any', __('Any', 'Event-Tickets-for-Elementor')),
            'country'        => $this->get_text_setting($settings, 'lbl_country', __('Country', 'Event-Tickets-for-Elementor')),
            'city'           => $this->get_text_setting($settings, 'lbl_city', __('City', 'Event-Tickets-for-Elementor')),
            'hide_cancelled' => $this->get_text_setting($settings, 'lbl_hide_cancelled', __('Hide cancelled', 'Event-Tickets-for-Elementor')),
            'hide_postponed' => $this->get_text_setting($settings, 'lbl_hide_postponed', __('Hide postponed', 'Event-Tickets-for-Elementor')),
            'virtual'        => $this->get_text_setting($settings, 'lbl_virtual', __('Virtual', 'Event-Tickets-for-Elementor')),
            'cost_prefix'    => $this->get_text_setting($settings, 'lbl_cost_prefix', __('Cost ($):', 'Event-Tickets-for-Elementor')),
            'from'           => $this->get_text_setting($settings, 'lbl_from', __('From', 'Event-Tickets-for-Elementor')),
            'to'             => $this->get_text_setting($settings, 'lbl_to', __('To', 'Event-Tickets-for-Elementor')),
            'remove'         => $this->get_text_setting($settings, 'lbl_remove', __('Remove', 'Event-Tickets-for-Elementor')),
            'virtual_all'    => $this->get_text_setting($settings, 'lbl_virtual_all', __('Show all', 'Event-Tickets-for-Elementor')),
            'virtual_only'   => $this->get_text_setting($settings, 'lbl_virtual_only', __('Show only virtual', 'Event-Tickets-for-Elementor')),
            'virtual_hide'   => $this->get_text_setting($settings, 'lbl_virtual_hide', __('Hide virtual', 'Event-Tickets-for-Elementor')),
            'dow_short'      => [
                $this->get_text_setting($settings, 'lbl_dow_sun', __('Sun', 'Event-Tickets-for-Elementor')),
                $this->get_text_setting($settings, 'lbl_dow_mon', __('Mon', 'Event-Tickets-for-Elementor')),
                $this->get_text_setting($settings, 'lbl_dow_tue', __('Tue', 'Event-Tickets-for-Elementor')),
                $this->get_text_setting($settings, 'lbl_dow_wed', __('Wed', 'Event-Tickets-for-Elementor')),
                $this->get_text_setting($settings, 'lbl_dow_thu', __('Thu', 'Event-Tickets-for-Elementor')),
                $this->get_text_setting($settings, 'lbl_dow_fri', __('Fri', 'Event-Tickets-for-Elementor')),
                $this->get_text_setting($settings, 'lbl_dow_sat', __('Sat', 'Event-Tickets-for-Elementor')),
            ],
        ];

        ob_start();
        parent::render();
        $html = (string) ob_get_clean();

        // Inject labels without touching the base widget template.
        $labels_attr = ' data-labels="' . esc_attr(wp_json_encode($labels)) . '" ';
        $html = preg_replace('/\\sdata-config=/', $labels_attr . 'data-config=', $html, 1);

        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
}
