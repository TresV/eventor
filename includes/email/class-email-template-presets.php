<?php

namespace EventTicketsElementor\Email;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Email template presets (3 built-ins) + current preset selection.
 */
class Email_Template_Presets
{
    public const OPTION_KEY = 'evt_tickets_email_preset';

    public const PRESET_CLASSIC = 'classic';
    public const PRESET_MINIMAL = 'minimal';
    public const PRESET_DARK    = 'dark';

    /**
     * @return array<string,string>
     */
    public static function presets(): array
    {
        return [
            self::PRESET_CLASSIC => __('Classic', 'Event-Tickets-for-Elementor'),
            self::PRESET_MINIMAL => __('Minimal', 'Event-Tickets-for-Elementor'),
            self::PRESET_DARK    => __('Dark', 'Event-Tickets-for-Elementor'),
        ];
    }

    public static function get_current(): string
    {
        $preset = get_option(self::OPTION_KEY, self::PRESET_CLASSIC);
        $preset = is_string($preset) ? strtolower(trim($preset)) : self::PRESET_CLASSIC;
        if (! isset(self::presets()[$preset])) {
            return self::PRESET_CLASSIC;
        }
        return $preset;
    }

    public static function sanitize_preset($value): string
    {
        $preset = is_string($value) ? strtolower(trim($value)) : self::PRESET_CLASSIC;
        if (! isset(self::presets()[$preset])) {
            return self::PRESET_CLASSIC;
        }
        return $preset;
    }

    /**
     * @return array{css:string,html:string}
     */
    public static function single(string $preset): array
    {
        $preset = self::sanitize_preset($preset);

        if (self::PRESET_CLASSIC === $preset) {
            return [
                'css'  => self::classic_css(),
                'html' => self::classic_single_html(),
            ];
        }

        if (self::PRESET_DARK === $preset) {
            return [
                'css'  => self::dark_css(),
                'html' => self::dark_single_html(),
            ];
        }

        if (self::PRESET_MINIMAL === $preset) {
            return [
                'css'  => self::minimal_css(),
                'html' => self::minimal_single_html(),
            ];
        }

        return [
            'css'  => self::classic_css(),
            'html' => self::classic_single_html(),
        ];
    }

    /**
     * @return array{css:string,html:string}
     */
    public static function multi(string $preset): array
    {
        $preset = self::sanitize_preset($preset);

        if (self::PRESET_CLASSIC === $preset) {
            return [
                'css'  => self::classic_css(),
                'html' => self::classic_multi_html(),
            ];
        }

        if (self::PRESET_DARK === $preset) {
            return [
                'css'  => self::dark_css(),
                'html' => self::dark_multi_html(),
            ];
        }

        if (self::PRESET_MINIMAL === $preset) {
            return [
                'css'  => self::minimal_css(),
                'html' => self::minimal_multi_html(),
            ];
        }

        return [
            'css'  => self::classic_css(),
            'html' => self::classic_multi_html(),
        ];
    }

    /**
     * @param array<string,string> $sample_tokens
     */
    public static function preview_srcdoc(string $preset, array $sample_tokens): string
    {
        $preset = self::sanitize_preset($preset);
        $tpl = self::single($preset);
        $html = self::replace($tpl['html'], $sample_tokens);
        $css = self::replace($tpl['css'], $sample_tokens);
        return self::srcdoc($css, $html);
    }

    private static function srcdoc(string $css, string $html): string
    {
        $out  = '<!doctype html><html><head><meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>';
        if ('' !== trim($css)) {
            $out .= '<style>' . $css . '</style>';
        }
        $out .= '</head><body>' . $html . '</body></html>';
        return $out;
    }

    /**
     * @param array<string,string> $tokens
     */
    private static function replace(string $template, array $tokens): string
    {
        $replacements = [];
        foreach ($tokens as $key => $value) {
            $replacements['{' . $key . '}'] = (string) $value;
        }
        return strtr((string) $template, $replacements);
    }

