<?php

namespace EventTicketsElementor\Admin;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Enqueues CodeMirror for editing the custom email HTML/CSS template.
 */
class Email_Template_Code_Editor_Assets
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

        if ('email' !== $tab || 'template' !== $subtab) {
            return;
        }

        // Ensure CodeMirror assets are available.
        $html_settings = wp_enqueue_code_editor(['type' => 'text/html']);
        $css_settings = wp_enqueue_code_editor(['type' => 'text/css']);
        wp_enqueue_script('wp-theme-plugin-editor');
        wp_enqueue_script('wp-codemirror');

        wp_enqueue_script(
            'evt-tickets-admin-email-code-editor',
            EVT_TICKETS_PLUGIN_URL . 'assets/js/admin-email-code-editor.js',
            ['jquery', 'wp-codemirror', 'wp-theme-plugin-editor'],
            defined('EVT_TICKETS_VERSION') ? \EVT_TICKETS_VERSION : 'dev',
            true
        );

        wp_localize_script(
            'evt-tickets-admin-email-code-editor',
            'EvtTicketsEmailCodeEditor',
            [
                'htmlSettings' => is_array($html_settings) ? $html_settings : null,
                'cssSettings'  => is_array($css_settings) ? $css_settings : null,
            ]
        );
    }
}

