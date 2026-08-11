<?php

namespace EventTicketsElementor\Elementor_Widgets\Concerns;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Style controls for the Events Browser widget.
 *
 * Uses CSS variables to keep markup/CSS lean.
 */
trait Events_Browser_Style_Controls
{
    protected function register_events_browser_style_controls(): void
    {
        $this->start_controls_section(
            'evt_browser_style_sidebar',
            [
                'label' => __('Sidebar', 'Event-Tickets-for-Elementor'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'evt_browser_sidebar_bg',
            [
                'label'     => __('Background', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-browser' => '--evt-sidebar-bg: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'evt_browser_sidebar_text',
            [
                'label'     => __('Text Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-browser' => '--evt-sidebar-text: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'evt_browser_sidebar_border',
            [
                'label'     => __('Divider Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-browser' => '--evt-sidebar-border: {{VALUE}};',
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
                    '{{WRAPPER}} .evt-browser' => '--evt-pill-bg: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'evt_browser_pill_text',
            [
                'label'     => __('Text Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-browser' => '--evt-pill-text: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'evt_browser_pill_border',
            [
                'label'     => __('Border Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-browser' => '--evt-pill-border: {{VALUE}};',
                ],
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

        $this->add_control(
            'evt_browser_header_bg',
            [
                'label'     => __('Background', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-browser' => '--evt-header-bg: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'evt_browser_header_text',
            [
                'label'     => __('Text Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-browser' => '--evt-header-text: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'evt_browser_header_typo',
                'selector' => '{{WRAPPER}} .evt-browser__header',
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'evt_browser_style_buttons',
            [
                'label' => __('Buttons', 'Event-Tickets-for-Elementor'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'evt_browser_btn_bg',
            [
                'label'     => __('Background', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-browser' => '--evt-btn-bg: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'evt_browser_btn_text',
            [
                'label'     => __('Text Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-browser' => '--evt-btn-text: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'evt_browser_btn_border',
            [
                'label'     => __('Border Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-browser' => '--evt-btn-border: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'evt_browser_btn_typo',
                'selector' => '{{WRAPPER}} .evt-browser button, {{WRAPPER}} .evt-browser select, {{WRAPPER}} .evt-browser input',
            ]
        );

        $this->end_controls_section();
    }
}

