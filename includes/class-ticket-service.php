<?php

namespace EventTicketsElementor;

use WP_Post;
use WP_Query;
use EventTicketsElementor\Tickets\Ticket_Email_Normalizer;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Core ticket logic (create, generate codes, query, check-in).
 * Source-agnostic: can be used by Elementor, Woo add-on, etc.
 */
class Ticket_Service
{

    /** @var Settings */
    protected $settings;

    /** @var Event_Capacity */
    protected $event_capacity;

    public function __construct(Settings $settings, Event_Capacity $event_capacity)
    {
        $this->settings       = $settings;
        $this->event_capacity = $event_capacity;
    }

    /**
     * Determine if a ticket is active for capacity and operational purposes.
     */
    public function is_active_ticket(int $ticket_id): bool
    {
        $ticket = get_post($ticket_id);
        if (! $ticket || CPT_Tickets::POST_TYPE !== $ticket->post_type) {
            return false;
        }

        return 'publish' === $ticket->post_status;
    }

    /**
     * Cancel a ticket (moves it to a dedicated post_status and records reason).
     *
     * @param int         $ticket_id
     * @param string|null $reason
     * @return bool
     */
    public function cancel_ticket(int $ticket_id, ?string $reason = null): bool
    {
        $ticket = get_post($ticket_id);
        if (! $ticket || CPT_Tickets::POST_TYPE !== $ticket->post_type) {
            return false;
        }

        update_post_meta($ticket_id, '_ticket_status', 'cancelled');
        update_post_meta($ticket_id, '_ticket_cancelled_at', current_time('mysql'));
        if (null !== $reason && '' !== $reason) {
            update_post_meta($ticket_id, '_ticket_cancel_reason', sanitize_text_field($reason));
        }

        $updated = wp_update_post(
            [
                'ID'          => $ticket_id,
                'post_status' => Ticket_Statuses::STATUS_CANCELLED,
            ],
            true
        );

        $event_id = (int) get_post_meta($ticket_id, '_ticket_event_id', true);
        if ($event_id) {
            delete_transient('evt_capacity_' . $event_id);
        }

        $this->sync_wallet($ticket_id);

        return ! is_wp_error($updated);
    }

    /**
     * Fetch tickets for an event.
     *
     * Supported $args:
     * - post_status (string|array) defaults ['publish', evt_cancelled]
     * - limit (int) defaults 50
     * - orderby (string) defaults date
     * - order (ASC|DESC) defaults DESC
     *
     * @param int   $event_id
     * @param array $args
     * @return WP_Post[]
     */
    public function get_tickets_for_event(int $event_id, array $args = []): array
    {
        $event_id = absint($event_id);
        if (! $event_id) {
            return [];
        }

        $query = new WP_Query(
            [
                'post_type'              => CPT_Tickets::POST_TYPE,
                'post_status'            => $args['post_status'] ?? ['publish', Ticket_Statuses::STATUS_CANCELLED],
                'posts_per_page'         => $args['limit'] ?? 50,
                'orderby'                => $args['orderby'] ?? 'date',
                'order'                  => $args['order'] ?? 'DESC',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'meta_query'             => [
                    [
                        'key'   => '_ticket_event_id',
                        'value' => $event_id,
                    ],
                ],
            ]
        );

        return is_array($query->posts) ? $query->posts : [];
    }

