<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Payments tab settings sections: direct payment processors.
 *
 * @param array<string,mixed> $ctx Shared schema context (blogname, admin_email, webhook URLs).
 * @return array<int,array<string,mixed>>
 */
function schema_payments(array $ctx): array
{
        $stripe_webhook_url = $ctx['stripe_webhook_url'];
        $epay_webhook_url   = $ctx['epay_webhook_url'];

        return [
            [
                'id'          => 'evt_tickets_payments_processors_section',
                'title'       => __('Payment Processors', 'Event-Tickets-for-Elementor'),
                'description' => __('Paid ticket requests go directly to a hosted payment page (Stripe for global cards, ePay.bg for Bulgarian bank cards).', 'Event-Tickets-for-Elementor'),
                'tab'         => 'payments',
                'fields'      => [
                    [
                        'key'         => 'payment_processor',
                        'label'       => __('Active Processor', 'Event-Tickets-for-Elementor'),
                        'description' => __('Choose where paid ticket requests are sent. myPOS support arrives in Phase 2.', 'Event-Tickets-for-Elementor'),
                        'type'        => 'select',
                        'default'     => '',
                        'options'     => [
                            ''       => __('Not configured', 'Event-Tickets-for-Elementor'),
                            'stripe' => __('Stripe — Global cards', 'Event-Tickets-for-Elementor'),
                            'epay'   => __('ePay.bg — Bulgaria', 'Event-Tickets-for-Elementor'),
                        ],
                    ],
                    [
                        'key'         => 'payment_currency',
                        'label'       => __('Currency', 'Event-Tickets-for-Elementor'),
                        'description' => __('ISO-4217 code used for direct checkout amounts (e.g. EUR).', 'Event-Tickets-for-Elementor'),
                        'type'        => 'text',
                        'default'     => 'EUR',
                    ],
                    [
                        'key'         => 'payment_hold_ttl_minutes',
                        'label'       => __('Seat Hold (minutes)', 'Event-Tickets-for-Elementor'),
                        'description' => __('How long a pending payment holds the seats before they are released.', 'Event-Tickets-for-Elementor'),
                        'type'        => 'number',
                        'default'     => 30,
                    ],
                    [
                        'key'         => 'stripe_mode',
                        'label'       => __('Stripe Mode', 'Event-Tickets-for-Elementor'),
                        'type'        => 'select',
                        'default'     => 'test',
                        'options'     => [
                            'live' => __('Live', 'Event-Tickets-for-Elementor'),
                            'test' => __('Test', 'Event-Tickets-for-Elementor'),
                        ],
                    ],
                    [
                        'key'         => 'stripe_secret_key',
                        'label'       => __('Stripe Secret Key (live)', 'Event-Tickets-for-Elementor'),
                        'type'        => 'secret',
                    ],
                    [
                        'key'         => 'stripe_test_secret_key',
                        'label'       => __('Stripe Secret Key (test)', 'Event-Tickets-for-Elementor'),
                        'type'        => 'secret',
                    ],
                    [
                        'key'         => 'stripe_webhook_secret',
                        'label'       => __('Stripe Webhook Secret (live)', 'Event-Tickets-for-Elementor'),
                        'description' => sprintf(
                            /* translators: %s is the Stripe webhook URL. */
                            __('The whsec_... value from the Stripe dashboard; the webhook URL is %s.', 'Event-Tickets-for-Elementor'),
                            $stripe_webhook_url
                        ),
                        'type'        => 'secret',
                    ],
                    [
                        'key'         => 'stripe_test_webhook_secret',
                        'label'       => __('Stripe Webhook Secret (test)', 'Event-Tickets-for-Elementor'),
                        'type'        => 'secret',
                    ],
                    [
                        'key'         => 'epay_merchant_id',
                        'label'       => __('ePay.bg Merchant ID (MIN)', 'Event-Tickets-for-Elementor'),
                        'type'        => 'text',
                    ],
                    [
                        'key'         => 'epay_secret',
                        'label'       => __('ePay.bg Secret', 'Event-Tickets-for-Elementor'),
                        'description' => sprintf(
                            /* translators: %s is the ePay.bg IPN URL. */
                            __('The IPN URL is %s.', 'Event-Tickets-for-Elementor'),
                            $epay_webhook_url
                        ),
                        'type'        => 'secret',
                    ],
                    [
                        'key'         => 'epay_test_mode',
                        'label'       => __('ePay.bg Demo mode', 'Event-Tickets-for-Elementor'),
                        'type'        => 'checkbox',
                        'default'     => 1,
                    ],
                ],
            ],
        ];
}
