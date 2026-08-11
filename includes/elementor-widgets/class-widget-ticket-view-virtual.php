<?php
namespace EventTicketsElementor\Elementor_Widgets;

use Elementor\Controls_Manager;
use EventTicketsElementor\Event_Virtual_Renderer;
use EventTicketsElementor\CPT_Events;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Ticket View widget extension that displays virtual/hybrid links/embeds.
 *
 * Does not modify the original Widget_Ticket_View (keeps codebase modular).
 */
class Widget_Ticket_View_Virtual extends Widget_Ticket_View {

    public function get_name() {
        return 'evt_ticket_view_virtual';
    }

    public function get_title() {
        return __( 'Event Ticket View (Virtual)', 'Event-Tickets-for-Elementor');
    }

    public function get_icon() {
        return 'eicon-video-camera';
    }

    public function get_style_depends() {
        return [ 'evt-tickets-ticket-view', 'evt-tickets-ticket-virtual' ];
    }

    protected function register_controls() {
        parent::register_controls();

        $this->start_controls_section(
            'section_virtual',
            [
                'label' => __( 'Virtual / Hybrid', 'Event-Tickets-for-Elementor'),
                'tab'   => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'show_virtual',
            [
                'label'        => __( 'Show virtual links/embed', 'Event-Tickets-for-Elementor'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'embed_livestream',
            [
                'label'        => __( 'Embed livestream (YouTube/Vimeo)', 'Event-Tickets-for-Elementor'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => 'yes',
                'condition'    => [
                    'show_virtual' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'embed_height',
            [
                'label'     => __( 'Embed height (px)', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::NUMBER,
                'default'   => 360,
                'min'       => 200,
                'max'       => 900,
                'condition' => [
                    'show_virtual'     => 'yes',
                    'embed_livestream' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'label_meeting',
            [
                'label'     => __( 'Meeting Label', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::TEXT,
                'default'   => __( 'Join meeting', 'Event-Tickets-for-Elementor'),
                'condition' => [
                    'show_virtual' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'label_livestream',
            [
                'label'     => __( 'Livestream Label', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::TEXT,
                'default'   => __( 'Watch livestream', 'Event-Tickets-for-Elementor'),
                'condition' => [
                    'show_virtual' => 'yes',
                ],
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Renders the base ticket card and appends virtual/hybrid section when available.
     */
    protected function render_ticket_card( array $ticket, array $settings, string $status_type, string $status_text ): void {
        parent::render_ticket_card( $ticket, $settings, $status_type, $status_text );

        if ( 'yes' !== ( $settings['show_virtual'] ?? 'yes' ) ) {
            return;
        }

        $ticket_id = (int) ( $ticket['id'] ?? 0 );
        if ( ! $ticket_id ) {
            return;
        }

        $event_id = (int) get_post_meta( $ticket_id, '_ticket_event_id', true );
        if ( ! $event_id ) {
            return;
        }

        $event = get_post( $event_id );
        if ( ! $event || CPT_Events::POST_TYPE !== $event->post_type ) {
            return;
        }

        $renderer = new Event_Virtual_Renderer();
        $urls = $renderer->get_urls_for_event( $event_id );

        $meeting = $urls['meeting_url'] ?? '';
        $stream  = $urls['livestream_url'] ?? '';

        if ( ! $meeting && ! $stream ) {
            return;
        }

        $label_meeting = $settings['label_meeting'] ?? __( 'Join meeting', 'Event-Tickets-for-Elementor');
        $label_stream  = $settings['label_livestream'] ?? __( 'Watch livestream', 'Event-Tickets-for-Elementor');
        $embed_height  = (int) ( $settings['embed_height'] ?? 360 );
        $embed_enabled = ( 'yes' === ( $settings['embed_livestream'] ?? 'yes' ) );

        echo '<div class="evt-virtual">';
        echo '<h4 class="evt-virtual__title">' . esc_html__( 'Online access', 'Event-Tickets-for-Elementor') . '</h4>';

        if ( $meeting ) {
            echo '<p class="evt-virtual__link">';
            echo '<a class="evt-virtual__button" target="_blank" rel="noopener noreferrer" href="' . esc_url( $meeting ) . '">';
            echo esc_html( $label_meeting );
            echo '</a>';
            echo '</p>';
        }

        if ( $stream ) {
            $embed_html = $embed_enabled ? $renderer->render_embed( $stream, $embed_height ) : '';
            if ( $embed_html ) {
                echo $embed_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            } else {
                echo '<p class="evt-virtual__link">';
                echo '<a class="evt-virtual__button" target="_blank" rel="noopener noreferrer" href="' . esc_url( $stream ) . '">';
                echo esc_html( $label_stream );
                echo '</a>';
                echo '</p>';
            }
        }

        echo '</div>';
    }
}
