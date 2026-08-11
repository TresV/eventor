<?php

namespace EventTicketsElementor\Elementor_Dynamic_Tags;

use Elementor\Controls_Manager;
use Elementor\Modules\DynamicTags\Module;
use EventTicketsElementor\Event_Status_Badge_Resolver;
use EventTicketsElementor\Plugin;

if (! defined('ABSPATH')) {
    exit;
}

class Event_Status_Tag extends Event_Tag_Base
{
    protected function register_controls()
    {
        parent::register_controls();

        $this->add_control(
            'visible_statuses',
            [
                'label'       => __('Show Statuses', 'Event-Tickets-for-Elementor'),
                'type'        => Controls_Manager::SELECT2,
                'multiple'    => true,
                'label_block' => true,
                'default'     => [
                    Event_Status_Badge_Resolver::STATUS_CANCELLED,
                    Event_Status_Badge_Resolver::STATUS_POSTPONED,
                    Event_Status_Badge_Resolver::STATUS_OVER,
                ],
                'options'     => [
                    Event_Status_Badge_Resolver::STATUS_CANCELLED => __('Cancelled', 'Event-Tickets-for-Elementor'),
                    Event_Status_Badge_Resolver::STATUS_POSTPONED => __('Postponed', 'Event-Tickets-for-Elementor'),
                    Event_Status_Badge_Resolver::STATUS_OVER      => __('Over', 'Event-Tickets-for-Elementor'),
                    Event_Status_Badge_Resolver::STATUS_SCHEDULED => __('Scheduled', 'Event-Tickets-for-Elementor'),
                ],
                'description' => __('Only the selected statuses will output text. Unselected statuses return an empty value.', 'Event-Tickets-for-Elementor'),
            ]
        );
    }

    public function get_name()
    {
        return 'evt_event_status';
    }

    public function get_title()
    {
        return __('Event Status', 'Event-Tickets-for-Elementor');
    }

    public function get_group()
    {
        return 'evt-tickets';
    }

    public function get_categories()
    {
        return [Module::TEXT_CATEGORY];
    }

    public function get_value(array $options = [])
    {
        $event_id = $this->get_event_id();
        if (! $event_id) {
            return '';
        }

        $resolver = Plugin::instance()->event_status_badge_resolver ?? null;
        if (! $resolver) {
            return (string) __('Scheduled', 'Event-Tickets-for-Elementor');
        }

        $resolved = $resolver->resolve_for_event($event_id);
        $status_key = (string) ($resolved['status_key'] ?? Event_Status_Badge_Resolver::STATUS_SCHEDULED);
        $visible_statuses = $this->normalize_visible_statuses($this->get_settings('visible_statuses'));

        if (! in_array($status_key, $visible_statuses, true)) {
            return '';
        }

        return (string) ($resolved['status_label'] ?? __('Scheduled', 'Event-Tickets-for-Elementor'));
    }

    public function render()
    {
        $value = $this->get_value();
        if ('' === trim((string) $value)) {
            return;
        }

        echo esc_html((string) $value);
    }

    /**
     * @param mixed $raw
     * @return array<int,string>
     */
    private function normalize_visible_statuses($raw): array
    {
        if (is_string($raw) && '' !== $raw) {
            $raw = explode(',', $raw);
        }

        if (! is_array($raw)) {
            $raw = [
                Event_Status_Badge_Resolver::STATUS_CANCELLED,
                Event_Status_Badge_Resolver::STATUS_POSTPONED,
                Event_Status_Badge_Resolver::STATUS_OVER,
            ];
        }

        $allowed = [
            Event_Status_Badge_Resolver::STATUS_CANCELLED,
            Event_Status_Badge_Resolver::STATUS_POSTPONED,
            Event_Status_Badge_Resolver::STATUS_OVER,
            Event_Status_Badge_Resolver::STATUS_SCHEDULED,
        ];

        return array_values(array_filter(array_map('sanitize_key', $raw), static function ($status) use ($allowed) {
            return in_array($status, $allowed, true);
        }));
    }
}