    private static function classic_css(): string
    {
        return 'body{margin:0;background:{background_color}}'
            . '.wrap{font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#111827;padding:18px}'
            . '.card{max-width:640px;margin:0 auto;background:#ffffff;border:1px solid rgba(17,24,39,0.08);border-radius:18px;overflow:hidden}'
            . '.header{padding:18px 20px;background:linear-gradient(135deg,{primary_color},{accent_color});color:#fff}'
            . '.logo{display:{logo_display};margin-bottom:10px}'
            . '.logo img{height:28px;max-width:180px;display:block}'
            . '.header .title{font-size:18px;font-weight:700;line-height:1.25}'
            . '.header .meta{margin-top:6px;font-size:12px;opacity:.92}'
            . '.content{padding:18px 20px}'
            . '.box{background:#f9fafb;border:1px solid #e5e7eb;border-radius:14px;padding:14px}'
            . '.muted{color:#6b7280;font-size:12px}'
            . '.code{font-size:18px;font-weight:800;letter-spacing:1px;color:#111827}'
            . '.btn{display:inline-block;padding:12px 16px;border-radius:999px;background:{accent_color};color:#fff;text-decoration:none;font-weight:700;font-size:13px}'
            . '.links a{color:{accent_color};text-decoration:none}'
            . '.footer{padding:14px 20px;background:#f9fafb;border-top:1px solid #e5e7eb}';
    }

    private static function classic_single_html(): string
    {
        return implode( "\n", [
            '<div class="wrap">',
            '  <div class="card">',
            '    <div class="header">',
            '      <div class="logo"><img src="{logo_url}" alt="{site_name}"/></div>',
            '      <div class="title">{event_name}</div>',
            '      <div class="meta">{event_start} – {event_end}</div>',
            '    </div>',
            '    <div class="content">',
            '      <div style="font-weight:700;margin-bottom:6px;">{greeting}</div>',
            '      <div style="color:#374151;line-height:1.6;margin-bottom:14px;">{intro}</div>',
            '      <div class="box">',
            '        <div class="muted">Ticket code</div>',
            '        <div class="code" style="margin-top:6px;">{ticket_code}</div>',
            '        <div class="muted" style="margin-top:10px;">Location</div>',
            '        <div style="margin-top:4px;color:#111827;">{event_location}</div>',
            '        <div class="muted" style="margin-top:10px;">Entry</div>',
            '        <div style="margin-top:4px;color:#111827;">Present this email or your PDF ticket at the entrance. Staff can verify your ticket code.</div>',
            '        <div style="margin-top:12px;text-align:center;">',
            '          <a class="btn" href="{pdf_url}">Download Ticket (PDF)</a>',
            '        </div>',
            '        <div class="links" style="margin-top:12px;font-size:12px;text-align:center;">',
            '          <a href="{ics_url}">Add to calendar (ICS)</a> · <a href="{google_cal_url}">Google Calendar</a> · <a href="{verify_url}">Verify</a>',
            '        </div>',
            '      </div>',
            '    </div>',
            '    <div class="footer">',
            '      <div class="links" style="font-size:12px;"><a href="{cancel_url}">Cancel ticket</a></div>',
            '      <div class="muted" style="margin-top:8px;white-space:pre-line;">{footer_text}</div>',
            '    </div>',
            '  </div>',
            '</div>',
        ] );
    }

    private static function classic_multi_html(): string
    {
        return implode( "\n", [
            '<div class="wrap">',
            '  <div class="card">',
            '    <div class="header">',
            '      <div class="logo"><img src="{logo_url}" alt="{site_name}"/></div>',
            '      <div class="title">{event_name}</div>',
            '      <div class="meta">{event_start} – {event_end}</div>',
            '    </div>',
            '    <div class="content">',
            '      <div style="font-weight:700;margin-bottom:6px;">{greeting}</div>',
            '      <div style="color:#374151;line-height:1.6;margin-bottom:14px;">{intro}</div>',
            '      <div class="box">',
            '        {tickets_html}',
            '        <div class="links" style="margin-top:12px;font-size:12px;text-align:center;">',
            '          <a href="{cancel_url}">Cancel ticket</a>',
            '        </div>',
            '      </div>',
            '    </div>',
            '  </div>',
            '</div>',
        ] );
    }

