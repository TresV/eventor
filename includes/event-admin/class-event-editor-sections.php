<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Renders the self-contained content sections inside the Event editor workspace.
 */
class Event_Editor_Sections
{
    private Event_Ticket_Stats $stats;

    public function __construct(Event_Ticket_Stats $stats)
    {
        $this->stats = $stats;
    }

    public function render_basics_section(\WP_Post $post): void
    {
        $status = $this->get_status_snapshot($post->ID);
        $featured_image = has_post_thumbnail($post);
        $editor_mode = $this->is_block_editor_active() ? __('Gutenberg', 'Event-Tickets-for-Elementor') : __('Classic Editor', 'Event-Tickets-for-Elementor');
        $taxonomy_groups = $this->get_taxonomy_summary($post->ID);
?>
        <div class="evt-event-overview">
            <div class="evt-event-overview__grid">
                <div class="evt-event-overview__item">
                    <span class="evt-event-overview__label"><?php esc_html_e('Editor Mode', 'Event-Tickets-for-Elementor'); ?></span>
                    <strong><?php echo esc_html($editor_mode); ?></strong>
                </div>
                <div class="evt-event-overview__item">
                    <span class="evt-event-overview__label"><?php esc_html_e('Current Status', 'Event-Tickets-for-Elementor'); ?></span>
                    <strong><?php echo esc_html($status); ?></strong>
                </div>
                <div class="evt-event-overview__item">
                    <span class="evt-event-overview__label"><?php esc_html_e('Featured Image', 'Event-Tickets-for-Elementor'); ?></span>
                    <strong><?php echo esc_html($featured_image ? __('Assigned', 'Event-Tickets-for-Elementor') : __('Not assigned', 'Event-Tickets-for-Elementor')); ?></strong>
                </div>
                <div class="evt-event-overview__item">
                    <span class="evt-event-overview__label"><?php esc_html_e('Event Content', 'Event-Tickets-for-Elementor'); ?></span>
                    <strong><?php echo esc_html('' !== trim((string) $post->post_content) ? __('Description added', 'Event-Tickets-for-Elementor') : __('Description is empty', 'Event-Tickets-for-Elementor')); ?></strong>
                </div>
            </div>

            <div class="evt-event-inline-note">
                <?php
                if ($this->is_block_editor_active()) {
                    esc_html_e('Use the Gutenberg document sidebar for taxonomy editing. This summary keeps your current assignments visible while you work.', 'Event-Tickets-for-Elementor');
                } else {
                    esc_html_e('Use the native side panels for Publish, Featured Image, and taxonomy editing. This summary keeps the current assignments visible in the main workspace.', 'Event-Tickets-for-Elementor');
                }
                ?>
            </div>
        </div>

        <div class="evt-event-taxonomy-summary">
            <?php foreach ($taxonomy_groups as $group) : ?>
                <div class="evt-event-taxonomy-summary__card">
                    <h4><?php echo esc_html($group['label']); ?></h4>
                    <?php if (! empty($group['terms'])) : ?>
                        <div class="evt-event-taxonomy-summary__chips">
                            <?php foreach ($group['terms'] as $term_name) : ?>
                                <span class="evt-event-taxonomy-summary__chip"><?php echo esc_html($term_name); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?>
                        <p class="description"><?php echo esc_html($group['empty']); ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php
        do_action('evt_tickets_event_section_basics', $post);
    }

