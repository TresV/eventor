<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Creates and maintains the plugin Staff role and its managed capabilities.
 */
class Staff_Access_Manager
{
    public const ROLE = 'evt_staff';
    public const CAP_USE_CHECKIN = 'evt_use_checkin';
    public const CAP_MANAGE_PLUGIN = 'evt_manage_plugin';
    public const SETTING_CHECKIN_PAGE_URL = 'staff_checkin_page_url';

    private Settings_Store $store;

    public function __construct(?Settings_Store $store = null, bool $register_hooks = true)
    {
        $this->store = $store ?: new Settings_Store();

        if ($register_hooks) {
            add_action('init', [$this, 'ensure_role'], 20);
            add_action('update_option_' . Settings_Store::OPTION_KEY, [$this, 'handle_settings_updated'], 10, 2);
        }
    }

    public static function activate(): void
    {
        $manager = new self(null, false);
        $manager->ensure_role();
    }

    /**
     * Remove the plugin-managed Staff role and strip the plugin caps that were
     * added to the Administrator and Editor roles. Called on plugin deactivation
     * so these capabilities do not persist in the DB after the plugin is gone.
     */
    public static function deactivate(): void
    {
        if (! function_exists('wp_roles')) {
            return;
        }

        $manager = new self(null, false);

        $administrator = get_role('administrator');
        if ($administrator) {
            foreach ($manager->plugin_capabilities() as $cap) {
                $administrator->remove_cap($cap);
            }
        }

        $editor = get_role('editor');
        if ($editor) {
            foreach ($manager->plugin_capabilities() as $cap) {
                $editor->remove_cap($cap);
            }
        }

        remove_role(self::ROLE);
    }

    public function ensure_role(): void
    {
        if (! function_exists('wp_roles')) {
            return;
        }

        $label = self::role_label();
        $role  = get_role(self::ROLE);

        // Fast path: the role exists, the caps were already synced for this
        // plugin version, and the role looks healthy. Each sync() call writes
        // to the roles option, so skipping it on the common path avoids a
        // (potentially large) option update on every request.
        if ($role && (string) get_option('evt_tickets_role_sync_version', '') === EVT_TICKETS_VERSION && $this->role_is_healthy()) {
            return;
        }

        if (! $role) {
            add_role(
                self::ROLE,
                $label,
                [
                    'read' => true,
                ]
            );
            $role = get_role(self::ROLE);
        }

        $this->sync_role_label($label);
        $this->sync_staff_role_caps();
        $this->sync_administrator_caps();
        $this->sync_editor_caps();

        update_option('evt_tickets_role_sync_version', EVT_TICKETS_VERSION, false);
    }

    /**
     * @param mixed $old_value
     * @param mixed $value
     */
    public function handle_settings_updated($old_value, $value): void
    {
        // Invalidate the sync marker so a settings change re-applies the
        // staff role capabilities on the next request.
        delete_option('evt_tickets_role_sync_version');
        $this->ensure_role();
    }

    /**
     * @return array<string,bool>
     */
    public function default_settings(): array
    {
        $defaults = [];

        foreach ($this->managed_capabilities_catalog() as $cap => $config) {
            $defaults[$this->setting_key_for_cap($cap)] = ! empty($config['default']);
        }

        return $defaults;
    }

    /**
     * @return array<string,string>
     */
    public function capability_labels(): array
    {
        return [
            self::CAP_MANAGE_PLUGIN => __('Manage Event Tickets admin screens', 'Event-Tickets-for-Elementor'),
            self::CAP_USE_CHECKIN => __('Use Check-in screen', 'Event-Tickets-for-Elementor'),
        ];
    }

    /**
     * @return array<string,bool>
     */
    public function managed_capabilities_from_settings(): array
    {
        $defaults = $this->default_settings();
        $managed  = [];

        foreach ($this->managed_capabilities_catalog() as $cap => $config) {
            $setting_key = $this->setting_key_for_cap($cap);
            $managed[$cap] = (bool) $this->store->get($setting_key, ! empty($defaults[$setting_key]) ? 1 : 0);
        }

        return $managed;
    }

