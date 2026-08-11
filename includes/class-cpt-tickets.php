<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Custom post type for Tickets.
 *
 * Stores attendee + event snapshot data.
 * Phase 2: adds a "Linked Event" field pointing to evt_event CPT via _ticket_event_id.
 */
class CPT_Tickets
{

    const POST_TYPE = 'evt_ticket';
    public const CAP_EDIT_POST = 'edit_evt_ticket';
    public const CAP_READ_POST = 'read_evt_ticket';
    public const CAP_DELETE_POST = 'delete_evt_ticket';
    public const CAP_EDIT_POSTS = 'edit_evt_tickets';
    public const CAP_EDIT_OTHERS = 'edit_others_evt_tickets';
    public const CAP_PUBLISH = 'publish_evt_tickets';
    public const CAP_READ_PRIVATE = 'read_private_evt_tickets';
    public const CAP_DELETE_POSTS = 'delete_evt_tickets';
    public const CAP_DELETE_PRIVATE = 'delete_private_evt_tickets';
    public const CAP_DELETE_PUBLISHED = 'delete_published_evt_tickets';
    public const CAP_DELETE_OTHERS = 'delete_others_evt_tickets';
    public const CAP_EDIT_PRIVATE = 'edit_private_evt_tickets';
    public const CAP_EDIT_PUBLISHED = 'edit_published_evt_tickets';

