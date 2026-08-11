<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Custom post type for paid-ticket payment orders (direct processor path).
 *
 * Stores the payment intent + lifecycle for Stripe / ePay.bg checkouts.
 * Lifecycle statuses:
 *   evt_pending → evt_paid (tickets issued)
 *   evt_pending → evt_failed | evt_expired (seats released)
 *   evt_paid   → evt_refunded (money moved back, tickets cancelled)
 *
 * The CPT is intentionally not exposed via REST, is excluded from search, and
 * is not publicly queryable — it is an internal transactional record.
 */
class CPT_Orders
{
    public const POST_TYPE = 'evt_order';

    public const STATUS_PENDING  = 'evt_pending';
    public const STATUS_PAID     = 'evt_paid';
    public const STATUS_FAILED   = 'evt_failed';
    public const STATUS_EXPIRED  = 'evt_expired';
    public const STATUS_REFUNDED = 'evt_refunded';

    // Capabilities (mirrors the Tickets CPT naming so the same roles apply).
    public const CAP_EDIT_POST        = 'edit_evt_order';
    public const CAP_READ_POST        = 'read_evt_order';
    public const CAP_DELETE_POST      = 'delete_evt_order';
    public const CAP_EDIT_POSTS       = 'edit_evt_orders';
    public const CAP_EDIT_OTHERS      = 'edit_others_evt_orders';
    public const CAP_PUBLISH          = 'publish_evt_orders';
    public const CAP_READ_PRIVATE     = 'read_private_evt_orders';
    public const CAP_DELETE_POSTS     = 'delete_evt_orders';
    public const CAP_DELETE_PRIVATE   = 'delete_private_evt_orders';
    public const CAP_DELETE_PUBLISHED = 'delete_published_evt_orders';
    public const CAP_DELETE_OTHERS    = 'delete_others_evt_orders';
    public const CAP_EDIT_PRIVATE     = 'edit_private_evt_orders';
    public const CAP_EDIT_PUBLISHED   = 'edit_published_evt_orders';

    public function __construct()
    {
        add_action('init', [$this, 'register']);
        add_filter('manage_edit-evt_order_columns', [$this, 'edit_columns']);
        add_action('manage_evt_order_posts_custom_column', [$this, 'render_columns'], 10, 2);
    }

    public function register(): void
    {
        $labels = [
            'name'               => __('Payment Orders', 'Event-Tickets-for-Elementor'),
            'singular_name'      => __('Payment Order', 'Event-Tickets-for-Elementor'),
            'menu_name'          => __('Orders', 'Event-Tickets-for-Elementor'),
            'name_admin_bar'     => __('Payment Order', 'Event-Tickets-for-Elementor'),
            'add_new'            => __('Add New', 'Event-Tickets-for-Elementor'),
            'add_new_item'       => __('Add New Order', 'Event-Tickets-for-Elementor'),
            'edit_item'          => __('Edit Order', 'Event-Tickets-for-Elementor'),
            'new_item'           => __('New Order', 'Event-Tickets-for-Elementor'),
            'view_item'          => __('View Order', 'Event-Tickets-for-Elementor'),
            'search_items'       => __('Search Orders', 'Event-Tickets-for-Elementor'),
            'not_found'          => __('No payment orders found.', 'Event-Tickets-for-Elementor'),
            'not_found_in_trash' => __('No payment orders found in Trash.', 'Event-Tickets-for-Elementor'),
            'all_items'          => __('Orders', 'Event-Tickets-for-Elementor'),
        ];

        $args = [
            'labels'          => $labels,
            'public'          => false,
            'show_ui'         => true,
            'show_in_menu'    => 'evt-tickets', // Attach under the plugin admin menu.
            'show_in_rest'    => false,
            'supports'        => ['title'],
            'capability_type' => ['evt_order', 'evt_orders'],
            'capabilities'    => [
                'edit_post'              => self::CAP_EDIT_POST,
                'read_post'              => self::CAP_READ_POST,
                'delete_post'            => self::CAP_DELETE_POST,
                'edit_posts'             => self::CAP_EDIT_POSTS,
                'edit_others_posts'      => self::CAP_EDIT_OTHERS,
                'publish_posts'          => self::CAP_PUBLISH,
                'read_private_posts'     => self::CAP_READ_PRIVATE,
                'delete_posts'           => self::CAP_DELETE_POSTS,
                'delete_private_posts'   => self::CAP_DELETE_PRIVATE,
                'delete_published_posts' => self::CAP_DELETE_PUBLISHED,
                'delete_others_posts'    => self::CAP_DELETE_OTHERS,
                'edit_private_posts'     => self::CAP_EDIT_PRIVATE,
                'edit_published_posts'   => self::CAP_EDIT_PUBLISHED,
                'create_posts'           => self::CAP_EDIT_POSTS,
            ],
            'map_meta_cap'       => true,
            'has_archive'        => false,
            'rewrite'            => false,
            'menu_position'      => null,
        ];

        register_post_type(self::POST_TYPE, $args);

        $this->register_statuses();
        $this->register_meta();
    }

