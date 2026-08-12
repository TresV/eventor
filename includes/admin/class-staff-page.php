<?php

namespace EventTicketsElementor\Admin;

use EventTicketsElementor\Staff_Access_Manager;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Admin screen for assigning and reviewing Staff users.
 */
class Staff_Page
{
    private Staff_Access_Manager $manager;

    public function __construct(Staff_Access_Manager $manager)
    {
        $this->manager = $manager;

        add_action('admin_post_evt_tickets_staff_caps_save', [$this, 'handle_caps_save']);
        add_action('admin_post_evt_tickets_staff_assign', [$this, 'handle_assign']);
        add_action('admin_post_evt_tickets_staff_remove', [$this, 'handle_remove']);
    }

    public function render_page(): void
    {
        if (! current_user_can(Staff_Access_Manager::CAP_MANAGE_PLUGIN)) {
            return;
        }

        $staff_users     = get_users(['role' => Staff_Access_Manager::ROLE, 'orderby' => 'display_name', 'order' => 'ASC']);
        $assignable_users = get_users([
            'role__not_in' => [Staff_Access_Manager::ROLE],
            'orderby'      => 'display_name',
            'order'        => 'ASC',
            'number'       => 200,
        ]);
        $enabled_caps = $this->manager->enabled_capability_labels();
        $capability_groups = $this->manager->grouped_capabilities();
        $always_enabled = $this->manager->always_enabled_capabilities();
        $checkin_page_url = $this->manager->configured_checkin_page_url();
        $resolved_checkin_page_url = $this->manager->resolved_checkin_page_url();
        $notice       = $this->current_notice();
        ?>
        <div class="wrap evt-dashboard evt-admin-page evt-admin-page--staff">
            <div class="evt-admin-page__hero">
                <div>
                    <h1><?php esc_html_e('Staff', 'Event-Tickets-for-Elementor'); ?></h1>
                    <p class="description"><?php esc_html_e('Assign operational users to the built-in Staff role and review which plugin permissions they currently receive.', 'Event-Tickets-for-Elementor'); ?></p>
                </div>
            </div>

            <div class="evt-admin-layout">
                <aside class="evt-admin-sidebar">
                    <div class="evt-admin-sidebar__card">
                        <h2><?php esc_html_e('Staff Role', 'Event-Tickets-for-Elementor'); ?></h2>
                        <p class="evt-admin-sidebar__copy"><?php esc_html_e('Use this screen to manage event-day staff without giving full plugin administration access.', 'Event-Tickets-for-Elementor'); ?></p>
                    </div>

                    <nav class="evt-admin-nav" aria-label="<?php esc_attr_e('Staff quick links', 'Event-Tickets-for-Elementor'); ?>">
                        <a class="evt-admin-nav__item" href="#evt-staff-access">
                            <strong><?php esc_html_e('Staff Access', 'Event-Tickets-for-Elementor'); ?></strong>
                            <span><?php esc_html_e('Control which plugin permissions and WordPress capabilities the Staff role receives.', 'Event-Tickets-for-Elementor'); ?></span>
                        </a>
                        <a class="evt-admin-nav__item" href="#evt-staff-users">
                            <strong><?php esc_html_e('Staff Users', 'Event-Tickets-for-Elementor'); ?></strong>
                            <span><?php esc_html_e('Assign existing users to the Staff role and review who currently has access.', 'Event-Tickets-for-Elementor'); ?></span>
                        </a>
                        <a class="evt-admin-nav__item" href="<?php echo esc_url(admin_url('users.php?role=' . Staff_Access_Manager::ROLE)); ?>">
                            <strong><?php esc_html_e('View Staff in Users', 'Event-Tickets-for-Elementor'); ?></strong>
                            <span><?php esc_html_e('Open the WordPress Users screen filtered to the Staff role.', 'Event-Tickets-for-Elementor'); ?></span>
                        </a>
                        <a class="evt-admin-nav__item" href="<?php echo esc_url(admin_url('user-new.php')); ?>">
                            <strong><?php esc_html_e('Create New User', 'Event-Tickets-for-Elementor'); ?></strong>
                            <span><?php esc_html_e('Create a new WordPress user and assign the Staff role from the role dropdown.', 'Event-Tickets-for-Elementor'); ?></span>
                        </a>
                    </nav>
                </aside>

                <section class="evt-admin-content evt-card">
                    <div class="evt-admin-content__head">
                        <div>
                            <h2><?php esc_html_e('Staff Management', 'Event-Tickets-for-Elementor'); ?></h2>
                            <p class="evt-docs__lead"><?php esc_html_e('Review assigned Staff users, add existing users to the role, and remove access when needed.', 'Event-Tickets-for-Elementor'); ?></p>
                        </div>
                        /* translators: %d is the number of staff users. */
                        <span class="evt-docs__badge"><?php echo esc_html(sprintf(_n('%d staff user', '%d staff users', count($staff_users), 'Event-Tickets-for-Elementor'), count($staff_users))); ?></span>
                    </div>

                    <div class="evt-admin-content__body">
                        <?php if ($notice) : ?>
                            <div class="notice inline notice-<?php echo esc_attr($notice['type']); ?>"><p><?php echo esc_html($notice['message']); ?></p></div>
                        <?php endif; ?>

                        <div class="evt-grid">
                            <div class="evt-card">
                                <div class="evt-card__header">
                                    <h3><?php esc_html_e('Role Status', 'Event-Tickets-for-Elementor'); ?></h3>
                                </div>
                                <p><strong><?php esc_html_e('Role label:', 'Event-Tickets-for-Elementor'); ?></strong> <?php echo esc_html(Staff_Access_Manager::role_label()); ?></p>
                                <p><strong><?php esc_html_e('Role slug:', 'Event-Tickets-for-Elementor'); ?></strong> <code><?php echo esc_html(Staff_Access_Manager::ROLE); ?></code></p>
                                <p><strong><?php esc_html_e('Health:', 'Event-Tickets-for-Elementor'); ?></strong> <?php echo esc_html($this->manager->role_is_healthy() ? __('Ready', 'Event-Tickets-for-Elementor') : __('Needs repair', 'Event-Tickets-for-Elementor')); ?></p>
                            </div>

                            <div class="evt-card">
                                <div class="evt-card__header">
                                    <h3><?php esc_html_e('Enabled Staff Permissions', 'Event-Tickets-for-Elementor'); ?></h3>
                                </div>
                                <?php if (empty($enabled_caps)) : ?>
                                    <p><?php esc_html_e('No plugin-managed Staff permissions are currently enabled.', 'Event-Tickets-for-Elementor'); ?></p>
                                <?php else : ?>
                                    <ul class="evt-list">
                                        <?php foreach ($enabled_caps as $cap => $label) : ?>
                                            <li><code><?php echo esc_html($cap); ?></code> <?php echo esc_html($label); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="evt-card evt-staff-card" id="evt-staff-access">
                            <div class="evt-card__header">
                                <h3><?php esc_html_e('Staff Access', 'Event-Tickets-for-Elementor'); ?></h3>
                            </div>
                            <p class="evt-staff-card__lead"><?php esc_html_e('Choose exactly which plugin and WordPress capabilities the Staff role should receive.', 'Event-Tickets-for-Elementor'); ?></p>
                            <div class="evt-staff-checkin-url">
                                <div class="evt-card__header evt-card__header--minor">
                                    <h4><?php esc_html_e('Check-in page URL', 'Event-Tickets-for-Elementor'); ?></h4>
                                </div>
                                <p><?php esc_html_e('Set the frontend page Staff users should be sent to for ticket check-in. Leave this blank to use the built-in Staff Check-in starter page or the default /staff-checkin/ fallback.', 'Event-Tickets-for-Elementor'); ?></p>
                                <p><strong><?php esc_html_e('Current target:', 'Event-Tickets-for-Elementor'); ?></strong> <code><?php echo esc_html($resolved_checkin_page_url); ?></code></p>
                            </div>
                            <?php if (! empty($always_enabled)) : ?>
                                <div class="evt-staff-always-on">
                                    <strong><?php esc_html_e('Always enabled baseline', 'Event-Tickets-for-Elementor'); ?></strong>
                                    <ul class="evt-list">
                                        <?php foreach ($always_enabled as $cap => $description) : ?>
                                            <li><code><?php echo esc_html($cap); ?></code> <?php echo esc_html($description); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="evt-staff-access-form">
                                <?php wp_nonce_field('evt_tickets_staff_caps_save', 'evt_tickets_staff_nonce'); ?>
                                <input type="hidden" name="action" value="evt_tickets_staff_caps_save" />

                                <div class="evt-staff-access-form__field">
                                    <label for="evt-staff-checkin-page-url"><strong><?php esc_html_e('Staff check-in page URL', 'Event-Tickets-for-Elementor'); ?></strong></label>
                                    <input
                                        type="url"
                                        class="regular-text code"
                                        id="evt-staff-checkin-page-url"
                                        name="<?php echo esc_attr(\EventTicketsElementor\Settings_Store::OPTION_KEY . '[' . Staff_Access_Manager::SETTING_CHECKIN_PAGE_URL . ']'); ?>"
                                        value="<?php echo esc_attr($checkin_page_url); ?>"
                                        placeholder="<?php echo esc_attr(home_url('/staff-checkin/')); ?>"
                                    />
                                    <p class="description"><?php esc_html_e('Example: https://example.com/ticket-checkin/ . This URL is used for Staff "Visit Site" redirects and other Staff check-in shortcuts.', 'Event-Tickets-for-Elementor'); ?></p>
                                </div>

                                <?php foreach ($capability_groups as $group_label => $caps) : ?>
                                    <details class="evt-staff-capability-group" <?php echo 'Event Tickets' === $group_label ? 'open' : ''; ?>>
                                        <summary class="evt-staff-capability-group__summary">
                                            <span><?php echo esc_html($group_label); ?></span>
                                        </summary>
                                        <div class="evt-staff-capability-group__body">
                                            <table class="widefat striped evt-staff-capability-table">
                                                <thead>
                                                    <tr>
                                                        <th style="width:36px;"></th>
                                                        <th><?php esc_html_e('Capability', 'Event-Tickets-for-Elementor'); ?></th>
                                                        <th><?php esc_html_e('Description', 'Event-Tickets-for-Elementor'); ?></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($caps as $cap => $config) : ?>
                                                        <?php $field_name = \EventTicketsElementor\Settings_Store::OPTION_KEY . '[' . $config['setting_key'] . ']'; ?>
                                                        <tr>
                                                            <td>
                                                                <input type="hidden" name="<?php echo esc_attr($field_name); ?>" value="0" />
                                                                <input type="checkbox" id="evt-staff-cap-<?php echo esc_attr($config['setting_key']); ?>" name="<?php echo esc_attr($field_name); ?>" value="1" <?php checked(! empty($config['checked'])); ?> />
                                                            </td>
                                                            <td>
                                                                <label for="evt-staff-cap-<?php echo esc_attr($config['setting_key']); ?>">
                                                                    <code><?php echo esc_html($cap); ?></code>
                                                                </label>
                                                            </td>
                                                            <td><?php echo esc_html((string) $config['description']); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </details>
                                <?php endforeach; ?>

                                <div class="evt-actions">
                                    <?php submit_button(__('Save Staff Access', 'Event-Tickets-for-Elementor'), 'primary', 'submit', false); ?>
                                    <button
                                        type="button"
                                        class="button button-secondary evt-staff-restore-defaults"
                                        data-defaults="<?php echo esc_attr(wp_json_encode($this->manager->default_settings())); ?>"
                                    >
                                        <?php esc_html_e('Restore Staff Defaults', 'Event-Tickets-for-Elementor'); ?>
                                    </button>
                                </div>
                            </form>
                        </div>

                        <div class="evt-card evt-staff-card" id="evt-staff-users">
                            <div class="evt-card__header">
                                <h3><?php esc_html_e('Staff Users', 'Event-Tickets-for-Elementor'); ?></h3>
                            </div>
                            <div class="evt-staff-users-stack">
                                <div class="evt-staff-users-panel">
                                    <div class="evt-card__header evt-card__header--minor">
                                        <h4><?php esc_html_e('Assign Existing User', 'Event-Tickets-for-Elementor'); ?></h4>
                                    </div>
                                    <?php if (empty($assignable_users)) : ?>
                                        <p><?php esc_html_e('All users are already assigned to Staff or no assignable users were found.', 'Event-Tickets-for-Elementor'); ?></p>
                                    <?php else : ?>
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="evt-staff-form">
                                            <?php wp_nonce_field('evt_tickets_staff_assign', 'evt_tickets_staff_nonce'); ?>
                                            <input type="hidden" name="action" value="evt_tickets_staff_assign" />
                                            <label for="evt-staff-user-id"><strong><?php esc_html_e('User', 'Event-Tickets-for-Elementor'); ?></strong></label>
                                            <select id="evt-staff-user-id" name="user_id">
                                                <?php foreach ($assignable_users as $user) : ?>
                                                    <option value="<?php echo esc_attr((string) $user->ID); ?>">
                                                        <?php echo esc_html($user->display_name . ' (' . $user->user_email . ')'); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php submit_button(__('Assign Staff Role', 'Event-Tickets-for-Elementor'), 'primary', 'submit', false); ?>
                                        </form>
                                    <?php endif; ?>
                                </div>

                                <div class="evt-staff-users-panel">
                                    <div class="evt-card__header evt-card__header--minor">
                                        <h4><?php esc_html_e('Current Staff Users', 'Event-Tickets-for-Elementor'); ?></h4>
                                    </div>
                                    <?php if (empty($staff_users)) : ?>
                                        <p><?php esc_html_e('No users are currently assigned to the Staff role.', 'Event-Tickets-for-Elementor'); ?></p>
                                    <?php else : ?>
                                        <table class="widefat striped evt-staff-table">
                                            <thead>
                                                <tr>
                                                    <th><?php esc_html_e('User', 'Event-Tickets-for-Elementor'); ?></th>
                                                    <th><?php esc_html_e('Email', 'Event-Tickets-for-Elementor'); ?></th>
                                                    <th><?php esc_html_e('Other roles', 'Event-Tickets-for-Elementor'); ?></th>
                                                    <th><?php esc_html_e('Actions', 'Event-Tickets-for-Elementor'); ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($staff_users as $user) : ?>
                                                    <?php
                                                    $other_roles = array_values(array_filter(
                                                        (array) $user->roles,
                                                        static function (string $role): bool {
                                                            return Staff_Access_Manager::ROLE !== $role;
                                                        }
                                                    ));
                                                    ?>
                                                    <tr>
                                                        <td><strong><?php echo esc_html($user->display_name); ?></strong></td>
                                                        <td><?php echo esc_html($user->user_email); ?></td>
                                                        <td>
                                                            <?php
                                                            if (empty($other_roles)) {
                                                                esc_html_e('None', 'Event-Tickets-for-Elementor');
                                                            } else {
                                                                echo esc_html(implode(', ', $other_roles));
                                                            }
                                                            ?>
                                                        </td>
                                                        <td class="evt-staff-table__actions">
                                                            <a class="button button-secondary" href="<?php echo esc_url(admin_url('user-edit.php?user_id=' . (int) $user->ID)); ?>">
                                                                <?php esc_html_e('Edit User', 'Event-Tickets-for-Elementor'); ?>
                                                            </a>
                                                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                                                <?php wp_nonce_field('evt_tickets_staff_remove_' . $user->ID, 'evt_tickets_staff_nonce'); ?>
                                                                <input type="hidden" name="action" value="evt_tickets_staff_remove" />
                                                                <input type="hidden" name="user_id" value="<?php echo esc_attr((string) $user->ID); ?>" />
                                                                <?php submit_button(__('Remove Staff Role', 'Event-Tickets-for-Elementor'), 'secondary', 'submit', false); ?>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
        <?php
    }