    /**
     * @return array<string,string>
     */
    public function enabled_capability_labels(): array
    {
        $labels  = $this->capability_labels();
        $enabled = [];

        foreach ($this->managed_capabilities_from_settings() as $cap => $is_enabled) {
            if ($is_enabled && isset($labels[$cap])) {
                $enabled[$cap] = $labels[$cap];
            }
        }

        return $enabled;
    }

    public function role_exists(): bool
    {
        return (bool) get_role(self::ROLE);
    }

    public function role_is_healthy(): bool
    {
        $role = get_role(self::ROLE);
        if (! $role) {
            return false;
        }

        if (! $role->has_cap('read')) {
            return false;
        }

        foreach ($this->managed_capabilities_from_settings() as $cap => $enabled) {
            if ($enabled && ! $role->has_cap($cap)) {
                return false;
            }

            if (! $enabled && $role->has_cap($cap)) {
                return false;
            }
        }

        return true;
    }

    public function staff_page_url(): string
    {
        return admin_url('admin.php?page=evt-tickets-staff');
    }

    public function configured_checkin_page_url(): string
    {
        return self::configured_checkin_page_url_static();
    }

    public function resolved_checkin_page_url(): string
    {
        return self::resolved_checkin_page_url_static();
    }

    public static function configured_checkin_page_url_static(): string
    {
        $settings = get_option(Settings_Store::OPTION_KEY, []);
        if (! is_array($settings)) {
            return '';
        }

        return isset($settings[self::SETTING_CHECKIN_PAGE_URL])
            ? self::normalize_checkin_page_url((string) $settings[self::SETTING_CHECKIN_PAGE_URL])
            : '';
    }

    public static function resolved_checkin_page_url_static(): string
    {
        $configured = self::configured_checkin_page_url_static();
        if ('' !== $configured) {
            return (string) esc_url_raw($configured);
        }

        $pages = get_option('evt_tickets_starter_pages', []);
        if (is_array($pages) && ! empty($pages['staff_checkin'])) {
            $url = get_permalink((int) $pages['staff_checkin']);
            if ($url) {
                return (string) $url;
            }
        }

        return (string) home_url('/staff-checkin/');
    }

    public static function normalize_checkin_page_url(string $raw): string
    {
        $raw = trim($raw);
        if ('' === $raw) {
            return '';
        }

        $validated = esc_url_raw($raw);
        if ('' !== $validated) {
            return $validated;
        }

        if ('/' === $raw[0]) {
            return (string) home_url($raw);
        }

        return (string) home_url('/' . ltrim($raw, '/'));
    }

    public static function role_label(): string
    {
        if (0 === strpos((string) get_locale(), 'bg_')) {
            return 'Персонал';
        }

        return __('Staff', 'Event-Tickets-for-Elementor');
    }

    public function settings_status_html(): string
    {
        $status_label = $this->role_exists() && $this->role_is_healthy()
            ? __('Ready', 'Event-Tickets-for-Elementor')
            : __('Needs repair', 'Event-Tickets-for-Elementor');

        $enabled_caps = $this->enabled_capability_labels();
        $cap_summary  = empty($enabled_caps)
            ? __('No plugin-managed Staff permissions are enabled.', 'Event-Tickets-for-Elementor')
            : sprintf(
                /* translators: %d is the number of enabled staff permissions */
                __('Enabled Staff permissions: %d', 'Event-Tickets-for-Elementor'),
                count($enabled_caps)
            );

        ob_start();
?>
        <div class="evt-staff-settings-status">
            <span class="evt-docs__badge"><?php echo esc_html($status_label); ?></span>
            <p><?php echo esc_html($cap_summary); ?></p>
            <p>
                <a class="button button-secondary" href="<?php echo esc_url($this->staff_page_url()); ?>">
                    <?php esc_html_e('Open Staff Management', 'Event-Tickets-for-Elementor'); ?>
                </a>
            </p>
        </div>
    <?php
        return (string) ob_get_clean();
    }

