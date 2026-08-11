<?php

namespace EventTicketsElementor\Admin;

use EventTicketsElementor\CPT_Events;
use EventTicketsElementor\CPT_Tickets;
use EventTicketsElementor\Staff_Access_Manager;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * First-run onboarding and persistent Get Started journey.
 */
class Get_Started_Page
{
    private Starter_Pages_Service $starter_pages;

    public function __construct()
    {
        $this->starter_pages = new Starter_Pages_Service();

        add_action('admin_init', [$this, 'maybe_create_starter_pages']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function enqueue_assets(): void
    {
        $screen = get_current_screen();
        if (! $screen) {
            return;
        }

        if (false === strpos((string) $screen->id, 'evt-tickets')) {
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
        if (! current_user_can(Staff_Access_Manager::CAP_MANAGE_PLUGIN)) {
            return;
        }

        $data = $this->build_data();
        $view = EVT_TICKETS_PLUGIN_DIR . 'includes/admin/views/get-started.php';

        if (file_exists($view)) {
            extract($data, EXTR_OVERWRITE);
            include $view;
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function build_data(): array
    {
        $events_total = 0;
        if (class_exists(CPT_Events::class)) {
            $counts = wp_count_posts(CPT_Events::POST_TYPE);
            $events_total = isset($counts->publish) ? (int) $counts->publish : 0;
        }

        $tickets_total = 0;
        if (class_exists(CPT_Tickets::class)) {
            $counts = wp_count_posts(CPT_Tickets::POST_TYPE);
            $tickets_total = isset($counts->publish) ? (int) $counts->publish : 0;
        }

        $starter_pages = $this->starter_pages->get_starter_pages();
        $has_events_page = ! empty($starter_pages['events']) && get_post((int) $starter_pages['events']);
        $has_checkin_page = ! empty($starter_pages['staff_checkin']) && get_post((int) $starter_pages['staff_checkin']);

        $checkpoints = [
            [
                'title' => __('Create your first event', 'Event-Tickets-for-Elementor'),
                'done'  => $events_total > 0,
            ],
            [
                'title' => __('Issue your first ticket', 'Event-Tickets-for-Elementor'),
                'done'  => $tickets_total > 0,
            ],
            [
                'title' => __('Set up ticket email content', 'Event-Tickets-for-Elementor'),
                'done'  => true,
            ],
            [
                'title' => __('Publish an Events Browser page', 'Event-Tickets-for-Elementor'),
                'done'  => (bool) $has_events_page,
            ],
            [
                'title' => __('Set up a staff Check-in page', 'Event-Tickets-for-Elementor'),
                'done'  => (bool) $has_checkin_page,
            ],
        ];

        return [
            'events_total'       => $events_total,
            'tickets_total'      => $tickets_total,
            'create_event_url'   => admin_url('post-new.php?post_type=evt_event'),
            'events_list_url'    => admin_url('edit.php?post_type=evt_event'),
            'tickets_list_url'   => admin_url('edit.php?post_type=evt_ticket'),
            'settings_email_url' => admin_url('admin.php?page=evt-tickets-settings&tab=email'),
            'settings_email_content_url' => admin_url('admin.php?page=evt-tickets-settings&tab=email&subtab=content'),
            'settings_email_template_url' => admin_url('admin.php?page=evt-tickets-settings&tab=email&subtab=template'),
            'settings_email_attachments_url' => admin_url('admin.php?page=evt-tickets-settings&tab=email&subtab=attachments'),
            'settings_pdf_url'  => admin_url('admin.php?page=evt-tickets-settings&tab=pdf'),
            'settings_url'       => admin_url('admin.php?page=evt-tickets-settings'),
            'tools_url'          => admin_url('admin.php?page=evt-tickets-tools'),
            'create_pages_url'   => wp_nonce_url(
                admin_url('admin.php?page=evt-tickets-get-started&evt_action=create_starter_pages'),
                'evt_create_starter_pages'
            ),
            'starter_pages'      => $this->starter_pages->starter_page_links($starter_pages),
            'creation_notice'    => $this->build_creation_notice(),
            'checkpoints'        => $checkpoints,
        ];
    }

    public function maybe_create_starter_pages(): void
    {
        if (! is_admin() || wp_doing_ajax()) {
            return;
        }
        if (! current_user_can(Staff_Access_Manager::CAP_MANAGE_PLUGIN)) {
            return;
        }
        if (! isset($_GET['page']) || 'evt-tickets-get-started' !== sanitize_text_field(wp_unslash($_GET['page']))) {
            return;
        }
        if (! isset($_GET['evt_action']) || 'create_starter_pages' !== sanitize_text_field(wp_unslash($_GET['evt_action']))) {
            return;
        }
        if (! check_admin_referer('evt_create_starter_pages')) {
            return;
        }

        $result = $this->starter_pages->create_starter_pages();
        $redirect = add_query_arg(
            [
                'page' => 'evt-tickets-get-started',
                'evt_created' => (int) $result['created'],
                'evt_existing' => (int) $result['existing'],
            ],
            admin_url('admin.php')
        );
        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * @return string
     */
    private function build_creation_notice(): string
    {
        $created = isset($_GET['evt_created']) ? absint((string) $_GET['evt_created']) : 0;
        $existing = isset($_GET['evt_existing']) ? absint((string) $_GET['evt_existing']) : 0;
        if (! $created && ! $existing) {
            return '';
        }

        return sprintf(
            /* translators: 1: number of pages created. 2: number of pages already existing. */
            __('Starter pages ready. Created: %1$d, already existing: %2$d.', 'Event-Tickets-for-Elementor'),
            $created,
            $existing
        );
    }
}
