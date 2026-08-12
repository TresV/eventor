<?php

/**
 * Plugin Name: Event Tickets for Elementor
 * Description: Event ticketing for Elementor with QR/PDF/ICS delivery, attendee flows, and direct payment processor checkout.
 * Version: 0.1.1
 * Author: Viktor Varbanov
 * Text Domain: Event-Tickets-for-Elementor
 * Domain Path: /languages
 */

if (! defined('ABSPATH')) {
    exit;
}

// Minimum PHP check (optional but good practice).
if (version_compare(PHP_VERSION, '7.4', '<')) {
    if (is_admin()) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>';
            esc_html_e('Event Tickets for Elementor requires PHP 7.4 or higher.', 'Event-Tickets-for-Elementor');
            echo '</p></div>';
        });
    }
    return;
}

// Define basic plugin constants.
define('EVT_TICKETS_PLUGIN_FILE', __FILE__);
define('EVT_TICKETS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('EVT_TICKETS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('EVT_TICKETS_VERSION', '0.1.1');

// Load main plugin class.
require_once EVT_TICKETS_PLUGIN_DIR . 'includes/class-plugin.php';

/**
 * Returns the main plugin instance.
 *
 * @return \EventTicketsElementor\Plugin
 */
function evt_tickets()
{
    return \EventTicketsElementor\Plugin::instance();
}

// Bootstrap.
evt_tickets();

// Activation / deactivation hooks (for future use, e.g. cron, rewrites).
register_activation_hook(__FILE__, ['\EventTicketsElementor\Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['\EventTicketsElementor\Plugin', 'deactivate']);