    /**
     * Count active tickets for a given event.
     *
     * Uses WP_Query's found_posts so the count is performed by the database
     * (a lightweight COUNT query) instead of loading every matching ticket ID
     * into memory.
     *
     * @param int      $event_id
     * @param int|null $limit If provided, counts up to this number.
     */
    public function count_active_tickets_for_event(int $event_id, ?int $limit = null): int
    {
        $event_id = absint($event_id);
        if (! $event_id) {
            return 0;
        }

        // When no limit is given we only need one row (plus the total count).
        $posts_per_page = (null === $limit) ? 1 : max(1, $limit);

        $query = new WP_Query(
            [
                'post_type'              => CPT_Tickets::POST_TYPE,
                'post_status'            => 'publish',
                'posts_per_page'         => $posts_per_page,
                'fields'                 => 'ids',
                'no_found_rows'          => false,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'meta_query'             => [
                    [
                        'key'   => '_ticket_event_id',
                        'value' => $event_id,
                    ],
                    [
                        'relation' => 'OR',
                        [
                            'key'     => '_ticket_status',
                            'compare' => 'NOT EXISTS',
                        ],
                        [
                            'key'     => '_ticket_status',
                            'value'   => 'cancelled',
                            'compare' => '!=',
                        ],
                    ],
                ],
            ]
        );

        $count = (int) $query->found_posts;
        if (null !== $limit && $count > $limit) {
            $count = $limit;
        }

        return $count;
    }