    public function handle_assign(): void
    {
        if (! current_user_can(Staff_Access_Manager::CAP_MANAGE_PLUGIN)) {
            wp_die(esc_html__('Unauthorized', 'Event-Tickets-for-Elementor'), '', ['response' => 403]);
        }

        check_admin_referer('evt_tickets_staff_assign', 'evt_tickets_staff_nonce');

        $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
        $user    = $user_id ? get_user_by('id', $user_id) : false;

        if (! $user) {
            $this->redirect_with_notice('error', __('The selected user could not be found.', 'Event-Tickets-for-Elementor'));
        }

        $user->add_role(Staff_Access_Manager::ROLE);

        $this->redirect_with_notice('success', __('Staff role assigned successfully.', 'Event-Tickets-for-Elementor'));
    }

    public function handle_caps_save(): void
    {
        if (! current_user_can(Staff_Access_Manager::CAP_MANAGE_PLUGIN)) {
            wp_die(esc_html__('Unauthorized', 'Event-Tickets-for-Elementor'), '', ['response' => 403]);
        }

        check_admin_referer('evt_tickets_staff_caps_save', 'evt_tickets_staff_nonce');

        $input = isset($_POST[\EventTicketsElementor\Settings_Store::OPTION_KEY]) && is_array($_POST[\EventTicketsElementor\Settings_Store::OPTION_KEY])
            ? wp_unslash($_POST[\EventTicketsElementor\Settings_Store::OPTION_KEY])
            : [];

        $existing = get_option(\EventTicketsElementor\Settings_Store::OPTION_KEY, []);
        if (! is_array($existing)) {
            $existing = [];
        }

        foreach ($this->manager->default_settings() as $setting_key => $default_value) {
            $existing[$setting_key] = empty($input[$setting_key]) ? 0 : 1;
        }

        $existing[Staff_Access_Manager::SETTING_CHECKIN_PAGE_URL] = isset($input[Staff_Access_Manager::SETTING_CHECKIN_PAGE_URL])
            ? Staff_Access_Manager::normalize_checkin_page_url((string) $input[Staff_Access_Manager::SETTING_CHECKIN_PAGE_URL])
            : '';

        update_option(\EventTicketsElementor\Settings_Store::OPTION_KEY, $existing, false);

        $this->redirect_with_notice('success', __('Staff access updated successfully.', 'Event-Tickets-for-Elementor'));
    }

