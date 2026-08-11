<?php

namespace EventTicketsElementor\Email;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Sends a calendar invite as the primary email body for Microsoft clients.
 *
 * Outlook/Hotmail/Live frequently only show the "Add to calendar" UI when the email
 * itself is a calendar message (text/calendar; method=REQUEST).
 */
class Microsoft_Calendar_Invite_Mailer
{
    public static function is_microsoft_recipient(string $email): bool
    {
        $email = strtolower(trim($email));
        if ('' === $email || false === strpos($email, '@')) {
            return false;
        }

        $domain = substr($email, (int) strrpos($email, '@') + 1);
        $domain = trim($domain);

        if ('' === $domain) {
            return false;
        }

        $domains = [
            'outlook.com',
            'hotmail.com',
            'live.com',
            'msn.com',
            'outlook.co.uk',
            'hotmail.co.uk',
            'live.co.uk',
        ];

        return in_array($domain, $domains, true);
    }

    /**
     * @param array<int,string> $headers
     * @param array<int,string> $attachments
     */
    public static function send(string $to, string $subject, string $ics, array $headers = [], array $attachments = []): bool
    {
        $ics = (string) $ics;
        if ('' === trim($ics)) {
            return false;
        }

        add_filter('wp_mail_content_type', [self::class, 'content_type']);

        $init = function ($phpmailer) {
            /** @var \PHPMailer\PHPMailer\PHPMailer $phpmailer */
            try {
                $phpmailer->isHTML(false);
                $phpmailer->ContentType = 'text/calendar; method=REQUEST; charset=utf-8';
                $phpmailer->CharSet = 'UTF-8';
                $phpmailer->addCustomHeader('Content-Class', 'urn:content-classes:calendarmessage');
            } catch (\Exception $e) {
                // Ignore.
            }
        };
        add_action('phpmailer_init', $init);

        $sent = wp_mail($to, $subject, $ics, $headers, $attachments);

        remove_action('phpmailer_init', $init);
        remove_filter('wp_mail_content_type', [self::class, 'content_type']);

        return (bool) $sent;
    }

    public static function content_type(): string
    {
        return 'text/calendar; method=REQUEST; charset=utf-8';
    }
}