    /**
     * Create a ticket from generic args.
     *
     * Expected keys in $args:
     * - attendee_name
     * - attendee_email
     * - event_name
     * - event_start (optional)
     * - event_end (optional)
     * - event_location (optional)
     * - event_id (optional linked Event CPT id)
     * - source (e.g. 'elementor_form', 'woo_order')
     * - source_id (string)
     * - form_snapshot (optional array)
     *
     * @param array $args
     * @return int|\WP_Error Ticket post ID or error.
     */
    public function create_ticket(array $args)
    {
        $attendee_name  = isset($args['attendee_name']) ? sanitize_text_field($args['attendee_name']) : '';
        $attendee_email = isset($args['attendee_email']) ? sanitize_email($args['attendee_email']) : '';
        $event_name     = isset($args['event_name']) ? sanitize_text_field($args['event_name']) : '';
        $event_id       = isset($args['event_id']) ? absint($args['event_id']) : 0;

        if (empty($attendee_email)) {
            return new \WP_Error('evt_missing_email', __('Ticket must have an attendee email.', 'Event-Tickets-for-Elementor'));
        }

        // Event details with fallback to linked event and defaults from settings.
        $event_start    = isset($args['event_start']) ? sanitize_text_field($args['event_start']) : '';
        $event_end      = isset($args['event_end']) ? sanitize_text_field($args['event_end']) : '';
        $event_location = isset($args['event_location']) ? sanitize_text_field($args['event_location']) : '';

        if ($event_id) {
            if ($this->event_capacity->is_full($event_id)) {
                return new \WP_Error('evt_event_full', __('This event is at capacity. No more tickets can be issued.', 'Event-Tickets-for-Elementor'));
            }

            $limit_one = (int) get_post_meta($event_id, Event_Timeslots::META_KEY_LIMIT_ONE_EMAIL, true);
            $max_per_email = (int) get_post_meta($event_id, Event_Timeslots::META_KEY_MAX_PER_EMAIL, true);
            $normalize = (int) get_post_meta($event_id, Event_Timeslots::META_KEY_NORMALIZE_EMAIL, true);
            if ($limit_one) {
                $max_per_email = 1;
            }
            if ($max_per_email > 0 && $attendee_email) {
                $normalized = $normalize ? Ticket_Email_Normalizer::normalize($attendee_email) : '';
                $current = $this->count_tickets_for_event_email($event_id, $attendee_email, $normalized);
                if ($current >= $max_per_email) {
                    return new \WP_Error('evt_ticket_limit', __('This email already reached the ticket limit for this event.', 'Event-Tickets-for-Elementor'));
                }
            }

            $snapshot = $this->build_snapshot_from_event($event_id);
            if ($snapshot) {
                $event_name     = $snapshot['name'];
                $event_start    = $event_start ?: $snapshot['start'];
                $event_end      = $event_end ?: $snapshot['end'];
                $event_location = $event_location ?: $snapshot['location'];
            }
        }

        if ('' === $event_name) {
            return new \WP_Error('evt_missing_event', __('Ticket must be linked to an event or include an event name.', 'Event-Tickets-for-Elementor'));
        }

        if ('' === $event_start) {
            return new \WP_Error('evt_missing_event_start', __('Ticket must be linked to an event or include an event start date/time.', 'Event-Tickets-for-Elementor'));
        }

        // Create bare ticket post.
        $post_id = wp_insert_post([
            'post_type'   => CPT_Tickets::POST_TYPE,
            'post_title'  => $attendee_name
                /* translators: %s is the attendee name. */
                ? sprintf(__('Ticket for %s', 'Event-Tickets-for-Elementor'), $attendee_name)
                : ($event_name ?: __('Event Ticket', 'Event-Tickets-for-Elementor')),
            'post_status' => 'publish',
        ]);

        if (is_wp_error($post_id) || ! $post_id) {
            return $post_id;
        }

        // Generate ticket code from settings.
        $ticket_code = $this->generate_ticket_code($post_id, $event_name, $event_id);

        // Core meta.
        update_post_meta($post_id, '_ticket_code', $ticket_code);
        update_post_meta($post_id, '_ticket_name', $attendee_name);
        update_post_meta($post_id, '_ticket_email', $attendee_email);
        update_post_meta($post_id, '_ticket_event_id', $event_id);
        update_post_meta($post_id, '_ticket_event_name', $event_name);
        update_post_meta($post_id, '_ticket_status', 'pending');
        delete_post_meta($post_id, '_ticket_checked_in_at');

        if ($event_id) {
            $limit_one = (int) get_post_meta($event_id, Event_Timeslots::META_KEY_LIMIT_ONE_EMAIL, true);
            $max_per_email = (int) get_post_meta($event_id, Event_Timeslots::META_KEY_MAX_PER_EMAIL, true);
            $normalize = (int) get_post_meta($event_id, Event_Timeslots::META_KEY_NORMALIZE_EMAIL, true);
            if (($limit_one || $max_per_email > 0) && $normalize && $attendee_email) {
                $normalized = Ticket_Email_Normalizer::normalize($attendee_email);
                if ($normalized) {
                    update_post_meta($post_id, '_ticket_email_normalized', $normalized);
                }
            }
        }

        // Event meta (only if present).
        if ($event_start) {
            update_post_meta($post_id, '_ticket_event_start', $event_start);
        }

        if ($event_end) {
            update_post_meta($post_id, '_ticket_event_end', $event_end);
        }

        if ($event_location) {
            update_post_meta($post_id, '_ticket_event_location', $event_location);
        }

        // Source meta.
        if (! empty($args['source'])) {
            update_post_meta($post_id, '_ticket_source', sanitize_text_field($args['source']));
        }
        if (! empty($args['source_id'])) {
            update_post_meta($post_id, '_ticket_source_id', sanitize_text_field($args['source_id']));
        }

        if ($event_id) {
            delete_transient('evt_capacity_' . $event_id);
        }

        // Form snapshot (if provided).
        if (! empty($args['form_snapshot']) && is_array($args['form_snapshot'])) {
            if (! empty($args['form_snapshot']['fields'])) {
                update_post_meta($post_id, '_ticket_form_fields', $args['form_snapshot']['fields']);
            }
            if (! empty($args['form_snapshot']['labels'])) {
                update_post_meta($post_id, '_ticket_form_labels', $args['form_snapshot']['labels']);
            }
            if (! empty($args['form_snapshot']['meta']) && is_array($args['form_snapshot']['meta'])) {
                foreach ($args['form_snapshot']['meta'] as $key => $value) {
                    update_post_meta($post_id, '_ticket_form_' . sanitize_key($key), sanitize_text_field($value));
                }
            }
        }

        return $post_id;
    }

