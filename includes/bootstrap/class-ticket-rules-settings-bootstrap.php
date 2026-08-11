<?php

namespace EventTicketsElementor\Bootstrap;

use EventTicketsElementor\Admin\Ticket_Rules_Settings;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Bootstraps ticket rules settings without modifying Plugin (keeps large files stable).
 */
class Ticket_Rules_Settings_Bootstrap
{
    public function __construct()
    {
        add_action('plugins_loaded', [$this, 'init'], 20);
    }

    public function init(): void
    {
        if (is_admin()) {
            new Ticket_Rules_Settings();
        }
    }
}

new Ticket_Rules_Settings_Bootstrap();

