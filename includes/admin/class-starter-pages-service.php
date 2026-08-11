<?php

namespace EventTicketsElementor\Admin;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Creates and tracks the plugin's starter pages (Events, Get Ticket,
 * Ticket Actions, Staff Check-in) with optional Elementor starter layouts.
 *
 * Shared by the Setup Wizard and the Get Started page so the page-creation
 * logic lives in exactly one place.
 */
class Starter_Pages_Service
{
    public const OPTION_KEY = 'evt_tickets_starter_pages';

    /**
     * @return array<string,int>
     */
    public function get_starter_pages(): array
    {
        $stored = get_option(self::OPTION_KEY, []);
        if (! is_array($stored)) {
            $stored = [];
        }

        $defaults = [
            'events'        => 0,
            'get_ticket'    => 0,
            'ticket_actions' => 0,
            'staff_checkin' => 0,
        ];

        $pages = array_merge($defaults, array_map('absint', $stored));
        $dirty = false;
        foreach ($pages as $key => $id) {
            if (! $id) {
                continue;
            }
            $post = get_post((int) $id);
            if (! $post || 'page' !== $post->post_type || 'trash' === $post->post_status) {
                $pages[$key] = 0;
                $dirty = true;
            }
        }

        if ($dirty) {
            update_option(self::OPTION_KEY, $pages, false);
        }

        return $pages;
    }

    /**
     * @return array{created:int,existing:int}
     */
    public function create_starter_pages(): array
    {
        $defs = [
            'events' => [
                'title'   => __('Events', 'Event-Tickets-for-Elementor'),
                'slug'    => 'events',
                'content' => __('Add the "Events Browser" Elementor widget on this page.', 'Event-Tickets-for-Elementor'),
            ],
            'get_ticket' => [
                'title'   => __('Get Ticket', 'Event-Tickets-for-Elementor'),
                'slug'    => 'get-ticket',
                'content' => __('Use a 2-column layout: left for event details (dynamic tags), right for Ticket Box.', 'Event-Tickets-for-Elementor'),
            ],
            'ticket_actions' => [
                'title'   => __('Ticket Actions', 'Event-Tickets-for-Elementor'),
                'slug'    => 'ticket-actions',
                'content' => __('Add Ticket View, Resend Ticket, and Cancel Ticket widgets.', 'Event-Tickets-for-Elementor'),
            ],
            'staff_checkin' => [
                'title'   => __('Staff Check-in', 'Event-Tickets-for-Elementor'),
                'slug'    => 'staff-checkin',
                'content' => __('Add the Check-in widget and restrict this page to staff.', 'Event-Tickets-for-Elementor'),
            ],
        ];

        $map = $this->get_starter_pages();
        $created = 0;
        $existing = 0;

        foreach ($defs as $key => $def) {
            $page_id = ! empty($map[$key]) ? (int) $map[$key] : 0;
            $page = $page_id ? get_post($page_id) : null;

            if (! $page || 'page' !== $page->post_type) {
                $path_match = get_page_by_path($def['slug']);
                if ($path_match && 'page' === $path_match->post_type) {
                    $page = $path_match;
                    $page_id = (int) $path_match->ID;
                }
            }

            if ($page_id > 0 && $page && 'trash' !== $page->post_status) {
                $map[$key] = $page_id;
                $this->maybe_apply_elementor_starter_layout($page_id, $key);
                $existing++;
                continue;
            }

            $new_id = wp_insert_post(
                [
                    'post_type'    => 'page',
                    'post_title'   => $def['title'],
                    'post_name'    => $def['slug'],
                    'post_status'  => 'publish',
                    'post_content' => '<!-- wp:paragraph --><p>' . esc_html($def['content']) . '</p><!-- /wp:paragraph -->',
                ],
                true
            );

            if (! is_wp_error($new_id) && $new_id > 0) {
                $map[$key] = (int) $new_id;
                $this->apply_elementor_starter_layout((int) $new_id, $key);
                $created++;
            }
        }

        update_option(self::OPTION_KEY, $map, false);
        return ['created' => $created, 'existing' => $existing];
    }

    private function apply_elementor_starter_layout(int $page_id, string $key): void
    {
        if ($page_id <= 0) {
            return;
        }

        $widgets_by_page = [
            'events' => ['evt_events_browser'],
            'get_ticket' => ['evt_ticket_box'],
            'ticket_actions' => ['evt_ticket_view', 'evt_ticket_resend', 'evt_ticket_cancel'],
            'staff_checkin' => ['evt_ticket_checkin'],
        ];

        $widgets = $widgets_by_page[$key] ?? [];
        if (empty($widgets)) {
            return;
        }

        $elements = [];
        foreach ($widgets as $widget_name) {
            $elements[] = [
                'id' => $this->element_id(),
                'elType' => 'widget',
                'widgetType' => $widget_name,
                'settings' => new \stdClass(),
                'elements' => [],
            ];
        }

        $layout = [
            [
                'id' => $this->element_id(),
                'elType' => 'container',
                'isInner' => false,
                'settings' => new \stdClass(),
                'elements' => $elements,
            ],
        ];

        update_post_meta($page_id, '_elementor_data', wp_slash((string) wp_json_encode($layout)));
        update_post_meta($page_id, '_elementor_edit_mode', 'builder');
        update_post_meta($page_id, '_elementor_template_type', 'wp-page');
        if (defined('ELEMENTOR_VERSION')) {
            update_post_meta($page_id, '_elementor_version', ELEMENTOR_VERSION);
        }
    }

    private function maybe_apply_elementor_starter_layout(int $page_id, string $key): void
    {
        $existing_data = get_post_meta($page_id, '_elementor_data', true);
        if (is_string($existing_data) && '' !== trim($existing_data)) {
            return;
        }

        $this->apply_elementor_starter_layout($page_id, $key);
    }

    private function element_id(): string
    {
        return substr(md5((string) wp_generate_uuid4()), 0, 8);
    }

    /**
     * @param array<string,int> $pages
     * @return array<string,array{title:string,id:int,edit_url:string,view_url:string}>
     */
    public function starter_page_links(array $pages): array
    {
        $labels = [
            'events'         => __('Events', 'Event-Tickets-for-Elementor'),
            'get_ticket'     => __('Get Ticket', 'Event-Tickets-for-Elementor'),
            'ticket_actions' => __('Ticket Actions', 'Event-Tickets-for-Elementor'),
            'staff_checkin'  => __('Staff Check-in', 'Event-Tickets-for-Elementor'),
        ];

        $out = [];
        foreach ($labels as $key => $label) {
            $id = isset($pages[$key]) ? absint($pages[$key]) : 0;
            $out[$key] = [
                'title'    => $label,
                'id'       => $id,
                'edit_url' => $id ? (string) get_edit_post_link($id, 'raw') : '',
                'view_url' => $id ? (string) get_permalink($id) : '',
            ];
        }

        return $out;
    }
}
