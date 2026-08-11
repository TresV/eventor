<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Event Gallery (multiple images) meta field for evt_event.
 *
 * Stored meta: _evt_event_gallery_ids (array of attachment IDs).
 */
class Event_Gallery_Meta
{
    public const META_KEY = '_evt_event_gallery_ids';

    public function __construct()
    {
        add_action('evt_tickets_event_section_basics', [$this, 'render_field'], 20);
        add_action('evt_tickets_event_details_save', [$this, 'save_field'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public function enqueue_admin_assets(string $hook): void
    {
        if (! in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (! $screen || CPT_Events::POST_TYPE !== $screen->post_type) {
            return;
        }

        wp_enqueue_media();

        $version = defined('EVT_TICKETS_VERSION') ? EVT_TICKETS_VERSION : (defined('EVT_TICKETS_PLUGIN_VERSION') ? EVT_TICKETS_PLUGIN_VERSION : '1.0.0');

        wp_enqueue_script(
            'evt-event-gallery-meta',
            EVT_TICKETS_PLUGIN_URL . 'assets/js/event-gallery-admin.js',
            ['jquery'],
            $version,
            true
        );

        wp_localize_script(
            'evt-event-gallery-meta',
            'evtEventGalleryMetaI18n',
            [
                'title'         => __('Event Gallery', 'Event-Tickets-for-Elementor'),
                'buttonUse'     => __('Use selected images', 'Event-Tickets-for-Elementor'),
                'empty'         => __('No gallery images selected.', 'Event-Tickets-for-Elementor'),
            ]
        );
    }

    public function render_field(\WP_Post $post): void
    {
        $ids = get_post_meta($post->ID, self::META_KEY, true);
        if (! is_array($ids)) {
            $ids = [];
        }

        $ids = array_values(array_filter(array_map('absint', $ids)));
        $id_string = implode(',', $ids);

        $thumbs = [];
        foreach ($ids as $attachment_id) {
            $src = wp_get_attachment_image_src($attachment_id, 'thumbnail');
            if ($src && ! empty($src[0])) {
                $thumbs[] = [
                    'id'  => $attachment_id,
                    'url' => $src[0],
                ];
            }
        }
        ?>
        <div class="evt-event-subsection">
        <h4><?php esc_html_e('Event Gallery', 'Event-Tickets-for-Elementor'); ?></h4>
        <p class="description"><?php esc_html_e('Add multiple images for this event. They can be used in Elementor via the “Event Gallery” dynamic tag.', 'Event-Tickets-for-Elementor'); ?></p>

        <input type="hidden" id="evt_event_gallery_ids" name="evt_event_gallery_ids" value="<?php echo esc_attr($id_string); ?>" />

        <div class="evt-event-gallery-meta" data-field="#evt_event_gallery_ids">
            <div class="evt-event-gallery-meta__actions" style="margin: 8px 0;">
                <button type="button" class="button evt-event-gallery-meta__add">
                    <?php esc_html_e('Add / Edit Gallery', 'Event-Tickets-for-Elementor'); ?>
                </button>
                <button type="button" class="button evt-event-gallery-meta__clear" style="margin-left: 6px;">
                    <?php esc_html_e('Clear', 'Event-Tickets-for-Elementor'); ?>
                </button>
            </div>

            <div class="evt-event-gallery-meta__preview" style="display:flex; gap:8px; flex-wrap:wrap;">
                <?php if (empty($thumbs)) : ?>
                    <em class="evt-event-gallery-meta__empty"><?php esc_html_e('No gallery images selected.', 'Event-Tickets-for-Elementor'); ?></em>
                <?php else : ?>
                    <?php foreach ($thumbs as $thumb) : ?>
                        <div class="evt-event-gallery-meta__thumb" data-id="<?php echo esc_attr((string) $thumb['id']); ?>" style="position:relative;">
                            <img src="<?php echo esc_url($thumb['url']); ?>" alt="" style="width:80px; height:80px; object-fit:cover; border:1px solid #ccd0d4; border-radius:4px;" />
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        </div>
        <?php
    }

    public function save_field(int $post_id, \WP_Post $post): void
    {
        if (CPT_Events::POST_TYPE !== $post->post_type) {
            return;
        }

        $raw = isset($_POST['evt_event_gallery_ids']) ? (string) sanitize_text_field(wp_unslash($_POST['evt_event_gallery_ids'])) : '';
        $raw = trim($raw);

        if ('' === $raw) {
            delete_post_meta($post_id, self::META_KEY);
            return;
        }

        $parts = array_filter(array_map('trim', explode(',', $raw)));
        $ids   = array_values(array_unique(array_filter(array_map('absint', $parts))));

        update_post_meta($post_id, self::META_KEY, $ids);
    }
}
