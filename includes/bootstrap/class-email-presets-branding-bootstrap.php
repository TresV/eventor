<?php

namespace EventTicketsElementor\Bootstrap;

use EventTicketsElementor\Admin\Email_Preset_Branding_Settings;
use EventTicketsElementor\Admin\Email_Presets_Preview_Assets;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Bootstraps email preset branding settings + live previews.
 */
class Email_Presets_Branding_Bootstrap
{
    public function __construct()
    {
        add_action('plugins_loaded', [$this, 'init'], 20);
    }

    public function init(): void
    {
        if (! is_admin()) {
            return;
        }

        new Email_Preset_Branding_Settings();
        new Email_Presets_Preview_Assets();
    }
}

new Email_Presets_Branding_Bootstrap();

