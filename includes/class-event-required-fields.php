<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Prevent publishing events with missing required details.
 */
class Event_Required_Fields
{
    private const NOTICE_TTL = 120;

    public function __construct()
    {
        add_filter('wp_insert_post_data', [$this, 'maybe_block_publish'], 10, 2);
        add_action('admin_notices', [$this, 'render_admin_notice']);
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $postarr
     * @return array<string,mixed>
     */
    public function maybe_block_publish(array $data, array $postarr): array
    {
        if (($data['post_type'] ?? '') !== CPT_Events::POST_TYPE) {
            return $data;
        }

        $status = (string) ($data['post_status'] ?? '');
        if (! in_array($status, ['publish', 'future', 'private'], true)) {
            return $data;
        }

        // Only enforce for normal admin edits where our metabox nonce is present.
        if (! isset($_POST['evt_event_meta_nonce']) || ! wp_verify_nonce(wp_unslash($_POST['evt_event_meta_nonce']), 'evt_save_event_meta')) {
            return $data;
        }

        $missing = $this->missing_required_fields_from_request();
        if (empty($missing)) {
            return $data;
        }

        $post_id = isset($postarr['ID']) ? (int) $postarr['ID'] : 0;
        $user_id = get_current_user_id();
        if ($post_id && $user_id) {
            set_transient($this->notice_key($user_id, $post_id), $missing, self::NOTICE_TTL);
        }

        $data['post_status'] = 'draft';
        return $data;
    }

    public function render_admin_notice(): void
    {
        if (! is_admin()) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (! $screen || 'post' !== ($screen->base ?? '') || CPT_Events::POST_TYPE !== ($screen->post_type ?? '')) {
            return;
        }

        $post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
        if (! $post_id) {
            return;
        }

        $user_id = get_current_user_id();
        if (! $user_id) {
            return;
        }

        $key = $this->notice_key($user_id, $post_id);
        $missing = get_transient($key);
        if (! is_array($missing) || empty($missing)) {
            return;
        }

        delete_transient($key);

        $labels = [];
        foreach ($missing as $field_key) {
            switch ($field_key) {
                case 'start':
                    $labels[] = __('Start date & time', 'Event-Tickets-for-Elementor');
                    break;
                case 'end':
                    $labels[] = __('End date & time', 'Event-Tickets-for-Elementor');
                    break;
                case 'location':
                    $labels[] = __('Location', 'Event-Tickets-for-Elementor');
                    break;
            }
        }

        $labels = array_values(array_unique(array_filter($labels)));
        $fields_str = $labels ? implode(', ', $labels) : __('event details', 'Event-Tickets-for-Elementor');

        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            /* translators: %s is the list of missing required fields. */
            esc_html(sprintf(__('Event was saved as draft. Please fill in required fields: %s.', 'Event-Tickets-for-Elementor'), $fields_str))
        );
    }

    /**
     * @return array<int,string>
     */
    private function missing_required_fields_from_request(): array
    {
        $start = isset($_POST['evt_event_start']) ? trim(sanitize_text_field(wp_unslash($_POST['evt_event_start']))) : '';
        $end = isset($_POST['evt_event_end']) ? trim(sanitize_text_field(wp_unslash($_POST['evt_event_end']))) : '';
        $location = isset($_POST['evt_event_location']) ? trim((string) wp_unslash($_POST['evt_event_location'])) : '';

        $missing = [];
        if ('' === $start) {
            $missing[] = 'start';
        }
        if ('' === $end) {
            $missing[] = 'end';
        }
        if ('' === $location) {
            $missing[] = 'location';
        }

        return $missing;
    }

    private function notice_key(int $user_id, int $post_id): string
    {
        return 'evt_event_required_' . $user_id . '_' . $post_id;
    }
}

