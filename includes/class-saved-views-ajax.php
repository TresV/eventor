<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Saved views for filters (user-meta backing).
 */
class Saved_Views_Ajax
{
    private const META_KEY = 'evt_tickets_saved_views';

    public function __construct()
    {
        add_action('wp_ajax_evt_saved_views_get', [$this, 'get_views']);
        add_action('wp_ajax_evt_saved_views_set', [$this, 'set_views']);
    }

    public function get_views(): void
    {
        check_ajax_referer('evt_saved_views', 'nonce');

        if (! is_user_logged_in()) {
            \evt_json_error('evt_not_logged_in', __('You must be logged in.', 'Event-Tickets-for-Elementor'), 401);
        }

        $views = get_user_meta(get_current_user_id(), self::META_KEY, true);
        if (! is_array($views)) {
            $views = [];
        }

        \evt_json_success(['views' => $views]);
    }

    public function set_views(): void
    {
        check_ajax_referer('evt_saved_views', 'nonce');

        if (! is_user_logged_in()) {
            \evt_json_error('evt_not_logged_in', __('You must be logged in.', 'Event-Tickets-for-Elementor'), 401);
        }

        $views = isset($_POST['views']) ? wp_unslash($_POST['views']) : null;
        if (is_string($views)) {
            $decoded = json_decode($views, true);
            $views = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($views)) {
            $views = [];
        }

        $views = $this->sanitize_views($views);
        update_user_meta(get_current_user_id(), self::META_KEY, $views);

        \evt_json_success(['views' => $views]);
    }

    /**
     * @param array $views
     * @return array
     */
    private function sanitize_views(array $views): array
    {
        $out = [];
        foreach ($views as $view) {
            if (! is_array($view)) {
                continue;
            }

            $name = sanitize_text_field($view['name'] ?? '');
            if ('' === $name) {
                continue;
            }

            $out[] = [
                'name'    => $name,
                'keyword' => sanitize_text_field($view['keyword'] ?? ''),
                'from'    => sanitize_text_field($view['from'] ?? ''),
                'to'      => sanitize_text_field($view['to'] ?? ''),
                'type'    => sanitize_text_field($view['type'] ?? ''),
            ];

            if (count($out) >= 50) {
                break;
            }
        }

        return $out;
    }
}

