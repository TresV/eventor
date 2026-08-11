<?php

namespace EventTicketsElementor\Admin;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Shows PDF template previews on the PDF Settings tab.
 */
class Pdf_Template_Previews
{
    public function __construct()
    {
        add_action('admin_init', [$this, 'register'], 30);
    }

    public function register(): void
    {
        add_settings_section(
            'evt_tickets_pdf_previews_section',
            __('PDF Templates Preview', 'Event-Tickets-for-Elementor'),
            [$this, 'render_section'],
            'evt-tickets-settings-pdf'
        );
    }

    public function render_section(): void
    {
        if (! defined('EVT_TICKETS_PLUGIN_URL')) {
            return;
        }

        $base = EVT_TICKETS_PLUGIN_URL . 'assets/images/pdf-presets/';
        $items = [
            'classic' => __('Classic', 'Event-Tickets-for-Elementor'),
            'minimal' => __('Minimal', 'Event-Tickets-for-Elementor'),
            'dark'    => __('Dark', 'Event-Tickets-for-Elementor'),
        ];

        echo '<p>' . esc_html__(
            'These are the built-in PDF layouts. Select one as global default above. If per-event override is enabled, events can choose a different template.',
            'Event-Tickets-for-Elementor') . '</p>';

        echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;max-width:900px;">';
        foreach ($items as $key => $label) {
            echo '<div style="border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;background:#fff;">';
            echo '<div style="padding:10px 12px;font-weight:700;">' . esc_html($label) . '</div>';
            echo '<img alt="' . esc_attr($label) . '" style="display:block;width:100%;height:auto;border-top:1px solid #e5e7eb;" src="' . esc_url($base . $key . '.svg') . '" />';
            echo '</div>';
        }
        echo '</div>';
    }
}
