<?php

namespace EventTicketsElementor\Elementor_Widgets\Concerns;

use Elementor\Controls_Manager;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Small helper for consistent per-widget label/text controls.
 */
trait Widget_Text_Controls
{
    protected function start_text_controls_section(string $section_id = 'section_text'): void
    {
        $this->start_controls_section(
            $section_id,
            [
                'label' => __('Text / Labels', 'Event-Tickets-for-Elementor'),
                'tab'   => Controls_Manager::TAB_CONTENT,
            ]
        );
    }

    protected function end_text_controls_section(): void
    {
        $this->end_controls_section();
    }

    protected function add_text_control(
        string $id,
        string $label,
        string $default = '',
        string $description = '',
        string $type = Controls_Manager::TEXT
    ): void {
        $args = [
            'label'   => $label,
            'type'    => $type,
            'default' => $default,
        ];
        if ($description !== '') {
            $args['description'] = $description;
        }
        $this->add_control($id, $args);
    }

    protected function get_text_setting(array $settings, string $key, string $fallback): string
    {
        $val = $settings[$key] ?? '';
        $val = is_string($val) ? $val : '';
        $val = trim($val);
        return $val !== '' ? $val : $fallback;
    }
}

