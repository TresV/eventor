<?php

namespace EventTicketsElementor\Bootstrap;

use EventTicketsElementor\Admin\Ticket_Admin_List_Statuses;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Bootstraps admin list tweaks for tickets without modifying large core files.
 */
class Ticket_Admin_List_Statuses_Bootstrap
{
    public function __construct()
    {
        add_action('plugins_loaded', [$this, 'init'], 20);
    }

    public function init(): void
    {
        if (is_admin()) {
            new Ticket_Admin_List_Statuses();
        }
    }
}

new Ticket_Admin_List_Statuses_Bootstrap();

