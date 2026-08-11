<?php

namespace EventTicketsElementor\Elementor_Widgets\Concerns;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Extended style controls for Events Browser.
 *
 * Mirrors the Calendar widget's "extensive" control surface, but targets the browser classes.
 */
trait Events_Browser_Style_Controls_Extended
{
    protected function register_events_browser_style_controls_extended(): void
    {
        $this->start_controls_section(
            'evt_browser_style_container',
            [
                'label' => __('Container', 'Event-Tickets-for-Elementor'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'evt_browser_global_typo',
                'selector' => '{{WRAPPER}} .evt-events-browser',
            ]
        );

        $this->add_group_control(
            Group_Control_Background::get_type(),
            [
                'name'     => 'evt_browser_container_bg',
                'selector' => '{{WRAPPER}} .evt-events-browser',
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name'     => 'evt_browser_container_border',
                'selector' => '{{WRAPPER}} .evt-events-browser',
            ]
        );

        $this->add_control(
            'evt_browser_container_radius',
            [
                'label'      => __('Border Radius', 'Event-Tickets-for-Elementor'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 0, 'max' => 30]],
                'selectors'  => [
                    '{{WRAPPER}} .evt-events-browser' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'evt_browser_container_padding',
            [
                'label'      => __('Padding', 'Event-Tickets-for-Elementor'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors'  => [
                    '{{WRAPPER}} .evt-events-browser' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name'     => 'evt_browser_container_shadow',
                'selector' => '{{WRAPPER}} .evt-events-browser',
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'evt_browser_style_header',
            [
                'label' => __('Header', 'Event-Tickets-for-Elementor'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Background::get_type(),
            [
                'name'     => 'evt_browser_header_bg',
                'selector' => '{{WRAPPER}} .evt-browser__header',
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'evt_browser_header_typo',
                'selector' => '{{WRAPPER}} .evt-browser__header',
            ]
        );

        $this->add_control(
            'evt_browser_header_text',
            [
                'label'     => __('Text Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-browser__header' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'evt_browser_style_buttons',
            [
                'label' => __('Buttons & Inputs', 'Event-Tickets-for-Elementor'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'evt_browser_btn_typo',
                'selector' => '{{WRAPPER}} .evt-browser button, {{WRAPPER}} .evt-browser select, {{WRAPPER}} .evt-browser input',
            ]
        );

        $this->add_control(
            'evt_browser_btn_text_color',
            [
                'label'     => __('Text Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-browser__btn, {{WRAPPER}} .evt-browser__iconbtn' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .evt-browser__searchinput, {{WRAPPER}} .evt-browser__view, {{WRAPPER}} .evt-browser__monthpick, {{WRAPPER}} .evt-browser__from, {{WRAPPER}} .evt-browser__to' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'evt_browser_btn_bg',
            [
                'label'     => __('Background Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-browser__btn, {{WRAPPER}} .evt-browser__iconbtn' => 'background-color: {{VALUE}};',
                    '{{WRAPPER}} .evt-browser__searchinput, {{WRAPPER}} .evt-browser__view, {{WRAPPER}} .evt-browser__monthpick, {{WRAPPER}} .evt-browser__from, {{WRAPPER}} .evt-browser__to' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'evt_browser_btn_border',
            [
                'label'     => __('Border Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-browser__btn, {{WRAPPER}} .evt-browser__iconbtn' => 'border-color: {{VALUE}};',
                    '{{WRAPPER}} .evt-browser__searchinput, {{WRAPPER}} .evt-browser__view, {{WRAPPER}} .evt-browser__monthpick, {{WRAPPER}} .evt-browser__from, {{WRAPPER}} .evt-browser__to' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'evt_browser_style_sidebar',
            [
                'label' => __('Sidebar', 'Event-Tickets-for-Elementor'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'evt_browser_sidebar_width',
            [
                'label'      => __('Width', 'Event-Tickets-for-Elementor'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 180, 'max' => 520]],
                'selectors'  => [
                    '{{WRAPPER}} .evt-browser__layout' => 'grid-template-columns: {{SIZE}}{{UNIT}} 1fr;',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Background::get_type(),
            [
                'name'     => 'evt_browser_sidebar_bg',
                'selector' => '{{WRAPPER}} .evt-browser__sidebar',
            ]
        );

        $this->add_control(
            'evt_browser_sidebar_text',
            [
                'label'     => __('Text Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-browser__sidebar' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'evt_browser_sidebar_divider',
            [
                'label'     => __('Divider Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-browser__sidebar' => 'border-right-color: {{VALUE}};',
                    '{{WRAPPER}} .evt-sidebar__title' => 'border-bottom-color: {{VALUE}};',
                    '{{WRAPPER}} .evt-sidebar__selections' => 'border-bottom-color: {{VALUE}};',
                    '{{WRAPPER}} .evt-acc' => 'border-bottom-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'evt_browser_sidebar_typo',
                'selector' => '{{WRAPPER}} .evt-browser__sidebar',
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'evt_browser_style_pills',
            [
                'label' => __('Selection Pills', 'Event-Tickets-for-Elementor'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'evt_browser_pill_bg',
            [
                'label'     => __('Background', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-pill, {{WRAPPER}} .evt-filter__pillbtn' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'evt_browser_pill_text',
            [
                'label'     => __('Text Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-pill, {{WRAPPER}} .evt-filter__pillbtn' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'evt_browser_pill_border',
            [
                'label'     => __('Border Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-pill, {{WRAPPER}} .evt-filter__pillbtn' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'evt_browser_pill_typo',
                'selector' => '{{WRAPPER}} .evt-pill, {{WRAPPER}} .evt-filter__pillbtn',
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'evt_browser_style_month',
            [
                'label' => __('Month View', 'Event-Tickets-for-Elementor'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'evt_browser_month_dow_typo',
                'selector' => '{{WRAPPER}} .evt-month__dowcell',
            ]
        );

        $this->add_control(
            'evt_browser_month_dow_color',
            [
                'label'     => __('Day-of-week Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-month__dowcell' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'evt_browser_month_daynum_typo',
                'selector' => '{{WRAPPER}} .evt-month__day',
            ]
        );

        $this->add_control(
            'evt_browser_month_daynum_color',
            [
                'label'     => __('Day Number Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-month__day' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'evt_browser_month_event_typo',
                'selector' => '{{WRAPPER}} .evt-month__event',
            ]
        );

        $this->add_control(
            'evt_browser_month_event_bg',
            [
                'label'     => __('Event Pill Background', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-month__event' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'evt_browser_month_event_color',
            [
                'label'     => __('Event Pill Text Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-month__event' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'evt_browser_style_list',
            [
                'label' => __('List / Day / Week / Summary', 'Event-Tickets-for-Elementor'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'evt_browser_list_title_typo',
                'selector' => '{{WRAPPER}} .evt-event-row__title',
            ]
        );

        $this->add_control(
            'evt_browser_list_title_color',
            [
                'label'     => __('Title Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-event-row__title' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'evt_browser_list_meta_typo',
                'selector' => '{{WRAPPER}} .evt-event-row__meta, {{WRAPPER}} .evt-event-row__time',
            ]
        );

        $this->add_control(
            'evt_browser_list_meta_color',
            [
                'label'     => __('Meta Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-event-row__meta, {{WRAPPER}} .evt-event-row__time, {{WRAPPER}} .evt-event-row__dow' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'evt_browser_list_excerpt_typo',
                'selector' => '{{WRAPPER}} .evt-event-row__excerpt',
            ]
        );

        $this->add_control(
            'evt_browser_list_excerpt_color',
            [
                'label'     => __('Excerpt Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-event-row__excerpt' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'evt_browser_list_cta_typo',
                'selector' => '{{WRAPPER}} .evt-event-row__tickets, {{WRAPPER}} .evt-event-row__price',
            ]
        );

        $this->add_control(
            'evt_browser_list_cta_color',
            [
                'label'     => __('CTA Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-event-row__tickets' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'evt_browser_style_grid',
            [
                'label' => __('Grid View', 'Event-Tickets-for-Elementor'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name'     => 'evt_browser_grid_card_border',
                'selector' => '{{WRAPPER}} .evt-card',
            ]
        );

        $this->add_control(
            'evt_browser_grid_card_bg',
            [
                'label'     => __('Card Background', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-card' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'evt_browser_grid_date_typo',
                'selector' => '{{WRAPPER}} .evt-card__date',
            ]
        );

        $this->add_control(
            'evt_browser_grid_date_color',
            [
                'label'     => __('Date Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-card__date' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'evt_browser_grid_title_typo',
                'selector' => '{{WRAPPER}} .evt-card__title',
            ]
        );

        $this->add_control(
            'evt_browser_grid_title_color',
            [
                'label'     => __('Title Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-card__title' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'evt_browser_style_map',
            [
                'label' => __('Map View', 'Event-Tickets-for-Elementor'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'evt_browser_map_height',
            [
                'label'      => __('Map Height', 'Event-Tickets-for-Elementor'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', 'vh'],
                'range'      => [
                    'px' => ['min' => 240, 'max' => 900],
                    'vh' => ['min' => 30, 'max' => 90],
                ],
                'selectors'  => [
                    '{{WRAPPER}} .evt-mapview__map' => 'height: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .evt-mapview__list' => 'max-height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'evt_browser_map_list_typo',
                'selector' => '{{WRAPPER}} .evt-mapview__list',
            ]
        );

        $this->end_controls_section();
    }
}
