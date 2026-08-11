<?php

namespace EventTicketsElementor\Admin;

use EventTicketsElementor\Settings_Store;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Payment processors status block for the Payments tab.
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
            __('Payment Processors', 'Event-Tickets-for-Elementor'),
            function (): void {
                echo '<p>' . esc_html__('Paid tickets can go directly to Stripe (global cards) or ePay.bg (Bulgaria) via hosted payment pages, with WooCommerce as an optional fallback.', 'Event-Tickets-for-Elementor') . '</p>';
            },
            'evt-tickets-settings-payments'
        );

        add_settings_field(
            'evt_tickets_payment_processors_status',
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

        // Read via Settings_Store so wp-config.php constant overrides are reflected.
        $store = new Settings_Store();

        $processor = isset($settings['payment_processor']) ? (string) $settings['payment_processor'] : '';
        $processor_labels = [
            ''            => __('Auto — WooCommerce if connected', 'Event-Tickets-for-Elementor'),
            'woocommerce' => __('WooCommerce (fallback)', 'Event-Tickets-for-Elementor'),
            'stripe'      => __('Stripe — Global cards', 'Event-Tickets-for-Elementor'),
            'epay'        => __('ePay.bg — Bulgaria', 'Event-Tickets-for-Elementor'),
        ];
        $processor_label = isset($processor_labels[$processor]) ? $processor_labels[$processor] : __('Not configured', 'Event-Tickets-for-Elementor');

        $stripe_configured = '' !== trim((string) $store->get('stripe_secret_key', ''))
            || '' !== trim((string) $store->get('stripe_test_secret_key', ''));
        $epay_configured = '' !== trim((string) $store->get('epay_merchant_id', ''))
            && '' !== trim((string) $store->get('epay_secret', ''));

        $wc_active = class_exists('WooCommerce');
        $show_wc = '' === $processor || 'woocommerce' === $processor || $wc_active;

        echo '<table class="widefat striped" style="max-width:760px;margin-top:10px;"><tbody>';

        echo '<tr><th style="width:240px;">' . esc_html__('Active processor', 'Event-Tickets-for-Elementor') . '</th><td>' . esc_html($processor_label) . '</td></tr>';
        echo '<tr><th>' . esc_html__('Stripe', 'Event-Tickets-for-Elementor') . '</th><td>' . esc_html($stripe_configured ? __('Configured', 'Event-Tickets-for-Elementor') : __('Not configured', 'Event-Tickets-for-Elementor')) . '</td></tr>';
        echo '<tr><th>' . esc_html__('ePay.bg', 'Event-Tickets-for-Elementor') . '</th><td>' . esc_html($epay_configured ? __('Configured', 'Event-Tickets-for-Elementor') : __('Not configured', 'Event-Tickets-for-Elementor')) . '</td></tr>';

        if ($show_wc) {
            $product_id = isset($settings['woocommerce_product_id']) ? absint($settings['woocommerce_product_id']) : 0;
            $redirect = isset($settings['woocommerce_checkout_redirect']) ? (string) $settings['woocommerce_checkout_redirect'] : 'checkout';
            $product = ($wc_active && $product_id > 0 && function_exists('wc_get_product')) ? wc_get_product($product_id) : null;

            $wc_status = $wc_active
                ? __('WooCommerce active', 'Event-Tickets-for-Elementor')
                : __('WooCommerce missing', 'Event-Tickets-for-Elementor');
            echo '<tr><th>' . esc_html__('WooCommerce', 'Event-Tickets-for-Elementor') . '</th><td>' . esc_html($wc_status) . '</td></tr>';
            echo '<tr><th>' . esc_html__('Plugin status', 'Event-Tickets-for-Elementor') . '</th><td>' . esc_html($wc_active ? __('Ready', 'Event-Tickets-for-Elementor') : __('Install and activate WooCommerce', 'Event-Tickets-for-Elementor')) . '</td></tr>';
            /* translators: %d is the WooCommerce product ID. */
            echo '<tr><th>' . esc_html__('Configured product', 'Event-Tickets-for-Elementor') . '</th><td>' . esc_html($product ? sprintf('%s (#%d)', $product->get_name(), $product_id) : ($product_id > 0 ? sprintf(__('Product #%d not found', 'Event-Tickets-for-Elementor'), $product_id) : __('Not configured', 'Event-Tickets-for-Elementor'))) . '</td></tr>';
            echo '<tr><th>' . esc_html__('Redirect target', 'Event-Tickets-for-Elementor') . '</th><td>' . esc_html(('cart' === $redirect) ? __('Cart', 'Event-Tickets-for-Elementor') : __('Checkout', 'Event-Tickets-for-Elementor')) . '</td></tr>';
        }

        echo '</tbody></table>';
    }
}