    /**
     * Register the order lifecycle statuses as internal statuses.
     */
    public function register_statuses(): void
    {
        $statuses = [
            self::STATUS_PENDING  => ['Pending',  _x('Pending', 'order status', 'Event-Tickets-for-Elementor')],
            self::STATUS_PAID     => ['Paid',     _x('Paid', 'order status', 'Event-Tickets-for-Elementor')],
            self::STATUS_FAILED   => ['Failed',   _x('Failed', 'order status', 'Event-Tickets-for-Elementor')],
            self::STATUS_EXPIRED  => ['Expired',  _x('Expired', 'order status', 'Event-Tickets-for-Elementor')],
            self::STATUS_REFUNDED => ['Refunded', _x('Refunded', 'order status', 'Event-Tickets-for-Elementor')],
        ];

        foreach ($statuses as $status => [$label, $label_x]) {
            register_post_status(
                $status,
                [
                    'label'                     => $label_x,
                    'public'                    => false,
                    'internal'                  => false,
                    'exclude_from_search'       => true,
                    'show_in_admin_all_list'    => true,
                    'show_in_admin_status_list' => true,
                    'label_count'               => _n_noop(
                        $label . ' <span class="count">(%s)</span>',
                        $label . ' <span class="count">(%s)</span>',
                        'Event-Tickets-for-Elementor'
                    ),
                ]
            );
        }
    }

    /**
     * Register order post meta with centralized sanitization so every write
     * path is covered consistently (mirrors the Tickets CPT pattern).
     */
    private function register_meta(): void
    {
        $string = 'sanitize_text_field';

        $keys = [
            '_evt_order_event_id'        => ['type' => 'integer', 'sanitize' => 'absint'],
            '_evt_order_timeslot_id'     => ['type' => 'string',  'sanitize' => 'sanitize_key'],
            '_evt_order_quantity'        => ['type' => 'integer', 'sanitize' => 'absint'],
            '_evt_order_attendee_name'   => ['type' => 'string',  'sanitize' => $string],
            '_evt_order_attendee_email'  => ['type' => 'string',  'sanitize' => 'sanitize_email'],
            '_evt_order_attendee_phone'  => ['type' => 'string',  'sanitize' => $string],
            '_evt_order_amount'          => ['type' => 'integer', 'sanitize' => 'absint'],
            '_evt_order_currency'        => ['type' => 'string',  'sanitize' => $string],
            '_evt_order_processor'       => ['type' => 'string',  'sanitize' => 'sanitize_key'],
            '_evt_order_transaction_id'  => ['type' => 'string',  'sanitize' => $string],
            '_evt_order_payment_ref'     => ['type' => 'string',  'sanitize' => $string],
            '_evt_order_idempotency_key' => ['type' => 'string',  'sanitize' => $string],
            '_evt_order_public_key'      => ['type' => 'string',  'sanitize' => $string],
            '_evt_order_hold_expires_at' => ['type' => 'integer', 'sanitize' => 'absint'],
            '_evt_order_refunded_amount' => ['type' => 'integer', 'sanitize' => 'absint'],
            '_evt_order_ticket_ids'      => ['type' => 'array'],
            '_evt_order_issued_at'       => ['type' => 'string',  'sanitize' => $string],
            '_evt_order_refunded_at'     => ['type' => 'string',  'sanitize' => $string],
            '_evt_order_error'           => ['type' => 'string',  'sanitize' => $string],
        ];

        foreach ($keys as $key => $config) {
            register_post_meta(
                self::POST_TYPE,
                $key,
                [
                    'type'              => $config['type'],
                    'single'            => true,
                    'show_in_rest'      => false,
                    'sanitize_callback' => $config['sanitize'] ?? null,
                ]
            );
        }
    }

    /**
     * Admin list columns.
     *
     * @param array<string,string> $columns
     * @return array<string,string>
     */
    public function edit_columns(array $columns): array
    {
        return [
            'cb'        => $columns['cb'] ?? '<input type="checkbox" />',
            'title'     => __('Order', 'Event-Tickets-for-Elementor'),
            'event'     => __('Event', 'Event-Tickets-for-Elementor'),
            'attendee'  => __('Attendee', 'Event-Tickets-for-Elementor'),
            'amount'    => __('Amount', 'Event-Tickets-for-Elementor'),
            'processor' => __('Processor', 'Event-Tickets-for-Elementor'),
            'date'      => __('Date', 'Event-Tickets-for-Elementor'),
        ];
    }

    /**
     * @param string $column
     * @param int    $post_id
     */
    public function render_columns(string $column, int $post_id): void
    {
        $event_id   = absint(get_post_meta($post_id, '_evt_order_event_id', true));
        $name       = (string) get_post_meta($post_id, '_evt_order_attendee_name', true);
        $email      = sanitize_email((string) get_post_meta($post_id, '_evt_order_attendee_email', true));
        $amount     = absint(get_post_meta($post_id, '_evt_order_amount', true));
        $currency   = (string) get_post_meta($post_id, '_evt_order_currency', true);
        $processor  = (string) get_post_meta($post_id, '_evt_order_processor', true);

        switch ($column) {
            case 'event':
                echo $event_id ? esc_html(get_the_title($event_id)) : '&mdash;';
                break;
            case 'attendee':
                echo esc_html($name ?: ($email ?: '&mdash;'));
                break;
            case 'amount':
                $currency = '' !== $currency ? $currency : 'EUR';
                echo esc_html(number_format_i18n($amount / 100, 2) . ' ' . $currency);
                break;
            case 'processor':
                echo esc_html('' !== $processor ? $processor : '&mdash;');
                break;
        }
    }
}
