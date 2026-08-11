<?php

namespace EventTicketsElementor\Elementor_Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

if (! defined('ABSPATH')) {
    exit;
}


/**
 * Ticket Cancel widget (self-service cancellation).
 */
class Widget_Ticket_Cancel extends Widget_Base
{
    public function get_name()
    {
        return 'evt_ticket_cancel';
    }

    public function get_title()
    {
        return __('Cancel Ticket', 'Event-Tickets-for-Elementor');
    }

    public function get_icon()
    {
        return 'eicon-ban';
    }

    public function get_categories()
    {
        return ['evt-tickets'];
    }

    public function get_style_depends()
    {
        return [];
    }

    public function get_script_depends()
    {
        return [];
    }

    protected function register_controls()
    {
        $this->start_controls_section(
            'content_section',
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
                'default'     => __('Cancel your ticket', 'Event-Tickets-for-Elementor'),
                'label_block' => true,
            ]
        );

        $this->add_control(
            'help_text',
            [
                'label'   => __('Help Text', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::TEXTAREA,
                'default' => __('Enter your ticket code and the email used during registration.', 'Event-Tickets-for-Elementor'),
            ]
        );

        $this->add_control(
            'show_refund',
            [
                'label'        => __('Allow “Refund requested”', 'Event-Tickets-for-Elementor'),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => __('Yes', 'Event-Tickets-for-Elementor'),
                'label_off'    => __('No', 'Event-Tickets-for-Elementor'),
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'button_text',
            [
                'label'   => __('Button Text', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::TEXT,
                'default' => __('Cancel ticket', 'Event-Tickets-for-Elementor'),
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
        $this->add_control('placeholder_ticket_code', ['label' => __('Placeholder: Ticket code', 'Event-Tickets-for-Elementor'), 'type' => Controls_Manager::TEXT, 'default' => __('Enter ticket code', 'Event-Tickets-for-Elementor')]);
        $this->add_control('label_email', ['label' => __('Label: Email', 'Event-Tickets-for-Elementor'), 'type' => Controls_Manager::TEXT, 'default' => __('Email', 'Event-Tickets-for-Elementor')]);
        $this->add_control('placeholder_email', ['label' => __('Placeholder: Email', 'Event-Tickets-for-Elementor'), 'type' => Controls_Manager::TEXT, 'default' => __('Enter email', 'Event-Tickets-for-Elementor')]);
        $this->add_control('label_reason', ['label' => __('Label: Reason', 'Event-Tickets-for-Elementor'), 'type' => Controls_Manager::TEXT, 'default' => __('Reason (optional)', 'Event-Tickets-for-Elementor')]);
        $this->add_control('placeholder_reason', ['label' => __('Placeholder: Reason', 'Event-Tickets-for-Elementor'), 'type' => Controls_Manager::TEXT, 'default' => __('Optional reason', 'Event-Tickets-for-Elementor')]);
        $this->add_control('label_refund', ['label' => __('Label: Refund checkbox', 'Event-Tickets-for-Elementor'), 'type' => Controls_Manager::TEXT, 'default' => __('Request a refund', 'Event-Tickets-for-Elementor'), 'condition' => ['show_refund' => 'yes']]);
        $this->add_control('i18n_submitting', ['label' => __('Message: Submitting', 'Event-Tickets-for-Elementor'), 'type' => Controls_Manager::TEXT, 'default' => __('Submitting…', 'Event-Tickets-for-Elementor')]);
        $this->add_control('i18n_success', ['label' => __('Message: Success', 'Event-Tickets-for-Elementor'), 'type' => Controls_Manager::TEXT, 'default' => __('Ticket cancelled.', 'Event-Tickets-for-Elementor')]);
        $this->add_control('i18n_confirmation_sent', ['label' => __('Message: Confirmation sent', 'Event-Tickets-for-Elementor'), 'type' => Controls_Manager::TEXT, 'default' => __('A confirmation link has been sent to your email.', 'Event-Tickets-for-Elementor')]);
        $this->add_control('i18n_error', ['label' => __('Message: Error (fallback)', 'Event-Tickets-for-Elementor'), 'type' => Controls_Manager::TEXT, 'default' => __('Unable to cancel ticket.', 'Event-Tickets-for-Elementor')]);

        $this->end_controls_section();

        $this->start_controls_section(
            'style_section',
            [
                'label' => __('Style', 'Event-Tickets-for-Elementor'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'container_padding',
            [
                'label'      => __('Container Padding', 'Event-Tickets-for-Elementor'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors'  => [
                    '{{WRAPPER}} .evt-ticket-cancel' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'container_bg',
            [
                'label'     => __('Container Background', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-ticket-cancel' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(Group_Control_Border::get_type(), ['name' => 'container_border', 'label' => __('Container Border', 'Event-Tickets-for-Elementor'), 'selector' => '{{WRAPPER}} .evt-ticket-cancel']);
        $this->add_control('container_border_radius', ['label' => __('Container Border Radius', 'Event-Tickets-for-Elementor'), 'type' => Controls_Manager::SLIDER, 'size_units' => ['px'], 'range' => ['px' => ['min' => 0, 'max' => 40]], 'selectors' => ['{{WRAPPER}} .evt-ticket-cancel' => 'border-radius: {{SIZE}}{{UNIT}};']]);
        $this->add_control('form_gap', ['label' => __('Form Row Gap', 'Event-Tickets-for-Elementor'), 'type' => Controls_Manager::SLIDER, 'size_units' => ['px'], 'range' => ['px' => ['min' => 0, 'max' => 40]], 'selectors' => ['{{WRAPPER}} .evt-ticket-cancel__form' => 'gap: {{SIZE}}{{UNIT}};']]);

        $this->add_control(
            'heading_color',
            [
                'label'     => __('Heading Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-ticket-cancel__heading' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'heading_typography',
                'label'    => __('Heading Typography', 'Event-Tickets-for-Elementor'),
                'selector' => '{{WRAPPER}} .evt-ticket-cancel__heading',
            ]
        );

        $this->add_control(
            'help_color',
            [
                'label'     => __('Help Text Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-ticket-cancel__help' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'help_typography',
                'label'    => __('Help Text Typography', 'Event-Tickets-for-Elementor'),
                'selector' => '{{WRAPPER}} .evt-ticket-cancel__help',
            ]
        );

        $this->add_control(
            'label_color',
            [
                'label'     => __('Label Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-ticket-cancel__label' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'label_typography',
                'label'    => __('Label Typography', 'Event-Tickets-for-Elementor'),
                'selector' => '{{WRAPPER}} .evt-ticket-cancel__label',
            ]
        );

        $this->add_control(
            'input_text_color',
            [
                'label'     => __('Input Text Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-ticket-cancel__input' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'input_bg',
            [
                'label'     => __('Input Background', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-ticket-cancel__input' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'input_border_color',
            [
                'label'     => __('Input Border Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-ticket-cancel__input' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'input_border_radius',
            [
                'label'      => __('Input Border Radius', 'Event-Tickets-for-Elementor'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => [
                    'px' => ['min' => 0, 'max' => 40],
                ],
                'selectors'  => [
                    '{{WRAPPER}} .evt-ticket-cancel__input' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'input_typography',
                'label'    => __('Input Typography', 'Event-Tickets-for-Elementor'),
                'selector' => '{{WRAPPER}} .evt-ticket-cancel__input',
            ]
        );

        $this->add_control(
            'button_text_color',
            [
                'label'     => __('Button Text Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-ticket-cancel__button' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'button_bg',
            [
                'label'     => __('Button Background', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-ticket-cancel__button' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'button_border_radius',
            [
                'label'      => __('Button Border Radius', 'Event-Tickets-for-Elementor'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => [
                    'px' => ['min' => 0, 'max' => 40],
                ],
                'selectors'  => [
                    '{{WRAPPER}} .evt-ticket-cancel__button' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'button_typography',
                'label'    => __('Button Typography', 'Event-Tickets-for-Elementor'),
                'selector' => '{{WRAPPER}} .evt-ticket-cancel__button',
            ]
        );

        $this->add_control(
            'message_success_color',
            [
                'label'     => __('Success Message Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-ticket-cancel__message.is-success' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'message_error_color',
            [
                'label'     => __('Error Message Color', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-ticket-cancel__message.is-error' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'message_typography',
                'label'    => __('Message Typography', 'Event-Tickets-for-Elementor'),
                'selector' => '{{WRAPPER}} .evt-ticket-cancel__message',
            ]
        );

        $this->end_controls_section();
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();

        wp_enqueue_script(
            'evt-tickets-ticket-cancel',
            EVT_TICKETS_PLUGIN_URL . 'assets/js/ticket-cancel.js',
            ['jquery'],
            defined('EVT_TICKETS_VERSION') ? \EVT_TICKETS_VERSION : 'dev',
            true
        );

        wp_localize_script(
            'evt-tickets-ticket-cancel',
            'EvtTicketsCancel',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('evt_ticket_cancel'),
                'i18n'    => [
                    'submitting' => __('Submitting…', 'Event-Tickets-for-Elementor'),
                    'success' => __('Ticket cancelled.', 'Event-Tickets-for-Elementor'),
                    'confirmation_sent' => __('A confirmation link has been sent to your email.', 'Event-Tickets-for-Elementor'),
                    'error'   => __('Unable to cancel ticket.', 'Event-Tickets-for-Elementor'),
                ],
            ]
        );

        wp_enqueue_style(
            'evt-tickets-ticket-cancel',
            EVT_TICKETS_PLUGIN_URL . 'assets/css/ticket-cancel.css',
            [],
            defined('EVT_TICKETS_VERSION') ? \EVT_TICKETS_VERSION : 'dev'
        );

        $show_refund = ('yes' === ($settings['show_refund'] ?? 'yes'));

        $label_ticket_code = is_string($settings['label_ticket_code'] ?? '') && trim((string) $settings['label_ticket_code']) !== '' ? trim((string) $settings['label_ticket_code']) : __('Ticket code', 'Event-Tickets-for-Elementor');
        $placeholder_ticket_code = is_string($settings['placeholder_ticket_code'] ?? '') && trim((string) $settings['placeholder_ticket_code']) !== '' ? trim((string) $settings['placeholder_ticket_code']) : __('Enter ticket code', 'Event-Tickets-for-Elementor');
        $label_email = is_string($settings['label_email'] ?? '') && trim((string) $settings['label_email']) !== '' ? trim((string) $settings['label_email']) : __('Email', 'Event-Tickets-for-Elementor');
        $placeholder_email = is_string($settings['placeholder_email'] ?? '') && trim((string) $settings['placeholder_email']) !== '' ? trim((string) $settings['placeholder_email']) : __('Enter email', 'Event-Tickets-for-Elementor');
        $label_reason = is_string($settings['label_reason'] ?? '') && trim((string) $settings['label_reason']) !== '' ? trim((string) $settings['label_reason']) : __('Reason (optional)', 'Event-Tickets-for-Elementor');
        $placeholder_reason = is_string($settings['placeholder_reason'] ?? '') && trim((string) $settings['placeholder_reason']) !== '' ? trim((string) $settings['placeholder_reason']) : __('Optional reason', 'Event-Tickets-for-Elementor');
        $label_refund = is_string($settings['label_refund'] ?? '') && trim((string) $settings['label_refund']) !== '' ? trim((string) $settings['label_refund']) : __('Request a refund', 'Event-Tickets-for-Elementor');

        $i18n = [
            'submitting' => is_string($settings['i18n_submitting'] ?? '') && trim((string) $settings['i18n_submitting']) !== '' ? trim((string) $settings['i18n_submitting']) : __('Submitting…', 'Event-Tickets-for-Elementor'),
            'success' => is_string($settings['i18n_success'] ?? '') && trim((string) $settings['i18n_success']) !== '' ? trim((string) $settings['i18n_success']) : __('Ticket cancelled.', 'Event-Tickets-for-Elementor'),
            'confirmation_sent' => is_string($settings['i18n_confirmation_sent'] ?? '') && trim((string) $settings['i18n_confirmation_sent']) !== '' ? trim((string) $settings['i18n_confirmation_sent']) : __('A confirmation link has been sent to your email.', 'Event-Tickets-for-Elementor'),
            'error'   => is_string($settings['i18n_error'] ?? '') && trim((string) $settings['i18n_error']) !== '' ? trim((string) $settings['i18n_error']) : __('Unable to cancel ticket.', 'Event-Tickets-for-Elementor'),
        ];

        $prefill_code = '';
        if (isset($_GET['ticket_code'])) {
            $prefill_code = sanitize_text_field(wp_unslash((string) $_GET['ticket_code']));
        } elseif (isset($_GET['code'])) {
            $prefill_code = sanitize_text_field(wp_unslash((string) $_GET['code']));
        }

        $prefill_email = '';
        if (isset($_GET['email'])) {
            $prefill_email = sanitize_email(wp_unslash((string) $_GET['email']));
        }

        $prefill_sig = isset($_GET['sig']) ? sanitize_text_field(wp_unslash((string) $_GET['sig'])) : '';
        $prefill_ts  = isset($_GET['ts']) ? absint($_GET['ts']) : 0;
?>
        <div class="evt-ticket-cancel" data-i18n="<?php echo esc_attr(wp_json_encode($i18n)); ?>">
            <?php if (! empty($settings['heading'])) : ?>
                <h3 class="evt-ticket-cancel__heading"><?php echo esc_html($settings['heading']); ?></h3>
            <?php endif; ?>

            <?php if (! empty($settings['help_text'])) : ?>
                <p class="evt-ticket-cancel__help"><?php echo esc_html($settings['help_text']); ?></p>
            <?php endif; ?>

            <form class="evt-ticket-cancel__form" method="post">
                <div class="evt-ticket-cancel__row">
                    <label class="evt-ticket-cancel__label">
                        <?php echo esc_html($label_ticket_code); ?>
                        <input class="evt-ticket-cancel__input" type="text" name="ticket_code" value="<?php echo esc_attr($prefill_code); ?>" placeholder="<?php echo esc_attr($placeholder_ticket_code); ?>" required />
                    </label>
                </div>

                <div class="evt-ticket-cancel__row">
                    <label class="evt-ticket-cancel__label">
                        <?php echo esc_html($label_email); ?>
                        <input class="evt-ticket-cancel__input" type="email" name="email" value="<?php echo esc_attr($prefill_email); ?>" placeholder="<?php echo esc_attr($placeholder_email); ?>" />
                    </label>
                </div>

                <?php if ('' !== trim($prefill_sig) && $prefill_ts > 0) : ?>
                    <input type="hidden" name="sig" value="<?php echo esc_attr($prefill_sig); ?>" />
                    <input type="hidden" name="ts" value="<?php echo esc_attr((string) $prefill_ts); ?>" />
                <?php endif; ?>

                <div class="evt-ticket-cancel__row">
                    <label class="evt-ticket-cancel__label">
                        <?php echo esc_html($label_reason); ?>
                        <input class="evt-ticket-cancel__input" type="text" name="reason" placeholder="<?php echo esc_attr($placeholder_reason); ?>" />
                    </label>
                </div>

                <?php if ($show_refund) : ?>
                    <div class="evt-ticket-cancel__row">
                        <label class="evt-ticket-cancel__checkbox">
                            <input type="checkbox" name="refund_request" value="1" />
                            <?php echo esc_html($label_refund); ?>
                        </label>
                    </div>
                <?php endif; ?>

                <div class="evt-ticket-cancel__row">
                    <button class="evt-ticket-cancel__button" type="submit">
                        <?php echo esc_html($settings['button_text'] ?? __('Cancel ticket', 'Event-Tickets-for-Elementor')); ?>
                    </button>
                </div>

                <div class="evt-ticket-cancel__message" aria-live="polite"></div>
            </form>
        </div>
<?php
    }
}