    public function render_related_section(\WP_Post $post): void
    {
        $counts = $this->stats->get_ticket_counts($post->ID);
        $tickets_url = add_query_arg(
            [
                'post_type'    => CPT_Tickets::POST_TYPE,
                'evt_event_id' => $post->ID,
            ],
            admin_url('edit.php')
        );
        $export_url = wp_nonce_url(
            add_query_arg(
                [
                    'action'   => 'evt_export_tickets',
                    'event_id' => $post->ID,
                ],
                admin_url('admin-post.php')
            ),
            'evt_export_tickets_' . $post->ID
        );
    ?>
        <div class="evt-event-overview__grid">
            <div class="evt-event-overview__item">
                <span class="evt-event-overview__label"><?php esc_html_e('Total Tickets', 'Event-Tickets-for-Elementor'); ?></span>
                <strong><?php echo esc_html((string) $counts['total']); ?></strong>
            </div>
            <div class="evt-event-overview__item">
                <span class="evt-event-overview__label"><?php esc_html_e('Pending', 'Event-Tickets-for-Elementor'); ?></span>
                <strong><?php echo esc_html((string) $counts['pending']); ?></strong>
            </div>
            <div class="evt-event-overview__item">
                <span class="evt-event-overview__label"><?php esc_html_e('Checked In', 'Event-Tickets-for-Elementor'); ?></span>
                <strong><?php echo esc_html((string) $counts['checked_in']); ?></strong>
            </div>
            <div class="evt-event-overview__item">
                <span class="evt-event-overview__label"><?php esc_html_e('Cancelled', 'Event-Tickets-for-Elementor'); ?></span>
                <strong><?php echo esc_html((string) $counts['cancelled']); ?></strong>
            </div>
        </div>
        <div class="evt-actions">
            <a class="button" href="<?php echo esc_url($tickets_url); ?>"><?php esc_html_e('View tickets for this event', 'Event-Tickets-for-Elementor'); ?></a>
            <a class="button" href="<?php echo esc_url($export_url); ?>"><?php esc_html_e('Download CSV', 'Event-Tickets-for-Elementor'); ?></a>
        </div>
        <?php if (0 === (int) $counts['total']) : ?>
            <p class="description"><?php esc_html_e('No tickets have been issued for this event yet.', 'Event-Tickets-for-Elementor'); ?></p>
        <?php endif; ?>
<?php
        do_action('evt_tickets_event_section_related', $post);
    }

    /**
     * @return array<int,array{label:string,terms:array<int,string>,empty:string}>
     */
    private function get_taxonomy_summary(int $post_id): array
    {
        $map = [
            Event_Taxonomies::TAX_CATEGORY => [
                'label' => __('Event Categories', 'Event-Tickets-for-Elementor'),
                'empty' => __('No event categories assigned yet.', 'Event-Tickets-for-Elementor'),
            ],
            Event_Taxonomies::TAX_TAG => [
                'label' => __('Event Tags', 'Event-Tickets-for-Elementor'),
                'empty' => __('No event tags assigned yet.', 'Event-Tickets-for-Elementor'),
            ],
            Event_Taxonomies::TAX_VENUE => [
                'label' => __('Venues', 'Event-Tickets-for-Elementor'),
                'empty' => __('No venues assigned yet.', 'Event-Tickets-for-Elementor'),
            ],
            Event_Taxonomies::TAX_ORGANIZER => [
                'label' => __('Organizers', 'Event-Tickets-for-Elementor'),
                'empty' => __('No organizers assigned yet.', 'Event-Tickets-for-Elementor'),
            ],
        ];

        $summary = [];
        foreach ($map as $taxonomy => $config) {
            $terms = get_the_terms($post_id, $taxonomy);
            $summary[] = [
                'label' => $config['label'],
                'empty' => $config['empty'],
                'terms' => is_array($terms) ? array_values(array_map(static function ($term) {
                    return (string) $term->name;
                }, $terms)) : [],
            ];
        }

        return $summary;
    }

    private function get_status_snapshot(int $post_id): string
    {
        if ((bool) get_post_meta($post_id, Event_Status::META_CANCELLED, true)) {
            return __('Cancelled', 'Event-Tickets-for-Elementor');
        }

        if ((bool) get_post_meta($post_id, Event_Status::META_POSTPONED, true)) {
            return __('Postponed', 'Event-Tickets-for-Elementor');
        }

        $end_ts = (int) get_post_meta($post_id, Event_Meta_Timestamps::END_TS_META, true);
        if ($end_ts > 0 && $end_ts < current_time('timestamp')) {
            return __('Over', 'Event-Tickets-for-Elementor');
        }

        return __('Scheduled', 'Event-Tickets-for-Elementor');
    }

    private function is_block_editor_active(): bool
    {
        if (function_exists('get_current_screen')) {
            $screen = get_current_screen();
            if ($screen) {
                if (method_exists($screen, 'is_block_editor')) {
                    return (bool) $screen->is_block_editor();
                }
                if (isset($screen->is_block_editor)) {
                    return (bool) $screen->is_block_editor;
                }
            }
        }

        return function_exists('use_block_editor_for_post_type') && use_block_editor_for_post_type(CPT_Events::POST_TYPE);
    }
}
