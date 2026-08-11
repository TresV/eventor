<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Enqueues the admin assets used on the Event editor screen.
 */
class Event_Editor_Assets
{
    public function enqueue_admin_assets(string $hook): void
    {
        if (! in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (! $screen || CPT_Events::POST_TYPE !== (string) $screen->post_type) {
            return;
        }

        // Don't load our admin scripts inside the Elementor editor — they conflict
        // with Elementor's own JS (flatpickr, color picker, etc.) and prevent the
        // editor from initialising.
        if (isset($_GET['action']) && 'elementor' === $_GET['action']) {
            return;
        }

        wp_enqueue_style(
            'evt-tickets-admin-event-details',
            EVT_TICKETS_PLUGIN_URL . 'assets/css/admin-event-details.css',
            [],
            defined('EVT_TICKETS_VERSION') ? \EVT_TICKETS_VERSION : 'dev'
        );

        wp_enqueue_style('wp-color-picker');

        wp_enqueue_script(
            'evt-tickets-admin-event-details',
            EVT_TICKETS_PLUGIN_URL . 'assets/js/admin-event-details.js',
            ['jquery'],
            defined('EVT_TICKETS_VERSION') ? \EVT_TICKETS_VERSION : 'dev',
            true
        );

        wp_enqueue_script(
            'evt-tickets-admin-settings-color',
            EVT_TICKETS_PLUGIN_URL . 'assets/js/admin-settings-color.js',
            ['jquery', 'wp-color-picker'],
            defined('EVT_TICKETS_VERSION') ? \EVT_TICKETS_VERSION : 'dev',
            true
        );

        $version = defined('EVT_TICKETS_VERSION') ? \EVT_TICKETS_VERSION : 'dev';
        wp_enqueue_style(
            'evt-tickets-flatpickr',
            EVT_TICKETS_PLUGIN_URL . 'assets/vendor/flatpickr/flatpickr.min.css',
            [],
            $version
        );
        wp_enqueue_script(
            'evt-tickets-flatpickr',
            EVT_TICKETS_PLUGIN_URL . 'assets/vendor/flatpickr/flatpickr.min.js',
            [],
            $version,
            true
        );

        $locale = function_exists('get_locale') ? (string) get_locale() : '';
        $locale_short = $locale ? strtolower(substr($locale, 0, 2)) : '';
        $flatpickr_l10n_path = EVT_TICKETS_PLUGIN_DIR . 'assets/vendor/flatpickr/l10n/' . rawurlencode($locale_short) . '.js';
        if ($locale_short && file_exists($flatpickr_l10n_path)) {
            wp_enqueue_script(
                'evt-tickets-flatpickr-l10n',
                EVT_TICKETS_PLUGIN_URL . 'assets/vendor/flatpickr/l10n/' . rawurlencode($locale_short) . '.js',
                ['evt-tickets-flatpickr'],
                $version,
                true
            );
        }

        wp_enqueue_script(
            'evt-tickets-admin-event-datetime',
            EVT_TICKETS_PLUGIN_URL . 'assets/js/admin-event-datetime.js',
            ['jquery', 'evt-tickets-flatpickr'],
            $version,
            true
        );

        wp_localize_script(
            'evt-tickets-admin-event-datetime',
            'evtTicketsFlatpickr',
            [
                'locale'    => $locale_short,
                'time_24hr' => true,
            ]
        );
    }
}