    /**
     * Generate a ticket code based on settings and event name.
     *
     * Pattern supports tokens:
     * - {RANDOM} or {RANDOM:n}
     * - {ID}
     * - {EVENT_SLUG}
     * - {DATEYMD}
     *
     * @param int    $ticket_id
     * @param string $event_name
     * @param int    $event_id
     * @return string
     */
    public function generate_ticket_code(int $ticket_id, string $event_name = '', int $event_id = 0): string
    {
        $pattern = 'EVT-{RANDOM:10}';
        if ($event_id) {
            $event_pattern = (string) get_post_meta($event_id, Event_Ticket_Code_Pattern_Meta::META_KEY, true);
            $event_pattern = trim($event_pattern);
            if ('' !== $event_pattern) {
                $pattern = $event_pattern;
            }
        }

        $event_slug = $event_name ? sanitize_title($event_name) : 'event';
        $date_ymd   = gmdate('Ymd');

        $code = $pattern;

        // {ID}
        $code = str_replace('{ID}', (string) $ticket_id, $code);

        // {EVENT_SLUG}
        $code = str_replace('{EVENT_SLUG}', $event_slug, $code);

        // {DATEYMD}
        $code = str_replace('{DATEYMD}', $date_ymd, $code);

        // {RANDOM:n} and {RANDOM}
        $code = preg_replace_callback(
            '/\{RANDOM(?::(\d+))?\}/',
            function ($matches) {
                $length = isset($matches[1]) ? (int) $matches[1] : 8;
                return $this->random_string(max(4, $length));
            },
            $code
        );

        return strtoupper($code);
    }

    /**
     * Basic random alphanumeric string.
     */
    protected function random_string(int $length): string
    {
        $chars  = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $result = '';

        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[wp_rand(0, strlen($chars) - 1)];
        }

