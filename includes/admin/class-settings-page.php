<?php

namespace EventTicketsElementor\Admin;

use EventTicketsElementor\Settings_Store;
use EventTicketsElementor\Staff_Access_Manager;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Settings page registration and rendering.
 */
class Settings_Page
{
    private Settings_Store $store;
    private ?array $subtabs_cache = null;

    public function __construct(Settings_Store $store)
    {
        $this->store = $store;
        add_action('admin_init', [$this, 'register']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function enqueue_assets(): void
    {
        if (! isset($_GET['page']) || 'evt-tickets-settings' !== sanitize_text_field(wp_unslash($_GET['page']))) {
            return;
        }

        $tab = isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : 'email';
        if (! in_array($tab, ['pdf', 'event'], true)) {
            return;
        }

        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script(
            'evt-tickets-admin-settings-color',
            EVT_TICKETS_PLUGIN_URL . 'assets/js/admin-settings-color.js',
            ['jquery', 'wp-color-picker'],
            defined('EVT_TICKETS_VERSION') ? EVT_TICKETS_VERSION : 'dev',
            true
        );
    }

    public function register(): void
    {
        register_setting(
            'evt_tickets_settings_group',
            Settings_Store::OPTION_KEY,
            [
                'type'              => 'array',
                'sanitize_callback' => [$this->store, 'sanitize'],
                'default'           => [],
            ]
        );

        foreach ($this->store->sections() as $section) {
            $tab = isset($section['tab']) ? (string) $section['tab'] : '';
            $subtab = isset($section['subtab']) ? (string) $section['subtab'] : '';
            $page = $this->page_for_tab_and_subtab($tab, $subtab);

            add_settings_section(
                $section['id'],
                $section['title'],
                function () use ($section) {
                    if (! empty($section['description_html'])) {
                        echo '<p>' . wp_kses_post((string) $section['description_html']) . '</p>';
                        return;
                    }
                    echo '<p>' . esc_html((string) ($section['description'] ?? '')) . '</p>';
                },
                $page
            );

            foreach ($section['fields'] as $field) {
                add_settings_field(
                    $field['key'],
                    $field['label'],
                    function () use ($field) {
                        $this->render_field($field);
                    },
                    $page,
                    $section['id']
                );
            }
        }
    }

    public function render_page(): void
    {
        if (! current_user_can(Staff_Access_Manager::CAP_MANAGE_PLUGIN)) {
            return;
        }

        $tabs = $this->tabs();
        $current_tab = isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : 'email';
        if (! isset($tabs[$current_tab])) {
            $current_tab = array_key_first($tabs) ?: 'email';
        }

        $current_subtab = isset($_GET['subtab']) ? sanitize_key((string) $_GET['subtab']) : '';
        $email_subtabs = $this->subtabs_for_email();
        if ('email' === $current_tab) {
            if (! $current_subtab || ! isset($email_subtabs[$current_subtab])) {
                $current_subtab = array_key_first($email_subtabs) ?: 'content';
            }
        } else {
            $current_subtab = '';
        }
?>
        <div class="wrap evt-dashboard evt-admin-page evt-admin-page--settings">
            <div class="evt-admin-page__hero">
                <div>
                    <h1><?php esc_html_e('Event Tickets Settings', 'Event-Tickets-for-Elementor'); ?></h1>
                    <p class="description"><?php esc_html_e('Configure global behavior for event publishing, ticket delivery, Elementor integration, PDF output, and payments.', 'Event-Tickets-for-Elementor'); ?></p>
                </div>
            </div>

            <div class="evt-admin-layout">
                <aside class="evt-admin-sidebar">
                    <div class="evt-admin-sidebar__card">
                        <h2><?php esc_html_e('Settings', 'Event-Tickets-for-Elementor'); ?></h2>
                        <p class="evt-admin-sidebar__copy"><?php esc_html_e('Move between settings areas without losing the shared Event Tickets admin layout.', 'Event-Tickets-for-Elementor'); ?></p>
                    </div>

                    <nav class="evt-admin-nav" aria-label="<?php esc_attr_e('Settings tabs', 'Event-Tickets-for-Elementor'); ?>">
                        <?php foreach ($tabs as $tab_key => $tab_label) : ?>
                            <?php
                            $url = add_query_arg(
                                ['page' => 'evt-tickets-settings', 'tab' => $tab_key],
                                admin_url('admin.php')
                            );
                            $classes = 'evt-admin-nav__item' . ($tab_key === $current_tab ? ' is-active' : '');
                            ?>
                            <a href="<?php echo esc_url($url); ?>" class="<?php echo esc_attr($classes); ?>">
                                <strong><?php echo esc_html($tab_label); ?></strong>
                                <span><?php echo esc_html($this->tab_description($tab_key)); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </nav>

                </aside>

                <section class="evt-admin-content evt-card">
                    <div class="evt-admin-content__head">
                        <div>
                            <h2><?php echo esc_html($tabs[$current_tab] ?? __('Settings', 'Event-Tickets-for-Elementor')); ?></h2>
                            <p class="evt-docs__lead"><?php echo esc_html($this->tab_description($current_tab)); ?></p>
                        </div>
                        <?php if ('email' === $current_tab && '' !== $current_subtab && isset($email_subtabs[$current_subtab])) : ?>
                            <span class="evt-docs__badge"><?php echo esc_html($email_subtabs[$current_subtab]); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="evt-admin-content__body">
                        <?php if ('email' === $current_tab && ! empty($email_subtabs)) : ?>
                            <div class="evt-admin-inline-subnav">
                                <h3><?php esc_html_e('Email Sections', 'Event-Tickets-for-Elementor'); ?></h3>
                                <div class="evt-admin-subnav">
                                    <?php foreach ($email_subtabs as $subtab_key => $subtab_label) : ?>
                                        <?php
                                        $url = add_query_arg(
                                            ['page' => 'evt-tickets-settings', 'tab' => 'email', 'subtab' => $subtab_key],
                                            admin_url('admin.php')
                                        );
                                        $classes = 'evt-admin-subnav__item' . ($subtab_key === $current_subtab ? ' is-active' : '');
                                        ?>
                                        <a href="<?php echo esc_url($url); ?>" class="<?php echo esc_attr($classes); ?>"><?php echo esc_html($subtab_label); ?></a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="options.php" class="evt-settings-form">
                            <?php
                            settings_fields('evt_tickets_settings_group');
                            do_settings_sections($this->page_for_tab_and_subtab($current_tab, $current_subtab));
                            submit_button(__('Save Settings', 'Event-Tickets-for-Elementor'));
                            ?>
                        </form>
                    </div>
                </section>
            </div>
        </div>
        <?php
    }

    /**
     * Render a field using the schema metadata.
     *
     * @param array<string,mixed> $field
     */
    private function render_field(array $field): void
    {
        $key         = $field['key'];
        $placeholder = isset($field['placeholder']) ? $field['placeholder'] : '';
        $default     = $field['default'] ?? '';
        $value       = $this->store->get($key, $default);
        $name        = Settings_Store::OPTION_KEY . '[' . $key . ']';

        switch ($field['type']) {
            case 'html':
                $allowed_html = wp_kses_allowed_html('post');
                $allowed_html['iframe'] = [
                    'title'      => true,
                    'style'      => true,
                    'width'      => true,
                    'height'     => true,
                    'src'        => true,
                    'srcdoc'     => true,
                    'frameborder' => true,
                ];
                echo isset($field['html']) ? wp_kses((string) $field['html'], $allowed_html) : '';
                break;
            case 'checkbox':
                printf(
                    '<input type="hidden" name="%1$s" value="0" /><label><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label>',
                    esc_attr($name),
                    checked((int) $value, 1, false),
                    esc_html__('Enabled', 'Event-Tickets-for-Elementor')
                );
                break;
            case 'checkbox_matrix':
                $capability = isset($field['capability']) ? (string) $field['capability'] : '';
                $item_label = isset($field['item_label']) ? (string) $field['item_label'] : (string) ($field['label'] ?? '');
                $item_description = isset($field['description']) ? (string) $field['description'] : '';
        ?>
                <table class="widefat striped evt-staff-capability-table">
                    <thead>
                        <tr>
                            <th style="width:36px;"></th>
                            <th><?php esc_html_e('Capability', 'Event-Tickets-for-Elementor'); ?></th>
                            <th><?php esc_html_e('Description', 'Event-Tickets-for-Elementor'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <input type="hidden" name="<?php echo esc_attr($name); ?>" value="0" />
                                <input type="checkbox" id="<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($name); ?>" value="1" <?php checked((int) $value, 1); ?> />
                            </td>
                            <td>
                                <label for="<?php echo esc_attr($key); ?>">
                                    <strong><?php echo esc_html($item_label); ?></strong><br />
                                    <code><?php echo esc_html($capability); ?></code>
                                </label>
                            </td>
                            <td><?php echo esc_html($item_description); ?></td>
                        </tr>
                    </tbody>
                </table>
            <?php
                break;
            case 'number':
                printf(
                    '<input type="number" name="%1$s" value="%2$s" class="regular-text" placeholder="%3$s" />',
                    esc_attr($name),
                    esc_attr((string) $value),
                    esc_attr($placeholder)
                );
                break;
            case 'select':
                $options = isset($field['options']) && is_array($field['options']) ? $field['options'] : [];
                echo '<select name="' . esc_attr($name) . '">';
                foreach ($options as $opt_value => $opt_label) {
                    echo '<option value="' . esc_attr((string) $opt_value) . '" ' . selected((string) $value, (string) $opt_value, false) . '>';
                    echo esc_html((string) $opt_label);
                    echo '</option>';
                }
                echo '</select>';
                break;
            case 'color':
                printf(
                    '<input type="text" name="%1$s" value="%2$s" class="regular-text evt-color-field" placeholder="%3$s" data-default-color="%4$s" />',
                    esc_attr($name),
                    esc_attr((string) $value),
                    esc_attr($placeholder),
                    esc_attr((string) ($field['default'] ?? $placeholder))
                );
                break;
            case 'email':
                printf(
                    '<input type="email" name="%1$s" value="%2$s" class="regular-text" placeholder="%3$s" />',
                    esc_attr($name),
                    esc_attr($value),
                    esc_attr($placeholder)
                );
                break;
            case 'secret':
                $secret_input_id = 'evt_secret_' . sanitize_key($key);
                $secret_constant = $this->store->secret_constant_for($key);
                $mask = '';
                if ('' !== (string) $value) {
                    $mask = '••••••••' . substr((string) $value, -4);
                }
                printf(
                    '<input type="password" name="%1$s" id="%2$s" value="" class="regular-text" placeholder="%3$s" autocomplete="new-password" />',
                    esc_attr($name),
                    esc_attr($secret_input_id),
                    esc_attr($mask)
                );
                echo ' <label style="display:inline-block;margin-left:8px;"><input type="checkbox" id="' . esc_attr($secret_input_id . '_toggle') . '" /> ' . esc_html__('Reveal', 'Event-Tickets-for-Elementor') . '</label>';
                echo '<p class="description">' . esc_html__('Leave blank to keep the current key.', 'Event-Tickets-for-Elementor') . '</p>';
                if (null !== $secret_constant) {
                    echo '<p class="description">' . esc_html(
                        sprintf(
                            /* translators: %s is a wp-config.php constant name. */
                            __('You can also set this via the %s constant in wp-config.php; it then overrides the stored value and is never written to the database, shown in full, or exposed via REST.', 'Event-Tickets-for-Elementor'),
                            $secret_constant
                        )
                    ) . '</p>';
                }
            ?>
                <script>
                    (function() {
                        var input = document.getElementById(<?php echo wp_json_encode($secret_input_id); ?>);
                        var toggle = document.getElementById(<?php echo wp_json_encode($secret_input_id . '_toggle'); ?>);
                        if (input && toggle) {
                            toggle.addEventListener('change', function() {
                                input.type = toggle.checked ? 'text' : 'password';
                            });
                        }
                    })();
                </script>
<?php
                break;
            case 'textarea':
                printf(
                    '<textarea name="%1$s" class="large-text" rows="3" placeholder="%3$s">%2$s</textarea>',
                    esc_attr($name),
                    esc_textarea($value),
                    esc_attr($placeholder)
                );
                break;
            case 'text':
            default:
                printf(
                    '<input type="text" name="%1$s" value="%2$s" class="regular-text" placeholder="%3$s" />',
                    esc_attr($name),
                    esc_attr($value),
                    esc_attr($placeholder)
                );
                break;
        }

        if (! empty($field['description']) && 'checkbox_matrix' !== ($field['type'] ?? '')) {
            echo '<p class="description">' . wp_kses_post((string) $field['description']) . '</p>';
        }
    }

    /**
     * @return array<string,string>
     */
    private function tabs(): array
    {
        return [
            'email'     => __('Email Settings', 'Event-Tickets-for-Elementor'),
            'event'     => __('Event Settings', 'Event-Tickets-for-Elementor'),
            'elementor' => __('Elementor Settings', 'Event-Tickets-for-Elementor'),
            'pdf'       => __('PDF Settings', 'Event-Tickets-for-Elementor'),
            'payments'  => __('Payments', 'Event-Tickets-for-Elementor'),
        ];
    }

    private function tab_description(string $tab): string
    {
        switch ($tab) {
            case 'event':
                return __('Site-wide event state presentation, labels, and status badge styling.', 'Event-Tickets-for-Elementor');
            case 'elementor':
                return __('Elementor widget behavior, dynamic integration, and form field mapping.', 'Event-Tickets-for-Elementor');
            case 'pdf':
                return __('Default PDF templates, branding, and ticket attachment output.', 'Event-Tickets-for-Elementor');
            case 'payments':
                return __('Payment processor connections, currency, seat holds, and paid ticket handling.', 'Event-Tickets-for-Elementor');
            case 'email':
            default:
                return __('Email content, templates, attachments, and client-specific delivery behavior.', 'Event-Tickets-for-Elementor');
        }
    }

    private function subtabs_for_email(): array
    {
        if (null !== $this->subtabs_cache) {
            return $this->subtabs_cache;
        }

        $subtabs = [];
        foreach ($this->store->sections() as $section) {
            if (('email' !== (string) ($section['tab'] ?? ''))) {
                continue;
            }

            $subtab = (string) ($section['subtab'] ?? 'content');
            if ('' === $subtab) {
                $subtab = 'content';
            }

            if (! isset($subtabs[$subtab])) {
                // Default labels.
                switch ($subtab) {
                    case 'attachments':
                        $subtabs[$subtab] = __('Attachments', 'Event-Tickets-for-Elementor');
                        break;
                    case 'template':
                        $subtabs[$subtab] = __('Template', 'Event-Tickets-for-Elementor');
                        break;
                    case 'advanced':
                        $subtabs[$subtab] = __('Advanced', 'Event-Tickets-for-Elementor');
                        break;
                    case 'content':
                    default:
                        $subtabs[$subtab] = __('Content', 'Event-Tickets-for-Elementor');
                        break;
                }
            }
        }

        if (empty($subtabs)) {
            $subtabs = [
                'content' => __('Content', 'Event-Tickets-for-Elementor'),
            ];
        }

        $this->subtabs_cache = $subtabs;
        return $subtabs;
    }

    private function page_for_tab_and_subtab(string $tab, string $subtab): string
    {
        $tab = $tab ? sanitize_key($tab) : 'email';
        if ('email' !== $tab) {
            return 'evt-tickets-settings-' . $tab;
        }

        $subtab = $subtab ? sanitize_key($subtab) : 'content';
        return 'evt-tickets-settings-' . $tab . '-' . $subtab;
    }
}
