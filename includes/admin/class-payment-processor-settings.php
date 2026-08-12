<?php

namespace EventTicketsElementor\Admin;

use EventTicketsElementor\Settings_Store;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Payment processors status block for the Payments tab.
 */
class Payment_Processor_Settings
{
    public function __construct()
    {
        add_action('admin_init', [$this, 'register']);
    }

    public function register(): void
    {
        add_settings_section(
            'evt_tickets_payment_processors_status_section',
            __('Payment Processors', 'Event-Tickets-for-Elementor'),
            function (): void {
                echo '<p>' . esc_html__('Paid tickets are routed directly to a hosted payment page — Stripe for global cards or ePay.bg for Bulgarian bank cards.', 'Event-Tickets-for-Elementor') . '</p>';
            },
            'evt-tickets-settings-payments'
        );

        add_settings_field(
            'evt_tickets_payment_processors_status',
            __('Status', 'Event-Tickets-for-Elementor'),
            [$this, 'render_status_field'],
            'evt-tickets-settings-payments',
            'evt_tickets_payment_processors_status_section'
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
            ''       => __('Not configured', 'Event-Tickets-for-Elementor'),
            'stripe' => __('Stripe — Global cards', 'Event-Tickets-for-Elementor'),
            'epay'   => __('ePay.bg — Bulgaria', 'Event-Tickets-for-Elementor'),
        ];
        $processor_label = isset($processor_labels[$processor]) ? $processor_labels[$processor] : __('Not configured', 'Event-Tickets-for-Elementor');

        $stripe_configured = '' !== trim((string) $store->get('stripe_secret_key', ''))
            || '' !== trim((string) $store->get('stripe_test_secret_key', ''));
        $epay_configured = '' !== trim((string) $store->get('epay_merchant_id', ''))
            && '' !== trim((string) $store->get('epay_secret', ''));

        echo '<table class="widefat striped" style="max-width:760px;margin-top:10px;"><tbody>';

        echo '<tr><th style="width:240px;">' . esc_html__('Active processor', 'Event-Tickets-for-Elementor') . '</th><td>' . esc_html($processor_label) . '</td></tr>';
        echo '<tr><th>' . esc_html__('Stripe', 'Event-Tickets-for-Elementor') . '</th><td>' . esc_html($stripe_configured ? __('Configured', 'Event-Tickets-for-Elementor') : __('Not configured', 'Event-Tickets-for-Elementor')) . '</td></tr>';
        echo '<tr><th>' . esc_html__('ePay.bg', 'Event-Tickets-for-Elementor') . '</th><td>' . esc_html($epay_configured ? __('Configured', 'Event-Tickets-for-Elementor') : __('Not configured', 'Event-Tickets-for-Elementor')) . '</td></tr>';

        echo '</tbody></table>';
    }
}
