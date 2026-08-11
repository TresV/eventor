<?php

namespace EventTicketsElementor\Bootstrap;

use EventTicketsElementor\Admin\Email_Qr_Settings_Hider;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Bootstraps hiding QR email settings without modifying large plugin files.
 */
class Email_Qr_Settings_Bootstrap
{
    public function __construct()
    {
        add_action('plugins_loaded', [$this, 'init'], 20);
    }

    public function init(): void
    {
        if (is_admin()) {
            new Email_Qr_Settings_Hider();
        }
    }
}

new Email_Qr_Settings_Bootstrap();

