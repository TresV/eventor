<?php
namespace EventTicketsElementor\Elementor_Widgets;

use Elementor\Controls_Manager;
use EventTicketsElementor\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Ticket View widget extension that adds a signed PDF download action.
 */
class Widget_Ticket_View_PDF extends Widget_Ticket_View {

    public function get_name() {
        return 'evt_ticket_view_pdf';
    }

    public function get_title() {
        return __( 'Event Ticket View (PDF)', 'Event-Tickets-for-Elementor');
    }

    public function get_icon() {
        return 'eicon-file-download';
    }

    protected function register_controls() {
        parent::register_controls();

        $this->start_controls_section(
            'section_pdf',
            [
                'label' => __( 'PDF', 'Event-Tickets-for-Elementor'),
                'tab'   => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'show_pdf_download',
            [
                'label'        => __( 'Show PDF download button', 'Event-Tickets-for-Elementor'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'label_pdf_download',
            [
                'label'     => __( 'Button Label', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::TEXT,
                'default'   => __( 'Download Ticket (PDF)', 'Event-Tickets-for-Elementor'),
                'condition' => [
                    'show_pdf_download' => 'yes',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render_ticket_card( array $ticket, array $settings, string $status_type, string $status_text ): void {
        parent::render_ticket_card( $ticket, $settings, $status_type, $status_text );

        if ( 'yes' !== ( $settings['show_pdf_download'] ?? 'yes' ) ) {
            return;
        }

        $ticket_id = (int) ( $ticket['id'] ?? 0 );
        if ( ! $ticket_id ) {
            return;
        }

        $pdf_url = Plugin::instance()->pdf_endpoint()->get_pdf_download_url( $ticket_id );
        if ( ! $pdf_url ) {
            return;
        }

        $label = $settings['label_pdf_download'] ?? __( 'Download Ticket (PDF)', 'Event-Tickets-for-Elementor');

        echo '<div class="evt-ticket-view-actions">';
        echo '<a class="evt-ticket-view-action" href="' . esc_url( $pdf_url ) . '">';
        echo esc_html( $label );
        echo '</a>';
        echo '</div>';
    }
}
