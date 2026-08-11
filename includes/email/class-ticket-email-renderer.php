<?php

namespace EventTicketsElementor\Email;

use EventTicketsElementor\Settings;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Renders ticket email HTML (default template + optional custom template).
 */
class Ticket_Email_Renderer
{
    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * @param array<string,string> $tokens
     * @param array<string,string> $view
     */
    public function render_single(array $tokens, array $view): string
    {
        if ($this->use_custom_template()) {
            $html_tpl = (string) $this->settings->get('email_custom_template_html', '');
            $css_tpl  = (string) $this->settings->get('email_custom_template_css', '');

            if ('' !== trim($html_tpl)) {
                $escaped_tokens = $this->escape_template_tokens($tokens);
                $css  = $this->replace_tokens($css_tpl, $escaped_tokens);
                $html = $this->replace_tokens($html_tpl, $escaped_tokens);

                return $this->wrap_with_css($css, $html);
            }
        }

        $preset = Email_Template_Presets::get_current();
        $t = $tokens;
        $t['greeting'] = (string) ($view['greeting'] ?? '');
        $t['intro'] = (string) ($view['intro'] ?? '');
        $t['footer_text'] = (string) ($view['footer_text'] ?? '');
        $t['ticket_code_intro'] = (string) ($view['ticket_code_intro'] ?? '');
        $t['qr_img_src'] = (string) ($view['qr_img_src'] ?? '');

        $qr_tpl = (string) ($view['qr_instructions_tpl'] ?? '');
        $t['qr_instructions'] = '';
        if ('' !== trim($qr_tpl)) {
            $t['qr_instructions'] = $this->replace_tokens($qr_tpl, $t);
        }

        $tpl = Email_Template_Presets::single($preset);
        if ('' !== trim((string) ($tpl['html'] ?? ''))) {
            $escaped_t = $this->escape_template_tokens($t);
            $css = $this->replace_tokens((string) ($tpl['css'] ?? ''), $escaped_t);
            $html = $this->replace_tokens((string) ($tpl['html'] ?? ''), $escaped_t);
            return $this->wrap_with_css($css, $html);
        }

        return $this->render_default_single($tokens, $view);
    }

    /**
     * @param array<string,string> $tokens
     * @param array<int,array<string,string>> $tickets
     */
    public function render_multi(array $tokens, array $tickets): string
    {
        if ($this->use_custom_template()) {
            $html_tpl = (string) $this->settings->get('email_custom_template_html', '');
            $css_tpl  = (string) $this->settings->get('email_custom_template_css', '');

            if ('' !== trim($html_tpl)) {
                $tickets_html = $this->render_default_tickets_list($tickets);
                $tokens['tickets_html'] = $tickets_html;
                $escaped_tokens = $this->escape_template_tokens($tokens);
                $css  = $this->replace_tokens($css_tpl, $escaped_tokens);
                $html = $this->replace_tokens($html_tpl, $escaped_tokens);

                return $this->wrap_with_css($css, $html);
            }
        }

        $preset = Email_Template_Presets::get_current();
        $t = $tokens;
        if (empty($t['tickets_html'])) {
            $t['tickets_html'] = $this->render_default_tickets_list($tickets);
        }

        $tpl = Email_Template_Presets::multi($preset);
        if ('' !== trim((string) ($tpl['html'] ?? ''))) {
            $escaped_t = $this->escape_template_tokens($t);
            $css = $this->replace_tokens((string) ($tpl['css'] ?? ''), $escaped_t);
            $html = $this->replace_tokens((string) ($tpl['html'] ?? ''), $escaped_t);
            return $this->wrap_with_css($css, $html);
        }

        return $this->render_default_multi($tokens, $tickets);
    }

