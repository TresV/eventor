<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Admin UI for Event timeslots + per-event capacity mode.
 */
class Event_Timeslots_Meta
{
    private Event_Timeslots $timeslots;

    public function __construct(Event_Timeslots $timeslots)
    {
        $this->timeslots = $timeslots;
        add_action('evt_tickets_event_section_schedule', [$this, 'render'], 20);
        add_action('evt_tickets_event_details_save', [$this, 'save'], 20, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public function enqueue_admin_assets(string $hook): void
    {
        if (! in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (! $screen || CPT_Events::POST_TYPE !== $screen->post_type) {
            return;
        }

        wp_enqueue_script(
            'evt-event-timeslots-admin',
            EVT_TICKETS_PLUGIN_URL . 'assets/js/admin-event-timeslots.js',
            ['jquery'],
            defined('EVT_TICKETS_VERSION') ? \EVT_TICKETS_VERSION : '0.1.0',
            true
        );

        wp_enqueue_style(
            'evt-event-timeslots-admin',
            EVT_TICKETS_PLUGIN_URL . 'assets/css/admin-event-timeslots.css',
            [],
            defined('EVT_TICKETS_VERSION') ? \EVT_TICKETS_VERSION : '0.1.0'
        );
    }

    public function render(\WP_Post $post): void
    {
        $ticketing_mode = $this->timeslots->get_ticketing_mode($post->ID);
        $mode = $this->timeslots->get_capacity_mode($post->ID);
        $slots = $this->timeslots->get_slots($post->ID);
        $limit_one = (int) get_post_meta($post->ID, Event_Timeslots::META_KEY_LIMIT_ONE_EMAIL, true);
        $normalize = (int) get_post_meta($post->ID, Event_Timeslots::META_KEY_NORMALIZE_EMAIL, true);
        $max_per_email = (int) get_post_meta($post->ID, Event_Timeslots::META_KEY_MAX_PER_EMAIL, true);
        ?>
        <div class="evt-event-subsection">
        <h4 class="evt-timeslots__title"><?php esc_html_e('Additional timeslots (optional)', 'Event-Tickets-for-Elementor'); ?></h4>
        <p class="description"><?php esc_html_e('Split this event into multiple time sessions. If you choose “Tickets are issued per timeslot”, buyers must pick a specific session.', 'Event-Tickets-for-Elementor'); ?></p>

        <div class="evt-ticket-limit">
            <label for="evt_event_limit_one_email" style="display:flex;align-items:center;gap:10px;">
                <input type="checkbox" id="evt_event_limit_one_email" name="evt_event_limit_one_email" value="1" <?php checked($limit_one, 1); ?> />
                <strong><?php esc_html_e('Limit to 1 ticket per email', 'Event-Tickets-for-Elementor'); ?></strong>
            </label>
            <p class="description" style="margin-top:6px;">
                <?php esc_html_e('When enabled, each email can receive only one ticket for this event.', 'Event-Tickets-for-Elementor'); ?>
            </p>
            <label for="evt_event_max_per_email" style="display:block;margin-top:10px;">
                <strong><?php esc_html_e('Max tickets per email (0 = no limit)', 'Event-Tickets-for-Elementor'); ?></strong>
            </label>
            <input
                type="number"
                id="evt_event_max_per_email"
                name="evt_event_max_per_email"
                min="0"
                step="1"
                class="small-text"
                value="<?php echo esc_attr((string) max(0, $max_per_email)); ?>"
            />
            <p class="description" style="margin-top:6px;">
                <?php esc_html_e('Limits how many total tickets one email can obtain for this event.', 'Event-Tickets-for-Elementor'); ?>
            </p>
            <label for="evt_event_normalize_email" style="display:flex;align-items:center;gap:10px;margin-top:8px;">
                <input type="checkbox" id="evt_event_normalize_email" name="evt_event_normalize_email" value="1" <?php checked($normalize, 1); ?> />
                <strong><?php esc_html_e('Normalize email addresses for this limit', 'Event-Tickets-for-Elementor'); ?></strong>
            </label>
            <p class="description" style="margin-top:6px;">
                <?php esc_html_e('Normalizes Gmail dots and plus tags; Outlook/iCloud/Proton/Fastmail plus tags; Yahoo dash tags.', 'Event-Tickets-for-Elementor'); ?>
            </p>
        </div>

        <p class="evt-timeslots__field">
            <label for="evt_event_ticketing_mode"><strong><?php esc_html_e('Ticketing mode', 'Event-Tickets-for-Elementor'); ?></strong></label><br />
            <select id="evt_event_ticketing_mode" name="evt_event_ticketing_mode">
                <option value="<?php echo esc_attr(Event_Timeslots::TICKETING_MODE_EVENT); ?>" <?php selected($ticketing_mode, Event_Timeslots::TICKETING_MODE_EVENT); ?>>
                    <?php esc_html_e('One ticket covers all timeslots', 'Event-Tickets-for-Elementor'); ?>
                </option>
                <option value="<?php echo esc_attr(Event_Timeslots::TICKETING_MODE_SLOT); ?>" <?php selected($ticketing_mode, Event_Timeslots::TICKETING_MODE_SLOT); ?>>
                    <?php esc_html_e('Tickets are issued per timeslot', 'Event-Tickets-for-Elementor'); ?>
                </option>
            </select>
        </p>

        <p class="evt-timeslots__field">
            <label for="evt_event_capacity_mode"><strong><?php esc_html_e('Capacity mode', 'Event-Tickets-for-Elementor'); ?></strong></label><br />
            <select id="evt_event_capacity_mode" name="evt_event_capacity_mode">
                <option value="<?php echo esc_attr(Event_Timeslots::CAPACITY_MODE_EVENT); ?>" <?php selected($mode, Event_Timeslots::CAPACITY_MODE_EVENT); ?>>
                    <?php esc_html_e('Event capacity only', 'Event-Tickets-for-Elementor'); ?>
                </option>
                <option value="<?php echo esc_attr(Event_Timeslots::CAPACITY_MODE_SLOT); ?>" <?php selected($mode, Event_Timeslots::CAPACITY_MODE_SLOT); ?>>
                    <?php esc_html_e('Timeslot capacity only', 'Event-Tickets-for-Elementor'); ?>
                </option>
                <option value="<?php echo esc_attr(Event_Timeslots::CAPACITY_MODE_BOTH); ?>" <?php selected($mode, Event_Timeslots::CAPACITY_MODE_BOTH); ?>>
                    <?php esc_html_e('Both (event + timeslot)', 'Event-Tickets-for-Elementor'); ?>
                </option>
            </select>
        </p>

        <div class="evt-timeslots" data-timeslots>
            <div class="evt-timeslots__rows" data-rows>
                <?php if (empty($slots)) : ?>
                    <?php $this->render_row(0, ['id' => '', 'start' => '', 'end' => '', 'capacity' => 0], true); ?>
                <?php else : ?>
                    <?php foreach (array_values($slots) as $i => $slot) : ?>
                        <?php $this->render_row((int) $i, $slot, false); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <p class="description evt-timeslots__hint">
                <?php esc_html_e('Slot ID is optional. If empty, one will be generated automatically.', 'Event-Tickets-for-Elementor'); ?>
            </p>

            <p class="evt-timeslots__actions">
                <button type="button" class="button" data-add-timeslot><?php esc_html_e('Add timeslot', 'Event-Tickets-for-Elementor'); ?></button>
            </p>

            <template data-timeslot-template>
                <?php $this->render_row(9999, ['id' => '', 'start' => '', 'end' => '', 'capacity' => 0], true); ?>
            </template>
        </div>
        </div>
        <?php
    }

    /**
     * @param int   $index
     * @param array{id:string,start:string,end:string,capacity:int} $slot
     * @param bool  $is_template
     */
    private function render_row(int $index, array $slot, bool $is_template): void
    {
        $id = isset($slot['id']) ? (string) $slot['id'] : '';
        $start = isset($slot['start']) ? (string) $slot['start'] : '';
        $end = isset($slot['end']) ? (string) $slot['end'] : '';
        $cap = isset($slot['capacity']) ? (int) $slot['capacity'] : 0;

        $start_local = $start ? $start : '';
        $end_local = $end ? $end : '';

        $name_prefix = $is_template ? 'evt_event_timeslots[__INDEX__]' : 'evt_event_timeslots[' . $index . ']';
        ?>
        <div class="evt-timeslots__row" data-row>
            <div class="evt-timeslots__col evt-timeslots__col--id">
                <label><strong><?php esc_html_e('Slot ID', 'Event-Tickets-for-Elementor'); ?></strong></label><br />
                <input type="text" class="regular-text" name="<?php echo esc_attr($name_prefix . '[id]'); ?>" value="<?php echo esc_attr($id); ?>" placeholder="slot_1" />
            </div>
            <div class="evt-timeslots__col evt-timeslots__col--start">
                <label><strong><?php esc_html_e('Start', 'Event-Tickets-for-Elementor'); ?></strong></label><br />
                <input type="text" class="regular-text evt-datetime-field" name="<?php echo esc_attr($name_prefix . '[start]'); ?>" value="<?php echo esc_attr($start_local); ?>" />
            </div>
            <div class="evt-timeslots__col evt-timeslots__col--end">
                <label><strong><?php esc_html_e('End', 'Event-Tickets-for-Elementor'); ?></strong></label><br />
                <input type="text" class="regular-text evt-datetime-field" name="<?php echo esc_attr($name_prefix . '[end]'); ?>" value="<?php echo esc_attr($end_local); ?>" />
            </div>
            <div class="evt-timeslots__col evt-timeslots__col--cap">
                <label><strong><?php esc_html_e('Slot capacity', 'Event-Tickets-for-Elementor'); ?></strong></label><br />
                <input type="number" min="0" step="1" class="regular-text" name="<?php echo esc_attr($name_prefix . '[capacity]'); ?>" value="<?php echo esc_attr($cap > 0 ? (string) $cap : ''); ?>" placeholder="<?php esc_attr_e('Unlimited', 'Event-Tickets-for-Elementor'); ?>" />
            </div>
            <div class="evt-timeslots__col evt-timeslots__col--remove">
                <span class="evt-timeslots__remove-spacer" aria-hidden="true"></span>
                <button type="button" class="button-link-delete" data-remove-timeslot><?php esc_html_e('Remove', 'Event-Tickets-for-Elementor'); ?></button>
            </div>
        </div>
        <?php
    }

    public function save(int $post_id, \WP_Post $post): void
    {
        if ($post->post_type !== CPT_Events::POST_TYPE) {
            return;
        }

        $limit_one = ! empty($_POST['evt_event_limit_one_email']) ? 1 : 0;
        $normalize = ! empty($_POST['evt_event_normalize_email']) ? 1 : 0;
        $max_per_email = isset($_POST['evt_event_max_per_email']) ? absint($_POST['evt_event_max_per_email']) : 0;
        if ($limit_one) {
            $max_per_email = 1;
        }
        update_post_meta($post_id, Event_Timeslots::META_KEY_LIMIT_ONE_EMAIL, $limit_one);
        update_post_meta($post_id, Event_Timeslots::META_KEY_NORMALIZE_EMAIL, $normalize);
        update_post_meta($post_id, Event_Timeslots::META_KEY_MAX_PER_EMAIL, $max_per_email);

        $ticketing_mode = isset($_POST['evt_event_ticketing_mode']) ? sanitize_key((string) wp_unslash($_POST['evt_event_ticketing_mode'])) : '';
        if (! in_array($ticketing_mode, [Event_Timeslots::TICKETING_MODE_EVENT, Event_Timeslots::TICKETING_MODE_SLOT], true)) {
            $ticketing_mode = Event_Timeslots::TICKETING_MODE_EVENT;
        }
        update_post_meta($post_id, Event_Timeslots::META_KEY_TICKETING_MODE, $ticketing_mode);

        $mode = isset($_POST['evt_event_capacity_mode']) ? sanitize_key((string) wp_unslash($_POST['evt_event_capacity_mode'])) : '';
        if (! in_array($mode, [Event_Timeslots::CAPACITY_MODE_EVENT, Event_Timeslots::CAPACITY_MODE_SLOT, Event_Timeslots::CAPACITY_MODE_BOTH], true)) {
            $mode = Event_Timeslots::CAPACITY_MODE_EVENT;
        }
        update_post_meta($post_id, Event_Timeslots::META_KEY_CAPACITY_MODE, $mode);

        $raw = isset($_POST['evt_event_timeslots']) ? (array) wp_unslash($_POST['evt_event_timeslots']) : [];
        $slots = [];

        $seen_ids = [];
        $i = 0;
        foreach ($raw as $slot) {
            if (! is_array($slot)) {
                continue;
            }

            $id = isset($slot['id']) ? sanitize_key((string) $slot['id']) : '';
            $start = isset($slot['start']) ? $this->timeslots->normalize_datetime((string) $slot['start']) : '';
            $end   = isset($slot['end']) ? $this->timeslots->normalize_datetime((string) $slot['end']) : '';
            $cap   = isset($slot['capacity']) ? absint($slot['capacity']) : 0;

            if ('' === $start || '' === $end) {
                continue;
            }
            if (strtotime($start) >= strtotime($end)) {
                continue;
            }

            if ('' === $id) {
                $id = 'slot_' . ($i + 1);
            }
            if (isset($seen_ids[$id])) {
                $id = $id . '_' . ($i + 1);
            }
            $seen_ids[$id] = true;

            $slots[] = [
                'id'       => $id,
                'start'    => $start,
                'end'      => $end,
                'capacity' => $cap,
            ];
            $i++;
        }

        if (empty($slots)) {
            delete_post_meta($post_id, Event_Timeslots::META_KEY_SLOTS);
        } else {
            update_post_meta($post_id, Event_Timeslots::META_KEY_SLOTS, $slots);
            $this->timeslots->sync_primary_range_from_slots($post_id);
        }
    }
}
