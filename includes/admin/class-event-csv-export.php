<?php

namespace EventTicketsElementor\Admin;

use EventTicketsElementor\CPT_Events;
use EventTicketsElementor\CPT_Tickets;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * CSV export of tickets for a given event.
 */
class Event_CSV_Export
{
    public function __construct()
    {
        add_action('add_meta_boxes', [$this, 'add_export_meta_box']);
        add_action('admin_post_evt_export_tickets', [$this, 'handle_export']);
    }

    public function add_export_meta_box(): void
    {
        add_meta_box(
            'evt_event_ticket_export',
            __('Export Tickets (CSV)', 'Event-Tickets-for-Elementor'),
            [$this, 'render_meta_box'],
            CPT_Events::POST_TYPE,
            'side',
            'default'
        );
    }

    public function render_meta_box(\WP_Post $post): void
    {
        $export_url = wp_nonce_url(
            add_query_arg(
                [
                    'action'   => 'evt_export_tickets',
                    'event_id' => $post->ID,
                ],
                admin_url('admin-post.php')
            ),
            'evt_export_tickets_' . $post->ID
        );
?>
        <p><?php esc_html_e('Download a CSV of all tickets linked to this event.', 'Event-Tickets-for-Elementor'); ?></p>
        <p><a class="button" href="<?php echo esc_url($export_url); ?>"><?php esc_html_e('Download CSV', 'Event-Tickets-for-Elementor'); ?></a></p>
<?php
    }

    public function handle_export(): void
    {
        $event_id = isset($_GET['event_id']) ? absint($_GET['event_id']) : 0;

        if (! $event_id || ! wp_verify_nonce(wp_unslash($_GET['_wpnonce'] ?? ''), 'evt_export_tickets_' . $event_id)) {
            wp_die(esc_html__('Invalid export request.', 'Event-Tickets-for-Elementor'));
        }

        if (! current_user_can('edit_post', $event_id)) {
            wp_die(esc_html__('You do not have permission to export tickets for this event.', 'Event-Tickets-for-Elementor'));
        }

        $tickets = \EventTicketsElementor\Plugin::instance()->tickets()->get_tickets_for_event(
            $event_id,
            [
                'post_status' => ['publish', \EventTicketsElementor\Ticket_Statuses::STATUS_CANCELLED],
                'limit'       => -1,
                'orderby'     => 'date',
                'order'       => 'ASC',
            ]
        );

        $filename = 'tickets-event-' . $event_id . '-' . gmdate('Ymd-His') . '.csv';

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        fputcsv($output, [
            'Ticket ID',
            'Code',
            'Attendee Name',
            'Attendee Email',
            'Status',
            'Checked-in At',
            'Event Name',
            'Event Start',
            'Event End',
            'Event Location',
        ]);

        foreach ($tickets as $ticket) {
            $ticket_id = (int) $ticket->ID;
            fputcsv($output, [
                $this->escape_cell($ticket_id),
                $this->escape_cell(get_post_meta($ticket_id, '_ticket_code', true)),
                $this->escape_cell(get_post_meta($ticket_id, '_ticket_name', true)),
                $this->escape_cell(get_post_meta($ticket_id, '_ticket_email', true)),
                $this->escape_cell(get_post_meta($ticket_id, '_ticket_status', true) ?: 'pending'),
                $this->escape_cell(get_post_meta($ticket_id, '_ticket_checked_in_at', true)),
                $this->escape_cell(get_post_meta($ticket_id, '_ticket_event_name', true)),
                $this->escape_cell(get_post_meta($ticket_id, '_ticket_event_start', true)),
                $this->escape_cell(get_post_meta($ticket_id, '_ticket_event_end', true)),
                $this->escape_cell(get_post_meta($ticket_id, '_ticket_event_location', true)),
            ]);
        }

        // PHP closes php://output automatically when the request ends.
        exit;
    }

    /**
     * Escape a CSV cell against spreadsheet formula injection.
     *
     * Excel, Google Sheets and LibreOffice interpret a leading =, +, - or @
     * as a formula (and tab/CR as a cell separator). Prefixing those cells
     * with a single quote forces them to be treated as literal text.
     *
     * @param mixed $value
     */
    private function escape_cell($value): string
    {
        $value = (string) $value;
        if ('' === $value) {
            return '';
        }

        $first = $value[0];
        if (in_array($first, ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $value;
        }

        return $value;
    }

    // Ticket retrieval delegated to Ticket_Service::get_tickets_for_event().
}
