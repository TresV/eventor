<?php

namespace EventTicketsElementor\Tickets;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Normalize emails for ticket-limit checks.
 *
 * Rules:
 * - Gmail/Googlemail: strip dots + plus tag.
 * - Outlook/Hotmail/Live/iCloud/Proton/Fastmail: strip plus tag.
 * - Yahoo: strip dash tag.
 */
class Ticket_Email_Normalizer
{
    /**
     * @return string Normalized email (lowercased). Empty if invalid.
     */
    public static function normalize(string $email): string
    {
        $email = trim(strtolower($email));
        if ('' === $email || false === strpos($email, '@')) {
            return '';
        }

        [$local, $domain] = array_map('trim', explode('@', $email, 2));
        if ('' === $local || '' === $domain) {
            return '';
        }

        $domain = strtolower($domain);

        $gmail_domains = ['gmail.com', 'googlemail.com'];
        $plus_domains = [
            'outlook.com', 'hotmail.com', 'live.com',
            'icloud.com', 'me.com', 'mac.com',
            'proton.me', 'protonmail.com',
            'fastmail.com',
        ];
        $yahoo_domains = ['yahoo.com', 'yahoo.co.uk', 'yahoo.de', 'yahoo.fr', 'yahoo.es', 'yahoo.it'];

        if (in_array($domain, $gmail_domains, true)) {
            $local = preg_replace('/\\+.*/', '', $local);
            $local = str_replace('.', '', $local);
        } elseif (in_array($domain, $yahoo_domains, true)) {
            $local = preg_replace('/\\-.*/', '', $local);
        } elseif (in_array($domain, $plus_domains, true)) {
            $local = preg_replace('/\\+.*/', '', $local);
        }

        return $local . '@' . $domain;
    }
}
