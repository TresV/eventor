<?php
namespace EventTicketsElementor\Elementor_Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use EventTicketsElementor\Plugin;
use EventTicketsElementor\CPT_Tickets;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Public-facing ticket view widget.
 *
 * - Displays a ticket card for a given ticket code.
 * - Ticket code can come from ?code= in URL or manual input.
 * - Shows QR + calendar links.
 */
class Widget_Ticket_View extends Widget_Base {

    public function get_name() {
        return 'evt_ticket_view';
    }

    public function get_title() {
        return __( 'Event Ticket View', 'Event-Tickets-for-Elementor');
    }

    public function get_icon() {
        return 'eicon-post-list';
    }

    public function get_categories() {
        return [ 'evt-tickets' ];
    }

    public function get_style_depends() {
        return [ 'evt-tickets-ticket-view' ];
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            [
                'label' => __( 'Content', 'Event-Tickets-for-Elementor'),
                'tab'   => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'show_heading',
            [
                'label'        => __( 'Show Heading', 'Event-Tickets-for-Elementor'),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => __( 'Yes', 'Event-Tickets-for-Elementor'),
                'label_off'    => __( 'No', 'Event-Tickets-for-Elementor'),
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'heading_text',
            [
                'label'       => __( 'Heading Text', 'Event-Tickets-for-Elementor'),
                'type'        => Controls_Manager::TEXT,
                'default'     => __( 'Your Ticket', 'Event-Tickets-for-Elementor'),
                'placeholder' => __( 'Your Ticket', 'Event-Tickets-for-Elementor'),
                'condition'   => [
                    'show_heading' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'allow_manual_lookup',
            [
                'label'        => __( 'Allow Manual Ticket Code Input', 'Event-Tickets-for-Elementor'),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => __( 'Yes', 'Event-Tickets-for-Elementor'),
                'label_off'    => __( 'No', 'Event-Tickets-for-Elementor'),
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'manual_help_text',
            [
                'label'       => __( 'Manual Input Help Text', 'Event-Tickets-for-Elementor'),
                'type'        => Controls_Manager::TEXTAREA,
                'default'     => __( 'If your ticket didn’t open automatically from the link, paste your ticket code below.', 'Event-Tickets-for-Elementor'),
                'placeholder' => __( 'Instructions for visitors…', 'Event-Tickets-for-Elementor'),
                'condition'   => [
                    'allow_manual_lookup' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'show_status',
            [
                'label'        => __( 'Show Status', 'Event-Tickets-for-Elementor'),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => __( 'Yes', 'Event-Tickets-for-Elementor'),
                'label_off'    => __( 'No', 'Event-Tickets-for-Elementor'),
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'show_qr',
            [
                'label'        => __( 'Show QR Code', 'Event-Tickets-for-Elementor'),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => __( 'Yes', 'Event-Tickets-for-Elementor'),
                'label_off'    => __( 'No', 'Event-Tickets-for-Elementor'),
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'show_calendar_links',
            [
                'label'        => __( 'Show "Add to calendar" Links', 'Event-Tickets-for-Elementor'),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => __( 'Yes', 'Event-Tickets-for-Elementor'),
                'label_off'    => __( 'No', 'Event-Tickets-for-Elementor'),
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->end_controls_section();

        /**
         * Labels section.
         */
        $this->start_controls_section(
            'section_labels',
            [
                'label' => __( 'Labels', 'Event-Tickets-for-Elementor'),
                'tab'   => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'label_attendee',
            [
                'label'   => __( 'Attendee Label', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::TEXT,
                'default' => __( 'Attendee', 'Event-Tickets-for-Elementor'),
            ]
        );

        $this->add_control(
            'label_ticket_code',
            [
                'label'   => __( 'Ticket Code Label', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::TEXT,
                'default' => __( 'Ticket code', 'Event-Tickets-for-Elementor'),
            ]
        );

        $this->add_control(
            'label_event',
            [
                'label'   => __( 'Event Label', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::TEXT,
                'default' => __( 'Event', 'Event-Tickets-for-Elementor'),
            ]
        );

        $this->add_control(
            'label_event_datetime',
            [
                'label'   => __( 'Date & Time Label', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::TEXT,
                'default' => __( 'Date & time', 'Event-Tickets-for-Elementor'),
            ]
        );

        $this->add_control(
            'label_event_location',
            [
                'label'   => __( 'Location Label', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::TEXT,
                'default' => __( 'Location', 'Event-Tickets-for-Elementor'),
            ]
        );

        $this->add_control(
            'label_status',
            [
                'label'   => __( 'Status Label', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::TEXT,
                'default' => __( 'Status', 'Event-Tickets-for-Elementor'),
                'condition' => [
                    'show_status' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'label_status_pending',
            [
                'label'   => __( 'Status – Pending', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::TEXT,
                'default' => __( 'Not checked in', 'Event-Tickets-for-Elementor'),
                'condition' => [
                    'show_status' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'label_status_checked_in',
            [
                'label'   => __( 'Status – Checked in', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::TEXT,
                'default' => __( 'Checked in', 'Event-Tickets-for-Elementor'),
                'condition' => [
                    'show_status' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'label_checked_in_at',
            [
                'label'   => __( '"Checked-in at" Label', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::TEXT,
                'default' => __( 'Checked-in at', 'Event-Tickets-for-Elementor'),
                'condition' => [
                    'show_status' => 'yes',
                ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_card',
            [
                'label' => __( 'Card', 'Event-Tickets-for-Elementor'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'card_background',
            [
                'label' => __( 'Background', 'Event-Tickets-for-Elementor'),
                'type'  => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-ticket-view' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'card_border',
            [
                'label' => __( 'Border', 'Event-Tickets-for-Elementor'),
                'type'  => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-ticket-view' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'button_background',
            [
                'label' => __( 'Button Background', 'Event-Tickets-for-Elementor'),
                'type'  => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-ticket-view .evt-ticket-actions a' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'button_color',
            [
                'label' => __( 'Button Text', 'Event-Tickets-for-Elementor'),
                'type'  => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .evt-ticket-view .evt-ticket-actions a' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();

        $ticket_data = [];
        $status_type = '';
        $status_text = '';

        $code = '';

        // Code from URL.
        if ( isset( $_GET['code'] ) ) {
            $code = sanitize_text_field( wp_unslash( $_GET['code'] ) );
        }

        // Manual POST lookup (fallback / non-AJAX).
        if ( 'POST' === sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) && isset( $_POST['evt_ticket_view_nonce'] ) ) {
            if ( wp_verify_nonce( wp_unslash( $_POST['evt_ticket_view_nonce'] ), 'evt_ticket_view' ) ) {
                if ( ! empty( $_POST['ticket_code'] ) ) {
                    $code = sanitize_text_field( wp_unslash( $_POST['ticket_code'] ) );
                }
            }
        }

        if ( $code ) {
            $ticket = Plugin::instance()->tickets()->get_ticket_by_code( $code );
            if ( $ticket ) {
                $ticket_data = $this->get_ticket_data( $ticket->ID );

                if ( 'checked_in' === $ticket_data['status'] ) {
                    $status_type = 'checked_in';
                    $status_text = $settings['label_status_checked_in'] ?? __( 'Checked in', 'Event-Tickets-for-Elementor');
                } else {
                    $status_type = 'pending';
                    $status_text = $settings['label_status_pending'] ?? __( 'Not checked in', 'Event-Tickets-for-Elementor');
                }
            }
        }

        echo '<div class="evt-ticket-view-wrapper">';

        if ( 'yes' === ( $settings['show_heading'] ?? 'yes' ) ) {
            echo '<h2 class="evt-ticket-view-heading">' . esc_html( $settings['heading_text'] ) . '</h2>';
        }

        // Manual lookup form.
        if ( 'yes' === ( $settings['allow_manual_lookup'] ?? 'yes' ) ) {
            echo '<div class="evt-ticket-view-manual">';
            if ( ! empty( $settings['manual_help_text'] ) ) {
                echo '<p class="evt-ticket-view-manual-help">' . esc_html( $settings['manual_help_text'] ) . '</p>';
            }
            ?>
            <form method="post" class="evt-ticket-view-form">
                <?php wp_nonce_field( 'evt_ticket_view', 'evt_ticket_view_nonce' ); ?>
                <label for="evt-ticket-view-code" class="evt-ticket-view-label">
                    <?php echo esc_html( $settings['label_ticket_code'] ?? __( 'Ticket code', 'Event-Tickets-for-Elementor') ); ?>
                </label>
                <input
                    type="text"
                    id="evt-ticket-view-code"
                    name="ticket_code"
                    class="evt-ticket-view-input"
                    value="<?php echo esc_attr( $code ); ?>"
                    placeholder="<?php esc_attr_e( 'Paste your ticket code…', 'Event-Tickets-for-Elementor'); ?>"
                />
                <button type="submit" class="evt-ticket-view-button">
                    <?php esc_html_e( 'View Ticket', 'Event-Tickets-for-Elementor'); ?>
                </button>
            </form>
            <?php
            echo '</div>';
        }

        // Ticket card.
        if ( $ticket_data ) {
            $this->render_ticket_card( $ticket_data, $settings, $status_type, $status_text );
        } elseif ( $code ) {
            echo '<div class="evt-ticket-view-message evt-ticket-view-error">';
            esc_html_e( 'No ticket was found for this code.', 'Event-Tickets-for-Elementor');
            echo '</div>';
        }

        echo '</div>'; // .evt-ticket-view-wrapper
    }

    /**
     * Render the ticket card with labels & toggles.
     */
    protected function render_ticket_card( array $ticket, array $settings, string $status_type, string $status_text ): void {
        $label_attendee         = $settings['label_attendee'] ?? __( 'Attendee', 'Event-Tickets-for-Elementor');
        $label_ticket_code      = $settings['label_ticket_code'] ?? __( 'Ticket code', 'Event-Tickets-for-Elementor');
        $label_event            = $settings['label_event'] ?? __( 'Event', 'Event-Tickets-for-Elementor');
        $label_event_datetime   = $settings['label_event_datetime'] ?? __( 'Date & time', 'Event-Tickets-for-Elementor');
        $label_event_location   = $settings['label_event_location'] ?? __( 'Location', 'Event-Tickets-for-Elementor');
        $label_status           = $settings['label_status'] ?? __( 'Status', 'Event-Tickets-for-Elementor');
        $label_checked_in_at    = $settings['label_checked_in_at'] ?? __( 'Checked-in at', 'Event-Tickets-for-Elementor');

        $show_status           = ( 'yes' === ( $settings['show_status'] ?? 'yes' ) );
        $show_qr               = ( 'yes' === ( $settings['show_qr'] ?? 'yes' ) );
        $show_calendar_links   = ( 'yes' === ( $settings['show_calendar_links'] ?? 'yes' ) );
        $event_name    = $ticket['event_name'];
        $event_start   = $ticket['event_start'];
        $event_location = $ticket['event_location'];

        // Calendar URLs
        $calendar = Plugin::instance()->calendar();
        $google_url = $calendar->get_google_calendar_url( $ticket['id'] );
        $ics_url    = $calendar->get_ics_download_url( $ticket['id'] );

        // QR code: link to staff check-in page with code param.
        // (Assumes a /ticket-checkin/ page – adjust if needed later.
        $checkin_url = add_query_arg(
            'code',
            rawurlencode( $ticket['code'] ),
            home_url( '/ticket-checkin/' )
        );
        $qr_src = ( new \EventTicketsElementor\Ticket_Qr() )->get_qr_url( (string) $ticket['code'], (string) $checkin_url, 220 );

        echo '<div class="evt-ticket-card">';

        echo '<div class="evt-ticket-card-main">';
        echo '<div class="evt-ticket-card-header">';
        echo '<div class="evt-ticket-card-title">' . esc_html( $event_name ) . '</div>';
        echo '<div class="evt-ticket-card-code">';
        echo '<span class="evt-ticket-card-code-label">' . esc_html( $label_ticket_code ) . ':</span> ';
        echo '<span class="evt-ticket-card-code-value">' . esc_html( $ticket['code'] ) . '</span>';
        echo '</div>';
        echo '</div>'; // header

        echo '<div class="evt-ticket-card-meta">';
        echo '<div class="evt-ticket-card-row">';
        echo '<span class="evt-ticket-card-label">' . esc_html( $label_attendee ) . ':</span> ';
        echo '<span class="evt-ticket-card-value">' . esc_html( $ticket['name'] ) . '</span>';
        echo '</div>';

        if ( $event_start ) {
            echo '<div class="evt-ticket-card-row">';
            echo '<span class="evt-ticket-card-label">' . esc_html( $label_event_datetime ) . ':</span> ';
            echo '<span class="evt-ticket-card-value">' . esc_html( $event_start ) . '</span>';
            echo '</div>';
        }

        if ( $event_location ) {
            echo '<div class="evt-ticket-card-row">';
            echo '<span class="evt-ticket-card-label">' . esc_html( $label_event_location ) . ':</span> ';
            echo '<span class="evt-ticket-card-value">' . esc_html( $event_location ) . '</span>';
            echo '</div>';
        }

        if ( $show_status ) {
            echo '<div class="evt-ticket-card-row evt-ticket-card-row-status evt-ticket-card-row-status-' . esc_attr( $status_type ) . '">';
            echo '<span class="evt-ticket-card-label">' . esc_html( $label_status ) . ':</span> ';
            echo '<span class="evt-ticket-card-value">' . esc_html( $status_text ) . '</span>';
            echo '</div>';

            if ( ! empty( $ticket['checked_in_at'] ) ) {
                echo '<div class="evt-ticket-card-row">';
                echo '<span class="evt-ticket-card-label">' . esc_html( $label_checked_in_at ) . ':</span> ';
                echo '<span class="evt-ticket-card-value">' . esc_html( $ticket['checked_in_at'] ) . '</span>';
                echo '</div>';
            }
        }

        echo '</div>'; // meta

        if ( $show_calendar_links ) {
            echo '<div class="evt-ticket-card-calendar">';
            echo '<span class="evt-ticket-card-calendar-label">' . esc_html__( 'Add to calendar:', 'Event-Tickets-for-Elementor') . '</span> ';
            if ( $google_url ) {
                echo '<a href="' . esc_url( $google_url ) . '" target="_blank" rel="noopener noreferrer" class="evt-ticket-card-calendar-link">';
                esc_html_e( 'Google Calendar', 'Event-Tickets-for-Elementor');
                echo '</a>';
            }
            if ( $ics_url ) {
                echo '<a href="' . esc_url( $ics_url ) . '" class="evt-ticket-card-calendar-link">';
                esc_html_e( '.ics file', 'Event-Tickets-for-Elementor');
                echo '</a>';
            }
            echo '</div>';
        }

        echo '</div>'; // main

        if ( $show_qr && $qr_src ) {
            echo '<div class="evt-ticket-card-qr">';
            echo '<img src="' . esc_url( $qr_src ) . '" alt="' . esc_attr__( 'Check-in QR code', 'Event-Tickets-for-Elementor') . '" />';
            echo '<p class="evt-ticket-card-qr-caption">' . esc_html__( 'Show this code at the entrance.', 'Event-Tickets-for-Elementor') . '</p>';
            echo '</div>';
        }

        echo '</div>'; // card
    }

    /**
     * Collect ticket data from meta.
     */
    protected function get_ticket_data( int $ticket_id ): array {
        $ticket = get_post( $ticket_id );
        if ( ! $ticket || CPT_Tickets::POST_TYPE !== $ticket->post_type ) {
            return [];
        }

        $code          = get_post_meta( $ticket_id, '_ticket_code', true );
        $name          = get_post_meta( $ticket_id, '_ticket_name', true );
        $email         = get_post_meta( $ticket_id, '_ticket_email', true );
        $event_name    = get_post_meta( $ticket_id, '_ticket_event_name', true );
        $event_start   = get_post_meta( $ticket_id, '_ticket_event_start', true );
        $event_location = get_post_meta( $ticket_id, '_ticket_event_location', true );
        $status        = get_post_meta( $ticket_id, '_ticket_status', true );
        $checked_in_at = get_post_meta( $ticket_id, '_ticket_checked_in_at', true );

        if ( ! $status ) {
            $status = 'pending';
        }

        return [
            'id'            => $ticket_id,
            'code'          => (string) $code,
            'name'          => (string) $name,
            'email'         => (string) $email,
            'event_name'    => (string) $event_name,
            'event_start'   => (string) $event_start,
            'event_location'=> (string) $event_location,
            'status'        => (string) $status,
            'checked_in_at' => (string) $checked_in_at,
        ];
    }
}
