<?php

namespace EventTicketsElementor\Admin;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Enqueues assets for email preset previews (color pickers + live preview updates).
 */
class Email_Presets_Preview_Assets
{
    public function __construct()
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function enqueue(string $hook): void
    {
        if (! isset($_GET['page']) || 'evt-tickets-settings' !== sanitize_text_field(wp_unslash($_GET['page']))) {
            return;
        }

        $tab = isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : 'email';
        $subtab = isset($_GET['subtab']) ? sanitize_key((string) $_GET['subtab']) : 'content';

        if ('email' !== $tab || 'content' !== $subtab) {
            return;
        }

        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script(
            'evt-tickets-admin-email-presets',
            EVT_TICKETS_PLUGIN_URL . 'assets/js/admin-email-presets.js',
            ['jquery', 'wp-color-picker'],
            defined('EVT_TICKETS_VERSION') ? \EVT_TICKETS_VERSION : 'dev',
            true
        );
    }
}

