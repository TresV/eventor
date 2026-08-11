<?php

namespace EventTicketsElementor\Admin;

use EventTicketsElementor\Settings_Store;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * WooCommerce integration status block for the Payments tab.
 */
class WooCommerce_Settings
{
    public function __construct()
    {
        add_action('admin_init', [$this, 'register']);
    }

    public function register(): void
    {
        add_settings_section(
            'evt_tickets_woocommerce_status_section',
            __('WooCommerce Integration', 'Event-Tickets-for-Elementor'),
            function (): void {
                echo '<p>' . esc_html__('Paid ticket requests are sent into WooCommerce so you can use native cart, checkout, and gateway plugins.', 'Event-Tickets-for-Elementor') . '</p>';
            },
            'evt-tickets-settings-payments'
        );

        add_settings_field(
            'evt_tickets_woocommerce_status',
            __('Status', 'Event-Tickets-for-Elementor'),
            [$this, 'render_status_field'],
            'evt-tickets-settings-payments',
            'evt_tickets_woocommerce_status_section'
        );
    }

    public function render_status_field(): void
    {
        $settings = get_option(Settings_Store::OPTION_KEY, []);
        if (! is_array($settings)) {
            $settings = [];
        }

        $active = class_exists('WooCommerce');
        $product_id = isset($settings['woocommerce_product_id']) ? absint($settings['woocommerce_product_id']) : 0;
        $redirect = isset($settings['woocommerce_checkout_redirect']) ? (string) $settings['woocommerce_checkout_redirect'] : 'checkout';
        $product = ($active && $product_id > 0 && function_exists('wc_get_product')) ? wc_get_product($product_id) : null;

        $pill_style = $active
            ? 'display:inline-flex;padding:4px 8px;border-radius:999px;background:#ecfdf5;color:#166534;border:1px solid #bbf7d0;font-weight:600;'
            : 'display:inline-flex;padding:4px 8px;border-radius:999px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;font-weight:600;';

        echo '<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">';
        echo '<span style="' . esc_attr($pill_style) . '">' . esc_html($active ? __('WooCommerce active', 'Event-Tickets-for-Elementor') : __('WooCommerce missing', 'Event-Tickets-for-Elementor')) . '</span>';
        echo '</div>';

        echo '<table class="widefat striped" style="max-width:760px;margin-top:10px;"><tbody>';
        $rows = [
            __('Plugin status', 'Event-Tickets-for-Elementor') => $active ? __('Ready', 'Event-Tickets-for-Elementor') : __('Install and activate WooCommerce', 'Event-Tickets-for-Elementor'),
            /* translators: %d is the WooCommerce product ID. */
            __('Configured product', 'Event-Tickets-for-Elementor') => $product ? sprintf('%s (#%d)', $product->get_name(), $product_id) : ($product_id > 0 ? sprintf(__('Product #%d not found', 'Event-Tickets-for-Elementor'), $product_id) : __('Not configured', 'Event-Tickets-for-Elementor')),
            __('Redirect target', 'Event-Tickets-for-Elementor') => ('cart' === $redirect) ? __('Cart', 'Event-Tickets-for-Elementor') : __('Checkout', 'Event-Tickets-for-Elementor'),
        ];
        foreach ($rows as $label => $value) {
            echo '<tr><th style="width:240px;">' . esc_html($label) . '</th><td>' . esc_html((string) $value) . '</td></tr>';
        }
        echo '</tbody></table>';
    }
}
