<?php

namespace EventTicketsElementor\Admin;

use EventTicketsElementor\CPT_Events;
use EventTicketsElementor\CPT_Tickets;
use EventTicketsElementor\Event_Meta_Timestamps;
use EventTicketsElementor\Qr_Service;
use EventTicketsElementor\Staff_Access_Manager;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Lightweight admin repair tools.
 */
class Repair_Tools_Page
{
    private const PAGE_SLUG = 'evt-tickets-tools';

    public function __construct()
    {
        add_action('admin_post_evt_tickets_tools', [$this, 'handle_actions']);
    }

    public function render_page(): void
    {
        if (! current_user_can(Staff_Access_Manager::CAP_MANAGE_PLUGIN)) {
            return;
        }

        $action_url = admin_url('admin-post.php');
?>
        <div class="wrap evt-dashboard evt-admin-page evt-admin-page--tools">
            <div class="evt-admin-page__hero">
                <div>
                    <h1><?php esc_html_e('Event Tickets Tools', 'Event-Tickets-for-Elementor'); ?></h1>
                    <p class="description"><?php esc_html_e('Repair timestamps, clear caches, and verify cross-record integrity using the same admin workspace style as the documentation page.', 'Event-Tickets-for-Elementor'); ?></p>
                </div>
            </div>

            <?php if (isset($_GET['evt_tools_done'])) : ?>
                <div class="notice notice-success inline">
                    <p><?php echo esc_html(wp_unslash($_GET['evt_tools_done'])); ?></p>
                </div>
            <?php endif; ?>

            <div class="evt-admin-layout evt-admin-layout--single">
                <section class="evt-admin-content evt-card">
                    <div class="evt-admin-content__head">
                        <div>
                            <h2><?php esc_html_e('Repair Tools', 'Event-Tickets-for-Elementor'); ?></h2>
                            <p class="evt-docs__lead"><?php esc_html_e('Each tool runs immediately and reports the result back to this screen.', 'Event-Tickets-for-Elementor'); ?></p>
                        </div>
                        <span class="evt-docs__badge"><?php esc_html_e('Operational', 'Event-Tickets-for-Elementor'); ?></span>
                    </div>
                    <div class="evt-admin-content__body">
                        <form method="post" action="<?php echo esc_url($action_url); ?>">
                            <?php wp_nonce_field('evt_tickets_tools'); ?>
                            <input type="hidden" name="action" value="evt_tickets_tools" />

                            <p class="evt-tools__row">
                                <button class="button" name="evt_tools_task" value="rebuild_timestamps">
                                    <?php esc_html_e('Rebuild event timestamps', 'Event-Tickets-for-Elementor'); ?>
                                </button>
                                <span class="description"><?php esc_html_e('Recomputes timestamp meta used for event date queries.', 'Event-Tickets-for-Elementor'); ?></span>
                            </p>

                            <p class="evt-tools__row">
                                <button class="button" name="evt_tools_task" value="clear_capacity_cache">
                                    <?php esc_html_e('Clear capacity cache', 'Event-Tickets-for-Elementor'); ?>
                                </button>
                                <span class="description"><?php esc_html_e('Clears per-event capacity transients.', 'Event-Tickets-for-Elementor'); ?></span>
                            </p>

                            <p class="evt-tools__row">
                                <button class="button" name="evt_tools_task" value="clear_qr_cache">
                                    <?php esc_html_e('Clear QR cache', 'Event-Tickets-for-Elementor'); ?>
                                </button>
                                <span class="description"><?php esc_html_e('Deletes cached ticket QR PNG files from uploads.', 'Event-Tickets-for-Elementor'); ?></span>
                            </p>

                            <p class="evt-tools__row">
                                <button class="button" name="evt_tools_task" value="verify_links">
                                    <?php esc_html_e('Verify ticket → event links', 'Event-Tickets-for-Elementor'); ?>
                                </button>
                                <span class="description"><?php esc_html_e('Finds tickets referencing missing events.', 'Event-Tickets-for-Elementor'); ?></span>
                            </p>
                        </form>
                    </div>
                </section>
            </div>
        </div>
<?php
    }

