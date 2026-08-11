<?php

namespace EventTicketsElementor\Elementor_Dynamic_Tags;

use Elementor\Controls_Manager;
use Elementor\Modules\DynamicTags\Module;

if (! defined('ABSPATH')) {
    exit;
}

class Event_Date_Tag extends Event_Tag_Base
{
    private string $mode;

    public function __construct($mode = 'start', array $data = [])
    {
        if (is_array($mode)) {
            $data = $mode;
            $mode = 'start';
        }

        parent::__construct($data);
        $this->mode = ('end' === $mode) ? 'end' : 'start';
    }

    public function get_name()
    {
        return 'start' === $this->mode ? 'evt_event_start' : 'evt_event_end';
    }

    public function get_title()
    {
        return 'start' === $this->mode
            ? __('Event Start', 'Event-Tickets-for-Elementor')
            : __('Event End', 'Event-Tickets-for-Elementor');
    }

    public function get_group()
    {
        return 'evt-tickets';
    }

    public function get_categories()
    {
        return [Module::TEXT_CATEGORY];
    }

    protected function register_controls()
    {
        parent::register_controls();

        $this->add_control(
            'format',
            [
                'label'       => __('Format', 'Event-Tickets-for-Elementor'),
                'type'        => Controls_Manager::TEXT,
                'default'     => get_option('date_format') . ' ' . get_option('time_format'),
                'placeholder' => 'Y-m-d H:i',
            ]
        );
    }

    public function get_value(array $options = [])
    {
        $event_id = $this->get_event_id();
        if (! $event_id) {
            return '';
        }

        $meta_key = 'start' === $this->mode ? '_evt_event_start' : '_evt_event_end';
        $raw = (string) get_post_meta($event_id, $meta_key, true);
        if ('' === trim($raw)) {
            return '';
        }

        $ts = strtotime($raw);
        if (! $ts) {
            return $raw;
        }

        $format = (string) $this->get_settings('format');
        $format = $format ?: (get_option('date_format') . ' ' . get_option('time_format'));
        return (string) date_i18n($format, $ts);
    }

    public function render()
    {
        $value = $this->get_value();
        if ('' === trim((string) $value)) {
            return;
        }

        echo esc_html((string) $value);
    }
}
