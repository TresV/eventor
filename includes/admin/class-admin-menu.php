<?php

namespace EventTicketsElementor\Admin;

use EventTicketsElementor\CPT_Events;
use EventTicketsElementor\CPT_Tickets;
use EventTicketsElementor\Staff_Access_Manager;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Registers the admin menu/submenus.
 */
class Admin_Menu
{
    private Dashboard_Page $dashboard;
    private Setup_Wizard_Page $setup_wizard_page;
    private Get_Started_Page $get_started_page;
    private Documentation_Page $documentation_page;
    private Settings_Page $settings_page;
    private Staff_Page $staff_page;
    private Repair_Tools_Page $tools_page;

    public function __construct(Dashboard_Page $dashboard, Setup_Wizard_Page $setup_wizard_page, Get_Started_Page $get_started_page, Documentation_Page $documentation_page, Settings_Page $settings_page, Staff_Page $staff_page, Repair_Tools_Page $tools_page)
    {
        $this->dashboard     = $dashboard;
        $this->setup_wizard_page  = $setup_wizard_page;
        $this->get_started_page = $get_started_page;
        $this->documentation_page = $documentation_page;
        $this->settings_page = $settings_page;
        $this->staff_page    = $staff_page;
        $this->tools_page    = $tools_page;

        add_action('admin_menu', [$this, 'register']);
        add_action('admin_menu', [$this, 'cleanup_staff_submenu'], 999);
        add_action('admin_bar_menu', [$this, 'adjust_admin_bar_for_staff'], 90);
    }

    public function register(): void
    {
        $is_admin_user = current_user_can(Staff_Access_Manager::CAP_MANAGE_PLUGIN);
        $can_manage_tickets = current_user_can(CPT_Tickets::CAP_EDIT_POSTS);

        add_menu_page(
            __('Event Tickets', 'Event-Tickets-for-Elementor'),
            __('Event Tickets', 'Event-Tickets-for-Elementor'),
            // A plugin cap rather than 'read', so the menu only appears for
            // users who can actually do something with it (admins, editors and
            // staff), not every logged-in user.
            Staff_Access_Manager::CAP_USE_CHECKIN,
            'evt-tickets',
            [$this, 'render_top_level'],
            'dashicons-tickets-alt',
            58
        );

        // Remove WP's auto-inserted duplicate top-level submenu so we can control order explicitly.
        remove_submenu_page('evt-tickets', 'evt-tickets');

        if ($is_admin_user) {
            add_submenu_page(
                'evt-tickets',
                __('Get Started', 'Event-Tickets-for-Elementor'),
                __('Get Started', 'Event-Tickets-for-Elementor'),
                Staff_Access_Manager::CAP_MANAGE_PLUGIN,
                'evt-tickets-get-started',
                [$this->get_started_page, 'render']
            );

            add_submenu_page(
                'evt-tickets',
                __('Dashboard', 'Event-Tickets-for-Elementor'),
                __('Dashboard', 'Event-Tickets-for-Elementor'),
                Staff_Access_Manager::CAP_MANAGE_PLUGIN,
                'evt-tickets',
                [$this->dashboard, 'render']
            );
        }

        add_submenu_page(
            'evt-tickets',
            __('Events', 'Event-Tickets-for-Elementor'),
            __('Events', 'Event-Tickets-for-Elementor'),
            CPT_Events::CAP_EDIT_POSTS,
            'edit.php?post_type=evt_event'
        );

        if ($can_manage_tickets) {
            add_submenu_page(
                'evt-tickets',
                __('Tickets', 'Event-Tickets-for-Elementor'),
                __('Tickets', 'Event-Tickets-for-Elementor'),
                CPT_Tickets::CAP_EDIT_POSTS,
                'edit.php?post_type=evt_ticket'
            );
        }

        if ($is_admin_user) {
            add_submenu_page(
                'evt-tickets',
                __('Staff', 'Event-Tickets-for-Elementor'),
                __('Staff', 'Event-Tickets-for-Elementor'),
                Staff_Access_Manager::CAP_MANAGE_PLUGIN,
                'evt-tickets-staff',
                [$this->staff_page, 'render_page']
            );

            add_submenu_page(
                'evt-tickets',
                __('Settings', 'Event-Tickets-for-Elementor'),
                __('Settings', 'Event-Tickets-for-Elementor'),
                Staff_Access_Manager::CAP_MANAGE_PLUGIN,
                'evt-tickets-settings',
                [$this->settings_page, 'render_page']
            );

            add_submenu_page(
                'evt-tickets',
                __('Tools', 'Event-Tickets-for-Elementor'),
                __('Tools', 'Event-Tickets-for-Elementor'),
                Staff_Access_Manager::CAP_MANAGE_PLUGIN,
                'evt-tickets-tools',
                [$this->tools_page, 'render_page']
            );

            add_submenu_page(
                'evt-tickets',
                __('User Guide', 'Event-Tickets-for-Elementor'),
                __('User Guide', 'Event-Tickets-for-Elementor'),
                Staff_Access_Manager::CAP_MANAGE_PLUGIN,
                'evt-tickets-user-guide',
                [$this->documentation_page, 'render']
            );

            add_submenu_page(
                'evt-tickets',
                __('Documentation', 'Event-Tickets-for-Elementor'),
                __('Documentation', 'Event-Tickets-for-Elementor'),
                Staff_Access_Manager::CAP_MANAGE_PLUGIN,
                'evt-tickets-documentation',
                [$this->documentation_page, 'render']
            );
        }

        // Hidden screen used only for activation onboarding.
        add_submenu_page(
            null,
            __('Setup Wizard', 'Event-Tickets-for-Elementor'),
            __('Setup Wizard', 'Event-Tickets-for-Elementor'),
            Staff_Access_Manager::CAP_MANAGE_PLUGIN,
            'evt-tickets-setup-wizard',
            [$this->setup_wizard_page, 'render']
        );
        global $submenu;
        $desired = $is_admin_user
            ? [
                'evt-tickets-get-started',
                'evt-tickets',
                'edit.php?post_type=evt_event',
                'edit.php?post_type=evt_ticket',
                'evt-tickets-staff',
                'evt-tickets-settings',
                'evt-tickets-tools',
                'evt-tickets-user-guide',
                'evt-tickets-documentation',
            ]
            : [
                'edit.php?post_type=evt_event',
                'edit.php?post_type=evt_ticket',
            ];

        if (! isset($submenu['evt-tickets']) || ! is_array($submenu['evt-tickets'])) {
            return;
        }

        $ordered = [];

        foreach ($desired as $slug) {
            foreach ($submenu['evt-tickets'] as $item) {
                if (isset($item[2]) && $item[2] === $slug) {
                    $ordered[] = $item;
                    break;
                }
            }
        }

        // Keep any unknown submenu pages at the end.
        foreach ($submenu['evt-tickets'] as $item) {
            if (! isset($item[2]) || in_array($item[2], $desired, true)) {
                continue;
            }

            if (! $is_admin_user && 'evt-tickets' === $item[2]) {
                continue;
            }

            $ordered[] = $item;
        }

        $submenu['evt-tickets'] = $ordered;
    }