    public function handle_actions(): void
    {
        if (! current_user_can(Staff_Access_Manager::CAP_MANAGE_PLUGIN)) {
            wp_die(esc_html__('You do not have permission to access this page.', 'Event-Tickets-for-Elementor'));
        }

        check_admin_referer('evt_tickets_tools');

        $task = isset($_POST['evt_tools_task']) ? sanitize_text_field(wp_unslash($_POST['evt_tools_task'])) : '';
        $message = '';

        switch ($task) {
            case 'rebuild_timestamps':
                $count = $this->rebuild_event_timestamps();
                $message = sprintf(
                    /* translators: %d is count */
                    __('Rebuilt timestamps for %d events.', 'Event-Tickets-for-Elementor'),
                    $count
                );
                break;
            case 'clear_capacity_cache':
                $count = $this->clear_capacity_cache();
                $message = sprintf(
                    /* translators: %d is the number of events. */
                    __('Cleared capacity cache for %d events.', 'Event-Tickets-for-Elementor'),
                    $count
                );
                break;
            case 'clear_qr_cache':
                $count = $this->clear_qr_cache();
                $message = sprintf(
                    /* translators: %d is the number of files deleted. */
                    __('Deleted %d QR files.', 'Event-Tickets-for-Elementor'),
                    $count
                );
                break;
            case 'verify_links':
                $broken = $this->count_broken_ticket_links();
                $message = sprintf(
                    /* translators: %d is the number of tickets. */
                    __('Found %d tickets linked to missing events.', 'Event-Tickets-for-Elementor'),
                    $broken
                );
                break;
            default:
                $message = __('No task selected.', 'Event-Tickets-for-Elementor');
                break;
        }

        wp_safe_redirect(
            add_query_arg(
                [
                    'page'          => self::PAGE_SLUG,
                    'evt_tools_done' => rawurlencode($message),
                ],
                admin_url('admin.php')
            )
        );
        exit;
    }

    private function rebuild_event_timestamps(): int
    {
        $ids = get_posts(
            [
                'post_type'      => CPT_Events::POST_TYPE,
                'post_status'    => 'publish',
                'numberposts'    => -1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
            ]
        );

        if (empty($ids)) {
            return 0;
        }

        $count = 0;
        foreach ($ids as $event_id) {
            $event_id = (int) $event_id;
            $start = get_post_meta($event_id, '_evt_event_start', true);
            $end   = get_post_meta($event_id, '_evt_event_end', true);

            $start_ts = $start ? strtotime((string) $start) : 0;
            $end_ts   = $end ? strtotime((string) $end) : 0;

            if ($start_ts > 0) {
                update_post_meta($event_id, Event_Meta_Timestamps::START_TS_META, $start_ts);
            } else {
                delete_post_meta($event_id, Event_Meta_Timestamps::START_TS_META);
            }

            if ($end_ts > 0) {
                update_post_meta($event_id, Event_Meta_Timestamps::END_TS_META, $end_ts);
            } else {
                delete_post_meta($event_id, Event_Meta_Timestamps::END_TS_META);
            }

            $count++;
        }

        return $count;
    }

    private function clear_capacity_cache(): int
    {
        $ids = get_posts(
            [
                'post_type'      => CPT_Events::POST_TYPE,
                'post_status'    => 'publish',
                'numberposts'    => -1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
            ]
        );

        if (empty($ids)) {
            return 0;
        }

        foreach ($ids as $event_id) {
            delete_transient('evt_capacity_' . (int) $event_id);
        }

        return count($ids);
    }

    private function clear_qr_cache(): int
    {
        $qr = new Qr_Service();
        $upload = wp_upload_dir();
        if (empty($upload['basedir'])) {
            return 0;
        }

        $dir = trailingslashit($upload['basedir']) . 'evt-tickets/qr';
        if (! is_dir($dir)) {
            return 0;
        }

        $files = glob($dir . '/*.png');
        if (! is_array($files)) {
            return 0;
        }

        $deleted = 0;
        foreach ($files as $file) {
            if (wp_delete_file($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    private function count_broken_ticket_links(): int
    {
        $query = new \WP_Query(
            [
                'post_type'              => CPT_Tickets::POST_TYPE,
                'post_status'            => ['publish', \EventTicketsElementor\Ticket_Statuses::STATUS_CANCELLED],
                'posts_per_page'         => -1,
                'fields'                 => 'ids',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'meta_query'             => [
                    [
                        'key'     => '_ticket_event_id',
                        'compare' => '>',
                        'value'   => 0,
                        'type'    => 'NUMERIC',
                    ],
                ],
            ]
        );

        if (empty($query->posts)) {
            return 0;
        }

        $broken = 0;
        foreach ($query->posts as $ticket_id) {
            $event_id = (int) get_post_meta((int) $ticket_id, '_ticket_event_id', true);
            $event = get_post($event_id);
            if (! $event || CPT_Events::POST_TYPE !== $event->post_type) {
                $broken++;
            }
        }

        return $broken;
    }
}
