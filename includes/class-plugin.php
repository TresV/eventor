<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

require_once EVT_TICKETS_PLUGIN_DIR . 'includes/requirements.php';

/**
 * Main plugin orchestrator.
 */
class Plugin
{
    /** @var Plugin */
    private static $instance;

    /** @var CPT_Tickets */
    public $cpt_tickets;

    /** @var CPT_Events */
    public $cpt_events;

    /** @var Settings */
    public $settings;

    /** @var Bulgarian_Translations */
    public $bulgarian_translations;

    /** @var Event_Capacity */
    public $event_capacity;

    /** @var Event_Meta_Timestamps */
    public $event_meta_timestamps;

    /** @var Event_Location_Meta */
    public $event_location_meta;

    /** @var Event_Required_Fields */
    public $event_required_fields;

    /** @var Event_Pdf_Preset_Meta */
    public $event_pdf_preset_meta;

    /** @var Event_Ticket_Code_Pattern_Meta */
    public $event_ticket_code_pattern_meta;

    /** @var Event_Taxonomies */
    public $event_taxonomies;

    /** @var Event_Discovery_Meta */
    public $event_discovery_meta;

    /** @var Event_Status */
    public $event_status;

    /** @var Event_Status_Badge_Renderer */
    public $event_status_badge_renderer;

    /** @var Event_Status_Badge_Resolver */
    public $event_status_badge_resolver;

    /** @var Event_Status_Badge_Meta */
    public $event_status_badge_meta;

    /** @var Event_Recurrence */
    public $event_recurrence;

    /** @var Event_Virtual_Meta */
    public $event_virtual_meta;

    /** @var Ticket_Statuses */
    public $ticket_statuses;

    /** @var Ticket_Service */
    public $ticket_service;

    /** @var Email_Service */
    public $email_service;

    /** @var Calendar_Service */
    public $calendar_service;

    /** @var Pdf_Ticket_Service */
    public $pdf_ticket_service;

    /** @var Ticket_Pdf_Endpoint */
    public $ticket_pdf_endpoint;

    /** @var Checkin_Ajax */
    public $checkin_ajax;

    /** @var Ticket_Box_Ajax */
    public $ticket_box_ajax;

    /** @var Elementor_Widgets_Loader */
    public $elementor_widgets_loader;

    /** @var Elementor_Theme_Conditions_Loader */
    public $elementor_theme_conditions_loader;

    /** @var Integrations\Elementor_Forms_Integration */
    public $elementor_forms_integration;

    /** @var Admin\Event_CSV_Export */
    public $event_csv_export;

    /** @var Admin\Ticket_Resend_Actions */
    public $ticket_resend_actions;

    /** @var Admin\Email_Template_Preview_Assets */
    public $email_template_preview_assets;

    /** @var Event_Query */
    public $event_query;

    /** @var Event_Filters_Source */
    public $event_filters_source;

    /** @var Event_Ajax */
    public $event_ajax;

    /** @var Event_Feed_Endpoint */
    public $event_feed_endpoint;

    /** @var CPT_Orders */
    public $cpt_orders;

    /** @var Payments\Payment_Order_Service */
    public $payment_orders;

    /** @var Payments\Reservation_Service */
    public $reservations;

    /** @var Payments\Ticket_Issuance_Service */
    public $ticket_issuance;

    /** @var Payments\Refund_Service */
    public $refunds;

    /** @var Payments\Payment_Service */
    public $payment_service;

    /** @var Admin\Ticket_Cancelled_Migrator */
    public $ticket_cancelled_migrator;

    /** @var Saved_Views_Ajax */
    public $saved_views_ajax;

