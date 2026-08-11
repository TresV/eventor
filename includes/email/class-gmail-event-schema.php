<?php

namespace EventTicketsElementor\Email;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Generates (and injects) Gmail structured data for event reservation.
 *
 * This improves Gmail's "Add to calendar" / event extraction UI.
 */
class Gmail_Event_Schema
{
    /**
     * @param array<string,string> $tokens
     */
    public static function build_jsonld(array $tokens): string
    {
        $event_name = (string) ($tokens['event_name'] ?? '');
        $event_location = (string) ($tokens['event_location'] ?? '');
        $ticket_code = (string) ($tokens['ticket_code'] ?? '');
        $attendee_name = (string) ($tokens['attendee_name'] ?? '');
        $attendee_email = (string) ($tokens['attendee_email'] ?? '');
        $verify_url = (string) ($tokens['verify_url'] ?? '');
        $pdf_url = (string) ($tokens['pdf_url'] ?? '');
        $ics_url = (string) ($tokens['ics_url'] ?? '');
        $map_url = (string) ($tokens['event_map_url'] ?? '');

        $start_iso = self::to_iso8601((string) ($tokens['event_start'] ?? ''));
        $end_iso = self::to_iso8601((string) ($tokens['event_end'] ?? ''));
        if ('' === $end_iso && '' !== $start_iso) {
            $end_iso = self::add_one_hour_iso($start_iso);
        }

        if ('' === $event_name || '' === $start_iso) {
            return '';
        }

        $schema = [
            '@context' => 'http://schema.org',
            '@type'    => 'EventReservation',
            'reservationNumber' => $ticket_code,
            'reservationStatus' => 'http://schema.org/ReservationConfirmed',
            'underName' => array_filter(
                [
                    '@type' => 'Person',
                    'name'  => $attendee_name,
                    'email' => $attendee_email,
                ],
                fn($v) => '' !== (string) $v
            ),
            'reservationFor' => array_filter(
                [
                    '@type'     => 'Event',
                    'name'      => $event_name,
                    'startDate' => $start_iso,
                    'endDate'   => $end_iso,
                    'location'  => array_filter(
                        [
                            '@type' => 'Place',
                            'name'  => $event_location,
                        ],
                        fn($v) => '' !== (string) $v
                    ),
                    'url' => $verify_url ?: ($pdf_url ?: ''),
                ],
                fn($v) => ! (is_string($v) && '' === $v)
            ),
            'additionalTicketText' => $verify_url,
        ];

        // Helpful hints/links (not guaranteed to be used by Gmail, but harmless).
        $schema['reservationFor']['sameAs'] = array_values(array_filter([$map_url, $ics_url, $pdf_url]));

        // JSON_HEX_* flags escape <, >, &, ' and " as unicode escapes so that
        // attendee/event-controlled strings can never break out of the HTML
        // <script> context the JSON-LD is injected into.
        $json = wp_json_encode(
            $schema,
            JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_HEX_TAG
                | JSON_HEX_AMP
                | JSON_HEX_APOS
                | JSON_HEX_QUOT
        );
        return is_string($json) ? $json : '';
    }

    public static function inject_jsonld(string $html, string $jsonld): string
    {
        $html = (string) $html;
        $jsonld = trim((string) $jsonld);

        if ('' === $jsonld || '' === trim($html)) {
            return $html;
        }

        $script = '<script type="application/ld+json">' . $jsonld . '</script>';

        if (false !== stripos($html, '</body>')) {
            return preg_replace('/<\\/body>/i', $script . "\n</body>", $html, 1) ?: ($html . $script);
        }

        return $html . "\n" . $script;
    }

    private static function to_iso8601(string $value): string
    {
        $value = trim($value);
        if ('' === $value) {
            return '';
        }

        $tz = function_exists('wp_timezone') ? wp_timezone() : null;
        if ($tz instanceof \DateTimeZone) {
            $dt = date_create_immutable($value, $tz);
            if ($dt instanceof \DateTimeImmutable) {
                return $dt->format('c');
            }
        }

        $ts = strtotime($value);
        if (! $ts) {
            return '';
        }

        return gmdate('c', (int) $ts);
    }

    private static function add_one_hour_iso(string $iso): string
    {
        try {
            $dt = new \DateTimeImmutable($iso);
            return $dt->modify('+1 hour')->format('c');
        } catch (\Exception $e) {
            return $iso;
        }
    }
}
