<?php

namespace EventTicketsElementor\Bootstrap;

use EventTicketsElementor\Admin\Email_Template_Presets_Settings;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Bootstraps the email preset settings without modifying Plugin.
 */
class Email_Template_Presets_Bootstrap
{
    public function __construct()
    {
        add_action('plugins_loaded', [$this, 'init'], 20);
    }

    public function init(): void
    {
        if (is_admin()) {
            new Email_Template_Presets_Settings();
        }
    }
}

new Email_Template_Presets_Bootstrap();

