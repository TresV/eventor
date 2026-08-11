<?php

namespace EventTicketsElementor\Payments;

use EventTicketsElementor\CPT_Orders;
use WP_REST_Request;
use WP_REST_Response;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Public REST endpoints for payment processor callbacks.
 *
 * - POST evt/v1/payments/<processor>/webhook — verifies the signature inside
 *   the processor, then lets Payment_Service reconcile the order. Webhooks are
 *   authenticated by the processor signature, so the permission callback is
 *   __return_true. ePay gets the plaintext INVOICE=...:STATUS=OK|ERR reply it
 *   requires; other processors get JSON.
 * - GET  evt/v1/payments/epay/start — renders a tiny auto-submitting form that
 *   POSTs ENCODED/CHECKSUM to the ePay payment page (ePay needs a form POST,
 *   not a plain redirect).
 */
class Payment_Webhook_Controller
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
            'payments/(?P<processor>[a-z]+)/webhook',
            [
                'methods'             => 'POST',
                'permission_callback' => '__return_true',
                'callback'            => [$this, 'handle'],
            ]
        );

        register_rest_route(
            'evt/v1',
            'payments/(?P<processor>[a-z]+)/start',
            [
                'methods'             => 'GET',
                'permission_callback' => '__return_true',
                'callback'            => [$this, 'handle_start'],
            ]
        );
    }

    /**
     * Process an incoming webhook/IPN and map the outcome to an HTTP response.
     */
    public function handle(WP_REST_Request $request)
    {
        $processor = sanitize_key((string) $request['processor']);
        $result    = $this->service->handle_webhook($processor, $request);

        // ePay always wants the plaintext INVOICE=...:STATUS=OK|ERR reply.
        if ('epay' === $processor) {
            return $this->epay_response($request, $result);
        }

        if (true === ($result['processed'] ?? false)) {
            return new WP_REST_Response(['received' => true, 'status' => $result['status'] ?? null], 200);
        }

        $code = (string) ($result['error'] ?? '');

        if ('evt_stripe_ignored' === $code) {
            return new WP_REST_Response(['received' => true, 'ignored' => true], 200);
        }

        // evt_*_bad_signature / evt_*_bad_checksum → 401.
        if (0 === strpos($code, 'evt_') && ('signature' === substr($code, -9) || 'checksum' === substr($code, -8))) {
            return new WP_REST_Response(['error' => $code], 401);
        }

        if ('order_not_found' === $code) {
            return new WP_REST_Response(['error' => $code], 404);
        }

        return new WP_REST_Response(['error' => $code], 400);
    }

    /**
     * Render the auto-submitting ePay payment form (redirect target).
     */
    public function handle_start(WP_REST_Request $request)
    {
        $processor = sanitize_key((string) $request['processor']);
        if ('epay' !== $processor) {
            return new WP_REST_Response(['error' => 'not_found'], 404);
        }

        $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
        if (! $order_id) {
            return new WP_REST_Response(['error' => 'not_found'], 404);
        }

        $order = Order_Record::from_post($order_id);
        if (CPT_Orders::STATUS_PENDING !== $order->status()) {
            return new WP_REST_Response(['error' => 'not_found'], 404);
        }

        $form   = Epay_Processor::build_payment_form($order);
        $action = (string) ($form['action'] ?? '');
        $fields = is_array($form['fields'] ?? null) ? $form['fields'] : [];
        if ('' === $action) {
            return new WP_REST_Response(['error' => 'not_found'], 404);
        }

        $response = new WP_REST_Response($this->render_form_html($action, $fields), 200);
        $response->header('Content-Type', 'text/html; charset=' . get_option('blog_charset', 'UTF-8'));
        return $response;
    }

    /**
     * ePay IPN plaintext response: INVOICE=<id>:STATUS=OK|ERR.
     */
    private function epay_response(WP_REST_Request $request, array $result): WP_REST_Response
    {
        $invoice   = Epay_Processor::invoice_from_request($request);
        $processed = true === ($result['processed'] ?? false);
        $body      = 'INVOICE=' . $invoice . ':STATUS=' . ($processed ? 'OK' : 'ERR');

        $response = new WP_REST_Response($body, $processed ? 200 : 400);
        $response->header('Content-Type', 'text/plain; charset=' . get_option('blog_charset', 'UTF-8'));
        return $response;
    }

    /**
     * A tiny HTML page that immediately submits the hidden fields to ePay.
     *
     * @param array<string,string> $fields
     */
    private function render_form_html(string $action, array $fields): string
    {
        $inputs = '';
        foreach ($fields as $key => $value) {
            $inputs .= '<input type="hidden" name="' . esc_attr((string) $key) . '" value="' . esc_attr((string) $value) . '">' . "\n";
        }

        return '<!DOCTYPE html>' . "\n"
            . '<html lang="' . esc_attr(get_locale()) . '">' . "\n"
            . '<head>' . "\n"
            . '<meta charset="' . esc_attr(get_bloginfo('charset')) . '">' . "\n"
            . '<title>' . esc_html__('Redirecting to secure payment…', 'Event-Tickets-for-Elementor') . '</title>' . "\n"
            . '</head>' . "\n"
            . '<body>' . "\n"
            . '<form method="post" action="' . esc_url($action) . '">' . "\n"
            . $inputs
            . '</form>' . "\n"
            . '<script>document.forms[0].submit();</script>' . "\n"
            . '</body>' . "\n"
            . '</html>';
    }
}