    /**
     * @return array<string,array<string,array<string,mixed>>>
     */
    public function grouped_capabilities(): array
    {
        $settings = $this->managed_capabilities_from_settings();
        $grouped  = [];

        foreach ($this->managed_capabilities_catalog() as $cap => $config) {
            $group = (string) $config['group'];
            if (! isset($grouped[$group])) {
                $grouped[$group] = [];
            }

            $grouped[$group][$cap] = [
                'setting_key' => $this->setting_key_for_cap($cap),
                'description' => (string) $config['description'],
                'checked'     => ! empty($settings[$cap]),
            ];
        }

        return $grouped;
    }

    /**
     * @return array<string,string>
     */
    public function always_enabled_capabilities(): array
    {
        return [
            'read' => __('Required so Staff users can log in and access protected screens.', 'Event-Tickets-for-Elementor'),
        ];
    }

    private function setting_key_for_cap(string $cap): string
    {
        return 'staff_cap_' . sanitize_key($cap);
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function managed_capabilities_catalog(): array
    {
        return [
            self::CAP_USE_CHECKIN => [
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
            'manage_woocommerce' => [
                'group'       => __('WooCommerce', 'Event-Tickets-for-Elementor'),
                'description' => __('Access WooCommerce menus and store settings.', 'Event-Tickets-for-Elementor'),
                'default'     => false,
            ],
            'view_woocommerce_reports' => [
                'group'       => __('WooCommerce', 'Event-Tickets-for-Elementor'),
                'description' => __('View WooCommerce reports.', 'Event-Tickets-for-Elementor'),
                'default'     => false,
            ],
            'edit_products' => [
                'group'       => __('WooCommerce', 'Event-Tickets-for-Elementor'),
                'description' => __('Edit WooCommerce products.', 'Event-Tickets-for-Elementor'),
                'default'     => false,
            ],
            'edit_others_products' => [
                'group'       => __('WooCommerce', 'Event-Tickets-for-Elementor'),
                'description' => __('Edit products created by other users.', 'Event-Tickets-for-Elementor'),
                'default'     => false,
            ],
            'publish_products' => [
                'group'       => __('WooCommerce', 'Event-Tickets-for-Elementor'),
                'description' => __('Publish WooCommerce products.', 'Event-Tickets-for-Elementor'),
                'default'     => false,
            ],
            'delete_products' => [
                'group'       => __('WooCommerce', 'Event-Tickets-for-Elementor'),
                'description' => __('Delete WooCommerce products.', 'Event-Tickets-for-Elementor'),
                'default'     => false,
            ],
            'edit_shop_orders' => [
                'group'       => __('WooCommerce', 'Event-Tickets-for-Elementor'),
                'description' => __('Edit WooCommerce orders.', 'Event-Tickets-for-Elementor'),
                'default'     => false,
            ],
            'edit_others_shop_orders' => [
                'group'       => __('WooCommerce', 'Event-Tickets-for-Elementor'),
                'description' => __('Edit orders created by other users.', 'Event-Tickets-for-Elementor'),
                'default'     => false,
            ],
            'publish_shop_orders' => [
                'group'       => __('WooCommerce', 'Event-Tickets-for-Elementor'),
                'description' => __('Create and publish WooCommerce orders.', 'Event-Tickets-for-Elementor'),
                'default'     => false,
            ],
            'delete_shop_orders' => [
                'group'       => __('WooCommerce', 'Event-Tickets-for-Elementor'),
                'description' => __('Delete WooCommerce orders.', 'Event-Tickets-for-Elementor'),
                'default'     => false,
            ],
            'edit_shop_coupons' => [
                'group'       => __('WooCommerce', 'Event-Tickets-for-Elementor'),
                'description' => __('Edit WooCommerce coupons.', 'Event-Tickets-for-Elementor'),
                'default'     => false,
            ],
            'publish_shop_coupons' => [
                'group'       => __('WooCommerce', 'Event-Tickets-for-Elementor'),
                'description' => __('Create and publish WooCommerce coupons.', 'Event-Tickets-for-Elementor'),
                'default'     => false,
            ],
            'delete_shop_coupons' => [
                'group'       => __('WooCommerce', 'Event-Tickets-for-Elementor'),
                'description' => __('Delete WooCommerce coupons.', 'Event-Tickets-for-Elementor'),
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

    public function restore_defaults_html(): string
    {
        $defaults = wp_json_encode($this->default_settings());
        $option_prefix = Settings_Store::OPTION_KEY;

        ob_start();
    ?>
        <p class="description"><?php esc_html_e('Reset the Staff role capabilities in this tab back to the plugin defaults before saving.', 'Event-Tickets-for-Elementor'); ?></p>
        <p>
            <button
                type="button"
                class="button button-secondary evt-staff-restore-defaults"
                data-defaults="<?php echo esc_attr((string) $defaults); ?>">
                <?php esc_html_e('Restore Staff Defaults', 'Event-Tickets-for-Elementor'); ?>
            </button>
        </p>
        <script>
            (function() {
                if (window.evtStaffDefaultsBound) {
                    return;
                }
                window.evtStaffDefaultsBound = true;

                document.addEventListener('click', function(event) {
                    var button = event.target.closest('.evt-staff-restore-defaults');
                    if (!button) {
                        return;
                    }

                    var form = button.closest('form');
                    if (!form) {
                        return;
                    }

                    var defaults = {};
                    try {
                        defaults = JSON.parse(button.getAttribute('data-defaults') || '{}') || {};
                    } catch (error) {
                        defaults = {};
                    }

                    Object.keys(defaults).forEach(function(key) {
                        var checkbox = form.querySelector('input[type="checkbox"][name="<?php echo esc_js($option_prefix); ?>[' + key + ']"]');
                        if (checkbox) {
                            checkbox.checked = !!Number(defaults[key]);
                        }
                    });
                });
            }());
        </script>
<?php
        return (string) ob_get_clean();
    }

    private function sync_staff_role_caps(): void
    {
        $role = get_role(self::ROLE);
        if (! $role) {
            return;
        }

        $role->add_cap('read', true);

        foreach ($this->managed_capabilities_from_settings() as $cap => $enabled) {
            if ($enabled) {
                $role->add_cap($cap, true);
            } else {
                $role->remove_cap($cap);
            }
        }
    }

    private function sync_administrator_caps(): void
    {
        $administrator = get_role('administrator');
        if (! $administrator) {
            return;
        }

        foreach ($this->plugin_capabilities() as $cap) {
            $administrator->add_cap($cap, true);
        }
    }

    private function sync_editor_caps(): void
    {
        $editor = get_role('editor');
        if (! $editor) {
            return;
        }

        foreach ($this->plugin_capabilities() as $cap) {
            $editor->add_cap($cap, true);
        }
    }

    /**
     * @return string[]
     */
    private function plugin_capabilities(): array
    {
        return [
            self::CAP_MANAGE_PLUGIN,
            self::CAP_USE_CHECKIN,
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

    private function sync_role_label(string $label): void
    {
        $roles = wp_roles();
        if (! isset($roles->roles[self::ROLE])) {
            return;
        }

        if (($roles->roles[self::ROLE]['name'] ?? '') === $label) {
            return;
        }

        $roles->roles[self::ROLE]['name'] = $label;
        $roles->role_names[self::ROLE] = $label;
        update_option($roles->role_key, $roles->roles, true);
    }
}