    public function handle_remove(): void
    {
        if (! current_user_can(Staff_Access_Manager::CAP_MANAGE_PLUGIN)) {
            wp_die(esc_html__('Unauthorized', 'Event-Tickets-for-Elementor'), '', ['response' => 403]);
        }

        $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
        check_admin_referer('evt_tickets_staff_remove_' . $user_id, 'evt_tickets_staff_nonce');

        $user = $user_id ? get_user_by('id', $user_id) : false;
        if (! $user) {
            $this->redirect_with_notice('error', __('The selected user could not be found.', 'Event-Tickets-for-Elementor'));
        }

        $user->remove_role(Staff_Access_Manager::ROLE);

        if (empty($user->roles)) {
            $default_role = (string) get_option('default_role', 'subscriber');
            if ($default_role && get_role($default_role)) {
                $user->set_role($default_role);
            }
        }

        $this->redirect_with_notice('success', __('Staff role removed successfully.', 'Event-Tickets-for-Elementor'));
    }

    /**
     * @return array{type:string,message:string}|null
     */
    private function current_notice(): ?array
    {
        $type    = isset($_GET['evt_staff_notice']) ? sanitize_key((string) $_GET['evt_staff_notice']) : '';
        $message = isset($_GET['evt_staff_message']) ? sanitize_text_field(wp_unslash((string) $_GET['evt_staff_message'])) : '';

        if (! $type || ! $message) {
            return null;
        }

        return [
            'type'    => in_array($type, ['success', 'error', 'warning'], true) ? $type : 'success',
            'message' => $message,
        ];
    }

    private function redirect_with_notice(string $type, string $message): void
    {
        wp_safe_redirect(
            add_query_arg(
                [
                    'page'              => 'evt-tickets-staff',
                    'evt_staff_notice'  => $type,
                    'evt_staff_message' => $message,
                ],
                admin_url('admin.php')
            )
        );
        exit;
    }
}