    /**
     * Singleton instance.
     */
    public static function instance(): Plugin
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct()
    {
        add_action('init', [$this, 'load_textdomain'], 0);
        add_action('plugins_loaded', [$this, 'init']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
        add_action('admin_init', [Schema::class, 'maybe_upgrade']);
    }

    /**
     * Load translations at init so taxonomy/post-type labels are translated reliably
     * and to comply with WP 6.7+ timing expectations.
     */
    public function load_textdomain(): void
    {
        load_plugin_textdomain(
            'Event-Tickets-for-Elementor',
            false,
            dirname(plugin_basename(EVT_TICKETS_PLUGIN_FILE)) . '/languages'
        );
    }

    /**
     * Initialize plugin after all plugins are loaded.
     */
    public function init(): void
    {
        $this->bulgarian_translations = new Bulgarian_Translations();
        $this->settings             = new Settings();
        $this->cpt_events           = new CPT_Events();
        $this->cpt_tickets          = new CPT_Tickets();
        $this->event_capacity       = new Event_Capacity();
        $this->event_meta_timestamps = new Event_Meta_Timestamps();
        $this->event_location_meta  = new Event_Location_Meta();
        $this->event_required_fields = new Event_Required_Fields();
        $this->event_pdf_preset_meta = new Event_Pdf_Preset_Meta();
        $this->event_ticket_code_pattern_meta = new Event_Ticket_Code_Pattern_Meta();
        $this->event_taxonomies     = new Event_Taxonomies();
        $this->event_discovery_meta = new Event_Discovery_Meta();
        $this->event_status         = new Event_Status();
        $this->event_status_badge_renderer = new Event_Status_Badge_Renderer();
        $this->event_status_badge_resolver = new Event_Status_Badge_Resolver($this->settings, $this->event_status_badge_renderer);
        $this->event_status_badge_meta = new Event_Status_Badge_Meta();
        $this->event_recurrence     = new Event_Recurrence();
        $this->event_virtual_meta   = new Event_Virtual_Meta();
        $this->ticket_statuses      = new Ticket_Statuses();
        $this->ticket_service       = new Ticket_Service($this->settings, $this->event_capacity);
        $this->email_service        = new Email_Service($this->settings);

        // Direct paid-ticket services (Stripe / ePay.bg).
        $this->cpt_orders      = new CPT_Orders();
        $this->payment_orders  = new Payments\Payment_Order_Service();
        $this->reservations    = new Payments\Reservation_Service($this->event_capacity, $this->payment_orders);
        $this->ticket_issuance = new Payments\Ticket_Issuance_Service($this->ticket_service, $this->email_service, $this->reservations);
        $this->refunds         = new Payments\Refund_Service($this->payment_orders);
        $this->payment_service = new Payments\Payment_Service($this->settings, $this->payment_orders, $this->reservations, $this->ticket_issuance, $this->refunds);
        $this->payment_service->maybe_schedule_sweep();

        $this->calendar_service     = new Calendar_Service($this->settings);
        $this->pdf_ticket_service   = new Pdf_Ticket_Service($this->settings);
        $this->ticket_pdf_endpoint  = new Ticket_Pdf_Endpoint($this->settings, $this->pdf_ticket_service);
        $this->event_csv_export     = new Admin\Event_CSV_Export();
        $this->event_query          = new Event_Query($this->event_capacity, $this->event_status_badge_resolver);
        $this->event_filters_source = new Event_Filters_Source();
        $this->event_ajax           = new Event_Ajax($this->event_query);
        $this->event_feed_endpoint  = new Event_Feed_Endpoint($this->event_query);

        // Payment webhook/status REST controllers (payments namespace). Guarded
        // so the plugin still loads if those files are not yet present.
        if (class_exists(Payments\Payment_Webhook_Controller::class)) {
            new Payments\Payment_Webhook_Controller($this->payment_service);
        }
        if (class_exists(Payments\Payment_Order_Status_Controller::class)) {
            new Payments\Payment_Order_Status_Controller($this->payment_service);
        }
        $this->ticket_cancelled_migrator = new Admin\Ticket_Cancelled_Migrator();
        $this->saved_views_ajax     = new Saved_Views_Ajax();

        if (is_admin()) {
            $this->ticket_resend_actions = new Admin\Ticket_Resend_Actions();
            new Admin\Ticket_Bulk_Actions();
            $this->email_template_preview_assets = new Admin\Email_Template_Preview_Assets();
        }

        // AJAX controller for staff check-in.
        $this->checkin_ajax = new Checkin_Ajax($this->ticket_service);
        $this->ticket_box_ajax = new Ticket_Box_Ajax();

        // Elementor integration. Only instantiate when Elementor is actually
        // active — the loaders register Elementor hooks, so constructing them
        // without Elementor present is pure overhead.
        if (defined('ELEMENTOR_VERSION')) {
            $this->elementor_widgets_loader = new Elementor_Widgets_Loader();
            $this->elementor_theme_conditions_loader = new Elementor_Theme_Conditions_Loader();
            $this->elementor_forms_integration = new Integrations\Elementor_Forms_Integration(
                $this->ticket_service,
                $this->settings,
                $this->email_service
            );
        }
    }

    /**
     * Register frontend scripts & styles.
     */
    public function register_assets(): void
    {
        // QR scanner library (html5-qrcode), vendored locally.
        wp_register_script(
            'html5-qrcode',
            EVT_TICKETS_PLUGIN_URL . 'assets/vendor/html5-qrcode/html5-qrcode.min.js',
            [],
            '2.3.8',
            true
        );

        // Staff check-in styles & JS.
        wp_register_style(
            'evt-tickets-checkin',
            EVT_TICKETS_PLUGIN_URL . 'assets/css/checkin.css',
            [],
            '0.3.2'
        );

        wp_register_script(
            'evt-tickets-checkin',
            EVT_TICKETS_PLUGIN_URL . 'assets/js/checkin.js',
            ['jquery', 'html5-qrcode'],
            '0.3.2',
            true
        );

        // Localize script for AJAX & i18n.
        $script_data = [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('evt_checkin_ajax'),
            'i18n'    => [
                'genericErrorTitle'  => __('Error', 'Event-Tickets-for-Elementor'),
                'genericErrorBody'   => __('Unable to process the request. Please try again.', 'Event-Tickets-for-Elementor'),
                'notFoundTitle'      => __('Ticket not found', 'Event-Tickets-for-Elementor'),
                'notFoundBody'       => __('No ticket was found for this code. Please check the code and try again.', 'Event-Tickets-for-Elementor'),
                'alreadyCheckedIn'   => __('Ticket already checked in', 'Event-Tickets-for-Elementor'),
                'validTicket'        => __('Valid ticket', 'Event-Tickets-for-Elementor'),
                'checkedIn'          => __('Ticket checked in', 'Event-Tickets-for-Elementor'),
                'missingCode'        => __('Please enter a ticket code.', 'Event-Tickets-for-Elementor'),
                'missingTicketId'    => __('Missing ticket ID.', 'Event-Tickets-for-Elementor'),
                'scannerUnavailable' => __('QR scanner is unavailable on this device or browser.', 'Event-Tickets-for-Elementor'),
                'scannerStarting'    => __('Starting camera…', 'Event-Tickets-for-Elementor'),
                'scannerStopping'    => __('Stopping camera…', 'Event-Tickets-for-Elementor'),
                'scannerHint'        => __('Point the camera at the ticket QR code.', 'Event-Tickets-for-Elementor'),
            ],
        ];

        wp_localize_script(
            'evt-tickets-checkin',
            'EvtTicketsCheckin',
            $script_data
        );

        // Ticket view widget styles.
        wp_register_style(
            'evt-tickets-ticket-view',
            EVT_TICKETS_PLUGIN_URL . 'assets/css/ticket-view.css',
            [],
            '0.1.0'
        );

        wp_register_style(
            'evt-tickets-ticket-virtual',
            EVT_TICKETS_PLUGIN_URL . 'assets/css/ticket-virtual.css',
            [],
            '0.1.0'
        );

        // Resend widget styles.
        wp_register_style(
            'evt-tickets-resend',
            EVT_TICKETS_PLUGIN_URL . 'assets/css/resend.css',
            [],
            '0.1.0'
        );

        wp_register_style(
            'evt-tickets-ticket-box',
            EVT_TICKETS_PLUGIN_URL . 'assets/css/ticket-box.css',
            [],
            '0.1.0'
        );

        wp_register_script(
            'evt-tickets-ticket-box',
            EVT_TICKETS_PLUGIN_URL . 'assets/js/ticket-box.js',
            ['jquery'],
            '0.1.0',
            true
        );

        wp_localize_script(
            'evt-tickets-ticket-box',
            'EvtTicketsBox',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('evt_ticket_box'),
                'i18n'    => [
                    'submit'      => __('Submit', 'Event-Tickets-for-Elementor'),
                    'submitting'  => __('Submitting…', 'Event-Tickets-for-Elementor'),
                    'success'     => __('Tickets created and emailed.', 'Event-Tickets-for-Elementor'),
                    'error'       => __('Something went wrong. Please try again.', 'Event-Tickets-for-Elementor'),
                ],
            ]
        );

        // Payment return-page status polling (paid direct checkout).
        // Enqueued only when the buyer lands back with ?evt_order=...
        wp_register_script(
            'evt-payment-return',
            EVT_TICKETS_PLUGIN_URL . 'assets/js/payment-return.js',
            [],
            '0.1.0',
            true
        );

        $evt_order_key = isset($_GET['evt_order']) ? sanitize_text_field(wp_unslash($_GET['evt_order'])) : '';
        if ('' !== $evt_order_key) {
            $evt_poll_url = rest_url('evt/v1/orders/' . rawurlencode($evt_order_key));
            $evt_ref      = isset($_GET['evt_ref']) ? sanitize_text_field(wp_unslash($_GET['evt_ref'])) : '';
            if ('' !== $evt_ref) {
                $evt_poll_url = add_query_arg('ref', $evt_ref, $evt_poll_url);
            }

            wp_enqueue_script('evt-payment-return');
            wp_localize_script(
                'evt-payment-return',
                'EvtPaymentReturn',
                [
                    'pollUrl' => esc_url_raw($evt_poll_url),
                    'i18n'    => [
                        'pending' => __('Payment received — your seats are held. Tickets are being prepared…', 'Event-Tickets-for-Elementor'),
                        'paid'    => __('Payment confirmed — tickets are on their way to your inbox.', 'Event-Tickets-for-Elementor'),
                        'failed'  => __('Payment was not completed. Your seats have been released.', 'Event-Tickets-for-Elementor'),
                        'timeout' => __('Still confirming your payment — tickets will arrive by email shortly.', 'Event-Tickets-for-Elementor'),
                    ],
                ]
            );
        }

        // Events calendar + summary + map assets.
        wp_register_style(
            'evt-tickets-events-calendar',
            EVT_TICKETS_PLUGIN_URL . 'assets/css/events-calendar.css',
            [],
            '0.1.0'
        );

        wp_register_script(
            'evt-tickets-events-calendar',
            EVT_TICKETS_PLUGIN_URL . 'assets/js/events-calendar.js',
            ['jquery'],
            '0.1.0',
            true
        );

        wp_localize_script(
            'evt-tickets-events-calendar',
            'EvtTicketsCalendar',
            [
                'ajaxUrl' => Event_Feed_Endpoint::calendar_feed_url(),
                'startOfWeek' => (int) get_option('start_of_week', 1),
                'timelineStartHour' => 7,
                'timelineEndHour' => 21,
            ]
        );

        wp_register_style(
            'evt-tickets-events-summary',
            EVT_TICKETS_PLUGIN_URL . 'assets/css/events-summary.css',
            [],
            '0.1.0'
        );

        wp_register_style(
            'evt-tickets-events-list',
            EVT_TICKETS_PLUGIN_URL . 'assets/css/events-list.css',
            [],
            '0.1.0'
        );

        wp_register_style(
            'leaflet',
            EVT_TICKETS_PLUGIN_URL . 'assets/vendor/leaflet/leaflet.css',
            [],
            '1.9.4'
        );

        wp_register_script(
            'leaflet',
            EVT_TICKETS_PLUGIN_URL . 'assets/vendor/leaflet/leaflet.js',
            [],
            '1.9.4',
            true
        );

        wp_register_style(
            'evt-tickets-events-map',
            EVT_TICKETS_PLUGIN_URL . 'assets/css/events-map.css',
            [],
            '0.1.0'
        );

        wp_register_script(
            'evt-tickets-events-map',
            EVT_TICKETS_PLUGIN_URL . 'assets/js/events-map.js',
            ['leaflet'],
            '0.1.0',
            true
        );

        wp_register_style(
            'evt-tickets-events-browser',
            EVT_TICKETS_PLUGIN_URL . 'assets/css/events-browser.css',
            [],
            EVT_TICKETS_VERSION
        );

        wp_register_style(
            'evt-tickets-events-browser-mobile',
            EVT_TICKETS_PLUGIN_URL . 'assets/css/events-browser-mobile.css',
            ['evt-tickets-events-browser'],
            EVT_TICKETS_VERSION
        );

        wp_register_script(
            'evt-tickets-events-browser-core',
            EVT_TICKETS_PLUGIN_URL . 'assets/js/events-browser/core.js',
            ['jquery'],
            EVT_TICKETS_VERSION,
            true
        );

        wp_register_script(
            'evt-tickets-events-browser-filters',
            EVT_TICKETS_PLUGIN_URL . 'assets/js/events-browser/filters.js',
            ['evt-tickets-events-browser-core'],
            EVT_TICKETS_VERSION,
            true
        );

        wp_register_script(
            'evt-tickets-events-browser-views',
            EVT_TICKETS_PLUGIN_URL . 'assets/js/events-browser/views.js',
            ['evt-tickets-events-browser-core', 'leaflet'],
            EVT_TICKETS_VERSION,
            true
        );

        wp_register_script(
            'evt-tickets-events-browser',
            EVT_TICKETS_PLUGIN_URL . 'assets/js/events-browser/init.js',
            ['evt-tickets-events-browser-filters', 'evt-tickets-events-browser-views'],
            EVT_TICKETS_VERSION,
            true
        );

        wp_localize_script(
            'evt-tickets-events-browser',
            'EvtTicketsEventBrowser',
            [
                'ajaxUrl'      => Event_Feed_Endpoint::calendar_feed_url(),
                'startOfWeek'  => (int) get_option('start_of_week', 1),
            ]
        );

        wp_register_style(
            'evt-tickets-month-calendar',
            EVT_TICKETS_PLUGIN_URL . 'assets/css/events-month-calendar.css',
            [],
            '0.1.0'
        );

        wp_register_script(
            'evt-tickets-month-calendar',
            EVT_TICKETS_PLUGIN_URL . 'assets/js/events-month-calendar.js',
            ['jquery'],
            '0.1.0',
            true
        );

        wp_localize_script(
            'evt-tickets-month-calendar',
            'EvtTicketsMonthCal',
            [
                'ajaxUrl' => Event_Feed_Endpoint::month_feed_url(),
            ]
        );

        wp_register_style(
            'evt-tickets-filter-bar',
            EVT_TICKETS_PLUGIN_URL . 'assets/css/events-filter-bar.css',
            [],
            '0.1.0'
        );

        wp_register_script(
            'evt-tickets-filter-bar',
            EVT_TICKETS_PLUGIN_URL . 'assets/js/events-filter-bar.js',
            ['jquery'],
            '0.1.0',
            true
        );

        wp_localize_script(
            'evt-tickets-filter-bar',
            'EvtTicketsFilterBar',
            [
                'ajaxUrl'   => admin_url('admin-ajax.php'),
                'nonce'     => wp_create_nonce('evt_saved_views'),
                'loggedIn'  => is_user_logged_in() ? 1 : 0,
                'i18n'      => [
                    'savePrompt' => __('Name this view', 'Event-Tickets-for-Elementor'),
                ],
            ]
        );
    }

