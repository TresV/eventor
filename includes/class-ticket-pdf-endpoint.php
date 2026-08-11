<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Serves signed PDF downloads by ticket code (non-enumerable).
 *
 * URL format:
 * ?evt_ticket_pdf={ticket_code}&sig={signature}&ts={timestamp}
 */
class Ticket_Pdf_Endpoint
{
    private Settings $settings;
    private Pdf_Ticket_Service $pdf_service;

    public function __construct(Settings $settings, Pdf_Ticket_Service $pdf_service)
    {
        $this->settings = $settings;
        $this->pdf_service = $pdf_service;

        add_filter('query_vars', [$this, 'add_query_vars']);
        add_action('template_redirect', [$this, 'maybe_output_pdf']);
    }

    public function add_query_vars($vars)
    {
        $vars[] = 'evt_ticket_pdf';
        return $vars;
    }

    public function get_pdf_download_url(int $ticket_id): string
    {
        $ticket_code = (string) get_post_meta($ticket_id, '_ticket_code', true);
        $ticket_code = trim($ticket_code);
        if ('' === $ticket_code) {
            return '';
        }

        $ts  = time();
        $sig = $this->build_pdf_signature($ticket_code, $ticket_id, $ts);

        return add_query_arg(
            [
                'evt_ticket_pdf' => $ticket_code,
                'sig'            => $sig,
                'ts'             => $ts,
            ],
            home_url('/')
        );
    }

    public function maybe_output_pdf(): void
    {
        $ticket_code = (string) get_query_var('evt_ticket_pdf');
        $ticket_code = trim($ticket_code);
        if ('' === $ticket_code) {
            return;
        }

        $ticket = Plugin::instance()->tickets()->get_ticket_by_code($ticket_code);
        if (! $ticket) {
            status_header(404);
            exit;
        }

        $ticket_id = (int) $ticket->ID;
        $sig       = isset($_GET['sig']) ? sanitize_text_field(wp_unslash($_GET['sig'])) : '';
        $ts        = isset($_GET['ts']) ? absint($_GET['ts']) : 0;

        if (! $this->is_valid_pdf_request($ticket_code, $ticket_id, $sig, $ts)) {
            status_header(404);
            exit;
        }

        $path = $this->pdf_service->generate_pdf($ticket_id);
        if (! $path || ! file_exists($path)) {
            status_header(404);
            exit;
        }

        nocache_headers();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="ticket-' . preg_replace('/[^A-Za-z0-9_-]/', '', $ticket_code) . '.pdf"');
        header('Content-Length: ' . (string) filesize($path));

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
        readfile($path);
        exit;
    }

    public function build_pdf_signature(string $ticket_code, int $ticket_id, int $ts = 0): string
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

        $raw = hash_hmac('sha256', $msg, wp_salt('evt_ticket_pdf'), true);
        return $this->base64url_encode($raw);
    }

    public function is_valid_pdf_request(string $ticket_code, int $ticket_id, string $sig, int $ts = 0): bool
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

        $expected = $this->build_pdf_signature($ticket_code, $ticket_id, $ts);
        return $expected && hash_equals($expected, $sig);
    }

    private function base64url_encode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
