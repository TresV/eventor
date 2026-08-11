<?php

namespace EventTicketsElementor\Elementor_Widgets;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use EventTicketsElementor\CPT_Events;
use EventTicketsElementor\Event_Selector;
use EventTicketsElementor\Event_Recurrence;

if (! defined('ABSPATH')) {
    exit;
}

require_once EVT_TICKETS_PLUGIN_DIR . 'includes/elementor-widgets/concerns/trait-ticket-box-style-controls.php';
require_once EVT_TICKETS_PLUGIN_DIR . 'includes/elementor-widgets/concerns/trait-widget-text-controls.php';

use EventTicketsElementor\Elementor_Widgets\Concerns\Ticket_Box_Style_Controls;
use EventTicketsElementor\Elementor_Widgets\Concerns\Widget_Text_Controls;

/**
 * Ticket Box widget: select event, quantity, name/email; creates tickets via AJAX.
 */
class Widget_Ticket_Box extends Widget_Base
{
    use Ticket_Box_Style_Controls;
    use Widget_Text_Controls;

    public function get_name()
    {
        return 'evt_ticket_box';
    }

    public function get_title()
    {
        return __('Event Ticket Box', 'Event-Tickets-for-Elementor');
    }

    public function get_icon()
    {
        return 'eicon-form-horizontal';
    }

    public function get_categories()
    {
        return ['evt-tickets'];
    }

    public function get_style_depends()
    {
        return ['evt-tickets-ticket-box'];
    }

    public function get_script_depends()
    {
        return ['evt-tickets-ticket-box'];
    }