        return $result;
    }

    /**
     * Get a ticket by code.
     *
     * @param string $code
     * @return WP_Post|null
     */
    public function get_ticket_by_code(string $code): ?WP_Post
    {
        $code = trim($code);
        if ('' === $code) {
            return null;
        }

        $query = new WP_Query([
            'post_type'      => CPT_Tickets::POST_TYPE,
            'post_status'    => ['publish', Ticket_Statuses::STATUS_CANCELLED],
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'   => '_ticket_code',
                    'value' => $code,
                ],
            ],
        ]);

        if (! $query->have_posts()) {
            return null;
        }

        return $query->posts[0];
    }

    /**
     * Check if an email already has a ticket for this event.
     */
    public function has_ticket_for_event(int $event_id, string $email, string $normalized = ''): bool
    {
        $event_id = absint($event_id);
        $email = sanitize_email($email);
        $normalized = sanitize_email($normalized);
        if (! $event_id || '' === $email) {
            return false;
        }

        $email_meta = [
            [
                'key'   => '_ticket_email',
                'value' => $email,
            ],
        ];
        if ($normalized && $normalized !== $email) {
            $email_meta[] = [
                'key'   => '_ticket_email',
                'value' => $normalized,
            ];
            $email_meta[] = [
                'key'   => '_ticket_email_normalized',
                'value' => $normalized,
            ];
        }

        $query = new WP_Query([
            'post_type'      => CPT_Tickets::POST_TYPE,
            'post_status'    => ['publish', Ticket_Statuses::STATUS_CANCELLED],
            'posts_per_page' => 1,
            'no_found_rows'  => true,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'   => '_ticket_event_id',
                    'value' => $event_id,
                ],
                array_merge(['relation' => 'OR'], $email_meta),
            ],
        ]);

        return ! empty($query->posts);
    }

    /**
     * Count active tickets for an email + event.
     */
    public function count_tickets_for_event_email(int $event_id, string $email, string $normalized = ''): int
    {
        $event_id = absint($event_id);
        $email = sanitize_email($email);
        $normalized = sanitize_email($normalized);
        if (! $event_id || '' === $email) {
            return 0;
        }

        $email_meta = [
            [
                'key'   => '_ticket_email',
                'value' => $email,
            ],
        ];
        if ($normalized && $normalized !== $email) {
            $email_meta[] = [
                'key'   => '_ticket_email',
                'value' => $normalized,
            ];
            $email_meta[] = [
                'key'   => '_ticket_email_normalized',
                'value' => $normalized,
            ];
        }

        $query = new WP_Query([
            'post_type'              => CPT_Tickets::POST_TYPE,
            'post_status'            => ['publish'],
            'posts_per_page'         => 1,
            'fields'                 => 'ids',
            'no_found_rows'          => false,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'meta_query'             => [
                [
                    'key'   => '_ticket_event_id',
                    'value' => $event_id,
                ],
                array_merge(['relation' => 'OR'], $email_meta),
            ],
        ]);

        return (int) $query->found_posts;
    }

    /**
     * Mark a ticket as checked in.
     *
     * @param int $ticket_id
     * @param int $user_id
     * @return void
     */
    public function check_in_ticket(int $ticket_id, int $user_id = 0): void
    {
        if (! $this->is_active_ticket($ticket_id)) {
            return;
        }

        $now = current_time('mysql');
        update_post_meta($ticket_id, '_ticket_status', 'checked_in');
        update_post_meta($ticket_id, '_ticket_checked_in_at', $now);

        if ($user_id) {
            update_post_meta($ticket_id, '_ticket_checked_in_by', absint($user_id));
        }

        $this->sync_wallet($ticket_id);
    }

    private function sync_wallet(int $ticket_id): void
    {
        if (! class_exists(Plugin::class)) {
            return;
        }

        $plugin = Plugin::instance();
        if (! $plugin || ! method_exists($plugin, 'google_wallet')) {
            return;
        }

        $wallet = $plugin->google_wallet();
        if ($wallet instanceof Google_Wallet_Service) {
            $wallet->sync_ticket_state($ticket_id);
        }
    }

    /**
     * Find tickets by criteria (email, event, status).
     *
     * @param array $args
     * @return WP_Post[]
     */
    public function find_tickets(array $args): array
    {
        $meta_query = [];

        if (! empty($args['email'])) {
            $meta_query[] = [
                'key'   => '_ticket_email',
                'value' => $args['email'],
            ];
        }

        if (! empty($args['event_name'])) {
            $meta_query[] = [
                'key'   => '_ticket_event_name',
                'value' => $args['event_name'],
            ];
        }

        if (! empty($args['event_id'])) {
            $meta_query[] = [
                'key'   => '_ticket_event_id',
                'value' => absint($args['event_id']),
            ];
        }

        if (! empty($args['status'])) {
            $meta_query[] = [
                'key'   => '_ticket_status',
                'value' => $args['status'],
            ];
        }

        if (empty($meta_query)) {
            return [];
        }

        $query_args = [
            'post_type'      => CPT_Tickets::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => $args['posts_per_page'] ?? 50,
            'meta_query'     => $meta_query,
            'orderby'        => $args['orderby'] ?? 'date',
            'order'          => $args['order'] ?? 'DESC',
        ];

        $query = new WP_Query($query_args);

        return $query->posts;
    }

    /**
     * Get the latest ticket for a given email (and optionally event).
     *
     * @param string $email
     * @param string $event_name
     * @return WP_Post|null
     */
    public function get_latest_ticket_for_email(string $email, string $event_name = ''): ?WP_Post
    {
        $email = sanitize_email($email);
        if ('' === $email) {
            return null;
        }

        $args = [
            'email'          => $email,
            'posts_per_page' => 1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        if ('' !== $event_name) {
            $args['event_name'] = sanitize_text_field($event_name);
        }

        $tickets = $this->find_tickets($args);

        return $tickets[0] ?? null;
    }

    /**
     * Build an event snapshot from an Event CPT record.
     *
     * @param int $event_id
     * @return array<string,string>|null
     */
    protected function build_snapshot_from_event(int $event_id): ?array
    {
        $event = get_post($event_id);
        if (! $event || CPT_Events::POST_TYPE !== $event->post_type) {
            return null;
        }

        $start    = get_post_meta($event_id, '_evt_event_start', true);
        $end      = get_post_meta($event_id, '_evt_event_end', true);
        $location = get_post_meta($event_id, '_evt_event_location', true);

        return [
            'name'     => $event->post_title,
            'start'    => (string) $start,
            'end'      => (string) $end,
            'location' => (string) $location,
        ];
    }
}
