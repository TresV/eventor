<?php

namespace EventTicketsElementor\Admin;

use EventTicketsElementor\Settings_Store;
use EventTicketsElementor\Tickets\Ticket_Rules;
use EventTicketsElementor\Email\Email_Template_Presets;
use EventTicketsElementor\Email\Email_Preset_Branding;
use EventTicketsElementor\Staff_Access_Manager;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * First-run setup wizard.
 */
class Setup_Wizard_Page
{
    private const PAGE_SLUG = 'evt-tickets-setup-wizard';

    private Settings_Store $store;
    private Starter_Pages_Service $starter_pages;

    public function __construct(Settings_Store $store)
    {
        $this->store = $store;
        $this->starter_pages = new Starter_Pages_Service();

        add_action('admin_init', [$this, 'maybe_redirect_after_activation']);
        add_action('admin_init', [$this, 'maybe_handle_submit']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_evt_setup_wizard_create_pages', [$this, 'ajax_create_pages']);
    }

    public function maybe_redirect_after_activation(): void
    {
        if (! is_admin() || wp_doing_ajax()) {
            return;
        }
        if (! current_user_can(Staff_Access_Manager::CAP_MANAGE_PLUGIN)) {
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
        wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG));
        exit;
    }

    public function maybe_handle_submit(): void
    {
        if (! is_admin() || wp_doing_ajax()) {
            return;
        }
        if (! current_user_can(Staff_Access_Manager::CAP_MANAGE_PLUGIN)) {
            return;
        }
        if (! isset($_GET['page']) || self::PAGE_SLUG !== sanitize_text_field(wp_unslash($_GET['page']))) {
            return;
        }
        if (! isset($_POST['evt_wizard_action']) || 'save_step' !== sanitize_key(wp_unslash($_POST['evt_wizard_action']))) {
            return;
        }
        check_admin_referer('evt_setup_wizard');

        $step = isset($_POST['evt_wizard_step']) ? sanitize_key((string) $_POST['evt_wizard_step']) : '';

        if ('email' === $step) {
            $input = isset($_POST['evt_tickets_settings']) && is_array($_POST['evt_tickets_settings'])
                ? wp_unslash($_POST['evt_tickets_settings'])
                : [];
            $sanitized = $this->store->sanitize($input);
            update_option(Settings_Store::OPTION_KEY, $sanitized, false);

            if (isset($_POST[Email_Template_Presets::OPTION_KEY])) {
                $preset = Email_Template_Presets::sanitize_preset(sanitize_text_field(wp_unslash((string) $_POST[Email_Template_Presets::OPTION_KEY])));
                update_option(Email_Template_Presets::OPTION_KEY, $preset, false);
            }

            if (isset($_POST[Email_Preset_Branding::OPTION_KEY]) && is_array($_POST[Email_Preset_Branding::OPTION_KEY])) {
                $branding = wp_unslash($_POST[Email_Preset_Branding::OPTION_KEY]);
                $current = Email_Preset_Branding::get_all();
                $sanitized_branding = [
                    'show_logo'        => empty($current['show_logo']) ? 0 : 1,
                    'background_color' => sanitize_hex_color((string) ($current['background_color'] ?? '')) ?: '#F3F4F6',
                    'primary_color'    => sanitize_hex_color((string) ($branding['primary_color'] ?? '')) ?: '#111827',
                    'accent_color'     => sanitize_hex_color((string) ($branding['accent_color'] ?? '')) ?: '#7C3AED',
                ];
                update_option(Email_Preset_Branding::OPTION_KEY, $sanitized_branding, false);
            }
        } elseif ('pdf' === $step) {
            $input = isset($_POST['evt_tickets_settings']) && is_array($_POST['evt_tickets_settings'])
                ? wp_unslash($_POST['evt_tickets_settings'])
                : [];
            $sanitized = $this->store->sanitize($input);
            update_option(Settings_Store::OPTION_KEY, $sanitized, false);
        } elseif ('rules' === $step) {
            $rules = isset($_POST[Ticket_Rules::OPTION_KEY]) && is_array($_POST[Ticket_Rules::OPTION_KEY])
                ? wp_unslash($_POST[Ticket_Rules::OPTION_KEY])
                : [];

            $sanitized_rules = [
                'enforce_timeslot_exclusivity' => empty($rules['enforce_timeslot_exclusivity']) ? 0 : 1,
                'timeslot_buffer_minutes'      => max(0, (int) ($rules['timeslot_buffer_minutes'] ?? 0)),
            ];
            update_option(Ticket_Rules::OPTION_KEY, $sanitized_rules, false);
        }

        if (isset($_POST['evt_wizard_finish']) && '1' === sanitize_text_field(wp_unslash($_POST['evt_wizard_finish']))) {
            update_option('evt_tickets_setup_wizard_completed', 1, false);
            wp_safe_redirect(admin_url('admin.php?page=evt-tickets-get-started'));
            exit;
        }

        $next_step = isset($_POST['evt_wizard_next']) ? sanitize_key((string) $_POST['evt_wizard_next']) : 'welcome';
        $redirect = add_query_arg(
            [
                'page' => self::PAGE_SLUG,
                'step' => $next_step,
                'saved' => 1,
            ],
            admin_url('admin.php')
        );
        wp_safe_redirect($redirect);
        exit;
    }

