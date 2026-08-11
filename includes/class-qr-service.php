<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Local QR generation + caching for ticket codes.
 *
 * Uses a vendored phpqrcode library (GPL compatible).
 */
class Qr_Service
{
    private bool $loaded = false;

    /**
     * Render QR PNG bytes.
     *
     * @param string $data
     * @param int    $size Approximate pixel size (best-effort).
     * @return string Raw PNG bytes.
     */
    public function render_png(string $data, int $size = 300): string
    {
        $this->ensure_library_loaded();

        if (! class_exists('\\QRcode')) {
            return '';
        }

        $data = (string) $data;
        if ('' === $data) {
            return '';
        }

        // phpqrcode uses "module size" (pixels per square), so approximate.
        $module_size = max(2, (int) floor($size / 45));
        $margin      = 2;

        ob_start();
        \QRcode::png($data, null, QR_ECLEVEL_M, $module_size, $margin);
        $png = ob_get_clean();

        return is_string($png) ? $png : '';
    }

    /**
     * Ensure a QR image exists on disk and return its absolute path.
     */
    public function ensure_cached(string $ticket_code, string $data, int $size = 300): string
    {
        $ticket_code = $this->sanitize_ticket_code($ticket_code);
        if ('' === $ticket_code) {
            return '';
        }

        $dir = $this->cache_dir();
        if (! $dir) {
            return '';
        }

        $path = trailingslashit($dir) . $ticket_code . '.png';

        if (file_exists($path) && filesize($path) > 0) {
            return $path;
        }

        $png = $this->render_png($data, $size);
        if ('' === $png) {
            return '';
        }

        // Best-effort write.
        $written = @file_put_contents($path, $png);
        if (! $written) {
            return '';
        }

        return $path;
    }

    /**
     * Public URL for a cached QR image.
     */
    public function get_cached_url(string $ticket_code): string
    {
        $ticket_code = $this->sanitize_ticket_code($ticket_code);
        if ('' === $ticket_code) {
            return '';
        }

        $upload = wp_upload_dir();
        if (empty($upload['baseurl'])) {
            return '';
        }

        return trailingslashit($upload['baseurl']) . 'evt-tickets/qr/' . rawurlencode($ticket_code) . '.png';
    }

    private function cache_dir(): string
    {
        $upload = wp_upload_dir();
        if (empty($upload['basedir'])) {
            return '';
        }

        $dir = trailingslashit($upload['basedir']) . 'evt-tickets/qr';
        if (! file_exists($dir)) {
            wp_mkdir_p($dir);
        }

        return (file_exists($dir) && is_dir($dir)) ? $dir : '';
    }

    private function sanitize_ticket_code(string $code): string
    {
        $code = strtoupper(trim($code));
        // Allow only safe filename characters.
        $code = preg_replace('/[^A-Z0-9_-]/', '', $code);
        return is_string($code) ? $code : '';
    }

    private function ensure_library_loaded(): void
    {
        if ($this->loaded) {
            return;
        }

        $lib = EVT_TICKETS_PLUGIN_DIR . 'includes/vendor/phpqrcode/qrlib.php';
        if (file_exists($lib)) {
            require_once $lib;
        }

        $this->loaded = true;
    }
}

