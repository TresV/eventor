<?php

namespace EventTicketsElementor\Calendar;

use EventTicketsElementor\Calendar_Service;
use EventTicketsElementor\Event_Location_Meta;
use EventTicketsElementor\Plugin;
use EventTicketsElementor\Settings;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Builds ticket invite ICS content tailored for Outlook "invite UI" emails.
 */
class Ticket_Invite_Ics_Builder
{
    private Settings $settings;
    private Calendar_Service $calendar;

    public function __construct(Settings $settings, Calendar_Service $calendar)
    {
        $this->settings = $settings;
        $this->calendar = $calendar;
    }

    public function build_single(int $ticket_id): string
    {
        $event = $this->calendar->get_event_data_for_ticket($ticket_id);
        if (empty($event) || empty($event['start_ts'])) {
            return '';
        }

        $event['description'] = $this->build_invite_description_for_ticket($ticket_id, $event);
        $event['x_alt_desc_html'] = $this->build_invite_alt_desc_html_for_ticket($ticket_id, $event);

        return $this->calendar->build_ics($ticket_id, $event);
    }

    /**
     * @param array<int,int> $ticket_ids
     */
    public function build_multi(array $ticket_ids): string
    {
        $ticket_ids = array_filter(array_map('absint', $ticket_ids));
        if (empty($ticket_ids)) {
            return '';
        }

        $org_name  = (string) $this->settings->get('from_name', get_bloginfo('name'));
        $org_email = (string) $this->settings->get('from_email', get_option('admin_email'));
        $organizer = '';
        if ('' !== trim($org_email)) {
            $organizer = 'ORGANIZER;CN=' . $this->calendar->escape_ics_text($org_name) . ':mailto:' . trim($org_email);
        }

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Event Tickets for Elementor//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:REQUEST',
        ];

