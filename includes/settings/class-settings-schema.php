<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/schema-email.php';
require_once __DIR__ . '/schema-elementor.php';
require_once __DIR__ . '/schema-event.php';
require_once __DIR__ . '/schema-pdf.php';
require_once __DIR__ . '/schema-payments.php';

/**
 * Builds the full settings sections + fields metadata by combining
 * per-tab schema definitions.
 */
class Settings_Schema
{
    /**
     * Build the combined list of settings sections.
     *
     * @return array<int,array<string,mixed>>
     */
    public function build(): array
    {
        $ctx = [
            'blogname'           => wp_specialchars_decode(get_option('blogname'), ENT_QUOTES),
            'admin_email'        => get_option('admin_email'),
            'stripe_webhook_url' => rest_url('evt/v1/payments/stripe/webhook'),
            'epay_webhook_url'   => rest_url('evt/v1/payments/epay/webhook'),
        ];

        return array_merge(
            schema_email($ctx),
            schema_elementor($ctx),
            schema_event($ctx),
            schema_pdf($ctx),
            schema_payments($ctx)
        );
    }
}
