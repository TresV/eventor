<?php

namespace EventTicketsElementor;

use Dompdf\Dompdf;
use Dompdf\Options;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Generates and caches PDF tickets (HTML -> PDF via dompdf).
 */
class Pdf_Ticket_Service
{
    private const TEMPLATE_VERSION = '1';

    private Settings $settings;

    private bool $dompdf_loaded = false;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Generate (or reuse cached) PDF for a ticket.
     *
     * @return string Absolute filesystem path to the PDF, or empty string on failure.
     */
    public function generate_pdf(int $ticket_id): string
    {
        $ticket = get_post($ticket_id);
        if (! $ticket || CPT_Tickets::POST_TYPE !== $ticket->post_type) {
            return '';
        }

        $ticket_code = (string) get_post_meta($ticket_id, '_ticket_code', true);
        $ticket_code = trim($ticket_code);
        if ('' === $ticket_code) {
            return '';
        }

        $pdf_path = $this->get_pdf_path($ticket_code);
        if ('' === $pdf_path) {
            return '';
        }

        $hash = $this->build_pdf_hash($ticket_id);
        $stored_hash = (string) get_post_meta($ticket_id, '_ticket_pdf_hash', true);

        if ($hash && $stored_hash && hash_equals($stored_hash, $hash) && file_exists($pdf_path) && filesize($pdf_path) > 0) {
            return $pdf_path;
        }

        $this->ensure_dompdf_loaded();
        if (! class_exists(Dompdf::class)) {
            return '';
        }

        $data = $this->get_ticket_pdf_data($ticket_id);
        if (empty($data['event_name'])) {
            $data['event_name'] = get_bloginfo('name');
        }

        $html = $this->render_ticket_html($data);
        if ('' === $html) {
            return '';
        }

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        // Keep dompdf dependency set lean: avoid requiring Masterminds/HTML5.
        $options->set('isHtml5ParserEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $tmp_dir = $this->temp_dir();
        if ($tmp_dir) {
            $options->set('tempDir', $tmp_dir);
            $options->set('fontCache', $tmp_dir);
            $options->set('logOutputFile', $tmp_dir . '/dompdf-log.html');
        }

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        $pdf_bytes = $dompdf->output();
        if (! is_string($pdf_bytes) || '' === $pdf_bytes) {
            return '';
        }

        if (! $this->ensure_dir(dirname($pdf_path))) {
            return '';
        }

        $written = @file_put_contents($pdf_path, $pdf_bytes);
        if (! $written) {
            return '';
        }

        if ($hash) {
            update_post_meta($ticket_id, '_ticket_pdf_hash', $hash);
        }

        return $pdf_path;
    }

    public function get_pdf_url_for_code(string $ticket_code): string
    {
        $ticket_code = trim((string) $ticket_code);
        if ('' === $ticket_code) {
            return '';
        }

        $upload = wp_upload_dir();
        if (empty($upload['baseurl'])) {
            return '';
        }

        return trailingslashit($upload['baseurl']) . 'evt-tickets/pdf/' . rawurlencode($ticket_code) . '.pdf';
    }

    private function get_pdf_path(string $ticket_code): string
    {
        $ticket_code = $this->sanitize_ticket_code($ticket_code);
        if ('' === $ticket_code) {
            return '';
        }

        $upload = wp_upload_dir();
        if (empty($upload['basedir'])) {
            return '';
        }

        $dir = trailingslashit($upload['basedir']) . 'evt-tickets/pdf';
        if (! $this->ensure_dir($dir)) {
            return '';
        }

        return trailingslashit($dir) . $ticket_code . '.pdf';
    }

    private function build_pdf_hash(int $ticket_id): string
    {
        $ticket_code = (string) get_post_meta($ticket_id, '_ticket_code', true);

        $snapshot = $this->get_event_snapshot_for_ticket($ticket_id);
        $preset = $this->get_pdf_preset_for_ticket($ticket_id);

        $settings = [
            'preset'     => (string) $preset,
            'show_logo'  => (int) $this->settings->get('pdf_show_logo', 1),
            'accent'     => (string) $this->settings->get('pdf_accent_color', '#2563eb'),
            'footer'     => (string) $this->settings->get('pdf_footer_text', ''),
        ];

        $ticket_data = [
            'template' => self::TEMPLATE_VERSION,
            'code'     => (string) $ticket_code,
            'name'     => (string) get_post_meta($ticket_id, '_ticket_name', true),
            'email'    => (string) get_post_meta($ticket_id, '_ticket_email', true),
            'event'    => $snapshot,
            'settings' => $settings,
        ];

        return hash('sha256', wp_json_encode($ticket_data));
    }

    /**
     * @return array<string,string>
     */
    private function get_ticket_pdf_data(int $ticket_id): array
    {
        $ticket_code    = (string) get_post_meta($ticket_id, '_ticket_code', true);
        $attendee_name  = (string) get_post_meta($ticket_id, '_ticket_name', true);
        $attendee_email = (string) get_post_meta($ticket_id, '_ticket_email', true);

        $snapshot = $this->get_event_snapshot_for_ticket($ticket_id);

        $verify_url = add_query_arg(
            ['code' => rawurlencode($ticket_code)],
            site_url('/ticket-checkin/')
        );

        $qr_data_uri = '';
        $qr_path = (new Qr_Service())->ensure_cached((string) $ticket_code, $verify_url, 320);
        if ($qr_path && file_exists($qr_path)) {
            $qr_bytes = @file_get_contents($qr_path);
            if (is_string($qr_bytes) && '' !== $qr_bytes) {
                $qr_data_uri = 'data:image/png;base64,' . base64_encode($qr_bytes);
            }
        }

        $logo_data_uri = '';
        $show_logo = (int) $this->settings->get('pdf_show_logo', 1);
        if ($show_logo) {
            $logo_data_uri = $this->get_site_logo_data_uri();
        }

        $preset = $this->get_pdf_preset_for_ticket($ticket_id);
        $accent = (string) $this->settings->get('pdf_accent_color', '#2563eb');
        $accent = sanitize_hex_color($accent) ?: '#2563eb';

        $footer = (string) $this->settings->get('pdf_footer_text', '');

        return [
            'ticket_id'      => (string) $ticket_id,
            'ticket_code'    => (string) $ticket_code,
            'attendee_name'  => (string) $attendee_name,
            'attendee_email' => (string) $attendee_email,
            'event_name'     => (string) $snapshot['name'],
            'event_start'    => (string) $snapshot['start'],
            'event_end'      => (string) $snapshot['end'],
            'event_location' => (string) $snapshot['location'],
            'verify_url'     => (string) $verify_url,
            'qr_data_uri'    => (string) $qr_data_uri,
            'logo_data_uri'  => (string) $logo_data_uri,
            'preset'         => (string) $preset,
            'accent'         => (string) $accent,
            'footer'         => (string) $footer,
        ];
    }

    private function get_pdf_preset_for_ticket(int $ticket_id): string
    {
        $global = (string) $this->settings->get('pdf_template_preset', 'classic');
        $global = $this->normalize_preset($global);
        $allow_event_override = (int) $this->settings->get('pdf_allow_event_override', 1);

        if (! $allow_event_override) {
            return $global;
        }

        $event_id = (int) get_post_meta($ticket_id, '_ticket_event_id', true);
        if ($event_id) {
            $preset = (string) get_post_meta($event_id, Event_Pdf_Preset_Meta::META_KEY, true);
            if ('' !== trim($preset)) {
                return $this->normalize_preset($preset);
            }
        }

        return $global;
    }

    private function normalize_preset(string $preset): string
    {
        $preset = trim($preset);
        if ('' === $preset) {
            return 'classic';
        }

        if (! in_array($preset, ['classic', 'minimal', 'dark'], true)) {
            return 'classic';
        }

        return $preset;
    }

    private function render_ticket_html(array $d): string
    {
        $preset = $d['preset'] ?? 'classic';
        $accent = $d['accent'] ?? '#2563eb';

        $bg    = '#ffffff';
        $card  = '#f9fafb';
        $text  = '#111827';
        $muted = '#6b7280';
        $line  = '#e5e7eb';

        if ('dark' === $preset) {
            $bg    = '#0b1220';
            $card  = '#0f172a';
            $text  = '#f8fafc';
            $muted = '#cbd5e1';
            $line  = '#1f2937';
        } elseif ('minimal' === $preset) {
            $card = '#ffffff';
        }

        $event_line = trim((string) ($d['event_start'] ?? ''));
        if (! empty($d['event_location'])) {
            $event_line = $event_line ? ($event_line . ' • ' . $d['event_location']) : (string) $d['event_location'];
        }

        $footer = trim((string) ($d['footer'] ?? ''));

        ob_start();
?>
        <!doctype html>
        <html lang="en">

        <head>
            <meta charset="UTF-8" />
            <style>
                @page {
                    margin: 28px 28px;
                }

                body {
                    font-family: DejaVu Sans, Arial, sans-serif;
                    font-size: 12px;
                    color: <?php echo  esc_attr($text) ?>;
                    background: <?php echo  esc_attr($bg) ?>;
                }

                .wrap {
                    border: 1px solid <?php echo  esc_attr($line) ?>;
                    border-radius: 12px;
                    background: <?php echo  esc_attr($card) ?>;
                    overflow: hidden;
                }

                .bar {
                    height: 6px;
                    background: <?php echo  esc_attr($accent) ?>;
                    border-radius: 12px 12px 0 0;
                }

                .header {
                    padding: 18px 18px 10px;
                    background: transparent;
                    border-bottom: 1px solid <?php echo  esc_attr($line) ?>;
                }

                .brand {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                }

                .logo {
                    width: 120px;
                    height: auto;
                }

                .title {
                    font-size: 18px;
                    font-weight: 700;
                    margin: 10px 0 4px;
                }

                .subtitle {
                    font-size: 12px;
                    color: <?php echo  esc_attr($muted) ?>;
                    margin: 0;
                }

                .content {
                    padding: 18px;
                }

                .grid {
                    width: 100%;
                    border-collapse: collapse;
                }

                .grid td {
                    vertical-align: top;
                }

                .left {
                    width: 62%;
                    padding-right: 12px;
                }

                .right {
                    width: 38%;
                    text-align: center;
                }

                .label {
                    font-size: 10px;
                    letter-spacing: 0.08em;
                    text-transform: uppercase;
                    color: <?php echo  esc_attr($muted) ?>;
                    margin: 0 0 3px;
                }

                .value {
                    margin: 0 0 12px;
                    font-size: 13px;
                }

                .code {
                    font-family: DejaVu Sans Mono, ui-monospace, Menlo, Monaco, Consolas, monospace;
                    letter-spacing: 0.08em;
                    font-size: 18px;
                    font-weight: 700;
                }

                .qr {
                    width: 220px;
                    height: auto;
                    border: 1px solid <?php echo  esc_attr($line) ?>;
                    border-radius: 10px;
                    padding: 10px;
                    background: #ffffff;
                    display: inline-block;
                }

                .hint {
                    margin: 8px 0 0;
                    font-size: 11px;
                    color: <?php echo  esc_attr($muted) ?>;
                }

                .note {
                    margin-top: 14px;
                    padding: 10px 12px;
                    border-radius: 8px;
                    border: 1px solid <?php echo  esc_attr($line) ?>;
                    background: <?php echo  esc_attr($card) ?>;
                    font-size: 11px;
                    color: <?php echo  esc_attr($muted) ?>;
                }

                .footer {
                    padding: 14px 18px;
                    border-top: 1px solid <?php echo  esc_attr($line) ?>;
                    font-size: 10px;
                    color: <?php echo  esc_attr($muted) ?>;
                    background: transparent;
                }
            </style>
        </head>

        <body>
            <div class="wrap">
                <div class="bar"></div>
                <div class="header">
                    <div class="brand">
                        <?php if (! empty($d['logo_data_uri'])) : ?>
                            <img class="logo" src="<?php echo  esc_attr($d['logo_data_uri']) ?>" alt="" />
                        <?php endif; ?>
                    </div>
                    <div class="title"><?php echo  esc_html((string) ($d['event_name'] ?? '')) ?></div>
                    <?php if ($event_line) : ?>
                        <p class="subtitle"><?php echo  esc_html($event_line) ?></p>
                    <?php endif; ?>
                </div>

                <div class="content">
                    <table class="grid">
                        <tr>
                            <td class="left">
                                <p class="label"><?php echo  esc_html__('Attendee', 'Event-Tickets-for-Elementor') ?></p>
                                <p class="value"><?php echo  esc_html((string) ($d['attendee_name'] ?? '')) ?></p>

                                <?php if (! empty($d['attendee_email'])) : ?>
                                    <p class="label"><?php echo  esc_html__('Email', 'Event-Tickets-for-Elementor') ?></p>
                                    <p class="value"><?php echo  esc_html((string) $d['attendee_email']) ?></p>
                                <?php endif; ?>

                                <p class="label"><?php echo  esc_html__('Ticket code', 'Event-Tickets-for-Elementor') ?></p>
                                <p class="value code"><?php echo  esc_html((string) ($d['ticket_code'] ?? '')) ?></p>

                                <div class="note">
                                    <?php echo  esc_html__('This ticket is valid only when scanned through the official check-in system.', 'Event-Tickets-for-Elementor') ?>
                                </div>
                            </td>
                            <td class="right">
                                <?php if (! empty($d['qr_data_uri'])) : ?>
                                    <img class="qr" src="<?php echo  esc_attr($d['qr_data_uri']) ?>" alt="" />
                                    <p class="hint"><?php echo  esc_html__('Show this QR at the entrance.', 'Event-Tickets-for-Elementor') ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="footer">
                    <?php if ($footer) : ?>
                        <div style="white-space: pre-line;"><?php echo  esc_html($footer) ?></div>
                    <?php else : ?>
                        <?php echo  esc_html__('Generated by Event Tickets for Elementor.', 'Event-Tickets-for-Elementor') ?>
                    <?php endif; ?>
                </div>
            </div>
        </body>

        </html>
<?php
        $html = ob_get_clean();

        return is_string($html) ? $html : '';
    }

    /**
     * Get event snapshot prioritising linked Event CPT over ticket snapshot.
     *
     * @return array{name:string,start:string,end:string,location:string}
     */
    private function get_event_snapshot_for_ticket(int $ticket_id): array
    {
        $name      = get_post_meta($ticket_id, '_ticket_event_name', true);
        $start     = get_post_meta($ticket_id, '_ticket_event_start', true);
        $end       = get_post_meta($ticket_id, '_ticket_event_end', true);
        $location  = get_post_meta($ticket_id, '_ticket_event_location', true);
        $event_id  = (int) get_post_meta($ticket_id, '_ticket_event_id', true);

        if ($event_id) {
            $event = get_post($event_id);
            if ($event && CPT_Events::POST_TYPE === $event->post_type) {
                $name      = $event->post_title ?: $name;
                $start     = $start ?: get_post_meta($event_id, '_evt_event_start', true);
                $end       = $end ?: get_post_meta($event_id, '_evt_event_end', true);
                $location  = $location ?: get_post_meta($event_id, '_evt_event_location', true);
            }
        }

        return [
            'name'     => (string) $name,
            'start'    => (string) $start,
            'end'      => (string) $end,
            'location' => (string) $location,
        ];
    }

    private function get_site_logo_data_uri(): string
    {
        $attachment_id = 0;
        if (function_exists('has_custom_logo') && has_custom_logo()) {
            $attachment_id = (int) get_theme_mod('custom_logo');
        }

        if (! $attachment_id) {
            $attachment_id = (int) get_option('site_icon');
        }

        if (! $attachment_id) {
            return '';
        }

        $path = get_attached_file($attachment_id);
        if (! $path || ! file_exists($path)) {
            return '';
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = '';
        if ('png' === $ext) {
            $mime = 'image/png';
        } elseif ('jpg' === $ext || 'jpeg' === $ext) {
            $mime = 'image/jpeg';
        } elseif ('gif' === $ext) {
            $mime = 'image/gif';
        } else {
            return '';
        }

        $bytes = @file_get_contents($path);
        if (! is_string($bytes) || '' === $bytes) {
            return '';
        }

        return 'data:' . $mime . ';base64,' . base64_encode($bytes);
    }

    private function sanitize_ticket_code(string $code): string
    {
        $code = strtoupper(trim($code));
        $code = preg_replace('/[^A-Z0-9_-]/', '', $code);
        return is_string($code) ? $code : '';
    }

    private function ensure_dompdf_loaded(): void
    {
        if ($this->dompdf_loaded) {
            return;
        }

        $autoload = EVT_TICKETS_PLUGIN_DIR . 'includes/vendor/dompdf/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        }

        $this->dompdf_loaded = true;
    }

    private function ensure_dir(string $dir): bool
    {
        if ('' === $dir) {
            return false;
        }

        if (! file_exists($dir)) {
            wp_mkdir_p($dir);
        }

        if (file_exists($dir) && is_dir($dir)) {
            // Block direct web access to generated files (PDFs contain PII).
            evt_protect_upload_dir($dir);
            return true;
        }

        return false;
    }

    private function temp_dir(): string
    {
        $upload = wp_upload_dir();
        if (empty($upload['basedir'])) {
            return '';
        }

        $dir = trailingslashit($upload['basedir']) . 'evt-tickets/tmp';
        if (! $this->ensure_dir($dir)) {
            return '';
        }

        return $dir;
    }
}
