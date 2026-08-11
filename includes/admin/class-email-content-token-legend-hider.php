<?php

namespace EventTicketsElementor\Admin;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Hides the legacy "Available tokens" description on Email Settings → Content.
 *
 * Kept as a small DOM tweak to avoid editing the large settings schema file.
 */
class Email_Content_Token_Legend_Hider
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

        wp_enqueue_script('jquery');
        wp_add_inline_script(
            'jquery',
            "jQuery(function($){var h=$('#evt_tickets_email_section');if(h.length){h.nextAll('p').first().hide();}});"
        );
    }
}