    /**
     * @param array<string,string> $tokens
     * @param array<string,string> $view
     */
    private function render_default_single(array $tokens, array $view): string
    {
        $event_heading = (string) $this->settings->get('email_event_details_heading', __('Event details', 'Event-Tickets-for-Elementor'));
        $label_event = (string) $this->settings->get('email_label_event', __('Event:', 'Event-Tickets-for-Elementor'));
        $label_datetime = (string) $this->settings->get('email_label_datetime', __('Date & time:', 'Event-Tickets-for-Elementor'));
        $label_end = (string) $this->settings->get('email_label_end', __('End:', 'Event-Tickets-for-Elementor'));
        $label_location = (string) $this->settings->get('email_label_location', __('Location:', 'Event-Tickets-for-Elementor'));

        $show_location_block = (int) $this->settings->get('email_show_location_block', 1);
        $loc_heading = (string) $this->settings->get('email_location_heading', __('Getting there', 'Event-Tickets-for-Elementor'));
        $loc_body_tpl = (string) $this->settings->get('email_location_body', "Venue: {event_location}\nOpen in Maps: {event_map_url}");
        $loc_body = $this->replace_tokens($loc_body_tpl, $tokens);

        $ticket_code_intro = (string) $this->settings->get('email_ticket_code_intro', __('Your ticket code is:', 'Event-Tickets-for-Elementor'));

        $calendar_heading = (string) $this->settings->get('email_add_to_calendar_heading', __('Add to calendar:', 'Event-Tickets-for-Elementor'));
        $google_label = (string) $this->settings->get('email_google_calendar_label', __('Google Calendar', 'Event-Tickets-for-Elementor'));
        $ics_label = (string) $this->settings->get('email_ics_label', __('Download .ics', 'Event-Tickets-for-Elementor'));
        $pdf_label = (string) $this->settings->get('email_pdf_label', __('Download Ticket (PDF)', 'Event-Tickets-for-Elementor'));
        $cancel_label = Email_Links::cancel_link_label();

        $default_css = (string) $this->settings->get('email_custom_css', '');

        $html  = '<style>';
        $html .= '.evt-email{font-family:Arial,sans-serif;font-size:14px;color:#222}';
        $html .= '.evt-email a{color:#111827}';
        $html .= '.evt-email__box{margin:16px 0;padding:10px 12px;border-radius:4px;background-color:#f7f7f7}';
        $html .= '.evt-email__muted{font-size:12px;color:#666}';
        $html .= '.evt-email__pill{display:inline-block;padding:6px 10px;border-radius:999px;border:1px solid #d1d5db;font-size:12px;color:#111827;text-decoration:none}';
        $html .= '.evt-email__btn{display:inline-block;padding:10px 14px;border-radius:8px;background:#111827;color:#ffffff;text-decoration:none;font-size:13px}';
        if ('' !== trim($default_css)) {
            $html .= "\n" . $this->escape_css($default_css) . "\n";
        }
        $html .= '</style>';

        $html .= '<div class="evt-email">';

        if (! empty($view['greeting'])) {
            $html .= '<p class="evt-email__greeting">' . esc_html((string) $view['greeting']) . '</p>';
        }
        if (! empty($view['intro'])) {
            $html .= '<p class="evt-email__intro">' . esc_html((string) $view['intro']) . '</p>';
        }

        if (! empty($tokens['event_name']) || ! empty($tokens['event_start']) || ! empty($tokens['event_end']) || ! empty($tokens['event_location'])) {
            $html .= '<div class="evt-email__box evt-email__event">';
            $html .= '<p style="margin: 0 0 4px;"><strong>' . esc_html($event_heading) . '</strong></p>';
            if (! empty($tokens['event_name'])) {
                $html .= '<p style="margin:0;">' . esc_html($label_event) . '&nbsp;' . esc_html($tokens['event_name']) . '</p>';
            }
            if (! empty($tokens['event_start'])) {
                $html .= '<p style="margin:0;">' . esc_html($label_datetime) . '&nbsp;' . esc_html($tokens['event_start']) . '</p>';
            }
            if (! empty($tokens['event_end'])) {
                $html .= '<p style="margin:0;">' . esc_html($label_end) . '&nbsp;' . esc_html($tokens['event_end']) . '</p>';
            }
            if (! empty($tokens['event_location'])) {
                $html .= '<p style="margin:0;">' . esc_html($label_location) . '&nbsp;' . esc_html($tokens['event_location']) . '</p>';
            }
            $html .= '</div>';
        }

        if (
            $show_location_block
            && ('' !== trim((string) ($tokens['event_location'] ?? '')) || '' !== trim((string) ($tokens['event_map_url'] ?? '')))
            && '' !== trim($loc_body)
        ) {
            $html .= '<div class="evt-email__box evt-email__location">';
            $html .= '<p style="margin:0 0 4px;"><strong>' . esc_html($loc_heading) . '</strong></p>';
            $html .= '<p style="margin:0; white-space: pre-line;">' . esc_html($loc_body) . '</p>';
            $html .= '</div>';
        }

        $html .= '<p class="evt-email__ticket-label">' . esc_html($ticket_code_intro) . '</p>';
        $html .= '<p style="font-size:18px;font-weight:bold;letter-spacing:1px;">' . esc_html((string) ($tokens['ticket_code'] ?? '')) . '</p>';

        $html .= '<p class="evt-email__muted">' . esc_html__('Present this email or your PDF ticket at the entrance. Staff can verify your ticket code.', 'Event-Tickets-for-Elementor') . '</p>';

        if (! empty($tokens['google_cal_url']) || ! empty($tokens['ics_url'])) {
            $html .= '<div class="evt-email__box evt-email__calendar" style="background-color:#f9fafb;">';
            $html .= '<p style="margin:0 0 6px;font-size:13px;font-weight:bold;">' . esc_html($calendar_heading) . '</p>';
            if (! empty($tokens['google_cal_url'])) {
                $html .= '<a class="evt-email__pill" style="margin-right:8px;" href="' . esc_url($tokens['google_cal_url']) . '" target="_blank" rel="noopener noreferrer">' . esc_html($google_label) . '</a>';
            }
            if (! empty($tokens['ics_url'])) {
                $html .= '<a class="evt-email__pill" href="' . esc_url($tokens['ics_url']) . '">' . esc_html($ics_label) . '</a>';
            }
            $html .= '</div>';
        }

        if (! empty($tokens['pdf_url'])) {
            $html .= '<div style="margin:18px 0;">';
            $html .= '<a class="evt-email__btn" href="' . esc_url($tokens['pdf_url']) . '">' . esc_html($pdf_label) . '</a>';
            $html .= '</div>';
        }

        if (! empty($tokens['cancel_url'])) {
            $html .= '<p style="margin:10px 0 0;font-size:12px;">';
            $html .= '<a href="' . esc_url($tokens['cancel_url']) . '">' . esc_html($cancel_label) . '</a>';
            $html .= '</p>';
        }

        if (! empty($view['footer_text'])) {
            $html .= '<hr style="border:none;border-top:1px solid #e1e1e1;margin:20px 0;" />';
            $html .= '<p style="font-size:12px;color:#555;white-space:pre-line;">' . esc_html((string) $view['footer_text']) . '</p>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * @param array<string,string> $tokens
     * @param array<int,array<string,string>> $tickets
     */
    private function render_default_multi(array $tokens, array $tickets): string
    {
        $default_css = (string) $this->settings->get('email_custom_css', '');
        $multi_intro = (string) $this->settings->get('email_multi_tickets_intro', __('Here are your tickets:', 'Event-Tickets-for-Elementor'));
        $label_datetime = (string) $this->settings->get('email_label_datetime', __('Date & time:', 'Event-Tickets-for-Elementor'));
        $label_end = (string) $this->settings->get('email_label_end', __('End:', 'Event-Tickets-for-Elementor'));
        $label_location = (string) $this->settings->get('email_label_location', __('Location:', 'Event-Tickets-for-Elementor'));
        $label_ticket_code = (string) $this->settings->get('email_label_ticket_code', __('Ticket code:', 'Event-Tickets-for-Elementor'));
        $calendar_heading = (string) $this->settings->get('email_add_to_calendar_heading', __('Add to calendar:', 'Event-Tickets-for-Elementor'));
        $google_label = (string) $this->settings->get('email_google_calendar_label', __('Google Calendar', 'Event-Tickets-for-Elementor'));
        $ics_label = (string) $this->settings->get('email_ics_label', __('Download .ics', 'Event-Tickets-for-Elementor'));
        $pdf_label = (string) $this->settings->get('email_pdf_label', __('Download Ticket (PDF)', 'Event-Tickets-for-Elementor'));
        $cancel_label = Email_Links::cancel_link_label();

        $show_location_block = (int) $this->settings->get('email_show_location_block', 1);
        $loc_heading = (string) $this->settings->get('email_location_heading', __('Getting there', 'Event-Tickets-for-Elementor'));
        $loc_body_tpl = (string) $this->settings->get('email_location_body', "Venue: {event_location}\nOpen in Maps: {event_map_url}");
        $loc_body = $this->replace_tokens($loc_body_tpl, $tokens);

        $html  = '<style>.evt-email{font-family:Arial,sans-serif;font-size:14px;color:#222}';
        if ('' !== trim($default_css)) {
            $html .= "\n" . $this->escape_css($default_css) . "\n";
        }
        $html .= '</style>';
        $html .= '<div class="evt-email">';

        if (! empty($tokens['greeting'])) {
            $html .= '<p>' . esc_html($tokens['greeting']) . '</p>';
        }
        if (! empty($tokens['intro'])) {
            $html .= '<p>' . esc_html($tokens['intro']) . '</p>';
        }

        if ('' !== trim($multi_intro)) {
            $html .= '<p>' . esc_html($multi_intro) . '</p>';
        }

        if (
            $show_location_block
            && ('' !== trim((string) ($tokens['event_location'] ?? '')) || '' !== trim((string) ($tokens['event_map_url'] ?? '')))
            && '' !== trim($loc_body)
        ) {
            $html .= '<div style="margin:16px 0;padding:10px 12px;border-radius:4px;background-color:#f7f7f7;">';
            $html .= '<p style="margin:0 0 4px;"><strong>' . esc_html($loc_heading) . '</strong></p>';
            $html .= '<p style="margin:0; white-space: pre-line;">' . esc_html($loc_body) . '</p>';
            $html .= '</div>';
        }

        foreach ($tickets as $ticket) {
            $html .= '<div style="margin:12px 0;padding:12px;border:1px solid #e5e7eb;border-radius:6px;">';
            $html .= '<p style="margin:0 0 6px;"><strong>' . esc_html($ticket['event'] ?? '') . '</strong></p>';
            if (! empty($ticket['start'])) {
                $html .= '<p style="margin:0;">' . esc_html($label_datetime) . ' ' . esc_html($ticket['start']) . '</p>';
            }
            if (! empty($ticket['end'])) {
                $html .= '<p style="margin:0;">' . esc_html($label_end) . ' ' . esc_html($ticket['end']) . '</p>';
            }
            if (! empty($ticket['location'])) {
                $html .= '<p style="margin:0;">' . esc_html($label_location) . ' ' . esc_html($ticket['location']) . '</p>';
            }

            $html .= '<p style="margin:8px 0 0;">' . esc_html($label_ticket_code) . ' <strong>' . esc_html($ticket['code'] ?? '') . '</strong></p>';

            if (! empty($ticket['gcal']) || ! empty($ticket['ics'])) {
                $html .= '<p style="margin:10px 0 0;font-size:12px;">' . esc_html($calendar_heading) . ' ';
                if (! empty($ticket['gcal'])) {
                    $html .= '<a href="' . esc_url($ticket['gcal']) . '" target="_blank" rel="noopener noreferrer">' . esc_html($google_label) . '</a>';
                }
                if (! empty($ticket['ics'])) {
                    $html .= ' | <a href="' . esc_url($ticket['ics']) . '">' . esc_html($ics_label) . '</a>';
                }
                $html .= '</p>';
            }

            if (! empty($ticket['pdf'])) {
                $html .= '<p style="margin:10px 0 0;font-size:12px;"><a href="' . esc_url($ticket['pdf']) . '">' . esc_html($pdf_label) . '</a></p>';
            }

            $html .= '</div>';
        }

        if (! empty($tokens['cancel_url'])) {
            $html .= '<p style="margin:10px 0 0;font-size:12px;">';
            $html .= '<a href="' . esc_url($tokens['cancel_url']) . '">' . esc_html($cancel_label) . '</a>';
            $html .= '</p>';
        }

        $html .= '<p style="margin-top:14px;font-size:12px;color:#555;">' . esc_html__('Present this email or your PDF ticket at the entrance. Staff can verify your ticket code.', 'Event-Tickets-for-Elementor') . '</p>';

        $html .= '</div>';
        return $html;
    }

    /**
     * @param array<int,array<string,string>> $tickets
     */
    private function render_default_tickets_list(array $tickets): string
    {
        $html = '';
        foreach ($tickets as $ticket) {
            $html .= '<div style="margin:12px 0;padding:12px;border:1px solid #e5e7eb;border-radius:6px;">';
            $html .= '<strong>' . esc_html($ticket['event'] ?? '') . '</strong>';
            $html .= ' — ' . esc_html($ticket['code'] ?? '');
            $html .= '</div>';
        }
        return $html;
    }

    private function use_custom_template(): bool
    {
        return (int) $this->settings->get('email_use_custom_template', 0) === 1;
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

    /**
     * Escape token values before substituting them into an HTML template.
     *
     * Text tokens are HTML-escaped; URL tokens are url-escaped; pre-rendered
     * HTML fragments (already escaped by their builders) pass through untouched.
     *
     * @param array<string,string> $tokens
     * @return array<string,string>
     */
    private function escape_template_tokens(array $tokens): array
    {
        // Pre-rendered HTML / image-source values that must pass through unchanged.
        $raw = [
            'tickets_html',   // built by render_default_tickets_list() (already escaped).
            'qr_instructions', // built from a wp_kses-post admin template.
            'qr_img_src',     // cid: scheme is not a valid esc_url() protocol.
        ];

        // Tokens rendered into href=/src= attributes.
        $urls = [
            'logo_url',
            'pdf_url',
            'ics_url',
            'google_cal_url',
            'verify_url',
            'cancel_url',
            'event_map_url',
            'event_url',
        ];

        $escaped = [];
        foreach ($tokens as $key => $value) {
            $key   = (string) $key;
            $value = (string) $value;

            if (in_array($key, $raw, true)) {
                $escaped[$key] = $value;
            } elseif (in_array($key, $urls, true)) {
                $escaped[$key] = esc_url($value);
            } else {
                $escaped[$key] = esc_html($value);
            }
        }

        return $escaped;
    }

    private function wrap_with_css(string $css, string $html): string
    {
        $css = trim((string) $css);
        $html = (string) $html;

        if ('' === $css) {
            return $html;
        }

        $style_tag = "<style>\n" . $this->escape_css($css) . "\n</style>";

        // If a full HTML document is provided, inject into <head>.
        if (false !== stripos($html, '<html')) {
            if (false !== stripos($html, '</head>')) {
                $patched = preg_replace('/<\\/head>/i', $style_tag . "\n</head>", $html, 1);
                return is_string($patched) ? $patched : ($style_tag . $html);
            }

            // Has <html> but no <head>: add one.
            $patched = preg_replace('/<html([^>]*)>/i', '<html$1><head>' . $style_tag . '</head>', $html, 1);
            return is_string($patched) ? $patched : ($style_tag . $html);
        }

        // Fragment: wrap into a minimal document so clients (Gmail) keep <style>.
        return '<!doctype html><html><head><meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>'
            . $style_tag
            . '</head><body>'
            . $html
            . '</body></html>';
    }

    private function escape_css(string $css): string
    {
        // Keep as plain text in <style>. (No HTML escaping that would break CSS.)
        return (string) preg_replace('/<\\/?style[^>]*>/i', '', (string) $css);
    }
}
