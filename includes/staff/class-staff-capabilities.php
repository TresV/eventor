<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Spec of the WordPress capabilities the plugin manages for the Staff role:
 * the managed-capabilities catalog (groups, descriptions, defaults), the
 * mapping between capabilities and their Staff settings keys, and the plugin
 * caps that are granted to the Administrator and Editor roles.
 */
class Staff_Capabilities
{
    /**
     * Maps a capability to its Staff role settings key.
     */
    public function setting_key_for_cap(string $cap): string
    {
        return 'staff_cap_' . sanitize_key($cap);
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public function managed_capabilities_catalog(): array
    {
        return [
            Staff_Access_Manager::CAP_USE_CHECKIN => [
                'group'       => __('Event Tickets', 'Event-Tickets-for-Elementor'),
                'description' => __('Lets Staff users open the Check-in widget, scan QR codes, look up tickets, and confirm check-in.', 'Event-Tickets-for-Elementor'),
                'default'     => true,
            ],
            CPT_Tickets::CAP_EDIT_POSTS => [
                'group'       => __('Event Tickets', 'Event-Tickets-for-Elementor'),
                'description' => __('Access the Tickets admin list and open ticket records.', 'Event-Tickets-for-Elementor'),
                'default'     => true,
            ],
            CPT_Tickets::CAP_EDIT_OTHERS => [
                'group'       => __('Event Tickets', 'Event-Tickets-for-Elementor'),
                'description' => __('View and edit tickets created by other users or issued automatically by the system.', 'Event-Tickets-for-Elementor'),
                'default'     => true,
            ],
            CPT_Tickets::CAP_EDIT_PUBLISHED => [
                'group'       => __('Event Tickets', 'Event-Tickets-for-Elementor'),
                'description' => __('Edit issued tickets, including attendee details and ticket status.', 'Event-Tickets-for-Elementor'),
                'default'     => true,
            ],
            CPT_Tickets::CAP_READ_PRIVATE => [
                'group'       => __('Event Tickets', 'Event-Tickets-for-Elementor'),
                'description' => __('Read non-public ticket records in admin when needed by the post type capability map.', 'Event-Tickets-for-Elementor'),
                'default'     => true,
            ],
            CPT_Tickets::CAP_READ_POST => [
                'group'       => __('Event Tickets', 'Event-Tickets-for-Elementor'),
                'description' => __('Open individual ticket records in the admin editor.', 'Event-Tickets-for-Elementor'),
                'default'     => true,
            ],
            'upload_files' => [
                'group'       => __('Dashboard & Media', 'Event-Tickets-for-Elementor'),
                'description' => __('Upload files to the Media Library.', 'Event-Tickets-for-Elementor'),
                'default'     => false,
            ],
            'moderate_comments' => [
                'group'       => __('Dashboard & Media', 'Event-Tickets-for-Elementor'),
                'description' => __('Approve, trash, or mark comments as spam.', 'Event-Tickets-for-Elementor'),
                'default'     => false,
            ],
            'manage_categories' => [
                'group'       => __('Dashboard & Media', 'Event-Tickets-for-Elementor'),
                'description' => __('Create, edit, and delete post categories.', 'Event-Tickets-for-Elementor'),
                'default'     => false,
            ],
            'import' => [
                'group'       => __('Dashboard & Media', 'Event-Tickets-for-Elementor'),
                'description' => __('Use the WordPress importer tools.', 'Event-Tickets-for-Elementor'),
                'default'     => false,
            ],
            'export' => [
                'group'       => __('Dashboard & Media', 'Event-Tickets-for-Elementor'),
                'description' => __('Export WordPress content and site data.', 'Event-Tickets-for-Elementor'),
                'default'     => false,
            ],
            'edit_posts' => [
                'group'       => __('Posts & Pages', 'Event-Tickets-for-Elementor'),
                'description' => __('Edit own posts and access standard post editing flows.', 'Event-Tickets-for-Elementor'),
                'default'     => false,
            ],
            'edit_others_posts' => [
                'group'       => __('Posts & Pages', 'Event-Tickets-for-Elementor'),
                'description' => __('Edit posts created by other users.', 'Event-Tickets-for-Elementor'),
                'default'     => false,
            ],
            'edit_published_posts' => [
                'group'       => __('Posts & Pages', 'Event-Tickets-for-Elementor'),
                'description' => __('Edit already published posts.', 'Event-Tickets-for-Elementor'),
                'default'     => false,
            ],
            'publish_posts' => [
                'group'       => __('Posts & Pages', 'Event-Tickets-for-Elementor'),
                'description' => __('Publish posts.', 'Event-Tickets-for-Elementor'),
                'default'     => false,
            ],
            'delete_posts' => [
                'group'       => __('Posts & Pages', 'Event-Tickets-for-Elementor'),
                'description' => __('Delete own posts.', 'Event-Tickets-for-Elementor'),
                'default'     => false,
            ],
            'delete_others_posts' => [
                'group'       => __('Posts & Pages', 'Event-Tickets-for-Elementor'),
                'description' => __('Delete posts created by other users.', 'Event-Tickets-for-Elementor'),
                'default'     => false,
            ],
            'read_private_posts' => [
                'group'       => __('Posts & Pages', 'Event-Tickets-for-Elementor'),
                'description' => __('Read private posts.', 'Event-Tickets-for-Elementor'),
                'default'     => false,
            ],
            'edit_pages' => [
                'group'       => __('Posts & Pages', 'Event-Tickets-for-Elementor'),
                'description' => __('Edit pages.', 'Event-Tickets-for-Elementor'),
                'default'     => false,
            ],
            'edit_others_pages' => [
                'group'       => __('Posts & Pages', 'Event-Tickets-for-Elementor'),
                'description' => __('Edit pages created by other users.', 'Event-Tickets-for-Elementor'),
                'default'     => false,
            ],
            'publish_pages' => [
                'group'       => __('Posts & Pages', 'Event-Tickets-for-Elementor'),
                'description' => __('Publish pages.', 'Event-Tickets-for-Elementor'),
                'default'     => false,
            ],
            'delete_pages' => [
                'group'       => __('Posts & Pages', 'Event-Tickets-for-Elementor'),
                'description' => __('Delete pages.', 'Event-Tickets-for-Elementor'),
                'default'     => false,
            ],
            'delete_others_pages' => [
                'group'       => __('Posts & Pages', 'Event-Tickets-for-Elementor'),
                'description' => __('Delete pages created by other users.', 'Event-Tickets-for-Elementor'),
                'default'     => false,
            ],
            'list_users' => [
                'group'       => __('Users', 'Event-Tickets-for-Elementor'),
                'description' => __('View the Users list in wp-admin.', 'Event-Tickets-for-Elementor'),
                'default'     => false,
            ],
            'create_users' => [
                'group'       => __('Users', 'Event-Tickets-for-Elementor'),
                'description' => __('Create new WordPress users.', 'Event-Tickets-for-Elementor'),
                'default'     => false,
            ],
            'edit_users' => [
                'group'       => __('Users', 'Event-Tickets-for-Elementor'),
                'description' => __('Edit existing WordPress users.', 'Event-Tickets-for-Elementor'),
                'default'     => false,
            ],
            'delete_users' => [
                'group'       => __('Users', 'Event-Tickets-for-Elementor'),
                'description' => __('Delete WordPress users.', 'Event-Tickets-for-Elementor'),
                'default'     => false,
            ],
            'promote_users' => [
                'group'       => __('Users', 'Event-Tickets-for-Elementor'),
                'description' => __('Change roles for WordPress users.', 'Event-Tickets-for-Elementor'),
                'default'     => false,
            ],
            'manage_options' => [
                'group'       => __('Advanced Admin', 'Event-Tickets-for-Elementor'),
                'description' => __('Manage site-wide settings, including plugin settings. Powerful permission: grant with care.', 'Event-Tickets-for-Elementor'),
                'default'     => false,
            ],
            'edit_theme_options' => [
                'group'       => __('Advanced Admin', 'Event-Tickets-for-Elementor'),
                'description' => __('Edit theme options and Customizer settings. Powerful permission: grant with care.', 'Event-Tickets-for-Elementor'),
                'default'     => false,
            ],
            'edit_files' => [
                'group'       => __('Advanced Admin', 'Event-Tickets-for-Elementor'),
                'description' => __('Edit theme and plugin files from wp-admin. Powerful permission: grant with care.', 'Event-Tickets-for-Elementor'),
                'default'     => false,
            ],
        ];
    }

    /**
     * @return string[]
     */
    public function plugin_capabilities(): array
    {
        return [
            Staff_Access_Manager::CAP_MANAGE_PLUGIN,
            Staff_Access_Manager::CAP_USE_CHECKIN,
            // Events CPT caps.
            CPT_Events::CAP_EDIT_POST,
            CPT_Events::CAP_READ_POST,
            CPT_Events::CAP_DELETE_POST,
            CPT_Events::CAP_EDIT_POSTS,
            CPT_Events::CAP_EDIT_OTHERS,
            CPT_Events::CAP_PUBLISH,
            CPT_Events::CAP_READ_PRIVATE,
            CPT_Events::CAP_DELETE_POSTS,
            CPT_Events::CAP_DELETE_PRIVATE,
            CPT_Events::CAP_DELETE_PUBLISHED,
            CPT_Events::CAP_DELETE_OTHERS,
            CPT_Events::CAP_EDIT_PRIVATE,
            CPT_Events::CAP_EDIT_PUBLISHED,
            // Tickets CPT caps.
            CPT_Tickets::CAP_EDIT_POST,
            CPT_Tickets::CAP_READ_POST,
            CPT_Tickets::CAP_DELETE_POST,
            CPT_Tickets::CAP_EDIT_POSTS,
            CPT_Tickets::CAP_EDIT_OTHERS,
            CPT_Tickets::CAP_PUBLISH,
            CPT_Tickets::CAP_READ_PRIVATE,
            CPT_Tickets::CAP_DELETE_POSTS,
            CPT_Tickets::CAP_DELETE_PRIVATE,
            CPT_Tickets::CAP_DELETE_PUBLISHED,
            CPT_Tickets::CAP_DELETE_OTHERS,
            CPT_Tickets::CAP_EDIT_PRIVATE,
            CPT_Tickets::CAP_EDIT_PUBLISHED,
        ];
    }
}
