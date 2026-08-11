<?php

namespace EventTicketsElementor\Bootstrap;

use EventTicketsElementor\Admin\Ticket_Admin_Status_Sync;
use EventTicketsElementor\Plugin;
use EventTicketsElementor\Ticket_Cancel_Ajax;
use EventTicketsElementor\Tickets\Ticket_Cancellation_Service;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Bootstraps the ticket cancellation module without modifying Plugin (keeps large files stable).
 */
class Ticket_Cancellation_Bootstrap
{
    public function __construct()
    {
        add_action('plugins_loaded', [$this, 'init'], 20);
    }

    public function init(): void
    {
        // Admin meta-to-post_status sync for the Tickets CPT.
        if (is_admin()) {
            new Ticket_Admin_Status_Sync();
        }

        // Public cancellation endpoint (AJAX).
        $service = new Ticket_Cancellation_Service(Plugin::instance()->ticket_service);
        new Ticket_Cancel_Ajax($service);
    }
}

new Ticket_Cancellation_Bootstrap();

