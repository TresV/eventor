<?php

namespace EventTicketsElementor\Elementor_Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

if (! defined('ABSPATH')) {
    exit;
}

require_once EVT_TICKETS_PLUGIN_DIR . 'includes/elementor-widgets/concerns/trait-widget-text-controls.php';


/**
 * Staff-facing Check-In widget.
 *
 * - Allows staff to enter or receive a ticket code.
 * - Displays ticket details and status.
 * - Allows confirming check-in (updates ticket status).
 *
 * Now enhanced with AJAX + QR scanning (via JS).
 */
class Widget_Checkin extends Widget_Base
{
    /**
     * Per-instance text overrides (used by helper render methods).
     *
     * @var array<string,string>
     */
    protected array $text_overrides = [];

    public function get_name()
    {
        return 'evt_ticket_checkin';
    }

    public function get_title()
    {
        return __('Event Ticket Check-In', 'Event-Tickets-for-Elementor');
    }

    public function get_icon()
    {
        return 'eicon-check-circle';
    }

    public function get_categories()
    {
        return ['evt-tickets'];
    }

    public function get_style_depends()
    {
        return ['evt-tickets-checkin'];
    }

    public function get_script_depends()
    {
        // Load our AJAX + QR handler.
        return ['evt-tickets-checkin'];
    }