    public function __construct()
    {
        add_action('init', [$this, 'register']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post', [$this, 'save_meta'], 10, 2);

        add_filter('manage_edit-evt_ticket_columns', [$this, 'edit_columns']);
        add_action('manage_evt_ticket_posts_custom_column', [$this, 'render_columns'], 10, 2);

        add_action('restrict_manage_posts', [$this, 'render_event_filter']);
        add_action('pre_get_posts', [$this, 'filter_by_event']);
    }

    /**
     * Register the Tickets CPT.
     */
    public function register(): void
    {
        $labels = array(
            'name'               => __('Tickets', 'Event-Tickets-for-Elementor'),
            'singular_name'      => __('Ticket', 'Event-Tickets-for-Elementor'),
            'menu_name'          => __('Tickets', 'Event-Tickets-for-Elementor'),
            'name_admin_bar'     => __('Ticket', 'Event-Tickets-for-Elementor'),
            'add_new'            => __('Add New', 'Event-Tickets-for-Elementor'),
            'add_new_item'       => __('Add New Ticket', 'Event-Tickets-for-Elementor'),
            'edit_item'          => __('Edit Ticket', 'Event-Tickets-for-Elementor'),
            'new_item'           => __('New Ticket', 'Event-Tickets-for-Elementor'),
            'view_item'          => __('View Ticket', 'Event-Tickets-for-Elementor'),
            'search_items'       => __('Search Tickets', 'Event-Tickets-for-Elementor'),
            'not_found'          => __('No tickets found.', 'Event-Tickets-for-Elementor'),
            'not_found_in_trash' => __('No tickets found in Trash.', 'Event-Tickets-for-Elementor'),
            'all_items'          => __('Tickets', 'Event-Tickets-for-Elementor'),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => false, // Attached under "Event Tickets" menu.
            'show_in_rest'       => false,
            'supports'           => array('title'),
            'capability_type'    => ['evt_ticket', 'evt_tickets'],
            'capabilities'       => [
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
        );

        register_post_type(self::POST_TYPE, $args);

        $this->register_meta();
    }

    /**
     * Register ticket post meta with centralized sanitization so every write
     * path (save handlers, REST, integrations) is covered consistently.
     */
    private function register_meta(): void
    {
        foreach ($this->meta_schema() as $key => $config) {
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
     * @return array<string,array{type:string,sanitize?:callable}>
     */
    private function meta_schema(): array
    {
        $string_array_sanitizer = static function ($values) {
            if (! is_array($values)) {
                return sanitize_text_field((string) $values);
            }
            foreach ($values as $k => $v) {
                $values[$k] = is_scalar($v) ? sanitize_text_field((string) $v) : $v;
            }
            return $values;
        };

        return [
            // Attendee identity.
            '_ticket_name'              => ['type' => 'string', 'sanitize' => 'sanitize_text_field'],
            '_ticket_email'             => ['type' => 'string', 'sanitize' => 'sanitize_email'],
            '_ticket_phone'             => ['type' => 'string', 'sanitize' => 'sanitize_text_field'],
            '_ticket_email_normalized'  => ['type' => 'string', 'sanitize' => 'sanitize_text_field'],
            // Code / status.
            '_ticket_code'              => ['type' => 'string', 'sanitize' => 'sanitize_text_field'],
            '_ticket_status'            => ['type' => 'string', 'sanitize' => 'sanitize_key'],
            '_ticket_checked_in_at'     => ['type' => 'string', 'sanitize' => 'sanitize_text_field'],
            '_ticket_cancelled_at'      => ['type' => 'string', 'sanitize' => 'sanitize_text_field'],
            '_ticket_cancel_reason'     => ['type' => 'string', 'sanitize' => 'sanitize_text_field'],
            '_ticket_cancel_source'     => ['type' => 'string', 'sanitize' => 'sanitize_key'],
            '_ticket_cancelled_by'      => ['type' => 'integer', 'sanitize' => 'absint'],
            '_ticket_refund_status'     => ['type' => 'string', 'sanitize' => 'sanitize_key'],
            // Event snapshot.
            '_ticket_event_id'          => ['type' => 'integer', 'sanitize' => 'absint'],
            '_ticket_event_name'        => ['type' => 'string', 'sanitize' => 'sanitize_text_field'],
            '_ticket_event_start'       => ['type' => 'string', 'sanitize' => 'sanitize_text_field'],
            '_ticket_event_end'         => ['type' => 'string', 'sanitize' => 'sanitize_text_field'],
            '_ticket_event_location'    => ['type' => 'string', 'sanitize' => 'sanitize_text_field'],
            '_ticket_timeslot_id'       => ['type' => 'string', 'sanitize' => 'sanitize_key'],
            // Provenance.
            '_ticket_source'            => ['type' => 'string', 'sanitize' => 'sanitize_key'],
            '_ticket_source_id'         => ['type' => 'string', 'sanitize' => 'sanitize_text_field'],
            // Payments.
            '_ticket_payment_provider'  => ['type' => 'string', 'sanitize' => 'sanitize_key'],
            '_ticket_payment_status'    => ['type' => 'string', 'sanitize' => 'sanitize_key'],
            '_ticket_order_id'          => ['type' => 'integer', 'sanitize' => 'absint'],
            '_ticket_order_item_id'     => ['type' => 'integer', 'sanitize' => 'absint'],
            // PDF hash.
            '_ticket_pdf_hash'          => ['type' => 'string', 'sanitize' => 'sanitize_text_field'],
            // Form snapshots: sanitized on write to prevent latent stored XSS.
            '_ticket_form_fields'       => ['type' => 'array', 'sanitize' => $string_array_sanitizer],
            '_ticket_form_labels'       => ['type' => 'array', 'sanitize' => $string_array_sanitizer],
        ];
    }

    /**
     * Add meta boxes for Tickets.
     */
    public function add_meta_boxes(): void
    {
        add_meta_box(
            'evt_ticket_attendee',
            __('Attendee & Ticket', 'Event-Tickets-for-Elementor'),
            array($this, 'render_attendee_meta_box'),
            self::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'evt_ticket_event',
            __('Event Snapshot', 'Event-Tickets-for-Elementor'),
            array($this, 'render_event_meta_box'),
            self::POST_TYPE,
            'normal',
            'default'
        );

        add_meta_box(
            'evt_ticket_linked_event',
            __('Linked Event', 'Event-Tickets-for-Elementor'),
            array($this, 'render_linked_event_meta_box'),
            self::POST_TYPE,
            'side',
            'default'
        );
    }

    /**
     * Attendee & ticket meta box.
     *
     * @param \WP_Post $post
     */
    public function render_attendee_meta_box(\WP_Post $post): void
    {
        wp_nonce_field('evt_save_ticket_meta', 'evt_ticket_meta_nonce');

        $code          = get_post_meta($post->ID, '_ticket_code', true);
        $name          = get_post_meta($post->ID, '_ticket_name', true);
        $email         = get_post_meta($post->ID, '_ticket_email', true);
        $status        = get_post_meta($post->ID, '_ticket_status', true);
        $checked_in_at = get_post_meta($post->ID, '_ticket_checked_in_at', true);

        if (! $status) {
            $status = 'pending';
        }

?>
        <p>
            <label for="evt_ticket_name"><strong><?php esc_html_e('Attendee name', 'Event-Tickets-for-Elementor'); ?></strong></label><br />
            <input
                type="text"
                id="evt_ticket_name"
                name="evt_ticket_name"
                class="regular-text"
                value="<?php echo esc_attr($name); ?>" />
        </p>

        <p>
            <label for="evt_ticket_email"><strong><?php esc_html_e('Attendee email', 'Event-Tickets-for-Elementor'); ?></strong></label><br />
            <input
                type="email"
                id="evt_ticket_email"
                name="evt_ticket_email"
                class="regular-text"
                value="<?php echo esc_attr($email); ?>" />
        </p>

        <p>
            <label for="evt_ticket_code"><strong><?php esc_html_e('Ticket code', 'Event-Tickets-for-Elementor'); ?></strong></label><br />
            <input
                type="text"
                id="evt_ticket_code"
                name="evt_ticket_code"
                class="regular-text"
                value="<?php echo esc_attr($code); ?>" />
            <span class="description">
                <?php esc_html_e('Changing the code will invalidate existing QR codes / links.', 'Event-Tickets-for-Elementor'); ?>
            </span>
        </p>

        <p>
            <label for="evt_ticket_status"><strong><?php esc_html_e('Status', 'Event-Tickets-for-Elementor'); ?></strong></label><br />
            <select id="evt_ticket_status" name="evt_ticket_status">
                <option value="pending" <?php selected($status, 'pending'); ?>>
                    <?php esc_html_e('Pending (not checked in)', 'Event-Tickets-for-Elementor'); ?>
                </option>
                <option value="checked_in" <?php selected($status, 'checked_in'); ?>>
                    <?php esc_html_e('Checked in', 'Event-Tickets-for-Elementor'); ?>
                </option>
                <option value="cancelled" <?php selected($status, 'cancelled'); ?>>
                    <?php esc_html_e('Cancelled', 'Event-Tickets-for-Elementor'); ?>
                </option>
            </select>
        </p>

        <p>
            <label for="evt_ticket_checked_in_at"><strong><?php esc_html_e('Checked-in at', 'Event-Tickets-for-Elementor'); ?></strong></label><br />
            <input
                type="text"
                id="evt_ticket_checked_in_at"
                name="evt_ticket_checked_in_at"
                class="regular-text"
                value="<?php echo esc_attr($checked_in_at); ?>"
                placeholder="<?php esc_attr_e('Set automatically when using the check-in screen', 'Event-Tickets-for-Elementor'); ?>" />
        </p>
    <?php
    }

    /**
     * Event snapshot meta box.
     *
     * @param \WP_Post $post
     */
    public function render_event_meta_box(\WP_Post $post): void
    {
        $event_name     = get_post_meta($post->ID, '_ticket_event_name', true);
        $event_start    = get_post_meta($post->ID, '_ticket_event_start', true);
        $event_end      = get_post_meta($post->ID, '_ticket_event_end', true);
        $event_location = get_post_meta($post->ID, '_ticket_event_location', true);
    ?>
        <p>
            <label for="evt_ticket_event_name"><strong><?php esc_html_e('Event name (snapshot)', 'Event-Tickets-for-Elementor'); ?></strong></label><br />
            <input
                type="text"
                id="evt_ticket_event_name"
                name="evt_ticket_event_name"
                class="regular-text"
                value="<?php echo esc_attr($event_name); ?>" />
            <span class="description">
                <?php esc_html_e('Name of the event at the time the ticket was issued.', 'Event-Tickets-for-Elementor'); ?>
            </span>
        </p>

        <p>
            <label for="evt_ticket_event_start"><strong><?php esc_html_e('Event start (snapshot)', 'Event-Tickets-for-Elementor'); ?></strong></label><br />
            <input
                type="text"
                id="evt_ticket_event_start"
                name="evt_ticket_event_start"
                class="regular-text"
                value="<?php echo esc_attr($event_start); ?>"
                placeholder="<?php esc_attr_e('e.g. 2025-12-31 19:00', 'Event-Tickets-for-Elementor'); ?>" />
        </p>

        <p>
            <label for="evt_ticket_event_end"><strong><?php esc_html_e('Event end (snapshot)', 'Event-Tickets-for-Elementor'); ?></strong></label><br />
            <input
                type="text"
                id="evt_ticket_event_end"
                name="evt_ticket_event_end"
                class="regular-text"
                value="<?php echo esc_attr($event_end); ?>"
                placeholder="<?php esc_attr_e('e.g. 2025-12-31 23:00', 'Event-Tickets-for-Elementor'); ?>" />
        </p>

        <p>
            <label for="evt_ticket_event_location"><strong><?php esc_html_e('Event location (snapshot)', 'Event-Tickets-for-Elementor'); ?></strong></label><br />
            <input
                type="text"
                id="evt_ticket_event_location"
                name="evt_ticket_event_location"
                class="regular-text"
                value="<?php echo esc_attr($event_location); ?>"
                placeholder="<?php esc_attr_e('e.g. City, venue name', 'Event-Tickets-for-Elementor'); ?>" />
        </p>
    <?php
    }

    /**
     * Linked Event meta box (Phase 2).
     *
     * @param \WP_Post $post
     */
    public function render_linked_event_meta_box(\WP_Post $post): void
    {
        $linked_event_id = (int) get_post_meta($post->ID, '_ticket_event_id', true);

        // Bound the dropdown: a site is unlikely to need more than 500 events
        // to choose from in a single admin meta box.
        $events = get_posts(
            Event_Recurrence::event_selector_query_args(
                array(
                    'post_type'      => CPT_Events::POST_TYPE,
                    'post_status'    => 'publish',
                    'numberposts'    => 500,
                    'orderby'        => 'title',
                    'order'          => 'ASC',
                )
            )
        );
    ?>
        <p>
            <label for="evt_ticket_event_id"><strong><?php esc_html_e('Linked Event (optional)', 'Event-Tickets-for-Elementor'); ?></strong></label><br />
            <select id="evt_ticket_event_id" name="evt_ticket_event_id">
                <option value="0"><?php esc_html_e('-- None --', 'Event-Tickets-for-Elementor'); ?></option>
                <?php
                if ($events) {
                    foreach ($events as $event) {
                        printf(
                            '<option value="%1$d"%2$s>%3$s</option>',
                            (int) $event->ID,
                            selected($linked_event_id, $event->ID, false),
                            esc_html($event->post_title)
                        );
                    }
                }
                ?>
            </select>
        </p>
        <?php if ($linked_event_id) : ?>
            <p>
                <a href="<?php echo esc_url(get_edit_post_link($linked_event_id)); ?>" target="_blank" rel="noopener noreferrer">
                    <?php esc_html_e('Edit linked event', 'Event-Tickets-for-Elementor'); ?>
                </a>
            </p>
        <?php endif; ?>

        <p class="description">
            <?php esc_html_e('Link this ticket to an Event (for reporting, widgets, and future features). The event details above remain a snapshot at the time of issue.', 'Event-Tickets-for-Elementor'); ?>
        </p>
    <?php
    }

    /**
     * Save ticket meta.
     *
     * @param int      $post_id
     * @param \WP_Post $post
     */
    public function save_meta(int $post_id, \WP_Post $post): void
    {
        if ($post->post_type !== self::POST_TYPE) {
            return;
        }

        // Nonce check: we only save when editing the full post screen.
        if (
            ! isset($_POST['evt_ticket_meta_nonce'])
            || ! wp_verify_nonce(wp_unslash($_POST['evt_ticket_meta_nonce']), 'evt_save_ticket_meta')
        ) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (! current_user_can('edit_post', $post_id)) {
            return;
        }

        // Attendee & ticket data.
        $name          = isset($_POST['evt_ticket_name']) ? sanitize_text_field(wp_unslash($_POST['evt_ticket_name'])) : '';
        $email         = isset($_POST['evt_ticket_email']) ? sanitize_email(wp_unslash($_POST['evt_ticket_email'])) : '';
        $code          = isset($_POST['evt_ticket_code']) ? sanitize_text_field(wp_unslash($_POST['evt_ticket_code'])) : '';
        $status        = isset($_POST['evt_ticket_status']) ? sanitize_text_field(wp_unslash($_POST['evt_ticket_status'])) : 'pending';
        $checked_in_at = isset($_POST['evt_ticket_checked_in_at']) ? sanitize_text_field(wp_unslash($_POST['evt_ticket_checked_in_at'])) : '';

        update_post_meta($post_id, '_ticket_name', $name);
        update_post_meta($post_id, '_ticket_email', $email);
        update_post_meta($post_id, '_ticket_code', $code);
        update_post_meta($post_id, '_ticket_status', $status);
        update_post_meta($post_id, '_ticket_checked_in_at', $checked_in_at);

        // Event snapshot.
        $event_name     = isset($_POST['evt_ticket_event_name']) ? sanitize_text_field(wp_unslash($_POST['evt_ticket_event_name'])) : '';
        $event_start    = isset($_POST['evt_ticket_event_start']) ? sanitize_text_field(wp_unslash($_POST['evt_ticket_event_start'])) : '';
        $event_end      = isset($_POST['evt_ticket_event_end']) ? sanitize_text_field(wp_unslash($_POST['evt_ticket_event_end'])) : '';
        $event_location = isset($_POST['evt_ticket_event_location']) ? sanitize_text_field(wp_unslash($_POST['evt_ticket_event_location'])) : '';

        update_post_meta($post_id, '_ticket_event_name', $event_name);
        update_post_meta($post_id, '_ticket_event_start', $event_start);
        update_post_meta($post_id, '_ticket_event_end', $event_end);
        update_post_meta($post_id, '_ticket_event_location', $event_location);

        // Linked Event (Phase 2).
        $linked_event_id = isset($_POST['evt_ticket_event_id']) ? absint($_POST['evt_ticket_event_id']) : 0;
        if ($linked_event_id > 0) {
            update_post_meta($post_id, '_ticket_event_id', $linked_event_id);
        } else {
            delete_post_meta($post_id, '_ticket_event_id');
        }
    }

    /**
     * Custom columns for Tickets list.
     *
     * @param array $columns
     *
     * @return array
     */
    public function edit_columns(array $columns): array
    {
        $new = array();

        // Keep checkbox if present.
        if (isset($columns['cb'])) {
            $new['cb'] = $columns['cb'];
        }

        $new['title']             = __('Ticket', 'Event-Tickets-for-Elementor');
        $new['ticket_code']       = __('Code', 'Event-Tickets-for-Elementor');
        $new['ticket_name']       = __('Attendee', 'Event-Tickets-for-Elementor');
        $new['ticket_email']      = __('Email', 'Event-Tickets-for-Elementor');
        $new['ticket_event']      = __('Event', 'Event-Tickets-for-Elementor');
        $new['ticket_status']     = __('Status', 'Event-Tickets-for-Elementor');
        $new['ticket_checked_in'] = __('Checked-in at', 'Event-Tickets-for-Elementor');

        return $new;
    }

    /**
     * Render custom columns.
     *
     * @param string $column
     * @param int    $post_id
     */
    public function render_columns(string $column, int $post_id): void
    {
        switch ($column) {
            case 'ticket_code':
                $code = get_post_meta($post_id, '_ticket_code', true);
                echo esc_html($code);
                break;

            case 'ticket_name':
                $name = get_post_meta($post_id, '_ticket_name', true);
                echo esc_html($name);
                break;

            case 'ticket_email':
                $email = get_post_meta($post_id, '_ticket_email', true);
                echo esc_html($email);
                break;

            case 'ticket_event':
                $event_name = get_post_meta($post_id, '_ticket_event_name', true);
                $linked_id  = (int) get_post_meta($post_id, '_ticket_event_id', true);

                if ($linked_id) {
                    $linked = get_post($linked_id);
                    if ($linked && CPT_Events::POST_TYPE === $linked->post_type) {
                        // Prefer the linked Event title.
                        echo esc_html($linked->post_title);
                    } elseif ($event_name) {
                        echo esc_html($event_name);
                    }
                } else {
                    echo esc_html($event_name);
                }
                break;

            case 'ticket_status':
                $status = get_post_meta($post_id, '_ticket_status', true);
                if (! $status) {
                    $status = 'pending';
                }

                switch ($status) {
                    case 'checked_in':
                        esc_html_e('Checked in', 'Event-Tickets-for-Elementor');
                        break;
                    case 'cancelled':
                        esc_html_e('Cancelled', 'Event-Tickets-for-Elementor');
                        break;
                    default:
                        esc_html_e('Pending', 'Event-Tickets-for-Elementor');
                        break;
                }
                break;

            case 'ticket_checked_in':
                $checked_in_at = get_post_meta($post_id, '_ticket_checked_in_at', true);
                echo esc_html($checked_in_at);
                break;
        }
    }

    /**
     * Dropdown filter in tickets list for event.
     */
    public function render_event_filter(): void
    {
        global $typenow;
        if (self::POST_TYPE !== $typenow) {
            return;
        }

        $selected = isset($_GET['evt_event_id']) ? absint($_GET['evt_event_id']) : 0;
        // Bound the dropdown: 500 recent events is more than enough for a
        // list-screen filter.
        $events = get_posts(
            Event_Recurrence::event_selector_query_args(
                array(
                    'post_type'      => CPT_Events::POST_TYPE,
                    'post_status'    => 'publish',
                    'numberposts'    => 500,
                    'orderby'        => 'title',
                    'order'          => 'ASC',
                )
            )
        );
    ?>
        <label for="filter_evt_event_id" class="screen-reader-text"><?php esc_html_e('Filter by event', 'Event-Tickets-for-Elementor'); ?></label>
        <select id="filter_evt_event_id" name="evt_event_id">
            <option value="0"><?php esc_html_e('All events', 'Event-Tickets-for-Elementor'); ?></option>
            <?php
            if ($events) {
                foreach ($events as $event) {
                    printf(
                        '<option value="%1$d"%2$s>%3$s</option>',
                        (int) $event->ID,
                        selected($selected, $event->ID, false),
                        esc_html($event->post_title)
                    );
                }
            }
            ?>
        </select>
<?php
    }

    /**
     * Apply event filter in admin list.
     *
     * @param \WP_Query $query
     * @return void
     */
    public function filter_by_event($query): void
    {
        if (! is_admin() || ! $query->is_main_query()) {
            return;
        }

        if ($query->get('post_type') !== self::POST_TYPE) {
            return;
        }

        $event_id = isset($_GET['evt_event_id']) ? absint($_GET['evt_event_id']) : 0;
        if ($event_id > 0) {
            $meta_query = (array) $query->get('meta_query');
            $meta_query[] = [
                'key'   => '_ticket_event_id',
                'value' => $event_id,
            ];
            $query->set('meta_query', $meta_query);
        }
    }
}
