<?php

namespace EventTicketsElementor\Bootstrap;

use EventTicketsElementor\Event_Timeslots;
use EventTicketsElementor\Event_Timeslots_Meta;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Bootstraps event timeslots UI without modifying Plugin.
 */
class Event_Timeslots_Bootstrap
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

        $timeslots = new Event_Timeslots();
        new Event_Timeslots_Meta($timeslots);
    }
}

new Event_Timeslots_Bootstrap();

