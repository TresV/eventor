<?php
if (! defined('ABSPATH')) {
    exit;
}
// View variables ($total_events, $ticket, …) are injected by the renderer via
// a controlled data array; they are not global state.
// phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

/**
 * Dashboard admin view.
 *
 * Variables provided by Dashboard_Page::render():
 * @var int   $total_events
 * @var int   $total_tickets
 * @var int   $checked_in_tickets
 * @var int   $pending_tickets
 * @var int   $cancelled_tickets
 * @var array $recent_events
 * @var array $recent_tickets
 * @var string $settings_page_url
 * @var string $events_list_url
 * @var string $tickets_list_url
 */
?>
<div class="wrap evt-dashboard evt-admin-page evt-admin-page--dashboard">
    <div class="evt-admin-page__hero">
        <div>
            <h1><?php esc_html_e('Event Tickets Dashboard', 'Event-Tickets-for-Elementor'); ?></h1>
            <p class="description">
                <?php esc_html_e('Overview of your events and tickets, plus quick access to common actions.', 'Event-Tickets-for-Elementor'); ?>
            </p>
        </div>
    </div>

    <div class="evt-admin-layout evt-admin-layout--single">
        <section class="evt-admin-content evt-card">
            <div class="evt-admin-content__head">
                <div>
                    <h2><?php esc_html_e('Snapshot', 'Event-Tickets-for-Elementor'); ?></h2>
                    <p class="evt-docs__lead"><?php esc_html_e('Use this overview to move quickly between events, tickets, and setup tasks.', 'Event-Tickets-for-Elementor'); ?></p>
                </div>
                <span class="evt-docs__badge"><?php esc_html_e('Overview', 'Event-Tickets-for-Elementor'); ?></span>
            </div>
            <div class="evt-admin-content__body">
                <div class="evt-grid">
                    <div class="evt-card">
                        <div class="evt-card__header">
                            <h2><?php esc_html_e('Events', 'Event-Tickets-for-Elementor'); ?></h2>
                        </div>
                        <p class="evt-card__stat"><?php echo esc_html($total_events); ?></p>
                        <p class="description"><?php esc_html_e('Published events', 'Event-Tickets-for-Elementor'); ?></p>
                        <div class="evt-actions">
                            <a class="button button-primary" href="<?php echo esc_url(admin_url('post-new.php?post_type=evt_event')); ?>">
                                <?php esc_html_e('Add New Event', 'Event-Tickets-for-Elementor'); ?>
                            </a>
                            <a class="button" href="<?php echo esc_url($events_list_url); ?>">
                                <?php esc_html_e('View All Events', 'Event-Tickets-for-Elementor'); ?>
                            </a>
                        </div>
                    </div>

                    <div class="evt-card">
                        <div class="evt-card__header">
                            <h2><?php esc_html_e('Tickets', 'Event-Tickets-for-Elementor'); ?></h2>
                        </div>
                        <p class="evt-card__stat"><?php echo esc_html($total_tickets); ?></p>
                        <p class="description"><?php esc_html_e('Total tickets (published)', 'Event-Tickets-for-Elementor'); ?></p>

                        <ul class="evt-list">
                            <li>
                                /* translators: %d is the number of checked-in tickets. */
                                <?php printf(esc_html__('Checked in: %d', 'Event-Tickets-for-Elementor'), (int) $checked_in_tickets); ?>
                            </li>
                            <li>
                                /* translators: %d is the number of pending tickets. */
                                <?php printf(esc_html__('Pending: %d', 'Event-Tickets-for-Elementor'), (int) $pending_tickets); ?>
                            </li>
                            <li>
                                /* translators: %d is the number of cancelled tickets. */
                                <?php printf(esc_html__('Cancelled: %d', 'Event-Tickets-for-Elementor'), (int) $cancelled_tickets); ?>
                            </li>
                        </ul>

                        <div class="evt-actions">
                            <a class="button" href="<?php echo esc_url($tickets_list_url); ?>">
                                <?php esc_html_e('View Tickets', 'Event-Tickets-for-Elementor'); ?>
                            </a>
                        </div>
                    </div>

                    <div class="evt-card">
                        <div class="evt-card__header">
                            <h2><?php esc_html_e('Quick Links', 'Event-Tickets-for-Elementor'); ?></h2>
                        </div>
                        <ul class="evt-list">
                            <li>
                                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=evt_event')); ?>">
                                    <?php esc_html_e('Create a new event', 'Event-Tickets-for-Elementor'); ?>
                                </a>
                            </li>
                            <li>
                                <a href="<?php echo esc_url($events_list_url); ?>">
                                    <?php esc_html_e('Manage events', 'Event-Tickets-for-Elementor'); ?>
                                </a>
                            </li>
                            <li>
                                <a href="<?php echo esc_url($tickets_list_url); ?>">
                                    <?php esc_html_e('Review tickets', 'Event-Tickets-for-Elementor'); ?>
                                </a>
                            </li>
                            <li>
                                <a href="<?php echo esc_url($settings_page_url); ?>">
                                    <?php esc_html_e('Configure settings', 'Event-Tickets-for-Elementor'); ?>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="evt-grid evt-grid--wide">
                    <div class="evt-card">
                        <div class="evt-card__header">
                            <h2><?php esc_html_e('Recent Events', 'Event-Tickets-for-Elementor'); ?></h2>
                        </div>
                        <?php if (! empty($recent_events)) : ?>
                            <ul class="evt-list">
                                <?php foreach ($recent_events as $event) : ?>
                                    <li>
                                        <a href="<?php echo esc_url(get_edit_post_link($event->ID)); ?>">
                                            <?php echo esc_html($event->post_title); ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else : ?>
                            <p class="description">
                                <?php esc_html_e('No events yet. Create your first event to get started.', 'Event-Tickets-for-Elementor'); ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="evt-card">
                        <div class="evt-card__header">
                            <h2><?php esc_html_e('Recent Tickets', 'Event-Tickets-for-Elementor'); ?></h2>
                        </div>
                        <?php if (! empty($recent_tickets)) : ?>
                            <ul class="evt-list">
                                <?php foreach ($recent_tickets as $ticket) : ?>
                                    <?php $code = get_post_meta($ticket->ID, '_ticket_code', true); ?>
                                    <li>
                                        <a href="<?php echo esc_url(get_edit_post_link($ticket->ID)); ?>">
                                            <?php echo esc_html(get_the_title($ticket)); ?>
                                        </a>
                                        <?php if ($code) : ?>
                                            <span class="evt-meta">&mdash; <?php echo esc_html($code); ?></span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else : ?>
                            <p class="description">
                                <?php esc_html_e('No tickets yet. Connect an Elementor form or use the event widgets to start issuing tickets.', 'Event-Tickets-for-Elementor'); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>