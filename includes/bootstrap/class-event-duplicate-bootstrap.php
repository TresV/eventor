<?php

namespace EventTicketsElementor\Bootstrap;

use EventTicketsElementor\Admin\Event_Duplicate_Actions;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Bootstraps event duplication without modifying Plugin.
 */
class Event_Duplicate_Bootstrap
{
    public function __construct()
    {
        add_action('plugins_loaded', [$this, 'init'], 20);
    }

    public function init(): void
    {
        if (is_admin()) {
            new Event_Duplicate_Actions();
        }
    }
}

new Event_Duplicate_Bootstrap();

