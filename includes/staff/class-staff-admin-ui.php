<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Renders the admin HTML blocks for the Staff role settings, status, and the
 * capability groups shown on the Staff management screens.
 */
class Staff_Admin_UI
{
    private Staff_Access_Manager $manager;

    private Staff_Capabilities $capabilities;

    public function __construct(Staff_Access_Manager $manager, Staff_Capabilities $capabilities)
    {
        $this->manager = $manager;
        $this->capabilities = $capabilities;
    }

    public function settings_status_html(): string
    {
        $status_label = $this->manager->role_exists() && $this->manager->role_is_healthy()
            ? __('Ready', 'Event-Tickets-for-Elementor')
            : __('Needs repair', 'Event-Tickets-for-Elementor');

        $enabled_caps = $this->manager->enabled_capability_labels();
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
                <a class="button button-secondary" href="<?php echo esc_url($this->manager->staff_page_url()); ?>">
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
        $settings = $this->manager->managed_capabilities_from_settings();
        $grouped  = [];

        foreach ($this->capabilities->managed_capabilities_catalog() as $cap => $config) {
            $group = (string) $config['group'];
            if (! isset($grouped[$group])) {
                $grouped[$group] = [];
            }

            $grouped[$group][$cap] = [
                'setting_key' => $this->capabilities->setting_key_for_cap($cap),
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

    public function restore_defaults_html(): string
    {
        $defaults = wp_json_encode($this->manager->default_settings());
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
}
