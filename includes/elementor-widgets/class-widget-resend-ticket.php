<?php

namespace EventTicketsElementor\Elementor_Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use EventTicketsElementor\Plugin;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Public-facing "Resend Ticket" widget.
 *
 * User enters an email, plugin re-sends the latest ticket for that email.
 */
class Widget_Resend_Ticket extends Widget_Base
{

    public function get_name()
    {
        return 'evt_ticket_resend';
    }

    public function get_title()
    {
        return __('Resend Event Ticket', 'Event-Tickets-for-Elementor');
    }

    public function get_icon()
    {
        return 'eicon-mail';
    }

    public function get_categories()
    {
        return ['evt-tickets'];
    }

    public function get_style_depends()
    {
        return ['evt-tickets-resend'];
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
                'default'     => __('Find your ticket', 'Event-Tickets-for-Elementor'),
                'placeholder' => __('Find your ticket', 'Event-Tickets-for-Elementor'),
                'condition'   => [
                    'show_heading' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'description_text',
            [
                'label'       => __('Description Text', 'Event-Tickets-for-Elementor'),
                'type'        => Controls_Manager::TEXTAREA,
                'default'     => __('Enter the email address you used when signing up. We will send your latest ticket to that email.', 'Event-Tickets-for-Elementor'),
            ]
        );

        $this->add_control(
            'label_email',
            [
                'label'   => __('Email Label', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::TEXT,
                'default' => __('Email address', 'Event-Tickets-for-Elementor'),
            ]
        );

        $this->add_control(
            'placeholder_email',
            [
                'label'   => __('Email Placeholder', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::TEXT,
                'default' => __('you@example.com', 'Event-Tickets-for-Elementor'),
            ]
        );

        $this->add_control(
            'button_text',
            [
                'label'   => __('Button Text', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::TEXT,
                'default' => __('Send my ticket', 'Event-Tickets-for-Elementor'),
            ]
        );

        $this->add_control(
            'success_message',
            [
                'label'       => __('Success Message', 'Event-Tickets-for-Elementor'),
                'type'        => Controls_Manager::TEXTAREA,
                'default'     => __('If we find a ticket for this email, we will send it shortly. Please check your inbox (and spam folder).', 'Event-Tickets-for-Elementor'),
            ]
        );

        $this->add_control(
            'error_message',
            [
                'label'       => __('Error Message (invalid email)', 'Event-Tickets-for-Elementor'),
                'type'        => Controls_Manager::TEXTAREA,
                'default'     => __('Please enter a valid email address.', 'Event-Tickets-for-Elementor'),
            ]
        );

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
                    '{{WRAPPER}} .evt-resend' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'box_border',
            [
                'label' => __('Border', 'Event-Tickets-for-Elementor'),
                'type'  => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-resend' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'button_background',
            [
                'label' => __('Button Background', 'Event-Tickets-for-Elementor'),
                'type'  => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-resend button[type="submit"]' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'button_color',
            [
                'label' => __('Button Text', 'Event-Tickets-for-Elementor'),
                'type'  => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-resend button[type="submit"]' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();

        $status_type = '';
        $status_text = '';

        if ('POST' === sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'] ?? '')) && isset($_POST['evt_ticket_resend_nonce'])) {
            if (wp_verify_nonce(wp_unslash($_POST['evt_ticket_resend_nonce']), 'evt_ticket_resend')) {
                $email = isset($_POST['evt_ticket_resend_email'])
                    ? sanitize_email(wp_unslash($_POST['evt_ticket_resend_email']))
                    : '';

                if ($email && is_email($email)) {
                    $this->handle_resend($email);
                    $status_type = 'success';
                    $status_text = $settings['success_message'];
                } else {
                    $status_type = 'error';
                    $status_text = $settings['error_message'];
                }
            }
        }

        echo '<div class="evt-ticket-resend-wrapper">';

        if ('yes' === ($settings['show_heading'] ?? 'yes')) {
            echo '<h2 class="evt-ticket-resend-heading">' . esc_html($settings['heading_text']) . '</h2>';
        }

        if (! empty($settings['description_text'])) {
            echo '<p class="evt-ticket-resend-description">' . esc_html($settings['description_text']) . '</p>';
        }

        if ($status_type && $status_text) {
            $class = 'evt-ticket-resend-status evt-ticket-resend-status-' . esc_attr($status_type);
            echo '<div class="' . esc_attr($class) . '">';
            echo '<p>' . esc_html($status_text) . '</p>';
            echo '</div>';
        }

?>
        <form method="post" class="evt-ticket-resend-form">
            <?php wp_nonce_field('evt_ticket_resend', 'evt_ticket_resend_nonce'); ?>
            <label for="evt-ticket-resend-email" class="evt-ticket-resend-label">
                <?php echo esc_html($settings['label_email']); ?>
            </label>
            <input
                type="email"
                id="evt-ticket-resend-email"
                name="evt_ticket_resend_email"
                class="evt-ticket-resend-input"
                placeholder="<?php echo esc_attr((string) ($settings['placeholder_email'] ?? __('you@example.com', 'Event-Tickets-for-Elementor'))); ?>"
                required />
            <button type="submit" class="evt-ticket-resend-button">
                <?php echo esc_html($settings['button_text']); ?>
            </button>
        </form>
<?php

        echo '</div>';
    }

    /**
     * Handle the resend logic.
     *
     * We intentionally always show the same success message on the frontend to
     * avoid email enumeration.
     */
    protected function handle_resend(string $email): void
    {
        $ticket_service = Plugin::instance()->tickets();
        $email_service  = Plugin::instance()->mailer();

        $ticket = $ticket_service->get_latest_ticket_for_email($email);
        if ($ticket) {
            $email_service->send_ticket_email($ticket->ID);
        }
    }
}
