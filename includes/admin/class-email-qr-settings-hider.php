<?php

namespace EventTicketsElementor\Admin;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Hides legacy QR-related email settings UI (QR is no longer supported in emails).
 *
 * This avoids editing the large Settings_Store schema file.
 */
class Email_Qr_Settings_Hider
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
        if ('email' !== $tab) {
            return;
        }

        wp_enqueue_script('jquery');
        wp_add_inline_script(
            'jquery',
            'jQuery(function($){
              var names=["evt_tickets_settings[email_qr_mode]","evt_tickets_settings[email_qr_size]","evt_tickets_settings[email_qr_instructions]"];
              names.forEach(function(n){
                $("input[name=\'"+n+"\'], select[name=\'"+n+"\'], textarea[name=\'"+n+"\']").closest("tr").hide();
              });
            });'
        );
    }
}