    private static function minimal_css(): string
    {
        return 'body{margin:0;background:{background_color}}'
            . '.wrap{font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#111827;padding:18px}'
            . '.card{max-width:640px;margin:0 auto;background:#ffffff;border:1px solid rgba(17,24,39,0.10);border-radius:16px;overflow:hidden}'
            . '.top{padding:16px 18px;border-bottom:1px solid #e5e7eb}'
            . '.logo{display:{logo_display};margin-bottom:10px}'
            . '.logo img{height:24px;max-width:160px;display:block}'
            . '.event{font-weight:800;font-size:16px}'
            . '.meta{margin-top:6px;color:#6b7280;font-size:12px}'
            . '.content{padding:16px 18px}'
            . '.row{display:flex;gap:12px;align-items:flex-start;flex-wrap:wrap}'
            . '.code{font-size:18px;font-weight:800;letter-spacing:1px;color:{primary_color}}'
            . '.btn{display:inline-block;padding:10px 14px;border-radius:10px;background:{primary_color};color:#fff;text-decoration:none;font-weight:700;font-size:13px}'
            . '.links a{color:{accent_color};text-decoration:none}'
            . '.qr{margin-top:14px;text-align:center}'
            . '.qr img{max-width:220px;height:auto;display:block;margin:0 auto}'
            . '.footer{padding:14px 18px;background:#f9fafb;border-top:1px solid #e5e7eb}';
    }

    private static function minimal_single_html(): string
    {
        return implode( "\n", [
            '<div class="wrap">',
            '  <div class="card">',
            '    <div class="top">',
            '      <div class="logo"><img src="{logo_url}" alt="{site_name}"/></div>',
            '      <div class="event">{event_name}</div>',
            '      <div class="meta">{event_start} – {event_end}</div>',
            '    </div>',
            '    <div class="content">',
            '      <div style="font-weight:700;margin-bottom:6px;">{greeting}</div>',
            '      <div style="color:#374151;line-height:1.6;margin-bottom:12px;">{intro}</div>',
            '      <div class="row">',
            '        <div style="flex:1;min-width:220px;">',
            '          <div class="meta">Ticket code</div>',
            '          <div class="code" style="margin-top:6px;">{ticket_code}</div>',
            '          <div class="meta" style="margin-top:10px;">Location</div>',
            '          <div style="margin-top:4px;color:#111827;">{event_location}</div>',
            '          <div class="meta" style="margin-top:10px;">Entry</div>',
            '          <div style="margin-top:4px;color:#111827;">Present this email or your PDF ticket at the entrance. Staff can verify your ticket code.</div>',
            '          <div style="margin-top:12px;">',
            '            <a class="btn" href="{pdf_url}">Download PDF</a>',
            '          </div>',
            '          <div class="links" style="margin-top:12px;font-size:12px;">',
            '            <a href="{ics_url}">ICS</a> · <a href="{google_cal_url}">Google Calendar</a> · <a href="{cancel_url}">Cancel</a>',
            '          </div>',
            '        </div>',
            '      </div>',
            '    </div>',
            '    <div class="footer">',
            '      <div class="meta" style="white-space:pre-line;">{footer_text}</div>',
            '    </div>',
            '  </div>',
            '</div>',
        ] );
    }