    protected function register_controls()
    {
        $this->start_controls_section(
            'section_content',
            [
                'label' => __('Content', 'Event-Tickets-for-Elementor'),
                'tab'   => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'heading',
            [
                'label'       => __('Heading', 'Event-Tickets-for-Elementor'),
                'type'        => Controls_Manager::TEXT,
                'default'     => __('Get your tickets', 'Event-Tickets-for-Elementor'),
            ]
        );

        $this->add_control(
            'submit_label',
            [
                'label'       => __('Button Text', 'Event-Tickets-for-Elementor'),
                'type'        => Controls_Manager::TEXT,
                'default'     => __('Get Tickets', 'Event-Tickets-for-Elementor'),
            ]
        );

        $this->add_control(
            'anchor_id',
            [
                'label'       => __('Anchor ID (for inline links)', 'Event-Tickets-for-Elementor'),
                'type'        => Controls_Manager::TEXT,
                'default'     => 'evt-ticket-box',
                'placeholder' => 'evt-ticket-box',
                'description' => __('Used as the #anchor for inline “Get Tickets” links on event pages.', 'Event-Tickets-for-Elementor'),
            ]
        );

        $this->add_control(
            'prefill_heading',
            [
                'label'     => __('Prefill', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::RAW_HTML,
                'separator' => 'before',
                'raw'       => '<strong>' . esc_html__('Prefill', 'Event-Tickets-for-Elementor') . '</strong>',
            ]
        );

        $this->add_control(
            'enable_url_prefill',
            [
                'label'        => __('Enable Event Prefill', 'Event-Tickets-for-Elementor'),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => __('Yes', 'Event-Tickets-for-Elementor'),
                'label_off'    => __('No', 'Event-Tickets-for-Elementor'),
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'prefill_source',
            [
                'label'     => __('Prefill Source', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::SELECT,
                'default'   => 'url',
                'options'   => [
                    'url'     => __('URL parameter', 'Event-Tickets-for-Elementor'),
                    'current' => __('Current Event (template)', 'Event-Tickets-for-Elementor'),
                ],
                'condition' => [
                    'enable_url_prefill' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'prefill_param',
            [
                'label'       => __('URL Param Name', 'Event-Tickets-for-Elementor'),
                'type'        => Controls_Manager::TEXT,
                'default'     => 'evt_event_id',
                'placeholder' => 'evt_event_id',
                'condition'   => [
                    'enable_url_prefill' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'lock_event_select',
            [
                'label'        => __('Hide Event Dropdown When Prefilled', 'Event-Tickets-for-Elementor'),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => __('Yes', 'Event-Tickets-for-Elementor'),
                'label_off'    => __('No', 'Event-Tickets-for-Elementor'),
                'return_value' => 'yes',
                'default'      => 'no',
                'condition'    => [
                    'enable_url_prefill' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'labels_heading',
            [
                'label'     => __('Labels / Text', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::RAW_HTML,
                'separator' => 'before',
                'raw'       => '<strong>' . esc_html__('Labels / Text', 'Event-Tickets-for-Elementor') . '</strong>',
            ]
        );

        $this->add_control(
            'label_event',
            [
                'label'   => __('Label: Event', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::TEXT,
                'default' => __('Event', 'Event-Tickets-for-Elementor'),
            ]
        );
        $this->add_control(
            'placeholder_event',
            [
                'label'   => __('Placeholder: Event', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::TEXT,
                'default' => __('Select an event', 'Event-Tickets-for-Elementor'),
            ]
        );
        $this->add_control(
            'label_quantity',
            [
                'label'   => __('Label: Quantity', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::TEXT,
                'default' => __('Quantity', 'Event-Tickets-for-Elementor'),
            ]
        );
        $this->add_control(
            'label_timeslot',
            [
                'label'   => __('Label: Timeslot', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::TEXT,
                'default' => __('Timeslot', 'Event-Tickets-for-Elementor'),
            ]
        );
        $this->add_control(
            'placeholder_timeslot',
            [
                'label'   => __('Placeholder: Timeslot', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::TEXT,
                'default' => __('Select a timeslot', 'Event-Tickets-for-Elementor'),
            ]
        );
        $this->add_control(
            'label_name',
            [
                'label'   => __('Label: Name', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::TEXT,
                'default' => __('Your name', 'Event-Tickets-for-Elementor'),
            ]
        );
        $this->add_control(
            'label_phone',
            [
                'label'   => __('Label: Telephone (optional)', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::TEXT,
                'default' => __('Telephone', 'Event-Tickets-for-Elementor'),
            ]
        );
        $this->add_control(
            'placeholder_phone',
            [
                'label'   => __('Placeholder: Telephone', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::TEXT,
                'default' => __('Optional', 'Event-Tickets-for-Elementor'),
            ]
        );
        $this->add_control(
            'label_email',
            [
                'label'   => __('Label: Email', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::TEXT,
                'default' => __('Email', 'Event-Tickets-for-Elementor'),
            ]
        );

        $this->add_control(
            'messages_heading',
            [
                'label'     => __('Messages', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::RAW_HTML,
                'separator' => 'before',
                'raw'       => '<strong>' . esc_html__('Messages', 'Event-Tickets-for-Elementor') . '</strong>',
            ]
        );
        $this->add_control(
            'i18n_submitting',
            [
                'label'   => __('Text: Submitting', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::TEXT,
                'default' => __('Submitting…', 'Event-Tickets-for-Elementor'),
            ]
        );
        $this->add_control(
            'i18n_success',
            [
                'label'   => __('Text: Success', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::TEXT,
                'default' => __('Tickets sent to your email.', 'Event-Tickets-for-Elementor'),
            ]
        );
        $this->add_control(
            'i18n_error',
            [
                'label'   => __('Text: Error (fallback)', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::TEXT,
                'default' => __('Something went wrong. Please try again.', 'Event-Tickets-for-Elementor'),
            ]
        );

        $this->end_controls_section();

        $this->register_ticket_box_style_controls();
    }

    protected function render()
    {
        $settings     = $this->get_settings_for_display();
        $heading      = $settings['heading'];
        $submit_label = $settings['submit_label'];

        $label_event = $this->get_text_setting($settings, 'label_event', __('Event', 'Event-Tickets-for-Elementor'));
        $placeholder_event = $this->get_text_setting($settings, 'placeholder_event', __('Select an event', 'Event-Tickets-for-Elementor'));
        $label_quantity = $this->get_text_setting($settings, 'label_quantity', __('Quantity', 'Event-Tickets-for-Elementor'));
        $label_timeslot = $this->get_text_setting($settings, 'label_timeslot', __('Timeslot', 'Event-Tickets-for-Elementor'));
        $placeholder_timeslot = $this->get_text_setting($settings, 'placeholder_timeslot', __('Select a timeslot', 'Event-Tickets-for-Elementor'));
        $label_name = $this->get_text_setting($settings, 'label_name', __('Your name', 'Event-Tickets-for-Elementor'));
        $label_phone = $this->get_text_setting($settings, 'label_phone', __('Telephone', 'Event-Tickets-for-Elementor'));
        $placeholder_phone = $this->get_text_setting($settings, 'placeholder_phone', __('Optional', 'Event-Tickets-for-Elementor'));
        $label_email = $this->get_text_setting($settings, 'label_email', __('Email', 'Event-Tickets-for-Elementor'));

        $i18n = [
            'submitting' => $this->get_text_setting($settings, 'i18n_submitting', __('Submitting…', 'Event-Tickets-for-Elementor')),
            'success'    => $this->get_text_setting($settings, 'i18n_success', __('Tickets sent to your email.', 'Event-Tickets-for-Elementor')),
            'error'      => $this->get_text_setting($settings, 'i18n_error', __('Something went wrong. Please try again.', 'Event-Tickets-for-Elementor')),
        ];

        $prefill_param = ! empty($settings['prefill_param']) ? sanitize_key((string) $settings['prefill_param']) : 'evt_event_id';
        $prefill_enabled = ('yes' === ($settings['enable_url_prefill'] ?? 'yes'));
        $prefill_source = $prefill_enabled ? (string) ($settings['prefill_source'] ?? 'url') : 'none';
        $prefill_event_id = 0;
        if ($prefill_source === 'current') {
            $current_id = get_queried_object_id();
            if ($current_id) {
                $post = get_post($current_id);
                if ($post && CPT_Events::POST_TYPE === $post->post_type && ! Event_Recurrence::is_series_parent((int) $post->ID)) {
                    $prefill_event_id = (int) $post->ID;
                }
            }
        } elseif ($prefill_source === 'url' && $prefill_enabled && isset($_GET[$prefill_param])) {
            $prefill_event_id = absint($_GET[$prefill_param]);
        }
        if ($prefill_event_id) {
            $event_post = get_post($prefill_event_id);
            if (! $event_post || CPT_Events::POST_TYPE !== $event_post->post_type || Event_Recurrence::is_series_parent((int) $event_post->ID)) {
                $prefill_event_id = 0;
            }
        }
        $lock_event_select = ('yes' === ($settings['lock_event_select'] ?? 'no'));

        $event_limit = Event_Selector::is_elementor_editor_context()
            ? Event_Selector::DEFAULT_EDITOR_LIMIT
            : 0;
        $events = Event_Selector::get_event_posts($event_limit, $prefill_event_id);
        $anchor_id = isset($settings['anchor_id']) ? sanitize_title((string) $settings['anchor_id']) : '';
?>
        <div
            class="evt-ticket-box"
            <?php echo $anchor_id ? 'id="' . esc_attr($anchor_id) . '"' : ''; ?>
            data-i18n="<?php echo esc_attr(wp_json_encode($i18n)); ?>"
            data-prefill-param="<?php echo esc_attr($prefill_param); ?>"
            data-prefill-event="<?php echo esc_attr((string) $prefill_event_id); ?>"
            data-prefill-lock="<?php echo esc_attr($lock_event_select ? '1' : '0'); ?>">
            <?php if ($heading) : ?>
                <h3 class="evt-ticket-box__heading"><?php echo esc_html($heading); ?></h3>
            <?php endif; ?>

            <form class="evt-ticket-box__form">
                <div class="evt-ticket-box__grid">
                    <p class="evt-ticket-box__event<?php echo $lock_event_select && $prefill_event_id ? ' is-locked' : ''; ?>">
                        <label for="evt_ticket_event_select"><?php echo esc_html($label_event); ?></label><br />
                        <select
                            id="evt_ticket_event_select"
                            name="evt_event_id"
                            required
                            <?php echo $lock_event_select && $prefill_event_id ? 'disabled' : ''; ?>>
                            <option value=""><?php echo esc_html($placeholder_event); ?></option>
                            <?php if ($events) : ?>
                                <?php foreach ($events as $event) : ?>
                                    <option value="<?php echo esc_attr($event->ID); ?>" <?php selected($prefill_event_id, $event->ID); ?>>
                                        <?php echo esc_html($event->post_title); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <?php if ($lock_event_select && $prefill_event_id) : ?>
                            <input type="hidden" name="evt_event_id" value="<?php echo esc_attr((string) $prefill_event_id); ?>" />
                        <?php endif; ?>
                    </p>

                    <p class="evt-ticket-box__quantity">
                        <label for="evt_ticket_quantity"><?php echo esc_html($label_quantity); ?></label><br />
                        <input type="number" id="evt_ticket_quantity" name="evt_ticket_quantity" min="1" max="10" value="1" />
                    </p>

                    <p class="evt-ticket-box__timeslot" style="display:none;">
                        <label for="evt_ticket_timeslot_select"><?php echo esc_html($label_timeslot); ?></label><br />
                        <select
                            id="evt_ticket_timeslot_select"
                            name="evt_timeslot_id"
                            data-placeholder="<?php echo esc_attr($placeholder_timeslot); ?>">
                            <option value=""><?php echo esc_html($placeholder_timeslot); ?></option>
                        </select>
                    </p>

                    <p>
                        <label for="evt_attendee_name"><?php echo esc_html($label_name); ?></label><br />
                        <input type="text" id="evt_attendee_name" name="evt_attendee_name" />
                    </p>

                    <p>
                        <label for="evt_attendee_phone"><?php echo esc_html($label_phone); ?></label><br />
                        <input type="tel" id="evt_attendee_phone" name="evt_attendee_phone" placeholder="<?php echo esc_attr($placeholder_phone); ?>" />
                    </p>

                    <p>
                        <label for="evt_attendee_email"><?php echo esc_html($label_email); ?></label><br />
                        <input type="email" id="evt_attendee_email" name="evt_attendee_email" required />
                    </p>
                </div>

                <input
                    type="text"
                    name="evt_website"
                    class="evt-ticket-box__hp"
                    tabindex="-1"
                    autocomplete="off"
                    aria-hidden="true"
                    style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0;overflow:hidden;" />

                <p>
                    <button type="submit" class="evt-ticket-box__submit"><?php echo esc_html($submit_label); ?></button>
                </p>

                <div class="evt-ticket-box__notice"></div>
            </form>
        </div>
<?php
    }
}
