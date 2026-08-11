<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Ticket QR helpers for frontend widgets/emails.
 */
class Ticket_Qr
{
    /**
     * Returns a public URL to a cached QR for a ticket code + payload.
     *
     * @param string $ticket_code
     * @param string $data
     * @param int    $size
     * @return string
     */
    public function get_qr_url(string $ticket_code, string $data, int $size = 300): string
    {
        $qr = new Qr_Service();
        $path = $qr->ensure_cached($ticket_code, $data, $size);
        if (! $path) {
            return '';
        }

        return $qr->get_cached_url($ticket_code);
    }
}

