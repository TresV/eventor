<?php

namespace EventTicketsElementor\Payments;

use WP_REST_Request;
use WP_REST_Response;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Public order-status lookup for the return-page poller.
 *
 * GET evt/v1/orders/<public_key> — returns only status + ticket_count (never
 * PII). When a `ref` query param is present (Stripe return page), the session
 * is verified server-side first so the pending window is shortened.
 */
class Payment_Order_Status_Controller
{
    private Payment_Service $service;

    public function __construct(Payment_Service $service)
    {
        $this->service = $service;
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route(
            'evt/v1',
            'orders/(?P<public_key>[a-fA-F0-9\-]+)',
            [
                'methods'             => 'GET',
                'permission_callback' => '__return_true',
                'callback'            => [$this, 'handle'],
            ]
        );
    }

    public function handle(WP_REST_Request $request)
    {
        $key = sanitize_text_field((string) $request['public_key']);

        $ref = $request->get_param('ref');
        if (is_string($ref) && '' !== trim($ref)) {
            $verified = $this->service->verify_return('stripe', sanitize_text_field($ref));
            if (is_array($verified)) {
                return new WP_REST_Response($verified, 200);
            }
        }

        $status = $this->service->public_status($key);
        if (null === $status) {
            return new WP_REST_Response(['error' => 'not_found'], 404);
        }

        return new WP_REST_Response(
            [
                'status'       => (string) ($status['status'] ?? ''),
                'ticket_count' => (int) ($status['ticket_count'] ?? 0),
            ],
            200
        );
    }
}
