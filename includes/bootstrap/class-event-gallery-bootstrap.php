<?php

namespace EventTicketsElementor\Bootstrap;

use EventTicketsElementor\Event_Gallery_Meta;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Bootstraps Event Gallery meta UI without growing Plugin class.
 */
class Event_Gallery_Bootstrap
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

        new Event_Gallery_Meta();
    }
}

new Event_Gallery_Bootstrap();

