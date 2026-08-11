<?php

namespace EventTicketsElementor\Admin;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * First-run welcome screen shown after activation.
 */
class Welcome_Page
{
    public function __construct()
    {
        add_action('admin_init', [$this, 'maybe_redirect_after_activation']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function maybe_redirect_after_activation(): void
    {
        if (! is_admin() || wp_doing_ajax()) {
            return;
        }
        if (! current_user_can('manage_options')) {
            return;
        }
        if (! get_option('evt_tickets_do_activation_redirect')) {
            return;
        }
        if (isset($_GET['activate-multi'])) {
            delete_option('evt_tickets_do_activation_redirect');
            return;
        }

        delete_option('evt_tickets_do_activation_redirect');
        wp_safe_redirect(admin_url('admin.php?page=evt-tickets-welcome'));
        exit;
    }

    public function enqueue_assets(): void
    {
        if (! isset($_GET['page']) || 'evt-tickets-welcome' !== sanitize_text_field(wp_unslash($_GET['page']))) {
            return;
        }

        wp_enqueue_style(
            'evt-tickets-admin',
            EVT_TICKETS_PLUGIN_URL . 'assets/css/admin.css',
            [],
            defined('EVT_TICKETS_VERSION') ? EVT_TICKETS_VERSION : 'dev'
        );
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $data = [
            'get_started_url' => admin_url('admin.php?page=evt-tickets-get-started'),
            'dashboard_url'   => admin_url('admin.php?page=evt-tickets'),
            'settings_url'    => admin_url('admin.php?page=evt-tickets-settings'),
        ];

        $view = EVT_TICKETS_PLUGIN_DIR . 'includes/admin/views/welcome.php';
        if (file_exists($view)) {
            extract($data, EXTR_OVERWRITE);
            include $view;
        }
    }
}
