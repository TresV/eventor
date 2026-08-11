<?php

namespace EventTicketsElementor;

use EventTicketsElementor\Calendar\Ticket_Invite_Ics_Builder;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Handles Add-to-Calendar links (Google URL + ICS download).
 */
class Calendar_Service
{

    /** @var Settings */
    protected $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;

        add_filter('query_vars', [$this, 'add_query_vars']);
        add_action('template_redirect', [$this, 'maybe_output_ics']);

        add_action('init', [$this, 'add_global_ics_rewrite']);
        add_action('init', [$this, 'add_ticket_ics_rewrite']);

        // (Kept for back-compat; some sites block /wp-admin/ for guests.)
        add_action('wp_ajax_evt_ticket_ics', [$this, 'handle_ajax_ticket_ics']);
        add_action('wp_ajax_nopriv_evt_ticket_ics', [$this, 'handle_ajax_ticket_ics']);
    }

    public function add_query_vars($vars)
    {
        $vars[] = 'evt_ticket_ics';
        $vars[] = 'evt_ticket_ics_sig';
        $vars[] = 'evt_ticket_ics_ts';
        $vars[] = 'evt_events_ics';
        return $vars;
    }

    public function add_global_ics_rewrite(): void
    {
        add_rewrite_rule('^events\\.ics$', 'index.php?evt_events_ics=1', 'top');
    }

    /**
     * Pretty + signed ticket ICS downloads (no query string required).
     *
     * /ticket-ics/{CODE}/{TS}/{SIG}.ics
     */
    public function add_ticket_ics_rewrite(): void
    {
        add_rewrite_rule(
            '^ticket-ics/([A-Za-z0-9_-]+)/([0-9]+)/([A-Za-z0-9_-]+)\\.ics$',
            'index.php?evt_ticket_ics=$matches[1]&evt_ticket_ics_ts=$matches[2]&evt_ticket_ics_sig=$matches[3]',
            'top'
        );
    }

    public function maybe_output_ics(): void
    {
        if (get_query_var('evt_events_ics')) {
            $this->output_global_ics();
            exit;
        }

        // Prefer pretty rewrite vars, then query_vars, then raw query string.
        $ticket_code = (string) get_query_var('evt_ticket_ics');
        $ticket_code = trim((string) $ticket_code);
        if ('' === $ticket_code) {
            return;
        }

        $sig = (string) get_query_var('evt_ticket_ics_sig');
        $ts  = (int) get_query_var('evt_ticket_ics_ts');

        if ('' === trim($sig) && isset($_GET['sig'])) {
            $sig = sanitize_text_field(wp_unslash($_GET['sig']));
        }
        if (! $ts && isset($_GET['ts'])) {
            $ts = absint($_GET['ts']);
        }

        $this->output_ticket_ics($ticket_code, $sig, $ts);
    }

    /**
     * Admin-ajax handler for ticket ICS download (public + signed).
     */
    public function handle_ajax_ticket_ics(): void
    {
        $ticket_code = isset($_GET['code']) ? sanitize_text_field(wp_unslash($_GET['code'])) : '';
        $ticket_code = trim((string) $ticket_code);
        if ('' === $ticket_code) {
            status_header(404);
            exit;
        }

        $sig = isset($_GET['sig']) ? sanitize_text_field(wp_unslash($_GET['sig'])) : '';
        $ts  = isset($_GET['ts']) ? absint($_GET['ts']) : 0;

        $this->output_ticket_ics($ticket_code, $sig, $ts);
    }

    public function get_google_calendar_url(int $ticket_id): string
    {
        $event = $this->get_event_data_for_ticket($ticket_id);
        if (empty($event) || empty($event['start_ts'])) {
            return '';
        }

        $start = $this->format_gcal_datetime($event['start_ts']);
        $end   = $this->format_gcal_datetime($event['end_ts']);

        $params = [
            'action'   => 'TEMPLATE',
            'text'     => $event['title'],
            'details'  => $event['description'],
            'location' => $event['location'],
            'dates'    => $start . '/' . $end,
        ];

        return 'https://www.google.com/calendar/render?' . http_build_query($params);
    }

    public function get_ics_download_url(int $ticket_id): string
    {
        $ticket_code = (string) get_post_meta($ticket_id, '_ticket_code', true);
        if ('' === trim($ticket_code)) {
            return '';
        }

        $ts  = time();
        $sig = $this->build_ics_signature($ticket_code, $ticket_id, $ts);

        // Prefer pretty endpoint (works even if /wp-admin/ is hidden).
        $pretty = home_url('/ticket-ics/' . rawurlencode($ticket_code) . '/' . $ts . '/' . rawurlencode($sig) . '.ics');
        return $pretty;
    }

    /**
     * Build a ticket ICS payload for email attachment use.
     */
    public function get_ticket_ics_content(int $ticket_id): string
    {
        $event = $this->get_event_data_for_ticket($ticket_id);
        if (empty($event) || empty($event['start_ts'])) {
            return '';
        }

        return $this->build_ics($ticket_id, $event);
    }

    /**
     * Build an iCalendar payload intended to be used as the primary email body
     * for Microsoft clients (Outlook/Hotmail/Live) so it renders as an invite UI.
     */
    public function get_ticket_invite_ics_content(int $ticket_id): string
    {
        $builder = new Ticket_Invite_Ics_Builder($this->settings, $this);
        return $builder->build_single($ticket_id);
    }

    /**
     * @param array<int,int> $ticket_ids
     */
    public function get_multi_ticket_invite_ics_content(array $ticket_ids): string
    {
        $builder = new Ticket_Invite_Ics_Builder($this->settings, $this);
        return $builder->build_multi($ticket_ids);
    }

    private function output_ticket_ics(string $ticket_code, string $sig, int $ts = 0): void
    {
        $ticket_code = trim((string) $ticket_code);
        if ('' === $ticket_code) {
            status_header(404);
            exit;
        }

        $ticket = Plugin::instance()->tickets()->get_ticket_by_code($ticket_code);
        if (! $ticket) {
            status_header(404);
            exit;
        }

        $ticket_id = (int) $ticket->ID;

        if (! $this->is_valid_ics_request($ticket_code, $ticket_id, $sig, $ts)) {
            status_header(404);
            exit;
        }

        $event = $this->get_event_data_for_ticket($ticket_id);
        if (empty($event) || empty($event['start_ts'])) {
            status_header(404);
            exit;
        }

        $ics = $this->build_ics($ticket_id, $event);

        nocache_headers();
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="ticket-' . preg_replace('/[^A-Za-z0-9_-]/', '', $ticket_code) . '.ics"');
        $ics_stream = fopen('php://output', 'wb'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        fwrite($ics_stream, $ics); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
        fclose($ics_stream); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        exit;
    }

    private function output_global_ics(): void
    {
        $month = isset($_GET['month']) ? sanitize_text_field(wp_unslash($_GET['month'])) : '';

        $start_ts = 0;
        $end_ts   = 0;

        if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
            [$y, $m] = array_map('intval', explode('-', $month));
            $start_ts = (int) mktime(0, 0, 0, $m, 1, $y);
            $end_ts   = (int) mktime(23, 59, 59, $m, (int) gmdate('t', $start_ts), $y);
        } else {
            $start_ts = time();
            $end_ts   = $start_ts + (90 * DAY_IN_SECONDS);
        }

        $args = [
            'limit'          => -1,
            'order'          => 'ASC',
            'start_ts'       => $start_ts,
            'end_ts'         => $end_ts,
            'hide_cancelled' => true,
        ];

        $cache_key = 'evt_events_ics_' . md5(wp_json_encode($args));
        $cached = get_transient($cache_key);
        if (is_string($cached) && '' !== $cached) {
            $ics = $cached;
        } else {
            $events = Plugin::instance()->event_query->get_feed($args);
            $ics = $this->build_global_ics($events);
            set_transient($cache_key, $ics, 120);
        }

        nocache_headers();
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: inline; filename="events.ics"');
        $ics_stream = fopen('php://output', 'wb'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        fwrite($ics_stream, $ics); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
        fclose($ics_stream); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
    }

    /**
     * @param array<int,array<string,mixed>> $events
     */
    private function build_global_ics(array $events): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Event Tickets for Elementor//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
        ];

        foreach ($events as $evt) {
            if (empty($evt['start_ts'])) {
                continue;
            }

            $uid     = 'event-' . (int) $evt['id'] . '@' . preg_replace('#^www\.#', '', sanitize_text_field(wp_unslash((string) ($_SERVER['HTTP_HOST'] ?? 'example.com'))));
            $dtstamp = gmdate('Ymd\THis\Z');
            $dtstart = gmdate('Ymd\THis\Z', (int) $evt['start_ts']);
            $dtend   = ! empty($evt['end_ts']) ? gmdate('Ymd\THis\Z', (int) $evt['end_ts']) : gmdate('Ymd\THis\Z', (int) $evt['start_ts'] + HOUR_IN_SECONDS);

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . $uid;
            $lines[] = 'DTSTAMP:' . $dtstamp;
            $lines[] = 'DTSTART:' . $dtstart;
            $lines[] = 'DTEND:' . $dtend;
            $lines[] = 'SUMMARY:' . $this->escape_ics_text((string) ($evt['title'] ?? ''));
            if (! empty($evt['location'])) {
                $lines[] = 'LOCATION:' . $this->escape_ics_text((string) $evt['location']);
            }
            if (! empty($evt['permalink'])) {
                $lines[] = 'URL:' . $this->escape_ics_text((string) $evt['permalink']);
            }
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines) . "\r\n";
    }

    /**
     * Build event data array from ticket meta + defaults.
     *
     * @param int $ticket_id
     * @return array<string,mixed>
     */
    public function get_event_data_for_ticket(int $ticket_id): array
    {
        $ticket = get_post($ticket_id);
        if (! $ticket || CPT_Tickets::POST_TYPE !== $ticket->post_type) {
            return [];
        }

        $event_name     = get_post_meta($ticket_id, '_ticket_event_name', true);
        $event_start    = get_post_meta($ticket_id, '_ticket_event_start', true);
        $event_end      = get_post_meta($ticket_id, '_ticket_event_end', true);
        $event_location = get_post_meta($ticket_id, '_ticket_event_location', true);

        $linked_event_id = (int) get_post_meta($ticket_id, '_ticket_event_id', true);
        if ($linked_event_id) {
            $linked_event = get_post($linked_event_id);
            if ($linked_event && CPT_Events::POST_TYPE === $linked_event->post_type) {
                $event_name     = $linked_event->post_title ?: $event_name;
                $event_start    = $event_start ?: get_post_meta($linked_event_id, '_evt_event_start', true);
                $event_end      = $event_end ?: get_post_meta($linked_event_id, '_evt_event_end', true);
                $event_location = $event_location ?: get_post_meta($linked_event_id, '_evt_event_location', true);
            }
        }

        // If we still have no start date, we cannot build a calendar entry.
        $start_ts = $event_start ? strtotime($event_start) : 0;
        if (! $start_ts) {
            return [];
        }

        $end_ts = $event_end ? strtotime($event_end) : 0;
        if (! $end_ts || $end_ts <= $start_ts) {
            $end_ts = $start_ts + HOUR_IN_SECONDS;
        }

        $title = $event_name ?: get_bloginfo('name');
        $location = $event_location ?: '';

        $description = sprintf(
            /* translators: %s is site name */
            __('Ticket generated by %s.', 'Event-Tickets-for-Elementor'),
            get_bloginfo('name')
        );

        return [
            'title'       => $title,
            'description' => $description,
            'location'    => $location,
            'start_ts'    => $start_ts,
            'end_ts'      => $end_ts,
        ];
    }

    protected function format_gcal_datetime(int $ts): string
    {
        return gmdate('Ymd\THis\Z', $ts);
    }

    public function build_ics(int $ticket_id, array $event): string
    {
        $host    = sanitize_text_field(wp_unslash((string) ($_SERVER['HTTP_HOST'] ?? 'example.com')));
        $uid     = 'ticket-' . $ticket_id . '@' . preg_replace('#^www\.#', '', $host);
        $dtstamp = gmdate('Ymd\THis\Z');
        $dtstart = gmdate('Ymd\THis\Z', $event['start_ts']);
        $dtend   = gmdate('Ymd\THis\Z', $event['end_ts']);

        $org_name  = (string) $this->settings->get('from_name', get_bloginfo('name'));
        $org_email = (string) $this->settings->get('from_email', get_option('admin_email'));
        $organizer = '';
        if ('' !== trim($org_email)) {
            $organizer = 'ORGANIZER;CN=' . $this->escape_ics_text($org_name) . ':mailto:' . trim($org_email);
        }

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Event Tickets for Elementor//EN',
            'CALSCALE:GREGORIAN',
            // REQUEST helps email clients detect the invite more reliably.
            'METHOD:REQUEST',
            'BEGIN:VEVENT',
            'UID:' . $uid,
            'DTSTAMP:' . $dtstamp,
            'DTSTART:' . $dtstart,
            'DTEND:' . $dtend,
            'SUMMARY:' . $this->escape_ics_text($event['title']),
            'DESCRIPTION:' . $this->escape_ics_text($event['description']),
            (! empty($event['x_alt_desc_html']) ? ('X-ALT-DESC;FMTTYPE=text/html:' . $this->escape_ics_text((string) $event['x_alt_desc_html'])) : null),
            'LOCATION:' . $this->escape_ics_text($event['location']),
            'STATUS:CONFIRMED',
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        $lines = array_values(array_filter($lines, static function ($v) {
            return null !== $v && '' !== $v;
        }));

        if ('' !== $organizer) {
            array_splice($lines, 7, 0, [$organizer]);
        }

        return implode("\r\n", $lines) . "\r\n";
    }

    public function escape_ics_text(string $text): string
    {
        $text = str_replace(['\\', ';', ',', "\n", "\r"], ['\\\\', '\;', '\,', '\\n', ''], $text);
        return $text;
    }

    /**
     * Build signature for ICS URLs.
     *
     * HMAC(ticket_code|ticket_id|ts, wp_salt('evt_ticket_ics')) base64url.
     * Note: the legacy timestamp-less form is no longer accepted by
     * is_valid_ics_request() because it could never expire.
     */
    public function build_ics_signature(string $ticket_code, int $ticket_id, int $ts = 0): string
    {
        $ticket_code = trim((string) $ticket_code);
        $ticket_id   = absint($ticket_id);

        if ('' === $ticket_code || ! $ticket_id) {
            return '';
        }

        $msg = $ticket_code . '|' . $ticket_id;
        if ($ts > 0) {
            $msg .= '|' . $ts;
        }

        $raw = hash_hmac('sha256', $msg, wp_salt('evt_ticket_ics'), true);
        return $this->base64url_encode($raw);
    }

    public function is_valid_ics_request(string $ticket_code, int $ticket_id, string $sig, int $ts = 0): bool
    {
        $sig = (string) $sig;
        if ('' === $sig) {
            return false;
        }

        // Require a timestamped signature within the validity window.
        // Legacy unsigned (ts=0) signatures never expired and are therefore
        // rejected. Newly issued links always carry a fresh timestamp.
        if ($ts <= 0) {
            return false;
        }

        $max_age = 90 * DAY_IN_SECONDS;
        if ($ts < (time() - $max_age)) {
            return false;
        }

        // Reject timestamps far in the future (guards against clock issues
        // producing effectively permanent links).
        if ($ts > (time() + DAY_IN_SECONDS)) {
            return false;
        }

        $expected = $this->build_ics_signature($ticket_code, $ticket_id, $ts);
        return $expected && hash_equals($expected, $sig);
    }

    private function base64url_encode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
