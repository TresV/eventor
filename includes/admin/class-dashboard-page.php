<?php

namespace EventTicketsElementor\Admin;

use EventTicketsElementor\CPT_Events;
use EventTicketsElementor\CPT_Tickets;
use EventTicketsElementor\Settings_Store;
use EventTicketsElementor\Staff_Access_Manager;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Dashboard admin page: stats and quick links.
 */
class Dashboard_Page
{
    private Settings_Store $store;

    public function __construct(Settings_Store $store)
    {
        $this->store = $store;
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function render(): void
    {
        if (! current_user_can(Staff_Access_Manager::CAP_MANAGE_PLUGIN)) {
            return;
        }

        $data = $this->build_data();
        $view = EVT_TICKETS_PLUGIN_DIR . 'includes/admin/views/dashboard.php';

        if (file_exists($view)) {
            // Make variables available to the template scope.
            extract($data, EXTR_OVERWRITE);
            include $view;
        }
    }

    public function enqueue_assets(): void
    {
        $screen = get_current_screen();
        if (! $screen) {
            return;
        }

        $screen_id = (string) $screen->id;
        $post_type = (string) ($screen->post_type ?? '');

        if (
            false === strpos($screen_id, 'evt-tickets')
            && ! in_array($post_type, [CPT_Events::POST_TYPE, CPT_Tickets::POST_TYPE], true)
        ) {
            return;
        }

        wp_enqueue_style(
            'evt-tickets-admin',
            EVT_TICKETS_PLUGIN_URL . 'assets/css/admin.css',
            [],
            defined('EVT_TICKETS_VERSION') ? EVT_TICKETS_VERSION : 'dev'
        );
    }

    /**
     * Gather data needed for the dashboard view.
     *
     * @return array<string,mixed>
     */
    private function build_data(): array
    {
        $total_events        = $this->count_events();
        $ticket_totals       = $this->count_tickets();
        $event_post_type     = class_exists(CPT_Events::class) ? CPT_Events::POST_TYPE : 'evt_event';
        $recent_events       = $this->recent_posts($event_post_type);
        $recent_tickets      = $this->recent_posts(CPT_Tickets::POST_TYPE);

        return [
            'total_events'        => $total_events,
            'total_tickets'       => $ticket_totals['total'],
            'checked_in_tickets'  => $ticket_totals['checked_in'],
            'pending_tickets'     => $ticket_totals['pending'],
            'cancelled_tickets'   => $ticket_totals['cancelled'],
            'recent_events'       => $recent_events,
            'recent_tickets'      => $recent_tickets,
            'settings_page_url'   => admin_url('admin.php?page=evt-tickets-settings'),
            'events_list_url'     => admin_url('edit.php?post_type=evt_event'),
            'tickets_list_url'    => admin_url('edit.php?post_type=evt_ticket'),
        ];
    }

    private function count_events(): int
    {
        if (! class_exists(CPT_Events::class)) {
            return 0;
        }

        $event_counts = wp_count_posts(CPT_Events::POST_TYPE);
        return isset($event_counts->publish) ? (int) $event_counts->publish : 0;
    }

    /**
     * @return array{total:int,checked_in:int,pending:int,cancelled:int}
     */
    private function count_tickets(): array
    {
        if (! class_exists(CPT_Tickets::class)) {
            return [
                'total'      => 0,
                'checked_in' => 0,
                'pending'    => 0,
                'cancelled'  => 0,
            ];
        }

        $ticket_counts = wp_count_posts(CPT_Tickets::POST_TYPE);
        $total         = isset($ticket_counts->publish) ? (int) $ticket_counts->publish : 0;

        return [
            'total'      => $total,
            'checked_in' => $this->count_tickets_by_status('checked_in'),
            'pending'    => $this->count_tickets_by_status('pending'),
            'cancelled'  => $this->count_tickets_by_status('cancelled'),
        ];
    }

    private function count_tickets_by_status(string $status): int
    {
        $query = new \WP_Query(
            [
                'post_type'      => CPT_Tickets::POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'no_found_rows'  => false,
                'meta_query'     => [
                    [
                        'key'   => '_ticket_status',
                        'value' => $status,
                    ],
                ],
            ]
        );

        return (int) $query->found_posts;
    }

    /**
     * @param string $post_type
     * @return array<int,\WP_Post>
     */
    private function recent_posts(string $post_type): array
    {
        if (! post_type_exists($post_type)) {
            return [];
        }

        return get_posts(
            [
                'post_type'      => $post_type,
                'post_status'    => 'publish',
                'numberposts'    => 5,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ]
        );
    }
}