    protected function register_controls()
    {
        $this->start_controls_section(
            'section_content',
            [
                'label' => __('Check-In Settings', 'Event-Tickets-for-Elementor'),
                'tab'   => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'show_heading',
            [
                'label'        => __('Show Heading', 'Event-Tickets-for-Elementor'),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => __('Yes', 'Event-Tickets-for-Elementor'),
                'label_off'    => __('No', 'Event-Tickets-for-Elementor'),
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'heading_text',
            [
                'label'       => __('Heading Text', 'Event-Tickets-for-Elementor'),
                'type'        => Controls_Manager::TEXT,
                'default'     => __('Event Check-In', 'Event-Tickets-for-Elementor'),
                'placeholder' => __('Event Check-In', 'Event-Tickets-for-Elementor'),
                'condition'   => [
                    'show_heading' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'show_help_text',
            [
                'label'        => __('Show Help Text', 'Event-Tickets-for-Elementor'),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => __('Yes', 'Event-Tickets-for-Elementor'),
                'label_off'    => __('No', 'Event-Tickets-for-Elementor'),
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'help_text',
            [
                'label'       => __('Help Text', 'Event-Tickets-for-Elementor'),
                'type'        => Controls_Manager::TEXTAREA,
                'default'     => __('Scan the QR code or type the ticket code exactly as shown on the ticket.', 'Event-Tickets-for-Elementor'),
                'placeholder' => __('Instructions for staff…', 'Event-Tickets-for-Elementor'),
                'condition'   => [
                    'show_help_text' => 'yes',
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

        $this->add_control('label_ticket_code', ['label' => __('Label: Ticket code', 'Event-Tickets-for-Elementor'), 'type' => Controls_Manager::TEXT, 'default' => __('Ticket code', 'Event-Tickets-for-Elementor')]);
        $this->add_control('placeholder_ticket_code', ['label' => __('Placeholder: Ticket code', 'Event-Tickets-for-Elementor'), 'type' => Controls_Manager::TEXT, 'default' => __('Scan QR or type code…', 'Event-Tickets-for-Elementor')]);
        $this->add_control('button_check', ['label' => __('Button: Check ticket', 'Event-Tickets-for-Elementor'), 'type' => Controls_Manager::TEXT, 'default' => __('Check Ticket', 'Event-Tickets-for-Elementor')]);
        $this->add_control('button_confirm', ['label' => __('Button: Confirm check-in', 'Event-Tickets-for-Elementor'), 'type' => Controls_Manager::TEXT, 'default' => __('Confirm Check-In', 'Event-Tickets-for-Elementor')]);

        $this->add_control('heading_details', ['label' => __('Heading: Ticket details', 'Event-Tickets-for-Elementor'), 'type' => Controls_Manager::TEXT, 'default' => __('Ticket details', 'Event-Tickets-for-Elementor')]);
        $this->add_control('label_code', ['label' => __('Label: Code', 'Event-Tickets-for-Elementor'), 'type' => Controls_Manager::TEXT, 'default' => __('Code:', 'Event-Tickets-for-Elementor')]);
        $this->add_control('label_name', ['label' => __('Label: Name', 'Event-Tickets-for-Elementor'), 'type' => Controls_Manager::TEXT, 'default' => __('Name:', 'Event-Tickets-for-Elementor')]);
        $this->add_control('label_email', ['label' => __('Label: Email', 'Event-Tickets-for-Elementor'), 'type' => Controls_Manager::TEXT, 'default' => __('Email:', 'Event-Tickets-for-Elementor')]);
        $this->add_control('label_event', ['label' => __('Label: Event', 'Event-Tickets-for-Elementor'), 'type' => Controls_Manager::TEXT, 'default' => __('Event:', 'Event-Tickets-for-Elementor')]);
        $this->add_control('label_status', ['label' => __('Label: Status', 'Event-Tickets-for-Elementor'), 'type' => Controls_Manager::TEXT, 'default' => __('Status:', 'Event-Tickets-for-Elementor')]);
        $this->add_control('label_pending', ['label' => __('Status: Pending', 'Event-Tickets-for-Elementor'), 'type' => Controls_Manager::TEXT, 'default' => __('Pending', 'Event-Tickets-for-Elementor')]);
        $this->add_control('label_checked_in', ['label' => __('Status: Checked in', 'Event-Tickets-for-Elementor'), 'type' => Controls_Manager::TEXT, 'default' => __('Checked in', 'Event-Tickets-for-Elementor')]);
        $this->add_control('label_checked_in_at', ['label' => __('Label: Checked-in at', 'Event-Tickets-for-Elementor'), 'type' => Controls_Manager::TEXT, 'default' => __('Checked-in at:', 'Event-Tickets-for-Elementor')]);

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_box',
            [
                'label' => __('Box', 'Event-Tickets-for-Elementor'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'box_background',
            [
                'label' => __('Background', 'Event-Tickets-for-Elementor'),
                'type'  => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-checkin' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'box_border',
            [
                'label' => __('Border Color', 'Event-Tickets-for-Elementor'),
                'type'  => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-checkin' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'button_background',
            [
                'label' => __('Button Background', 'Event-Tickets-for-Elementor'),
                'type'  => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-checkin button' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'button_color',
            [
                'label' => __('Button Text', 'Event-Tickets-for-Elementor'),
                'type'  => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-checkin button' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render()
    {
        if (! is_user_logged_in()) {
            echo '<div class="evt-checkin-message evt-checkin-error">';
            esc_html_e('You must be logged in to use the check-in screen.', 'Event-Tickets-for-Elementor');
            echo '</div>';
            return;
        }

        if (! current_user_can(\EventTicketsElementor\Staff_Access_Manager::CAP_USE_CHECKIN)) {
            echo '<div class="evt-checkin-message evt-checkin-error">';
            esc_html_e('You do not have permission to access this page.', 'Event-Tickets-for-Elementor');
            echo '</div>';
            return;
        }

        $settings = $this->get_settings_for_display();

        $fallback = static function (array $settings, string $key, string $default): string {
            $val = $settings[$key] ?? '';
            $val = is_string($val) ? trim($val) : '';
            return $val !== '' ? $val : $default;
        };

        $this->text_overrides = [
            'label_ticket_code'       => $fallback($settings, 'label_ticket_code', __('Ticket code', 'Event-Tickets-for-Elementor')),
            'placeholder_ticket_code' => $fallback($settings, 'placeholder_ticket_code', __('Scan QR or type code…', 'Event-Tickets-for-Elementor')),
            'button_check'            => $fallback($settings, 'button_check', __('Check Ticket', 'Event-Tickets-for-Elementor')),
            'button_confirm'          => $fallback($settings, 'button_confirm', __('Confirm Check-In', 'Event-Tickets-for-Elementor')),
            'heading_details'         => $fallback($settings, 'heading_details', __('Ticket details', 'Event-Tickets-for-Elementor')),
            'label_code'              => $fallback($settings, 'label_code', __('Code:', 'Event-Tickets-for-Elementor')),
            'label_name'              => $fallback($settings, 'label_name', __('Name:', 'Event-Tickets-for-Elementor')),
            'label_email'             => $fallback($settings, 'label_email', __('Email:', 'Event-Tickets-for-Elementor')),
            'label_event'             => $fallback($settings, 'label_event', __('Event:', 'Event-Tickets-for-Elementor')),
            'label_status'            => $fallback($settings, 'label_status', __('Status:', 'Event-Tickets-for-Elementor')),
            'label_pending'           => $fallback($settings, 'label_pending', __('Pending', 'Event-Tickets-for-Elementor')),
            'label_checked_in'        => $fallback($settings, 'label_checked_in', __('Checked in', 'Event-Tickets-for-Elementor')),
            'label_checked_in_at'     => $fallback($settings, 'label_checked_in_at', __('Checked-in at:', 'Event-Tickets-for-Elementor')),
        ];

        // These variables are only used for non-JS fallback rendering.
        $status_type  = '';
        $status_title = '';
        $status_body  = '';
        $ticket_data  = [];

        // Fallback: handle POST (non-AJAX scenario).
        if ('POST' === sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'] ?? '')) && isset($_POST['evt_checkin_nonce'])) {
            if (wp_verify_nonce(wp_unslash($_POST['evt_checkin_nonce']), 'evt_ticket_checkin')) {

                // Confirm check-in action.
                if (isset($_POST['evt_confirm_checkin']) && ! empty($_POST['ticket_id'])) {
                    $ticket_id = absint($_POST['ticket_id']);
                    $ticket    = get_post($ticket_id);

                    if ($ticket && $ticket->post_type === \EventTicketsElementor\CPT_Tickets::POST_TYPE) {
                        \EventTicketsElementor\Plugin::instance()
                            ->tickets()
                            ->check_in_ticket($ticket_id, get_current_user_id());

                        $ticket_data = $this->get_ticket_data($ticket_id);
                        $status_type  = 'success';
                        $status_title = __('Ticket checked in', 'Event-Tickets-for-Elementor');
                        $status_body  = __('The ticket has been successfully marked as checked in.', 'Event-Tickets-for-Elementor');
                    } else {
                        $status_type  = 'error';
                        $status_title = __('Ticket not found', 'Event-Tickets-for-Elementor');
                        $status_body  = __('Unable to check in this ticket. It may have been deleted.', 'Event-Tickets-for-Elementor');
                    }
                }
                // Search/check ticket by code.
                else {
                    $ticket_code = isset($_POST['ticket_code'])
                        ? sanitize_text_field(wp_unslash($_POST['ticket_code']))
                        : '';

                    if ('' !== $ticket_code) {
                        $ticket = \EventTicketsElementor\Plugin::instance()
                            ->tickets()
                            ->get_ticket_by_code($ticket_code);

                        if ($ticket) {
                            $ticket_data = $this->get_ticket_data($ticket->ID);

                            if ('checked_in' === $ticket_data['status']) {
                                $status_type  = 'warning';
                                $status_title = __('Ticket already checked in', 'Event-Tickets-for-Elementor');
                                $status_body  = sprintf(
                                    /* translators: %s check-in time */
                                    __('This ticket was checked in at %s.', 'Event-Tickets-for-Elementor'),
                                    esc_html($ticket_data['checked_in_at'])
                                );
                            } else {
                                $status_type  = 'success';
                                $status_title = __('Valid ticket', 'Event-Tickets-for-Elementor');
                                $status_body  = __('This ticket is valid and not yet checked in.', 'Event-Tickets-for-Elementor');
                            }
                        } else {
                            $status_type  = 'error';
                            $status_title = __('Ticket not found', 'Event-Tickets-for-Elementor');
                            $status_body  = __('No ticket was found for this code. Please check the code and try again.', 'Event-Tickets-for-Elementor');
                        }
                    }
                }
            }
        } else {
            // Fallback: initial lookup if ?code= param exists and JS is disabled.
            $code_from_url = isset($_GET['code'])
                ? sanitize_text_field(wp_unslash($_GET['code']))
                : '';

            if ('' !== $code_from_url) {
                $ticket = \EventTicketsElementor\Plugin::instance()
                    ->tickets()
                    ->get_ticket_by_code($code_from_url);

                if ($ticket) {
                    $ticket_data = $this->get_ticket_data($ticket->ID);

                    if ('checked_in' === $ticket_data['status']) {
                        $status_type  = 'warning';
                        $status_title = __('Ticket already checked in', 'Event-Tickets-for-Elementor');
                        $status_body  = sprintf(
                            /* translators: %s is the check-in time. */
                            __('This ticket was checked in at %s.', 'Event-Tickets-for-Elementor'),
                            esc_html($ticket_data['checked_in_at'])
                        );
                    } else {
                        $status_type  = 'success';
                        $status_title = __('Valid ticket', 'Event-Tickets-for-Elementor');
                        $status_body  = __('This ticket is valid and not yet checked in.', 'Event-Tickets-for-Elementor');
                    }
                } else {
                    $status_type  = 'error';
                    $status_title = __('Ticket not found', 'Event-Tickets-for-Elementor');
                    $status_body  = __('No ticket was found for this code.', 'Event-Tickets-for-Elementor');
                }
            }
        }

        echo '<div class="evt-checkin-wrapper">';

        if ('yes' === $settings['show_heading']) {
            echo '<h2 class="evt-checkin-heading">' . esc_html($settings['heading_text']) . '</h2>';
        }

        if ('yes' === $settings['show_help_text'] && ! empty($settings['help_text'])) {
            echo '<p class="evt-checkin-help">' . esc_html($settings['help_text']) . '</p>';
        }

        // Container that JS will manage for status messages.
        echo '<div class="evt-checkin-status-container">';
        if ($status_type) {
            $class = 'evt-checkin-status evt-checkin-status-' . esc_attr($status_type);
            echo '<div class="' . esc_attr($class) . '">';
            if ($status_title) {
                echo '<strong class="evt-checkin-status-title">' . esc_html($status_title) . '</strong>';
            }
            if ($status_body) {
                echo '<p class="evt-checkin-status-body">' . esc_html($status_body) . '</p>';
            }
            echo '</div>';
        }
        echo '</div>'; // .evt-checkin-status-container

        // Container that JS will manage for ticket details + confirm button.
        echo '<div class="evt-checkin-ticket-container">';
        if ($ticket_data) {
            $this->render_ticket_details_block($ticket_data);
        }
        echo '</div>'; // .evt-checkin-ticket-container

        // Main "scan / enter code" form (always present, JS will intercept submit).
?>
        <form method="post" class="evt-checkin-form evt-checkin-form-search">
            <?php wp_nonce_field('evt_ticket_checkin', 'evt_checkin_nonce'); ?>
            <label for="evt-ticket-code-input" class="evt-checkin-label">
                <?php echo esc_html($this->text_overrides['label_ticket_code'] ?? __('Ticket code', 'Event-Tickets-for-Elementor')); ?>
            </label>
            <input
                type="text"
                id="evt-ticket-code-input"
                name="ticket_code"
                class="evt-checkin-input"
                placeholder="<?php echo esc_attr($this->text_overrides['placeholder_ticket_code'] ?? __('Scan QR or type code…', 'Event-Tickets-for-Elementor')); ?>"
                value="<?php echo isset($ticket_data['code']) ? esc_attr($ticket_data['code']) : ''; ?>" />
            <button type="submit" class="evt-checkin-button evt-checkin-button-search">
                <?php echo esc_html($this->text_overrides['button_check'] ?? __('Check Ticket', 'Event-Tickets-for-Elementor')); ?>
            </button>
        </form>

    <?php
        // Optional: a simple placeholder area for a JS-based camera/QR UI (implemented in JS).
        echo '<div class="evt-checkin-qr-area"></div>';

        echo '</div>'; // .evt-checkin-wrapper
    }

    /**
     * Render the ticket details block (including Confirm Check-In button if pending).
     *
     * @param array $ticket_data
     * @return void
     */
    protected function render_ticket_details_block(array $ticket_data): void
    {
    ?>
        <div class="evt-checkin-ticket-details">
            <h3><?php echo esc_html($this->text_overrides['heading_details'] ?? __('Ticket details', 'Event-Tickets-for-Elementor')); ?></h3>
            <ul>
                <li><strong><?php echo esc_html($this->text_overrides['label_code'] ?? __('Code:', 'Event-Tickets-for-Elementor')); ?></strong> <?php echo esc_html($ticket_data['code']); ?></li>
                <li><strong><?php echo esc_html($this->text_overrides['label_name'] ?? __('Name:', 'Event-Tickets-for-Elementor')); ?></strong> <?php echo esc_html($ticket_data['name']); ?></li>
                <li><strong><?php echo esc_html($this->text_overrides['label_email'] ?? __('Email:', 'Event-Tickets-for-Elementor')); ?></strong> <?php echo esc_html($ticket_data['email']); ?></li>
                <li><strong><?php echo esc_html($this->text_overrides['label_event'] ?? __('Event:', 'Event-Tickets-for-Elementor')); ?></strong> <?php echo esc_html($ticket_data['event_name']); ?></li>
                <li><strong><?php echo esc_html($this->text_overrides['label_status'] ?? __('Status:', 'Event-Tickets-for-Elementor')); ?></strong>
                    <?php
                    if ('checked_in' === $ticket_data['status']) {
                        echo esc_html($this->text_overrides['label_checked_in'] ?? __('Checked in', 'Event-Tickets-for-Elementor'));
                    } else {
                        echo esc_html($this->text_overrides['label_pending'] ?? __('Pending', 'Event-Tickets-for-Elementor'));
                    }
                    ?>
                </li>
                <?php if (! empty($ticket_data['checked_in_at'])) : ?>
                    <li><strong><?php echo esc_html($this->text_overrides['label_checked_in_at'] ?? __('Checked-in at:', 'Event-Tickets-for-Elementor')); ?></strong>
                        <?php echo esc_html($ticket_data['checked_in_at']); ?>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
        <?php

        // Show Confirm Check-In button only if pending.
        if ('pending' === $ticket_data['status']) : ?>
            <form method="post" class="evt-checkin-form evt-checkin-form-confirm">
                <?php wp_nonce_field('evt_ticket_checkin', 'evt_checkin_nonce'); ?>
                <input type="hidden" name="ticket_id" value="<?php echo esc_attr($ticket_data['id']); ?>" />
                <button type="submit" name="evt_confirm_checkin" class="evt-checkin-button evt-checkin-button-confirm">
                    <?php echo esc_html($this->text_overrides['button_confirm'] ?? __('Confirm Check-In', 'Event-Tickets-for-Elementor')); ?>
                </button>
            </form>
<?php
        endif;
    }

    /**
     * Collect ticket data into a simple array for PHP fallback mode.
     *
     * @param int $ticket_id
     * @return array
     */
    protected function get_ticket_data(int $ticket_id): array
    {
        $ticket = get_post($ticket_id);
        if (! $ticket || $ticket->post_type !== \EventTicketsElementor\CPT_Tickets::POST_TYPE) {
            return [];
        }

        $code          = get_post_meta($ticket_id, '_ticket_code', true);
        $name          = get_post_meta($ticket_id, '_ticket_name', true);
        $email         = get_post_meta($ticket_id, '_ticket_email', true);
        $event_name    = get_post_meta($ticket_id, '_ticket_event_name', true);
        $status        = get_post_meta($ticket_id, '_ticket_status', true);
        $checked_in_at = get_post_meta($ticket_id, '_ticket_checked_in_at', true);

        if (! $status) {
            $status = 'pending';
        }

        return [
            'id'            => $ticket_id,
            'code'          => $code,
            'name'          => $name,
            'email'         => $email,
            'event_name'    => $event_name,
            'status'        => $status,
            'checked_in_at' => $checked_in_at,
        ];
    }
}