    private static function minimal_multi_html(): string
    {
        return implode( "\n", [
            '<div class="wrap">',
            '  <div class="card">',
            '    <div class="top">',
            '      <div class="logo"><img src="{logo_url}" alt="{site_name}"/></div>',
            '      <div class="event">{event_name}</div>',
            '      <div class="meta">{event_start} – {event_end}</div>',
            '    </div>',
            '    <div class="content">',
            '      <div style="font-weight:700;margin-bottom:6px;">{greeting}</div>',
            '      <div style="color:#374151;line-height:1.6;margin-bottom:12px;">{intro}</div>',
            '      {tickets_html}',
            '      <div class="links" style="margin-top:12px;font-size:12px;">',
            '        <a href="{cancel_url}">Cancel ticket</a>',
            '      </div>',
            '    </div>',
            '  </div>',
            '</div>',
        ] );
    }

    private static function dark_css(): string
    {
        return 'body{margin:0;background:{background_color}}.wrap{font-family:Arial,sans-serif;font-size:14px;color:#eef0ff;padding:16px}.card{background:#070a1b;border:1px solid #151b44;border-radius:16px;overflow:hidden}.hd{padding:18px 20px;background:linear-gradient(135deg,{primary_color},{accent_color},#2B0F3A)}.muted{color:#c7cbe6;font-size:12px}.px{padding:18px 20px}.logo{display:{logo_display};margin-bottom:10px}.logo img{height:26px;max-width:180px;display:block}a{color:{accent_color};text-decoration:none}';
    }

    private static function dark_single_html(): string
    {
        return implode( "\n", [
            '<div class="wrap">',
            '  <div class="card">',
            '    <div class="hd">',
            '      <div class="logo"><img src="{logo_url}" alt="{site_name}"/></div>',
            '      <div style="font-weight:700;font-size:18px;">{event_name}</div>',
            '      <div class="muted" style="margin-top:6px;">{event_start} – {event_end}</div>',
            '    </div>',
            '    <div class="px">',
            '      <div style="font-weight:700;font-size:16px;margin-bottom:8px;">{greeting}</div>',
            '      <div style="color:#c7cbe6;line-height:1.6;margin-bottom:14px;">{intro}</div>',
            '      <div style="background:#0b1030;border:1px solid #1b2357;border-radius:14px;padding:14px;">',
            '        <div class="muted">Ticket code</div>',
            '        <div style="font-size:18px;font-weight:700;letter-spacing:1px;margin-top:6px;">{ticket_code}</div>',
            '        <div class="muted" style="margin-top:10px;">Location</div>',
            '        <div style="margin-top:4px;color:#eef0ff;">{event_location}</div>',
            '        <div style="margin-top:10px;font-size:12px;">',
            '          <a href="{pdf_url}">Download PDF</a> · <a href="{ics_url}">ICS</a> · <a href="{google_cal_url}">Google Calendar</a>',
            '        </div>',
            '      </div>',
            '      <div style="margin-top:12px;font-size:12px;">',
            '        <a href="{cancel_url}">Cancel ticket</a>',
            '      </div>',
            '    </div>',
            '  </div>',
            '</div>',
        ] );
    }

    private static function dark_multi_html(): string
    {
        return implode( "\n", [
            '<div class="wrap">',
            '  <div class="card">',
            '    <div class="hd">',
            '      <div class="logo"><img src="{logo_url}" alt="{site_name}"/></div>',
            '      <div style="font-weight:700;font-size:18px;">{event_name}</div>',
            '      <div class="muted" style="margin-top:6px;">{event_start} – {event_end}</div>',
            '    </div>',
            '    <div class="px">',
            '      <div style="font-weight:700;font-size:16px;margin-bottom:8px;">{greeting}</div>',
            '      <div style="color:#c7cbe6;line-height:1.6;margin-bottom:14px;">{intro}</div>',
            '      {tickets_html}',
            '      <div style="margin-top:12px;font-size:12px;">',
            '        <a href="{cancel_url}">Cancel ticket</a>',
            '      </div>',
            '    </div>',
            '  </div>',
            '</div>',
        ] );
    }
}
