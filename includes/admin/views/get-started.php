<?php
if (! defined('ABSPATH')) {
    exit;
}
// View variables ($events_total, $item, …) are injected by the renderer via a
// controlled data array; they are not global state.
// phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

/**
 * Get Started admin view.
 *
 * @var int   $events_total
 * @var int   $tickets_total
 * @var string $create_event_url
 * @var string $events_list_url
 * @var string $tickets_list_url
 * @var string $settings_email_url
 * @var string $settings_email_content_url
 * @var string $settings_email_template_url
 * @var string $settings_email_attachments_url
 * @var string $settings_pdf_url
 * @var string $settings_url
 * @var string $tools_url
 * @var string $create_pages_url
 * @var string $creation_notice
 * @var array<string,array{title:string,id:int,edit_url:string,view_url:string}> $starter_pages
 * @var array<int,array{title:string,done:bool}> $checkpoints
 */
?>
<div class="wrap evt-dashboard evt-get-started evt-admin-page evt-admin-page--get-started">
    <div class="evt-admin-page__hero">
        <div>
            <h1><?php esc_html_e('Get Started with Event Tickets', 'Event-Tickets-for-Elementor'); ?></h1>
            <p class="description">
                <?php esc_html_e('Follow this journey to launch your events and ticket flow quickly, then expand with advanced options.', 'Event-Tickets-for-Elementor'); ?>
            </p>
        </div>
    </div>
    <?php if (! empty($creation_notice)) : ?>
        <div class="notice notice-success inline">
            <p><?php echo esc_html($creation_notice); ?></p>
        </div>
    <?php endif; ?>

    <div class="evt-admin-layout evt-admin-layout--single">
        <section class="evt-admin-content evt-card">
            <div class="evt-admin-content__head">
                <div>
                    <h2><?php esc_html_e('Launch Journey', 'Event-Tickets-for-Elementor'); ?></h2>
                    <p class="evt-docs__lead"><?php esc_html_e('The fastest route from plugin activation to a live event discovery and ticketing flow.', 'Event-Tickets-for-Elementor'); ?></p>
                </div>
                <span class="evt-docs__badge"><?php esc_html_e('Onboarding', 'Event-Tickets-for-Elementor'); ?></span>
            </div>
            <div class="evt-admin-content__body">
                <div class="evt-get-started__stack">
                    <div class="evt-card">
                        <div class="evt-card__header">
                            <h2><?php esc_html_e('Quick Start (5 minutes)', 'Event-Tickets-for-Elementor'); ?></h2>
                        </div>
                        <ol class="evt-steps">
                            <li><?php esc_html_e('Create an event with start/end date, location, and capacity.', 'Event-Tickets-for-Elementor'); ?></li>
                            <li><?php esc_html_e('Add the Events Browser widget to your public events page.', 'Event-Tickets-for-Elementor'); ?></li>
                            <li><?php esc_html_e('Add Ticket Box widget to your event page or get-ticket page.', 'Event-Tickets-for-Elementor'); ?></li>
                            <li><?php esc_html_e('Send a test registration and confirm email, PDF, and .ics delivery.', 'Event-Tickets-for-Elementor'); ?></li>
                        </ol>
                        <div class="evt-actions">
                            <a class="button button-primary" href="<?php echo esc_url($create_event_url); ?>"><?php esc_html_e('Create First Event', 'Event-Tickets-for-Elementor'); ?></a>
                            <a class="button" href="<?php echo esc_url($events_list_url); ?>"><?php esc_html_e('Manage Events', 'Event-Tickets-for-Elementor'); ?></a>
                        </div>
                    </div>

                    <div class="evt-card">
                        <div class="evt-card__header">
                            <h2><?php esc_html_e('Journey Checkpoints', 'Event-Tickets-for-Elementor'); ?></h2>
                        </div>
                        <ul class="evt-checkpoints">
                            <?php foreach ($checkpoints as $item) : ?>
                                <li class="<?php echo $item['done'] ? 'is-done' : 'is-todo'; ?>">
                                    <span class="evt-checkpoints__state" aria-hidden="true"><?php echo $item['done'] ? '&#10003;' : '&#9675;'; ?></span>
                                    <span><?php echo esc_html($item['title']); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <p class="description">
                            /* translators: 1: number of events. 2: number of tickets. */
                            <?php printf(esc_html__('Current totals: %1$d events, %2$d tickets.', 'Event-Tickets-for-Elementor'), (int) $events_total, (int) $tickets_total); ?>
                        </p>
                    </div>

                    <div class="evt-card">
                        <div class="evt-card__header">
                            <h2><?php esc_html_e('Configure Core Experience', 'Event-Tickets-for-Elementor'); ?></h2>
                        </div>
                        <ul class="evt-list">
                            <li><?php esc_html_e('Email content and labels', 'Event-Tickets-for-Elementor'); ?></li>
                            <li><?php esc_html_e('PDF template defaults', 'Event-Tickets-for-Elementor'); ?></li>
                            <li><?php esc_html_e('Calendar and ticket behavior', 'Event-Tickets-for-Elementor'); ?></li>
                            <li><?php esc_html_e('Repair tools and diagnostics', 'Event-Tickets-for-Elementor'); ?></li>
                        </ul>
                        <div class="evt-actions">
                            <a class="button" href="<?php echo esc_url($settings_email_url); ?>"><?php esc_html_e('Email Settings', 'Event-Tickets-for-Elementor'); ?></a>
                            <a class="button" href="<?php echo esc_url($settings_url); ?>"><?php esc_html_e('All Settings', 'Event-Tickets-for-Elementor'); ?></a>
                            <a class="button" href="<?php echo esc_url($tools_url); ?>"><?php esc_html_e('Open Tools', 'Event-Tickets-for-Elementor'); ?></a>
                        </div>
                    </div>

                    <div class="evt-card">
                        <div class="evt-card__header">
                            <h2><?php esc_html_e('Starter Pages', 'Event-Tickets-for-Elementor'); ?></h2>
                        </div>
                        <p class="description">
                            <?php esc_html_e('Create all recommended pages in one step. Existing pages are reused.', 'Event-Tickets-for-Elementor'); ?>
                        </p>
                        <div class="evt-actions">
                            <a class="button button-primary" href="<?php echo esc_url($create_pages_url); ?>">
                                <?php esc_html_e('Create Starter Pages', 'Event-Tickets-for-Elementor'); ?>
                            </a>
                        </div>
                        <ul class="evt-list">
                            <?php foreach ($starter_pages as $page) : ?>
                                <li>
                                    <strong><?php echo esc_html($page['title']); ?></strong>
                                    <?php if (! empty($page['id'])) : ?>
                                        <span class="evt-meta">#<?php echo esc_html((string) $page['id']); ?></span>
                                        <span class="evt-inline-links">
                                            <a href="<?php echo esc_url($page['edit_url']); ?>"><?php esc_html_e('Edit', 'Event-Tickets-for-Elementor'); ?></a>
                                            |
                                            <a href="<?php echo esc_url($page['view_url']); ?>" target="_blank" rel="noopener"><?php esc_html_e('View', 'Event-Tickets-for-Elementor'); ?></a>
                                        </span>
                                    <?php else : ?>
                                        <span class="evt-meta"><?php esc_html_e('Not created yet', 'Event-Tickets-for-Elementor'); ?></span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <div class="evt-card">
                        <div class="evt-card__header">
                            <h2><?php esc_html_e('Email & PDF Customization Guide', 'Event-Tickets-for-Elementor'); ?></h2>
                        </div>
                        <ol class="evt-steps">
                            <li><?php esc_html_e('Open Email Settings > Content and localize all labels/titles used in outgoing emails.', 'Event-Tickets-for-Elementor'); ?></li>
                            <li><?php esc_html_e('Open Email Settings > Template to choose a preset or enable your custom HTML/CSS template.', 'Event-Tickets-for-Elementor'); ?></li>
                            <li><?php esc_html_e('Open Email Settings > Attachments to control QR and .ics delivery behavior.', 'Event-Tickets-for-Elementor'); ?></li>
                            <li><?php esc_html_e('Open PDF Settings to define branding defaults and email PDF attachment behavior.', 'Event-Tickets-for-Elementor'); ?></li>
                            <li><?php esc_html_e('Send a real test ticket and verify Gmail/Outlook rendering before going live.', 'Event-Tickets-for-Elementor'); ?></li>
                        </ol>
                        <div class="evt-actions">
                            <a class="button" href="<?php echo esc_url($settings_email_content_url); ?>"><?php esc_html_e('Email Content', 'Event-Tickets-for-Elementor'); ?></a>
                            <a class="button" href="<?php echo esc_url($settings_email_template_url); ?>"><?php esc_html_e('Email Template', 'Event-Tickets-for-Elementor'); ?></a>
                            <a class="button" href="<?php echo esc_url($settings_email_attachments_url); ?>"><?php esc_html_e('Email Attachments', 'Event-Tickets-for-Elementor'); ?></a>
                            <a class="button" href="<?php echo esc_url($settings_pdf_url); ?>"><?php esc_html_e('PDF Settings', 'Event-Tickets-for-Elementor'); ?></a>
                        </div>
                    </div>

                    <div class="evt-card">
                        <div class="evt-card__header">
                            <h2><?php esc_html_e('Recommended Page Setup', 'Event-Tickets-for-Elementor'); ?></h2>
                        </div>
                        <ul class="evt-list">
                            <li><strong><?php esc_html_e('Events page:', 'Event-Tickets-for-Elementor'); ?></strong> <?php esc_html_e('Use the Events Browser widget.', 'Event-Tickets-for-Elementor'); ?></li>
                            <li><strong><?php esc_html_e('Get Ticket page:', 'Event-Tickets-for-Elementor'); ?></strong> <?php esc_html_e('2-column layout: event details + Ticket Box.', 'Event-Tickets-for-Elementor'); ?></li>
                            <li><strong><?php esc_html_e('Ticket actions page:', 'Event-Tickets-for-Elementor'); ?></strong> <?php esc_html_e('Use Ticket View, Resend Ticket, and Cancel Ticket.', 'Event-Tickets-for-Elementor'); ?></li>
                            <li><strong><?php esc_html_e('Staff check-in page:', 'Event-Tickets-for-Elementor'); ?></strong> <?php esc_html_e('Use the Check-in widget and allow access only to the built-in Staff role.', 'Event-Tickets-for-Elementor'); ?></li>
                        </ul>
                    </div>

                    <div class="evt-card">
                        <div class="evt-card__header">
                            <h2><?php esc_html_e('Staff Check-in & Ticket Cancellation', 'Event-Tickets-for-Elementor'); ?></h2>
                        </div>
                        <ol class="evt-steps">
                            <li><?php esc_html_e('Create a dedicated Staff Check-in page and add only the Check-in widget.', 'Event-Tickets-for-Elementor'); ?></li>
                            <li><?php esc_html_e('Assign the built-in Staff role to event-day operators and use Staff Access settings to control check-in permission.', 'Event-Tickets-for-Elementor'); ?></li>
                            <li><?php esc_html_e('Create a Ticket Actions page with Ticket View, Resend Ticket, and Cancel Ticket widgets.', 'Event-Tickets-for-Elementor'); ?></li>
                            <li><?php esc_html_e('In Cancel Ticket settings, decide whether refund requests are allowed and tailor the labels.', 'Event-Tickets-for-Elementor'); ?></li>
                            <li><?php esc_html_e('Run a full attendee flow: issue ticket, resend ticket, cancel ticket, and verify status changes in Tickets admin.', 'Event-Tickets-for-Elementor'); ?></li>
                        </ol>
                        <div class="evt-actions">
                            <a class="button" href="<?php echo esc_url($tickets_list_url); ?>"><?php esc_html_e('Review Tickets', 'Event-Tickets-for-Elementor'); ?></a>
                            <?php if (! empty($starter_pages['staff_checkin']['edit_url'])) : ?>
                                <a class="button" href="<?php echo esc_url($starter_pages['staff_checkin']['edit_url']); ?>"><?php esc_html_e('Edit Staff Check-in Page', 'Event-Tickets-for-Elementor'); ?></a>
                            <?php endif; ?>
                            <?php if (! empty($starter_pages['ticket_actions']['edit_url'])) : ?>
                                <a class="button" href="<?php echo esc_url($starter_pages['ticket_actions']['edit_url']); ?>"><?php esc_html_e('Edit Ticket Actions Page', 'Event-Tickets-for-Elementor'); ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>