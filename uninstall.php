<?php

/**
 * Uninstall cleanup for Event Tickets for Elementor.
 *
 * Removes the plugin's options and cleans up the managed Staff role/caps that
 * were added to core roles. Event/ticket posts and their metadata are
 * intentionally left in place so attendee data is not destroyed on a mistake;
 * use a dedicated cleanup tool if full data removal is required.
 *
 * @package EventTicketsElementor
 */

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Known plugin options. Kept explicit (rather than a wildcard DELETE) so we
// never touch options that happen to share the prefix from another source.
$evt_options = [
    'evt_tickets_settings',
    'evt_tickets_do_activation_redirect',
    'evt_tickets_setup_wizard_completed',
    'evt_tickets_starter_pages',
    'evt_tickets_email_preset',
    'evt_tickets_email_links',
    'evt_tickets_email_preset_branding',
    'evt_tickets_ticket_rules',
    'evt_tickets_cancel_migration_done',
    'evt_tickets_db_version',
    'evt_tickets_role_sync_version',
];

foreach ($evt_options as $evt_option) {
    delete_option($evt_option);
}

// Remove the managed Staff role and strip plugin caps from core roles.
if (function_exists('wp_roles')) {
    remove_role('evt_staff');

    $evt_plugin_caps = [
        // Staff access manager.
        'evt_manage_plugin',
        'evt_use_checkin',
        // Event CPT capabilities.
        'edit_evt_event',
        'read_evt_event',
        'delete_evt_event',
        'edit_evt_events',
        'edit_others_evt_events',
        'publish_evt_events',
        'read_private_evt_events',
        'delete_evt_events',
        'delete_private_evt_events',
        'delete_published_evt_events',
        'delete_others_evt_events',
        'edit_private_evt_events',
        'edit_published_evt_events',
        // Ticket CPT capabilities.
        'edit_evt_ticket',
        'read_evt_ticket',
        'delete_evt_ticket',
        'edit_evt_tickets',
        'edit_others_evt_tickets',
        'publish_evt_tickets',
        'read_private_evt_tickets',
        'delete_evt_tickets',
        'delete_private_evt_tickets',
        'delete_published_evt_tickets',
        'delete_others_evt_tickets',
        'edit_private_evt_tickets',
        'edit_published_evt_tickets',
    ];

    foreach (['administrator', 'editor'] as $evt_role_name) {
        $evt_role = get_role($evt_role_name);
        if (! $evt_role) {
            continue;
        }
        foreach ($evt_plugin_caps as $evt_cap) {
            $evt_role->remove_cap($evt_cap);
        }
    }
}