    public function enqueue_assets(): void
    {
        if (! isset($_GET['page']) || self::PAGE_SLUG !== sanitize_text_field(wp_unslash($_GET['page']))) {
            return;
        }

        wp_enqueue_style(
            'evt-tickets-admin',
            EVT_TICKETS_PLUGIN_URL . 'assets/css/admin.css',
            [],
            defined('EVT_TICKETS_VERSION') ? EVT_TICKETS_VERSION : 'dev'
        );
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');

        wp_enqueue_script(
            'evt-tickets-setup-wizard',
            EVT_TICKETS_PLUGIN_URL . 'assets/js/admin-setup-wizard.js',
            ['jquery'],
            defined('EVT_TICKETS_VERSION') ? EVT_TICKETS_VERSION : 'dev',
            true
        );
        wp_enqueue_script(
            'evt-tickets-admin-email-presets',
            EVT_TICKETS_PLUGIN_URL . 'assets/js/admin-email-presets.js',
            ['jquery', 'wp-color-picker'],
            defined('EVT_TICKETS_VERSION') ? EVT_TICKETS_VERSION : 'dev',
            true
        );

        wp_localize_script(
            'evt-tickets-setup-wizard',
            'EvtSetupWizard',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('evt_setup_wizard_ajax'),
                'i18n'    => [
                    'creating' => __('Creating pages...', 'Event-Tickets-for-Elementor'),
                    'success'  => __('Starter pages are ready.', 'Event-Tickets-for-Elementor'),
                    'error'    => __('Could not create pages. Please try again.', 'Event-Tickets-for-Elementor'),
                ],
            ]
        );
    }

    public function render(): void
    {
        if (! current_user_can(Staff_Access_Manager::CAP_MANAGE_PLUGIN)) {
            return;
        }

        $steps = [
            'welcome' => __('Welcome', 'Event-Tickets-for-Elementor'),
            'email'   => __('Email', 'Event-Tickets-for-Elementor'),
            'pdf'     => __('PDF', 'Event-Tickets-for-Elementor'),
            'rules'   => __('Rules', 'Event-Tickets-for-Elementor'),
            'pages'   => __('Pages', 'Event-Tickets-for-Elementor'),
            'ready'   => __('Ready', 'Event-Tickets-for-Elementor'),
        ];

        $step = isset($_GET['step']) ? sanitize_key((string) $_GET['step']) : 'welcome';
        if (! isset($steps[$step])) {
            $step = 'welcome';
        }

        $settings = get_option(Settings_Store::OPTION_KEY, []);
        if (! is_array($settings)) {
            $settings = [];
        }
        $rules = Ticket_Rules::get_all();
        $saved = isset($_GET['saved']) ? (int) $_GET['saved'] : 0;

        $data = [
            'step'                 => $step,
            'steps'                => $steps,
            'settings'             => $settings,
            'rules'                => $rules,
            'saved'                => $saved,
            'wizard_url'           => admin_url('admin.php?page=' . self::PAGE_SLUG),
            'get_started_url'      => admin_url('admin.php?page=evt-tickets-get-started'),
            'dashboard_url'        => admin_url('admin.php?page=evt-tickets'),
            'settings_url'         => admin_url('admin.php?page=evt-tickets-settings'),
            'create_event_url'     => admin_url('post-new.php?post_type=evt_event'),
            'starter_pages'        => $this->starter_pages->starter_page_links($this->starter_pages->get_starter_pages()),
        ];

        $view = EVT_TICKETS_PLUGIN_DIR . 'includes/admin/views/setup-wizard.php';
        if (file_exists($view)) {
            extract($data, EXTR_OVERWRITE);
            include $view;
        }
    }

    public function ajax_create_pages(): void
    {
        check_ajax_referer('evt_setup_wizard_ajax', 'nonce');

        if (! current_user_can(Staff_Access_Manager::CAP_MANAGE_PLUGIN)) {
            wp_send_json_error(['message' => __('You do not have permission to do this.', 'Event-Tickets-for-Elementor')], 403);
        }

        $result = $this->starter_pages->create_starter_pages();

        wp_send_json_success(
            [
                'created'  => (int) ($result['created'] ?? 0),
                'existing' => (int) ($result['existing'] ?? 0),
                'pages'    => $this->starter_pages->starter_page_links($this->starter_pages->get_starter_pages()),
            ]
        );
    }
}