    public function cleanup_staff_submenu(): void
    {
        if (current_user_can(Staff_Access_Manager::CAP_MANAGE_PLUGIN)) {
            return;
        }

        global $submenu;

        if (! isset($submenu['evt-tickets']) || ! is_array($submenu['evt-tickets'])) {
            return;
        }

        $submenu['evt-tickets'] = array_values(array_filter(
            $submenu['evt-tickets'],
            static function (array $item): bool {
                return isset($item[2]) && 'evt-tickets' !== $item[2];
            }
        ));
    }

    public function render_top_level(): void
    {
        if (current_user_can(Staff_Access_Manager::CAP_MANAGE_PLUGIN)) {
            $this->dashboard->render();
            return;
        }

        if (current_user_can(CPT_Tickets::CAP_EDIT_POSTS)) {
            $target = admin_url('edit.php?post_type=' . CPT_Tickets::POST_TYPE);

            if (! headers_sent()) {
                wp_safe_redirect($target);
                exit;
            }

            echo '<script>window.location.href=' . wp_json_encode($target) . ';</script>';
            echo '<p><a href="' . esc_url($target) . '">' . esc_html__('Continue to Tickets', 'Event-Tickets-for-Elementor') . '</a></p>';
            exit;
        }

        wp_die(esc_html__('You are not allowed to access this page.', 'Event-Tickets-for-Elementor'));
    }

    public function adjust_admin_bar_for_staff(\WP_Admin_Bar $wp_admin_bar): void
    {
        if (! is_user_logged_in() || current_user_can(Staff_Access_Manager::CAP_MANAGE_PLUGIN)) {
            return;
        }

        if (! current_user_can(Staff_Access_Manager::CAP_USE_CHECKIN)) {
            return;
        }

        $staff_checkin_url = $this->staff_checkin_url();
        if (! $staff_checkin_url) {
            return;
        }

        $site_node = $wp_admin_bar->get_node('site-name');
        if ($site_node) {
            $site_node->href = $staff_checkin_url;
            $wp_admin_bar->add_node((array) $site_node);
        }

        $visit_site_node = $wp_admin_bar->get_node('view-site');
        if ($visit_site_node) {
            $visit_site_node->href = $staff_checkin_url;
            $wp_admin_bar->add_node((array) $visit_site_node);
        }
    }

    private function staff_checkin_url(): string
    {
        return Staff_Access_Manager::resolved_checkin_page_url_static();
    }
}
