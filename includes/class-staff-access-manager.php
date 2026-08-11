<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/staff/class-staff-capabilities.php';
require_once __DIR__ . '/staff/class-staff-admin-ui.php';

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

    private Staff_Capabilities $capabilities;

    private Staff_Admin_UI $admin_ui;

    public function __construct(?Settings_Store $store = null, bool $register_hooks = true)
    {
        $this->store = $store ?: new Settings_Store();

        $this->capabilities = new Staff_Capabilities();
        $this->admin_ui     = new Staff_Admin_UI($this, $this->capabilities);

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
            foreach ($manager->capabilities->plugin_capabilities() as $cap) {
                $administrator->remove_cap($cap);
            }
        }

        $editor = get_role('editor');
        if ($editor) {
            foreach ($manager->capabilities->plugin_capabilities() as $cap) {
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

        foreach ($this->capabilities->managed_capabilities_catalog() as $cap => $config) {
            $defaults[$this->capabilities->setting_key_for_cap($cap)] = ! empty($config['default']);
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

        foreach ($this->capabilities->managed_capabilities_catalog() as $cap => $config) {
            $setting_key = $this->capabilities->setting_key_for_cap($cap);
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
        return $this->admin_ui->settings_status_html();
    }

    /**
     * @return array<string,array<string,array<string,mixed>>>
     */
    public function grouped_capabilities(): array
    {
        return $this->admin_ui->grouped_capabilities();
    }

    /**
     * @return array<string,string>
     */
    public function always_enabled_capabilities(): array
    {
        return $this->admin_ui->always_enabled_capabilities();
    }

    public function restore_defaults_html(): string
    {
        return $this->admin_ui->restore_defaults_html();
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

        foreach ($this->capabilities->plugin_capabilities() as $cap) {
            $administrator->add_cap($cap, true);
        }
    }

    private function sync_editor_caps(): void
    {
        $editor = get_role('editor');
        if (! $editor) {
            return;
        }

        foreach ($this->capabilities->plugin_capabilities() as $cap) {
            $editor->add_cap($cap, true);
        }
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
