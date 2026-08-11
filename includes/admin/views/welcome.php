<?php
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Activation welcome view.
 *
 * @var string $get_started_url
 * @var string $dashboard_url
 * @var string $settings_url
 */
?>
<div class="wrap evt-welcome">
    <div class="evt-welcome__card">
        <h1><?php esc_html_e('Welcome to Event Tickets for Elementor', 'Event-Tickets-for-Elementor'); ?></h1>
        <p class="description">
            <?php esc_html_e('The plugin is active. Launch your first event flow in a few minutes with the guided setup.', 'Event-Tickets-for-Elementor'); ?>
        </p>

        <ul class="evt-welcome__highlights">
            <li><?php esc_html_e('Create events with capacity, status, location, and timeslots', 'Event-Tickets-for-Elementor'); ?></li>
            <li><?php esc_html_e('Use Events Browser for a complete discovery experience', 'Event-Tickets-for-Elementor'); ?></li>
            <li><?php esc_html_e('Issue tickets with PDF, QR, and calendar files', 'Event-Tickets-for-Elementor'); ?></li>
            <li><?php esc_html_e('Run staff check-in with ticket validation', 'Event-Tickets-for-Elementor'); ?></li>
        </ul>

        <div class="evt-welcome__primary">
            <a class="button button-primary button-hero" href="<?php echo esc_url($get_started_url); ?>">
                <?php esc_html_e('Start Setup Journey', 'Event-Tickets-for-Elementor'); ?>
            </a>
        </div>
        <div class="evt-welcome__secondary">
            <a class="button" href="<?php echo esc_url($dashboard_url); ?>">
                <?php esc_html_e('Go to Dashboard', 'Event-Tickets-for-Elementor'); ?>
            </a>
            <a class="button" href="<?php echo esc_url($settings_url); ?>">
                <?php esc_html_e('Open Settings', 'Event-Tickets-for-Elementor'); ?>
            </a>
        </div>
    </div>
</div>