    /**
     * Helpers.
     */
    public function tickets(): Ticket_Service
    {
        return $this->ticket_service;
    }

    public function mailer(): Email_Service
    {
        return $this->email_service;
    }

    public function calendar(): Calendar_Service
    {
        return $this->calendar_service;
    }

    public function payment_service(): Payments\Payment_Service
    {
        return $this->payment_service;
    }

    public function pdf(): Pdf_Ticket_Service
    {
        return $this->pdf_ticket_service;
    }

    public function pdf_endpoint(): Ticket_Pdf_Endpoint
    {
        return $this->ticket_pdf_endpoint;
    }

    /**
     * Activation / deactivation.
     */
    public static function activate(): void
    {
        $cpt = new CPT_Tickets();
        $cpt->register();
        $events = new CPT_Events();
        $events->register();
        Staff_Access_Manager::activate();

        // Apply schema upgrades (postmeta index) before the site goes live.
        Schema::maybe_upgrade();

        // Register ICS rewrites before flushing.
        $settings = new Settings();
        $calendar = new Calendar_Service($settings);
        $calendar->add_global_ics_rewrite();
        $calendar->add_ticket_ics_rewrite();

        // Register public event feed rewrites before flushing.
        Event_Feed_Endpoint::register_rewrites();
        flush_rewrite_rules();

        // First-run onboarding redirect.
        update_option('evt_tickets_do_activation_redirect', 1, false);
    }

    public static function deactivate(): void
    {
        // Remove the managed Staff role and plugin caps from Admin/Editor roles
        // so they do not persist in the DB after the plugin is deactivated.
        Staff_Access_Manager::deactivate();
        flush_rewrite_rules();
    }
}
