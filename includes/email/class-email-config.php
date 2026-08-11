<?php

namespace EventTicketsElementor\Email;

use EventTicketsElementor\Settings;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Normalized email configuration derived from plugin settings.
 */
class Email_Config
{
    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * QR mode for single-ticket emails.
     *
     * @return 'inline'|'attach'|'both'|'off'
     */
    public function qr_mode(): string
    {
        // Product decision: never include ticket QR codes in emails.
        return 'off';
    }

    public function qr_size(): int
    {
        $size = (int) $this->settings->get('email_qr_size', 300);
        if ($size < 120) {
            return 120;
        }
        if ($size > 800) {
            return 800;
        }
        return $size;
    }

    public function attach_ics(): bool
    {
        return (int) $this->settings->get('email_attach_ics', 1) === 1;
    }

    public function microsoft_invite_enabled(): bool
    {
        return (int) $this->settings->get('email_microsoft_invite_enabled', 1) === 1;
    }
}