        foreach ($ticket_ids as $ticket_id) {
            $event = $this->calendar->get_event_data_for_ticket((int) $ticket_id);
            if (empty($event) || empty($event['start_ts'])) {
                continue;
            }

            $event['description'] = $this->build_invite_description_for_ticket((int) $ticket_id, $event);
            $event['x_alt_desc_html'] = $this->build_invite_alt_desc_html_for_ticket((int) $ticket_id, $event);

            $host    = sanitize_text_field(wp_unslash((string) ($_SERVER['HTTP_HOST'] ?? 'example.com')));
            $uid     = 'ticket-' . (int) $ticket_id . '@' . preg_replace('#^www\.#', '', $host);
            $dtstamp = gmdate('Ymd\THis\Z');
            $dtstart = gmdate('Ymd\THis\Z', (int) $event['start_ts']);
            $dtend   = gmdate('Ymd\THis\Z', (int) $event['end_ts']);

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . $uid;
            $lines[] = 'DTSTAMP:' . $dtstamp;
            if ('' !== $organizer) {
                $lines[] = $organizer;
            }
            $lines[] = 'DTSTART:' . $dtstart;
            $lines[] = 'DTEND:' . $dtend;
            $lines[] = 'SUMMARY:' . $this->calendar->escape_ics_text((string) $event['title']);
            $lines[] = 'DESCRIPTION:' . $this->calendar->escape_ics_text((string) $event['description']);
            if (! empty($event['x_alt_desc_html'])) {
                $lines[] = 'X-ALT-DESC;FMTTYPE=text/html:' . $this->calendar->escape_ics_text((string) $event['x_alt_desc_html']);
            }
            $lines[] = 'LOCATION:' . $this->calendar->escape_ics_text((string) $event['location']);
            $lines[] = 'STATUS:CONFIRMED';
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines) . "\r\n";
    }

    /**
     * @param array<string,mixed> $event
     */
    private function build_invite_description_for_ticket(int $ticket_id, array $event): string
    {
        $ticket_code = (string) get_post_meta($ticket_id, '_ticket_code', true);

        $verify_url = add_query_arg(
            ['code' => rawurlencode($ticket_code)],
            site_url('/ticket-checkin/')
        );
        $pdf_url = Plugin::instance()->pdf_endpoint()->get_pdf_download_url($ticket_id);
        $ics_url = $this->calendar->get_ics_download_url($ticket_id);

        $event_id = (int) get_post_meta($ticket_id, '_ticket_event_id', true);
        $map_url = '';
        if ($event_id) {
            $map_url = (string) get_post_meta($event_id, Event_Location_Meta::MAP_URL_META, true);
        }

        $tokens = [
            'event_name'      => (string) ($event['title'] ?? ''),
            'event_start'     => gmdate('Y-m-d H:i', (int) ($event['start_ts'] ?? 0)),
            'event_end'       => gmdate('Y-m-d H:i', (int) ($event['end_ts'] ?? 0)),
            'event_location'  => (string) ($event['location'] ?? ''),
            'event_map_url'   => (string) $map_url,
            'ticket_code'     => (string) $ticket_code,
            'verify_url'      => (string) $verify_url,
            'ics_url'         => (string) $ics_url,
            'pdf_url'         => (string) $pdf_url,
            'attendee_name'   => '',
            'attendee_email'  => '',
            'google_cal_url'  => '',
        ];

        $greeting_tpl = (string) $this->settings->get('email_greeting', __('Hi,', 'Event-Tickets-for-Elementor'));
        $intro_tpl = (string) $this->settings->get('email_intro', __('Thank you for registering for {event_name}.', 'Event-Tickets-for-Elementor'));
        $qr_tpl = (string) $this->settings->get('email_qr_instructions', __('Show the QR code below at the entrance. Our staff will scan it to check you in.', 'Event-Tickets-for-Elementor'));
        $loc_block_tpl = (string) $this->settings->get('email_location_body', "Venue: {event_location}\nOpen in Maps: {event_map_url}");

        $greeting = trim($this->replace_tokens($greeting_tpl, $tokens));
        $intro = trim($this->replace_tokens($intro_tpl, $tokens));
        $qr = trim($this->replace_tokens($qr_tpl, $tokens));
        $loc_block = trim($this->replace_tokens($loc_block_tpl, $tokens));

        $lines = [];
        if ('' !== $greeting) {
            $lines[] = $greeting;
        }
        if ('' !== $intro) {
            $lines[] = $intro;
        }
        $lines[] = '';

        $lines[] = 'Event: ' . (string) ($event['title'] ?? '');
        $lines[] = 'Start: ' . $tokens['event_start'];
        $lines[] = 'End: ' . $tokens['event_end'];
        if ('' !== trim((string) $tokens['event_location'])) {
            $lines[] = 'Location: ' . $tokens['event_location'];
        }
        if ('' !== trim((string) $map_url)) {
            $lines[] = 'Map: ' . $map_url;
        }

        $lines[] = '';
        $lines[] = 'Ticket code: ' . $ticket_code;
        if ('' !== trim((string) $pdf_url)) {
            $lines[] = 'PDF: ' . $pdf_url;
        }
        if ('' !== trim((string) $ics_url)) {
            $lines[] = 'ICS: ' . $ics_url;
        }
        if ('' !== trim((string) $verify_url)) {
            $lines[] = 'Check-in: ' . $verify_url;
        }

        if ('' !== $qr) {
            $lines[] = '';
            $lines[] = $qr;
        }

        if ('' !== $loc_block) {
            $lines[] = '';
            $lines[] = $loc_block;
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string,mixed> $event
     */
    private function build_invite_alt_desc_html_for_ticket(int $ticket_id, array $event): string
    {
        $ticket_code = (string) get_post_meta($ticket_id, '_ticket_code', true);
        $verify_url = add_query_arg(
            ['code' => rawurlencode($ticket_code)],
            site_url('/ticket-checkin/')
        );
        $pdf_url = Plugin::instance()->pdf_endpoint()->get_pdf_download_url($ticket_id);

        $html = '<div>';
        $html .= '<p><strong>' . esc_html((string) ($event['title'] ?? '')) . '</strong></p>';
        $html .= '<p>' . esc_html(gmdate('Y-m-d H:i', (int) ($event['start_ts'] ?? 0))) . ' – ' . esc_html(gmdate('Y-m-d H:i', (int) ($event['end_ts'] ?? 0))) . '</p>';
        if (! empty($event['location'])) {
            $html .= '<p>' . esc_html((string) $event['location']) . '</p>';
        }
        if ('' !== trim($ticket_code)) {
            $html .= '<p><strong>' . esc_html__('Ticket code:', 'Event-Tickets-for-Elementor') . '</strong> ' . esc_html($ticket_code) . '</p>';
        }
        if ('' !== trim((string) $pdf_url)) {
            $html .= '<p><a href="' . esc_url($pdf_url) . '">' . esc_html__('Download Ticket (PDF)', 'Event-Tickets-for-Elementor') . '</a></p>';
        }
        if ('' !== trim((string) $verify_url)) {
            $html .= '<p><a href="' . esc_url($verify_url) . '">' . esc_html__('Check-in URL', 'Event-Tickets-for-Elementor') . '</a></p>';
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * @param array<string,string> $tokens
     */
    private function replace_tokens(string $template, array $tokens): string
    {
        $replacements = [];
        foreach ($tokens as $key => $value) {
            $replacements['{' . $key . '}'] = (string) $value;
        }

        return strtr((string) $template, $replacements);
    }
}

