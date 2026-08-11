<?php

namespace EventTicketsElementor\Admin;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Enqueues JS for custom email template preview on the settings page.
 */
class Email_Template_Preview_Assets
{
    public function __construct()
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function enqueue(string $hook): void
    {
        if ('toplevel_page_evt-tickets' === $hook) {
            return;
        }

        if (! isset($_GET['page']) || 'evt-tickets-settings' !== sanitize_text_field(wp_unslash($_GET['page']))) {
            return;
        }

        wp_enqueue_script(
            'evt-tickets-admin-email-preview',
            EVT_TICKETS_PLUGIN_URL . 'assets/js/admin-email-template-preview.js',
            ['jquery'],
            defined('EVT_TICKETS_VERSION') ? \EVT_TICKETS_VERSION : 'dev',
            true
        );
    }
}

