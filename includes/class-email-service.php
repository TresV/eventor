<?php

namespace EventTicketsElementor;

use EventTicketsElementor\Email\Microsoft_Calendar_Invite_Mailer;
use EventTicketsElementor\Email\Email_Config;
use EventTicketsElementor\Email\Email_Links;
use EventTicketsElementor\Email\Email_Preset_Branding;
use EventTicketsElementor\Email\Ticket_Email_Renderer;
use EventTicketsElementor\Email\Gmail_Event_Schema;
use EventTicketsElementor\Tickets\Ticket_Cancel_Link;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Handles all outgoing ticket-related emails.
 */
class Email_Service
{

    /** @var Settings */
    protected $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;

        // Ensure every HTML email we send also carries a plain-text
        // alternative body for clients/preferences that can't render HTML.
        add_action('phpmailer_init', [$this, 'ensure_plain_text_alt_body']);
    }

    /**
     * Populate the plain-text alternative body for HTML emails.
     *
     * WordPress marks our emails as text/html; without an AltBody, plain-text
     * clients receive the raw HTML. This derives a readable text version from
     * the HTML body and sets it as the multipart/alternative part.
     *
     * @param mixed $phpmailer
     */
    public function ensure_plain_text_alt_body($phpmailer): void
    {
        if (! class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
            return;
        }

        if (! $phpmailer instanceof \PHPMailer\PHPMailer\PHPMailer) {
            return;
        }

        if ('text/html' !== $phpmailer->ContentType) {
            return;
        }

        if ('' !== trim((string) $phpmailer->AltBody)) {
            return;
        }

        $html = (string) $phpmailer->Body;
        if ('' === trim($html)) {
            return;
        }

        $phpmailer->AltBody = self::html_to_text($html);
    }

    /**
     * Best-effort HTML → plain text conversion for email AltBody.
     *
     * @param string $html
     */
    public static function html_to_text(string $html): string
    {
        // Break lines after block/structural tags.
        $html = preg_replace(
            '#<(br\s*/?|/p|/div|/li|/ul|/ol|/h[1-6]|/tr|/table|/blockquote)[^>]*>#i',
            "\n",
            $html
        );

        // Replace <a href="url">text</a> with "text (url)".
        $html = (string) preg_replace_callback(
            '#<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#is',
            static function ($m) {
                $text = trim((string) strip_tags((string) $m[2]));
                if ('' === $text) {
                    return '';
                }
                $url = trim((string) $m[1]);
                if ('' !== $url && $url !== $text) {
                    return $text . ' (' . $url . ')';
                }
                return $text;
            },
            $html
        );

        $text = trim((string) strip_tags((string) $html));
        $text = (string) preg_replace('/[ \t]+/', ' ', $text);
        $text = (string) preg_replace('/\n{3,}/', "\n\n", $text);

        return $text;
    }

    /**
     * Send the main ticket email to the attendee.
     *
     * @param int $ticket_id
     * @return bool
     */
    public function send_ticket_email(int $ticket_id): bool
    {
        $ticket = get_post($ticket_id);
        if (! $ticket || CPT_Tickets::POST_TYPE !== $ticket->post_type) {
            return false;
        }

        $attendee_name   = get_post_meta($ticket_id, '_ticket_name', true);
        $attendee_email  = get_post_meta($ticket_id, '_ticket_email', true);
        $ticket_code     = get_post_meta($ticket_id, '_ticket_code', true);
        $event_snapshot  = $this->get_event_snapshot_for_ticket($ticket_id);
        $event_name      = $event_snapshot['name'];
        $event_start     = $event_snapshot['start'];
        $event_end       = $event_snapshot['end'];
        $event_location  = $event_snapshot['location'];
        $event_map_url   = $event_snapshot['map_url'] ?? '';

        if (! $attendee_email) {
            return false;
        }

        // Fallbacks.
        $blogname  = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
        $from_name = $this->settings->get('from_name', $blogname);
        $from_email = $this->settings->get('from_email', get_option('admin_email'));

        $subject_tpl = $this->settings->get(
            'ticket_email_subject',
            __('Your ticket for {event_name}', 'Event-Tickets-for-Elementor')
        );

        $subject = $this->replace_subject_tokens(
            $subject_tpl,
            [
                'event_name'    => $event_name ?: __('our event', 'Event-Tickets-for-Elementor'),
                'ticket_code'   => $ticket_code,
                'attendee_name' => $attendee_name,
            ]
        );

        // Verification URL (for staff check-in).
        $verify_url = add_query_arg(
            ['code' => rawurlencode($ticket_code)],
            site_url('/ticket-checkin/')
        );

        // Local QR generation + caching.
        $config = new Email_Config($this->settings);
        $qr_service = new Qr_Service();
        $qr_path = '';
        $qr_mode = $config->qr_mode();
        if ('off' !== $qr_mode) {
            $qr_path = $qr_service->ensure_cached((string) $ticket_code, $verify_url, $config->qr_size());
        }
        $qr_cid  = 'evt-ticket-qr-' . $ticket_id;
        $qr_img_src = ($qr_path && in_array($qr_mode, ['inline', 'both'], true)) ? ('cid:' . $qr_cid) : '';

        // Event meta (for nicer email and calendar).
        $event_start    = $event_start;
        $event_location = $event_location;

        // Calendar links (if event date is available or defaults make it valid).
        $calendar_service = Plugin::instance()->calendar();
        $google_cal_url   = $calendar_service->get_google_calendar_url($ticket_id);
        $ics_url          = $calendar_service->get_ics_download_url($ticket_id);

        $pdf_url = Plugin::instance()->pdf_endpoint()->get_pdf_download_url($ticket_id);
        $cancel_base_url = Ticket_Cancel_Link::build_signed_url(
            (string) $ticket_code,
            $ticket_id,
            Email_Links::cancel_page_url()
        );
        $cancel_url = $cancel_base_url;
        if ($cancel_url && Email_Links::include_email_in_cancel_link()) {
            $cancel_url = (string) add_query_arg(
                ['email' => rawurlencode((string) $attendee_email)],
                $cancel_url
            );
        }

        $tokens = [
            'site_name'       => (string) wp_specialchars_decode(get_option('blogname'), ENT_QUOTES),
            'logo_url'        => (string) Email_Preset_Branding::logo_url(),
            'logo_display'    => (string) Email_Preset_Branding::logo_display(),
            'primary_color'   => (string) Email_Preset_Branding::primary_color(),
            'accent_color'    => (string) Email_Preset_Branding::accent_color(),
            'background_color' => (string) Email_Preset_Branding::background_color(),
            'event_name'      => (string) ($event_name ?: __('our event', 'Event-Tickets-for-Elementor')),
            'event_start'     => (string) $event_start,
            'event_end'       => (string) $event_end,
            'event_location'  => (string) $event_location,
            'event_map_url'   => (string) $event_map_url,
            'ticket_code'     => (string) $ticket_code,
            'attendee_name'   => (string) $attendee_name,
            'attendee_email'  => (string) $attendee_email,
            'verify_url'      => (string) $verify_url,
            'ics_url'         => (string) $ics_url,
            'google_cal_url'  => (string) $google_cal_url,
            'pdf_url'         => (string) $pdf_url,
            'cancel_url'      => (string) $cancel_url,
        ];

        $greeting_tpl = (string) $this->settings->get('email_greeting', __('Hi {attendee_name},', 'Event-Tickets-for-Elementor'));
        $intro_tpl = (string) $this->settings->get('email_intro', __('Thank you for registering for {event_name}.', 'Event-Tickets-for-Elementor'));
        $ticket_code_intro = (string) $this->settings->get('email_ticket_code_intro', __('Your ticket code is:', 'Event-Tickets-for-Elementor'));
        $qr_instructions_tpl = (string) $this->settings->get('email_qr_instructions', __('Show the QR code below at the entrance. Our staff will scan it to check you in.', 'Event-Tickets-for-Elementor'));

        $footer_text = $this->settings->get(
            'ticket_email_footer',
            __('If you need to make changes or cancel, please contact us by reply to this email.', 'Event-Tickets-for-Elementor')
        );

        $greeting = $this->replace_body_tokens($greeting_tpl, $tokens);
        $intro = $this->replace_body_tokens($intro_tpl, $tokens);
        $renderer = new Ticket_Email_Renderer($this->settings);
        $message = $renderer->render_single(
            $tokens,
            [
                'greeting'    => $greeting,
                'intro'       => $intro,
                'qr_img_src'  => (string) $qr_img_src,
                'footer_text' => (string) $footer_text,
                'ticket_code_intro' => (string) $ticket_code_intro,
                'qr_instructions_tpl' => (string) $qr_instructions_tpl,
            ]
        );

        $jsonld = Gmail_Event_Schema::build_jsonld($tokens);
        if ('' !== $jsonld) {
            $message = Gmail_Event_Schema::inject_jsonld((string) $message, $jsonld);
        }

        // Build headers.
        $headers   = [];
        $from_name = $from_name ? $from_name : $blogname;

        $headers[] = 'From: ' . sprintf('%s <%s>', $from_name, $from_email);
        $headers[] = 'Reply-To: ' . sprintf('%s <%s>', $from_name, $from_email);

        $attachments = [];
        $attach_pdf = (int) $this->settings->get('pdf_attach_to_emails', 1);
        if ($attach_pdf) {
            $pdf_path = Plugin::instance()->pdf()->generate_pdf($ticket_id);
            if ($pdf_path && file_exists($pdf_path)) {
                $attachments[] = $pdf_path;
            }
        }
        if ($qr_path && file_exists($qr_path) && in_array($qr_mode, ['attach', 'both'], true)) {
            $attachments[] = $qr_path;
        }

        // Option B: for Microsoft clients, send the invite as the email body (Outlook shows prompt UI).
        if ($config->microsoft_invite_enabled() && Microsoft_Calendar_Invite_Mailer::is_microsoft_recipient((string) $attendee_email)) {
            $invite_ics = Plugin::instance()->calendar()->get_ticket_invite_ics_content($ticket_id);
            if (is_string($invite_ics) && '' !== trim($invite_ics)) {
                return Microsoft_Calendar_Invite_Mailer::send((string) $attendee_email, (string) $subject, $invite_ics, $headers, $attachments);
            }
        }

        add_filter('wp_mail_content_type', [$this, 'set_html_mail_content_type']);

        $embedder = null;
        if ($qr_path && file_exists($qr_path) && in_array($qr_mode, ['inline', 'both'], true)) {
            $embedder = function ($phpmailer) use ($qr_path, $qr_cid) {
                /** @var \PHPMailer\PHPMailer\PHPMailer $phpmailer */
                try {
                    $phpmailer->addEmbeddedImage($qr_path, $qr_cid, 'ticket-qr.png', 'base64', 'image/png');
                } catch (\Exception $e) {
                    // Ignore: attachment remains as fallback.
                }
            };
            add_action('phpmailer_init', $embedder);
        }

        $ics_embedder = null;
        $ics_content = '';
        if ($config->attach_ics()) {
            $ics_content = Plugin::instance()->calendar()->get_ticket_ics_content($ticket_id);
        }
        if (is_string($ics_content) && '' !== $ics_content) {
            $ics_filename = 'event.ics';
            if (is_string($event_name) && '' !== trim($event_name)) {
                $ics_filename = sanitize_file_name($event_name) . '.ics';
            }

            $ics_embedder = function ($phpmailer) use ($ics_content, $ics_filename) {
                /** @var \PHPMailer\PHPMailer\PHPMailer $phpmailer */
                try {
                    // Keep an explicit attachment as a fallback.
                    $phpmailer->addStringAttachment(
                        $ics_content,
                        $ics_filename,
                        'base64',
                        'text/calendar; method=REQUEST; charset=utf-8',
                        'inline'
                    );
                } catch (\Exception $e) {
                    // Ignore: still send email without ICS attachment.
                }
            };
            add_action('phpmailer_init', $ics_embedder);
        }

        $sent = wp_mail($attendee_email, $subject, $message, $headers, $attachments);

        if ($embedder) {
            remove_action('phpmailer_init', $embedder);
        }
        if ($ics_embedder) {
            remove_action('phpmailer_init', $ics_embedder);
        }

        remove_filter('wp_mail_content_type', [$this, 'set_html_mail_content_type']);

        return (bool) $sent;
    }

    /**
     * Content type callback so wp_mail sends HTML.
     *
     * @return string
     */
    public function set_html_mail_content_type(): string
    {
        return 'text/html';
    }

    /**
     * Send a single email containing multiple tickets.
     *
     * @param array<int> $ticket_ids
     * @param string     $recipient_email
     * @param string     $recipient_name
     * @return bool
     */
    public function send_multi_ticket_email(array $ticket_ids, string $recipient_email, string $recipient_name = ''): bool
    {
        $recipient_email = sanitize_email($recipient_email);
        if (empty($recipient_email) || empty($ticket_ids)) {
            return false;
        }

        $ticket_ids = array_filter(array_map('absint', $ticket_ids));
        if (empty($ticket_ids)) {
            return false;
        }

        $blogname  = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
        $from_name = $this->settings->get('from_name', $blogname);
        $from_email = $this->settings->get('from_email', get_option('admin_email'));

        $tickets_data = [];
        $attachments = [];
        $attach_pdf = (int) $this->settings->get('pdf_attach_to_emails', 1);
        $max_pdf_attachments = 10;
        $config = new Email_Config($this->settings);

        $first_event_id = 0;
        foreach ($ticket_ids as $ticket_id) {
            $ticket = get_post($ticket_id);
            if (! $ticket || CPT_Tickets::POST_TYPE !== $ticket->post_type) {
                continue;
            }

            $code    = get_post_meta($ticket_id, '_ticket_code', true);
            $name    = get_post_meta($ticket_id, '_ticket_name', true);
            $email   = get_post_meta($ticket_id, '_ticket_email', true);
            $snapshot = $this->get_event_snapshot_for_ticket($ticket_id);
            if (! $first_event_id) {
                $first_event_id = (int) get_post_meta($ticket_id, '_ticket_event_id', true);
            }

            $tickets_data[] = [
                'id'       => $ticket_id,
                'code'     => $code,
                'name'     => $name,
                'email'    => $email,
                'event'    => $snapshot['name'],
                'start'    => $snapshot['start'],
                'end'      => $snapshot['end'],
                'location' => $snapshot['location'],
                'gcal'     => Plugin::instance()->calendar()->get_google_calendar_url($ticket_id),
                'ics'      => Plugin::instance()->calendar()->get_ics_download_url($ticket_id),
                'pdf'      => Plugin::instance()->pdf_endpoint()->get_pdf_download_url($ticket_id),
            ];

            if ($attach_pdf) {
                if (count($attachments) < $max_pdf_attachments) {
                    $pdf_path = Plugin::instance()->pdf()->generate_pdf($ticket_id);
                    if ($pdf_path && file_exists($pdf_path)) {
                        $attachments[] = $pdf_path;
                    }
                }
            }
        }

        if (empty($tickets_data)) {
            return false;
        }

        $subject_tpl = $this->settings->get(
            'ticket_email_subject',
            __('Your ticket for {event_name}', 'Event-Tickets-for-Elementor')
        );

        $subject = $this->replace_subject_tokens(
            $subject_tpl,
            [
                'event_name'    => $tickets_data[0]['event'] ?: __('our event', 'Event-Tickets-for-Elementor'),
                'ticket_code'   => $tickets_data[0]['code'],
                'attendee_name' => $recipient_name,
            ]
        );

        // Option B: for Microsoft clients, send the invite as the email body.
        if (Microsoft_Calendar_Invite_Mailer::is_microsoft_recipient((string) $recipient_email)) {
            $invite_ics = Plugin::instance()->calendar()->get_multi_ticket_invite_ics_content(array_column($tickets_data, 'id'));
            if (is_string($invite_ics) && '' !== trim($invite_ics)) {
                $headers = [];
                $from_name = $from_name ? $from_name : $blogname;
                $headers[] = 'From: ' . sprintf('%s <%s>', $from_name, $from_email);
                $headers[] = 'Reply-To: ' . sprintf('%s <%s>', $from_name, $from_email);

                if ($config->microsoft_invite_enabled()) {
                    return Microsoft_Calendar_Invite_Mailer::send((string) $recipient_email, (string) $subject, $invite_ics, $headers, $attachments);
                }
            }
        }

        $tokens = [
            'site_name'       => (string) wp_specialchars_decode(get_option('blogname'), ENT_QUOTES),
            'logo_url'        => (string) Email_Preset_Branding::logo_url(),
            'logo_display'    => (string) Email_Preset_Branding::logo_display(),
            'primary_color'   => (string) Email_Preset_Branding::primary_color(),
            'accent_color'    => (string) Email_Preset_Branding::accent_color(),
            'background_color' => (string) Email_Preset_Branding::background_color(),
            'event_name'     => (string) ($tickets_data[0]['event'] ?: __('our event', 'Event-Tickets-for-Elementor')),
            'event_start'    => (string) ($tickets_data[0]['start'] ?? ''),
            'event_end'      => (string) ($tickets_data[0]['end'] ?? ''),
            'event_location' => (string) ($tickets_data[0]['location'] ?? ''),
            'event_map_url'  => $first_event_id ? (string) get_post_meta($first_event_id, Event_Location_Meta::MAP_URL_META, true) : '',
            'ticket_code'    => (string) ($tickets_data[0]['code'] ?? ''),
            'attendee_name'  => (string) $recipient_name,
            'attendee_email' => (string) $recipient_email,
            'verify_url'     => '',
            'ics_url'        => (string) ($tickets_data[0]['ics'] ?? ''),
            'google_cal_url' => (string) ($tickets_data[0]['gcal'] ?? ''),
            'pdf_url'        => (string) ($tickets_data[0]['pdf'] ?? ''),
        ];

        // Signed cancel link (for the first ticket) used by {cancel_url}.
        $cancel_url = '';
        $first_ticket_id = (int) ($tickets_data[0]['id'] ?? 0);
        if ($first_ticket_id) {
            $cancel_url = Ticket_Cancel_Link::build_signed_url(
                (string) ($tickets_data[0]['code'] ?? ''),
                $first_ticket_id,
                Email_Links::cancel_page_url()
            );
            if ($cancel_url && Email_Links::include_email_in_cancel_link()) {
                $cancel_url = (string) add_query_arg(
                    ['email' => rawurlencode((string) $recipient_email)],
                    $cancel_url
                );
            }
        }
        $tokens['cancel_url'] = (string) $cancel_url;

        $greeting_tpl = (string) $this->settings->get('email_greeting', __('Hi {attendee_name},', 'Event-Tickets-for-Elementor'));
        $intro_tpl = (string) $this->settings->get('email_intro', __('Thank you for registering for {event_name}.', 'Event-Tickets-for-Elementor'));

        $greeting = $this->replace_body_tokens($greeting_tpl, $tokens);
        $intro = $this->replace_body_tokens($intro_tpl, $tokens);
        $tokens['greeting'] = $greeting;
        $tokens['intro'] = $intro;

        $renderer = new Ticket_Email_Renderer($this->settings);
        $message = $renderer->render_multi($tokens, $tickets_data);

        $jsonld = Gmail_Event_Schema::build_jsonld($tokens);
        if ('' !== $jsonld) {
            $message = Gmail_Event_Schema::inject_jsonld((string) $message, $jsonld);
        }

        $headers   = [];
        $from_name = $from_name ? $from_name : $blogname;

        $headers[] = 'From: ' . sprintf('%s <%s>', $from_name, $from_email);
        $headers[] = 'Reply-To: ' . sprintf('%s <%s>', $from_name, $from_email);

        add_filter('wp_mail_content_type', [$this, 'set_html_mail_content_type']);

        $ics_embedder = null;
        if ($config->attach_ics()) {
            $first_ticket_id = (int) $tickets_data[0]['id'];
            if ($first_ticket_id) {
                $ics_content = Plugin::instance()->calendar()->get_ticket_ics_content($first_ticket_id);
                if (is_string($ics_content) && '' !== $ics_content) {
                    $ics_filename = 'event.ics';
                    if (! empty($tickets_data[0]['event'])) {
                        $ics_filename = sanitize_file_name((string) $tickets_data[0]['event']) . '.ics';
                    }

                    $ics_embedder = function ($phpmailer) use ($ics_content, $ics_filename) {
                        /** @var \PHPMailer\PHPMailer\PHPMailer $phpmailer */
                        try {
                            $phpmailer->addStringAttachment(
                                $ics_content,
                                $ics_filename,
                                'base64',
                                'text/calendar; method=REQUEST; charset=utf-8',
                                'inline'
                            );
                        } catch (\Exception $e) {
                            // Ignore: still send email without ICS attachment.
                        }
                    };
                    add_action('phpmailer_init', $ics_embedder);
                }
            }
        }

        $sent = wp_mail($recipient_email, $subject, $message, $headers, $attachments);

        if ($ics_embedder) {
            remove_action('phpmailer_init', $ics_embedder);
        }
        remove_filter('wp_mail_content_type', [$this, 'set_html_mail_content_type']);

        return (bool) $sent;
    }

    /**
     * Replace tokens in subject templates.
     *
     * Supported tokens:
     * - {event_name}
     * - {ticket_code}
     * - {attendee_name}
     *
     * @param string $template
     * @param array  $data
     * @return string
     */
    protected function replace_subject_tokens(string $template, array $data): string
    {
        $replacements = [
            '{event_name}'    => $data['event_name'] ?? '',
            '{ticket_code}'   => $data['ticket_code'] ?? '',
            '{attendee_name}' => $data['attendee_name'] ?? '',
        ];

        return strtr($template, $replacements);
    }

    /**
     * Get event snapshot prioritising linked Event CPT over ticket snapshot.
     *
     * @param int $ticket_id
     * @return array{name:string,start:string,end:string,location:string,map_url:string}
     */
    private function get_event_snapshot_for_ticket(int $ticket_id): array
    {
        $name      = get_post_meta($ticket_id, '_ticket_event_name', true);
        $start     = get_post_meta($ticket_id, '_ticket_event_start', true);
        $end       = get_post_meta($ticket_id, '_ticket_event_end', true);
        $location  = get_post_meta($ticket_id, '_ticket_event_location', true);
        $event_id  = (int) get_post_meta($ticket_id, '_ticket_event_id', true);
        $map_url   = '';

        if ($event_id) {
            $event = get_post($event_id);
            if ($event && CPT_Events::POST_TYPE === $event->post_type) {
                $name      = $event->post_title ?: $name;
                $start     = $start ?: get_post_meta($event_id, '_evt_event_start', true);
                $end       = $end ?: get_post_meta($event_id, '_evt_event_end', true);
                $location  = $location ?: get_post_meta($event_id, '_evt_event_location', true);
                $map_url   = (string) get_post_meta($event_id, Event_Location_Meta::MAP_URL_META, true);
            }
        }

        return [
            'name'      => (string) $name,
            'start'     => (string) $start,
            'end'       => (string) $end,
            'location'  => (string) $location,
            'map_url'   => (string) $map_url,
        ];
    }

    /**
     * Replace tokens in email body strings.
     *
     * @param string               $template
     * @param array<string,string> $tokens
     */
    private function replace_body_tokens(string $template, array $tokens): string
    {
        $template = (string) $template;
        if ('' === $template) {
            return '';
        }

        $replacements = [];
        foreach ($tokens as $key => $value) {
            $replacements['{' . $key . '}'] = (string) $value;
        }

        return strtr($template, $replacements);
    }
}
