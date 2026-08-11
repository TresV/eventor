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
 * Shared style controls for the Events Calendar widget.
 *
 * Keep widget class lean by extracting the large control surface.
 */
trait Calendar_Style_Controls
{
    protected function register_calendar_style_controls(): void
    {
        $this->start_controls_section(
            'section_style_container',
            [
                'label' => __('Container', 'Event-Tickets-for-Elementor'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Background::get_type(),
            [
                'name'     => 'container_bg',
                'selector' => '{{WRAPPER}} .evt-events-calendar',
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name'     => 'container_border',
                'selector' => '{{WRAPPER}} .evt-events-calendar',
            ]
        );

        $this->add_control(
            'container_radius',
            [
                'label'      => __('Border Radius', 'Event-Tickets-for-Elementor'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 0, 'max' => 30]],
                'selectors'  => [
                    '{{WRAPPER}} .evt-events-calendar' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'container_padding',
            [
                'label'      => __('Padding', 'Event-Tickets-for-Elementor'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors'  => [
                    '{{WRAPPER}} .evt-events-calendar' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name'     => 'container_shadow',
                'selector' => '{{WRAPPER}} .evt-events-calendar',
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_header',
            [
                'label' => __('Header', 'Event-Tickets-for-Elementor'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Background::get_type(),
            [
                'name'     => 'header_bg',
                'selector' => '{{WRAPPER}} .evt-cal__header',
            ]
        );

        $this->add_control(
            'header_border_color',
            [
                'label'     => __('Divider Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-cal__header' => 'border-bottom-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'header_title_color',
            [
                'label'     => __('Title Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-cal__title' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'header_title_typography',
                'selector' => '{{WRAPPER}} .evt-cal__title',
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_buttons',
            [
                'label' => __('Buttons', 'Event-Tickets-for-Elementor'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'btn_typography',
                'selector' => '{{WRAPPER}} .evt-cal__btn',
            ]
        );

        $this->start_controls_tabs('tabs_btn_styles');

        $this->start_controls_tab(
            'tab_btn_normal',
            ['label' => __('Normal', 'Event-Tickets-for-Elementor')]
        );

        $this->add_control(
            'btn_text_color',
            [
                'label'     => __('Text Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-cal__btn' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Background::get_type(),
            [
                'name'     => 'btn_bg',
                'selector' => '{{WRAPPER}} .evt-cal__btn',
            ]
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'tab_btn_hover',
            ['label' => __('Hover', 'Event-Tickets-for-Elementor')]
        );

        $this->add_control(
            'btn_text_color_hover',
            [
                'label'     => __('Text Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-cal__btn:hover' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'btn_bg_hover',
            [
                'label'     => __('Background Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-cal__btn:hover' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'tab_btn_active',
            ['label' => __('Active', 'Event-Tickets-for-Elementor')]
        );

        $this->add_control(
            'btn_text_color_active',
            [
                'label'     => __('Text Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-cal__btn.is-active' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'btn_bg_active',
            [
                'label'     => __('Background Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-cal__btn.is-active' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name'     => 'btn_border',
                'selector' => '{{WRAPPER}} .evt-cal__btn',
            ]
        );

        $this->add_control(
            'btn_radius',
            [
                'label'      => __('Border Radius', 'Event-Tickets-for-Elementor'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 0, 'max' => 30]],
                'selectors'  => [
                    '{{WRAPPER}} .evt-cal__btn' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'btn_padding',
            [
                'label'      => __('Padding', 'Event-Tickets-for-Elementor'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors'  => [
                    '{{WRAPPER}} .evt-cal__btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_month_grid',
            [
                'label' => __('Month Grid', 'Event-Tickets-for-Elementor'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'grid_border_color',
            [
                'label'     => __('Border Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-cal__grid' => 'border-color: {{VALUE}};',
                    '{{WRAPPER}} .evt-cal__cell' => 'border-color: {{VALUE}};',
                    '{{WRAPPER}} .evt-cal__dow-cell' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'dow_bg',
            [
                'label'     => __('Day-of-week Background', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-cal__dow-cell' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'dow_text',
            [
                'label'     => __('Day-of-week Text', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-cal__dow-cell' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'dow_typography',
                'selector' => '{{WRAPPER}} .evt-cal__dow-cell',
            ]
        );

        $this->add_control(
            'cell_bg',
            [
                'label'     => __('Cell Background', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-cal__cell' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'cell_bg_outside',
            [
                'label'     => __('Outside Month Background', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-cal__cell.is-outside' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'cell_bg_today',
            [
                'label'     => __('Today Background', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-cal__cell.is-today' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'daynum_color',
            [
                'label'     => __('Day Number Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-cal__daynum' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'daynum_typography',
                'selector' => '{{WRAPPER}} .evt-cal__daynum',
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_events',
            [
                'label' => __('Events', 'Event-Tickets-for-Elementor'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'event_pill_bg',
            [
                'label'     => __('Pill Background', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-cal__event' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'event_pill_text',
            [
                'label'     => __('Pill Text', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-cal__event' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'event_pill_hover_opacity',
            [
                'label' => __('Hover Opacity', 'Event-Tickets-for-Elementor'),
                'type'  => Controls_Manager::SLIDER,
                'range' => [
                    '%' => ['min' => 30, 'max' => 100],
                ],
                'selectors' => [
                    '{{WRAPPER}} .evt-cal__event:hover' => 'opacity: calc({{SIZE}}/100);',
                ],
            ]
        );

        $this->add_control(
            'event_pill_radius',
            [
                'label'      => __('Pill Radius', 'Event-Tickets-for-Elementor'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 0, 'max' => 24]],
                'selectors'  => [
                    '{{WRAPPER}} .evt-cal__event' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'event_pill_typography',
                'selector' => '{{WRAPPER}} .evt-cal__event',
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_timeline',
            [
                'label' => __('Timeline (Week/Day)', 'Event-Tickets-for-Elementor'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'timeline_border_color',
            [
                'label'     => __('Border Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-cal__timeline' => 'border-color: {{VALUE}};',
                    '{{WRAPPER}} .evt-cal__timeline-hours' => 'border-right-color: {{VALUE}};',
                    '{{WRAPPER}} .evt-cal__timeline-hour' => 'border-bottom-color: {{VALUE}};',
                    '{{WRAPPER}} .evt-cal__timeline-day' => 'border-right-color: {{VALUE}};',
                    '{{WRAPPER}} .evt-cal__timeline-dayhead' => 'border-bottom-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'timeline_hours_bg',
            [
                'label'     => __('Hours Background', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-cal__timeline-hours' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'timeline_hours_text',
            [
                'label'     => __('Hours Text', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-cal__timeline-hour' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'timeline_hours_typography',
                'selector' => '{{WRAPPER}} .evt-cal__timeline-hour',
            ]
        );

        $this->add_control(
            'timeline_dayhead_text',
            [
                'label'     => __('Day Header Text', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-cal__timeline-dayhead' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'timeline_dayhead_typography',
                'selector' => '{{WRAPPER}} .evt-cal__timeline-dayhead',
            ]
        );

        $this->add_control(
            'timeline_block_bg',
            [
                'label'     => __('Event Block Background', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-cal__event--block' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'timeline_block_text',
            [
                'label'     => __('Event Block Text', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-cal__event--block' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'timeline_block_typography',
                'selector' => '{{WRAPPER}} .evt-cal__event--block',
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_list',
            [
                'label' => __('List View', 'Event-Tickets-for-Elementor'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'list_item_divider',
            [
                'label'     => __('Divider Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-cal__list-item' => 'border-bottom-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'list_link_color',
            [
                'label'     => __('Link Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-cal__list-item a' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'list_link_typography',
                'selector' => '{{WRAPPER}} .evt-cal__list-item a',
            ]
        );

        $this->add_control(
            'list_date_color',
            [
                'label'     => __('Date Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-cal__list-date' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'list_date_typography',
                'selector' => '{{WRAPPER}} .evt-cal__list-date',
            ]
        );

        $this->end_controls_section();
    }
}
