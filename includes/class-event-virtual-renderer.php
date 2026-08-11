<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Renders embeds/links for virtual/hybrid event URLs.
 */
class Event_Virtual_Renderer
{
    /**
     * @return array{meeting_url:string,livestream_url:string}
     */
    public function get_urls_for_event(int $event_id): array
    {
        $event = get_post($event_id);
        if (! $event || CPT_Events::POST_TYPE !== $event->post_type) {
            return ['meeting_url' => '', 'livestream_url' => ''];
        }

        return [
            'meeting_url'   => (string) get_post_meta($event_id, Event_Virtual_Meta::MEETING_URL_META, true),
            'livestream_url'=> (string) get_post_meta($event_id, Event_Virtual_Meta::LIVESTREAM_URL_META, true),
        ];
    }

    public function render_embed(string $url, int $height = 360): string
    {
        $url = trim($url);
        if ('' === $url) {
            return '';
        }

        $embed = $this->youtube_embed_url($url) ?: $this->vimeo_embed_url($url);
        if (! $embed) {
            return '';
        }

        $height = max(200, min(900, (int) $height));

        return sprintf(
            '<div class="evt-virtual__embed"><iframe src="%1$s" loading="lazy" referrerpolicy="no-referrer" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen style="width:100%%;height:%2$dpx;border:0;"></iframe></div>',
            esc_url($embed),
            $height
        );
    }

    private function youtube_embed_url(string $url): string
    {
        $parts = wp_parse_url($url);
        if (empty($parts['host'])) {
            return '';
        }

        $host = strtolower($parts['host']);
        $id = '';

        if (false !== strpos($host, 'youtu.be')) {
            $path = trim((string) ($parts['path'] ?? ''), '/');
            $id = $path;
        } elseif (false !== strpos($host, 'youtube.com')) {
            if (! empty($parts['query'])) {
                parse_str($parts['query'], $q);
                $id = isset($q['v']) ? (string) $q['v'] : '';
            }
            if (! $id && ! empty($parts['path']) && preg_match('#/embed/([^/]+)#', $parts['path'], $m)) {
                $id = (string) $m[1];
            }
        }

        $id = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $id);
        if (! $id) {
            return '';
        }

        return 'https://www.youtube-nocookie.com/embed/' . rawurlencode($id);
    }

    private function vimeo_embed_url(string $url): string
    {
        $parts = wp_parse_url($url);
        if (empty($parts['host'])) {
            return '';
        }

        $host = strtolower($parts['host']);
        if (false === strpos($host, 'vimeo.com')) {
            return '';
        }

        $path = trim((string) ($parts['path'] ?? ''), '/');
        if (! preg_match('/^\d+$/', $path)) {
            return '';
        }

        return 'https://player.vimeo.com/video/' . rawurlencode($path);
    }
}

