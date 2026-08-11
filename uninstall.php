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

// Payment orders are transactional/financial records — unlike event/ticket
// posts (intentionally left in place), they are removed on uninstall so
// payment data does not linger without the plugin to interpret it.
$evt_order_ids = get_posts(
    [
        'post_type'       => 'evt_order',
        'post_status'     => 'any',
        'posts_per_page'  => -1,
        'fields'          => 'ids',
        'suppress_filters' => true,
    ]
);
foreach ($evt_order_ids as $evt_order_id) {
    wp_delete_post((int) $evt_order_id, true);
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
        // Payment order CPT capabilities.
        'edit_evt_order',
        'read_evt_order',
        'delete_evt_order',
        'edit_evt_orders',
        'edit_others_evt_orders',
        'publish_evt_orders',
        'read_private_evt_orders',
        'delete_evt_orders',
        'delete_private_evt_orders',
        'delete_published_evt_orders',
        'delete_others_evt_orders',
        'edit_private_evt_orders',
        'edit_published_evt_orders',
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
