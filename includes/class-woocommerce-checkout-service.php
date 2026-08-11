<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * WooCommerce checkout bridge for paid ticket requests.
 */
class WooCommerce_Checkout_Service
{
    public const CART_ITEM_KEY = '_evt_tickets_request';

    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;

        add_action('woocommerce_before_calculate_totals', [$this, 'apply_custom_price'], 20);
        add_filter('woocommerce_get_item_data', [$this, 'render_cart_item_data'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'store_order_item_meta'], 10, 4);
    }

    public function is_connected(): bool
    {
        if (! class_exists('WooCommerce') || ! function_exists('wc_get_product')) {
            return false;
        }

        $product_id = $this->configured_product_id();
        if ($product_id <= 0) {
            return false;
        }

        $product = wc_get_product($product_id);
        return $product instanceof \WC_Product;
    }

    public function is_event_paid(int $event_id): bool
    {
        if ($event_id <= 0) {
            return false;
        }

        $paid = (bool) get_post_meta($event_id, Event_Discovery_Meta::PAID_ENABLED_META, true);
        if (! $paid) {
            return false;
        }

        return $this->event_unit_amount($event_id) > 0;
    }

    public function event_unit_amount(int $event_id): int
    {
        $raw = (string) get_post_meta($event_id, Event_Discovery_Meta::COST_META, true);
        if ('' === trim($raw) || ! is_numeric($raw)) {
            return 0;
        }

        $amount = (float) $raw;
        if ($amount <= 0) {
            return 0;
        }

        return (int) round($amount * 100);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>|\WP_Error
     */
    public function begin_checkout(array $payload)
    {
        $event_id = isset($payload['event_id']) ? absint($payload['event_id']) : 0;
        $quantity = isset($payload['quantity']) ? max(1, (int) $payload['quantity']) : 1;
        $attendee_email = isset($payload['attendee_email']) ? sanitize_email((string) $payload['attendee_email']) : '';
        $attendee_name = isset($payload['attendee_name']) ? sanitize_text_field((string) $payload['attendee_name']) : '';
        $attendee_phone = isset($payload['attendee_phone']) ? sanitize_text_field((string) $payload['attendee_phone']) : '';
        $timeslot_id = isset($payload['timeslot_id']) ? sanitize_key((string) $payload['timeslot_id']) : '';

        if (! $event_id || '' === $attendee_email) {
            return new \WP_Error('evt_woocommerce_invalid_payload', __('Missing checkout payload values.', 'Event-Tickets-for-Elementor'));
        }

        if (! $this->is_connected()) {
            return new \WP_Error('evt_woocommerce_not_connected', __('WooCommerce is not configured yet.', 'Event-Tickets-for-Elementor'));
        }

        $unit_amount = $this->event_unit_amount($event_id);
        if ($unit_amount <= 0) {
            return new \WP_Error('evt_woocommerce_invalid_price', __('This event does not have a valid paid ticket price.', 'Event-Tickets-for-Elementor'));
        }

        if (function_exists('wc_load_cart')) {
            wc_load_cart();
        }

        if (! function_exists('WC') || ! WC()->cart) {
            return new \WP_Error('evt_woocommerce_cart_unavailable', __('WooCommerce cart is not available right now.', 'Event-Tickets-for-Elementor'));
        }

        if (WC()->session && method_exists(WC()->session, 'set_customer_session_cookie')) {
            WC()->session->set_customer_session_cookie(true);
        }

        $product = wc_get_product($this->configured_product_id());
        if (! $product || ! $product->is_purchasable()) {
            return new \WP_Error('evt_woocommerce_invalid_product', __('The configured WooCommerce product is not purchasable.', 'Event-Tickets-for-Elementor'));
        }

        $event_name = get_the_title($event_id);
        if (! is_string($event_name) || '' === trim($event_name)) {
            $event_name = __('Event Ticket', 'Event-Tickets-for-Elementor');
        }

        $request = [
            'event_id'        => $event_id,
            'event_name'      => $event_name,
            'timeslot_id'     => $timeslot_id,
            'quantity'        => $quantity,
            'attendee_name'   => $attendee_name,
            'attendee_phone'  => $attendee_phone,
            'attendee_email'  => $attendee_email,
            'price_per_ticket'=> wc_format_decimal($unit_amount / 100, wc_get_price_decimals()),
            'unique_key'      => wp_generate_uuid4(),
        ];

        $cart_item_key = WC()->cart->add_to_cart(
            $this->configured_product_id(),
            $quantity,
            0,
            [],
            [
                self::CART_ITEM_KEY => $request,
                'evt_tickets_unique_key' => $request['unique_key'],
            ]
        );

        if (! $cart_item_key) {
            return new \WP_Error('evt_woocommerce_add_to_cart_failed', __('Could not add this ticket request to WooCommerce cart.', 'Event-Tickets-for-Elementor'));
        }

        WC()->cart->calculate_totals();

        $redirect_url = ('cart' === $this->redirect_target()) ? wc_get_cart_url() : wc_get_checkout_url();

        return [
            'cart_item_key' => $cart_item_key,
            'redirect_url'  => $redirect_url,
        ];
    }

    public function apply_custom_price($cart): void
    {
        if (! is_object($cart) || ! method_exists($cart, 'get_cart')) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item) {
            if (empty($cart_item[self::CART_ITEM_KEY]) || empty($cart_item['data']) || ! is_object($cart_item['data'])) {
                continue;
            }

            $request = is_array($cart_item[self::CART_ITEM_KEY]) ? $cart_item[self::CART_ITEM_KEY] : [];
            $price = isset($request['price_per_ticket']) ? (float) $request['price_per_ticket'] : 0.0;
            if ($price <= 0) {
                continue;
            }

            $cart_item['data']->set_price($price);
        }
    }

    /**
     * @param array<int,array<string,string>> $item_data
     * @param array<string,mixed> $cart_item
     * @return array<int,array<string,string>>
     */
    public function render_cart_item_data(array $item_data, array $cart_item): array
    {
        if (empty($cart_item[self::CART_ITEM_KEY]) || ! is_array($cart_item[self::CART_ITEM_KEY])) {
            return $item_data;
        }

        $request = $cart_item[self::CART_ITEM_KEY];
        $item_data[] = [
            'key'   => __('Event', 'Event-Tickets-for-Elementor'),
            'value' => isset($request['event_name']) ? (string) $request['event_name'] : '',
        ];
        $item_data[] = [
            'key'   => __('Attendee Email', 'Event-Tickets-for-Elementor'),
            'value' => isset($request['attendee_email']) ? (string) $request['attendee_email'] : '',
        ];

        if (! empty($request['timeslot_id'])) {
            $item_data[] = [
                'key'   => __('Timeslot', 'Event-Tickets-for-Elementor'),
                'value' => (string) $request['timeslot_id'],
            ];
        }

        return $item_data;
    }

    /**
     * @param \WC_Order_Item_Product $item
     * @param string                 $cart_item_key
     * @param array<string,mixed>    $values
     * @param \WC_Order              $order
     */
    public function store_order_item_meta($item, string $cart_item_key, array $values, $order): void
    {
        if (empty($values[self::CART_ITEM_KEY]) || ! is_array($values[self::CART_ITEM_KEY])) {
            return;
        }

        $request = $values[self::CART_ITEM_KEY];
        $item->add_meta_data('_evt_tickets_request_json', wp_json_encode($request), true);
        $item->add_meta_data('_evt_tickets_event_id', isset($request['event_id']) ? absint($request['event_id']) : 0, true);
        $item->add_meta_data('_evt_tickets_attendee_email', isset($request['attendee_email']) ? sanitize_email((string) $request['attendee_email']) : '', true);
        $item->add_meta_data('_evt_tickets_attendee_name', isset($request['attendee_name']) ? sanitize_text_field((string) $request['attendee_name']) : '', true);
        $item->add_meta_data('_evt_tickets_attendee_phone', isset($request['attendee_phone']) ? sanitize_text_field((string) $request['attendee_phone']) : '', true);
        $item->add_meta_data('_evt_tickets_timeslot_id', isset($request['timeslot_id']) ? sanitize_key((string) $request['timeslot_id']) : '', true);
    }

    public function configured_product_id(): int
    {
        return absint($this->settings->get('woocommerce_product_id', 0));
    }

    public function redirect_target(): string
    {
        $target = (string) $this->settings->get('woocommerce_checkout_redirect', 'checkout');
        return ('cart' === $target) ? 'cart' : 'checkout';
    }
}
