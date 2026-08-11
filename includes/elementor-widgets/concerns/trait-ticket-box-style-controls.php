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
 * Shared style controls for the Ticket Box widget.
 */
trait Ticket_Box_Style_Controls
{
    protected function register_ticket_box_style_controls(): void
    {
        $this->start_controls_section(
            'section_style_box',
            [
                'label' => __('Box', 'Event-Tickets-for-Elementor'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Background::get_type(),
            [
                'name'     => 'box_bg',
                'selector' => '{{WRAPPER}} .evt-ticket-box',
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name'     => 'box_border',
                'selector' => '{{WRAPPER}} .evt-ticket-box',
            ]
        );

        $this->add_control(
            'box_radius',
            [
                'label'      => __('Border Radius', 'Event-Tickets-for-Elementor'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 0, 'max' => 30]],
                'selectors'  => [
                    '{{WRAPPER}} .evt-ticket-box' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'box_padding',
            [
                'label'      => __('Padding', 'Event-Tickets-for-Elementor'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors'  => [
                    '{{WRAPPER}} .evt-ticket-box' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name'     => 'box_shadow',
                'selector' => '{{WRAPPER}} .evt-ticket-box',
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_heading',
            [
                'label' => __('Heading', 'Event-Tickets-for-Elementor'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'heading_color',
            [
                'label'     => __('Text Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-ticket-box__heading' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'heading_typography',
                'selector' => '{{WRAPPER}} .evt-ticket-box__heading',
            ]
        );

        $this->add_control(
            'heading_spacing',
            [
                'label'      => __('Spacing', 'Event-Tickets-for-Elementor'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 0, 'max' => 40]],
                'selectors'  => [
                    '{{WRAPPER}} .evt-ticket-box__heading' => 'margin-bottom: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_fields',
            [
                'label' => __('Fields', 'Event-Tickets-for-Elementor'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'label_color',
            [
                'label'     => __('Label Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-ticket-box label' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'label_typography',
                'selector' => '{{WRAPPER}} .evt-ticket-box label',
            ]
        );

        $this->add_control(
            'field_text_color',
            [
                'label'     => __('Field Text Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-ticket-box input, {{WRAPPER}} .evt-ticket-box select' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'field_bg',
            [
                'label'     => __('Field Background', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-ticket-box input, {{WRAPPER}} .evt-ticket-box select' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name'     => 'field_border',
                'selector' => '{{WRAPPER}} .evt-ticket-box input, {{WRAPPER}} .evt-ticket-box select',
            ]
        );

        $this->add_control(
            'field_radius',
            [
                'label'      => __('Field Border Radius', 'Event-Tickets-for-Elementor'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 0, 'max' => 24]],
                'selectors'  => [
                    '{{WRAPPER}} .evt-ticket-box input, {{WRAPPER}} .evt-ticket-box select' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'field_padding',
            [
                'label'      => __('Field Padding', 'Event-Tickets-for-Elementor'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors'  => [
                    '{{WRAPPER}} .evt-ticket-box input, {{WRAPPER}} .evt-ticket-box select' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_button',
            [
                'label' => __('Button', 'Event-Tickets-for-Elementor'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'btn_typography',
                'selector' => '{{WRAPPER}} .evt-ticket-box__submit',
            ]
        );

        $this->start_controls_tabs('tabs_btn');

        $this->start_controls_tab('tab_btn_normal', ['label' => __('Normal', 'Event-Tickets-for-Elementor')]);

        $this->add_control(
            'btn_text_color',
            [
                'label'     => __('Text Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-ticket-box__submit' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Background::get_type(),
            [
                'name'     => 'btn_bg',
                'selector' => '{{WRAPPER}} .evt-ticket-box__submit',
            ]
        );

        $this->end_controls_tab();

        $this->start_controls_tab('tab_btn_hover', ['label' => __('Hover', 'Event-Tickets-for-Elementor')]);

        $this->add_control(
            'btn_text_color_hover',
            [
                'label'     => __('Text Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-ticket-box__submit:hover' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'btn_bg_hover',
            [
                'label'     => __('Background Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-ticket-box__submit:hover' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_tab();
        $this->end_controls_tabs();

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name'     => 'btn_border',
                'selector' => '{{WRAPPER}} .evt-ticket-box__submit',
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
                    '{{WRAPPER}} .evt-ticket-box__submit' => 'border-radius: {{SIZE}}{{UNIT}};',
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
                    '{{WRAPPER}} .evt-ticket-box__submit' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_notice',
            [
                'label' => __('Notice', 'Event-Tickets-for-Elementor'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'notice_text',
            [
                'label'     => __('Text Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-ticket-box__notice' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'notice_typography',
                'selector' => '{{WRAPPER}} .evt-ticket-box__notice',
            ]
        );

        $this->end_controls_section();
    }
}

