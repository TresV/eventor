<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Per-event PDF preset selector.
 */
class Event_Pdf_Preset_Meta
{
    public const META_KEY = '_evt_event_pdf_preset';

    /**
     * @var array<string,string>
     */
    private array $presets = [
        'classic' => 'Classic',
        'minimal' => 'Minimal',
        'dark'    => 'Dark',
    ];

    public function __construct()
    {
        add_action('evt_tickets_event_section_ticket_output', [$this, 'render_event_details_fields'], 10);
        add_action('evt_tickets_event_details_save', [$this, 'save_event_details_fields'], 10, 2);
    }

    public function render_event_details_fields(\WP_Post $post): void
    {
        $allow_override = $this->allow_event_override();
        $value = (string) get_post_meta($post->ID, self::META_KEY, true);
        $value = $this->normalize_preset($value);
        $base = defined('EVT_TICKETS_PLUGIN_URL') ? EVT_TICKETS_PLUGIN_URL : '';
        ?>
        <div class="evt-event-subsection">
        <p>
            <label for="evt_event_pdf_preset"><strong><?php esc_html_e('PDF Ticket Template', 'Event-Tickets-for-Elementor'); ?></strong></label><br />
            <?php if (! $allow_override) : ?>
                <span class="description">
                    <?php esc_html_e('Per-event template override is disabled in global PDF settings. This event uses the global template.', 'Event-Tickets-for-Elementor'); ?>
                </span>
            <?php endif; ?>
            <br />
            <select id="evt_event_pdf_preset" name="evt_event_pdf_preset" class="regular-text" <?php disabled(! $allow_override); ?>>
                <?php foreach ($this->presets as $key => $label) : ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($value, $key); ?>>
                        <?php echo esc_html($this->preset_label($key, $label)); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <br />
            <span class="description">
                <?php esc_html_e('Choose the PDF ticket layout preset for this event.', 'Event-Tickets-for-Elementor'); ?>
            </span>
        </p>
        <?php if ($base) : ?>
            <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin:10px 0 0;">
                <?php foreach ($this->presets as $key => $label) : ?>
                    <?php $is_active = ($value === $key); ?>
                    <div style="border:1px solid <?php echo $is_active ? '#111827' : '#e5e7eb'; ?>;border-radius:10px;overflow:hidden;background:#fff;">
                        <div style="padding:6px 8px;font-size:12px;font-weight:600;<?php echo $is_active ? 'color:#111827;' : 'color:#6b7280;'; ?>">
                            <?php echo esc_html($this->preset_label($key, $label)); ?>
                        </div>
                        <img alt="<?php echo esc_attr($this->preset_label($key, $label)); ?>" style="display:block;width:100%;height:auto;" src="<?php echo esc_url($base . 'assets/images/pdf-presets/' . $key . '.svg'); ?>" />
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        </div>
        <?php
    }

    public function save_event_details_fields(int $post_id, \WP_Post $post): void
    {
        if (CPT_Events::POST_TYPE !== $post->post_type) {
            return;
        }

        if (! $this->allow_event_override()) {
            return;
        }

        $preset = isset($_POST['evt_event_pdf_preset']) ? sanitize_text_field(wp_unslash($_POST['evt_event_pdf_preset'])) : '';
        $preset = $this->normalize_preset($preset);
        update_post_meta($post_id, self::META_KEY, $preset);
    }

    private function allow_event_override(): bool
    {
        $options = get_option(Settings_Store::OPTION_KEY, []);
        if (! is_array($options)) {
            return true;
        }

        return ! isset($options['pdf_allow_event_override']) || ! empty($options['pdf_allow_event_override']);
    }

    private function normalize_preset(string $preset): string
    {
        $preset = trim($preset);
        if ('' === $preset) {
            return 'classic';
        }
        if (! isset($this->presets[$preset])) {
            return 'classic';
        }
        return $preset;
    }

    private function preset_label(string $key, string $fallback): string
    {
        // Translation wrapper while keeping stable keys.
        switch ($key) {
            case 'minimal':
                return __('Minimal', 'Event-Tickets-for-Elementor');
            case 'dark':
                return __('Dark', 'Event-Tickets-for-Elementor');
            case 'classic':
            default:
                return __('Classic', 'Event-Tickets-for-Elementor');
        }
    }
}
