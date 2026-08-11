<?php

namespace EventTicketsElementor;

use EventTicketsElementor\Email\Email_Links;
use EventTicketsElementor\Tickets\Ticket_Cancellation_Service;
use EventTicketsElementor\Tickets\Ticket_Cancel_Link;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * AJAX endpoint for self-service ticket cancellation.
 */
class Ticket_Cancel_Ajax
{
    private Ticket_Cancellation_Service $cancellation;

    public function __construct(Ticket_Cancellation_Service $cancellation)
    {
        $this->cancellation = $cancellation;

        add_action('wp_ajax_evt_ticket_cancel', [$this, 'handle']);
        add_action('wp_ajax_nopriv_evt_ticket_cancel', [$this, 'handle']);
    }

    public function handle(): void
    {
        check_ajax_referer('evt_ticket_cancel', 'nonce');

        $ticket_code = isset($_POST['ticket_code']) ? sanitize_text_field(wp_unslash($_POST['ticket_code'])) : '';
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $sig = isset($_POST['sig']) ? sanitize_text_field(wp_unslash($_POST['sig'])) : '';
        $ts = isset($_POST['ts']) ? absint($_POST['ts']) : 0;
        $mode = isset($_POST['mode']) ? sanitize_key(wp_unslash($_POST['mode'])) : 'cancel_only';
        $reason = isset($_POST['reason']) ? sanitize_text_field(wp_unslash($_POST['reason'])) : '';

        $ticket_code = trim($ticket_code);
        if ('' === $ticket_code) {
            \evt_json_error('evt_missing_ticket_code', __('Please enter your ticket code.', 'Event-Tickets-for-Elementor'), 400);
        }

        $has_sig = ('' !== trim($sig) && $ts > 0);

        // The email-match path is a weak bearer credential: throttle attempts so
        // a leaked ticket code cannot be brute-forced against the attendee email.
        if (! $has_sig && evt_rate_limit_hit('ticket_cancel:email:' . strtolower($ticket_code) . ':' . evt_client_ip(), 5, 900)) {
            \evt_json_error('evt_rate_limited', __('Too many attempts. Please try again in a few minutes.', 'Event-Tickets-for-Elementor'), 429);
        }

        $ticket = Plugin::instance()->ticket_service->get_ticket_by_code($ticket_code);
        if (! $ticket) {
            \evt_json_error('evt_ticket_not_found', __('Ticket not found.', 'Event-Tickets-for-Elementor'), 404);
        }

        $ticket_id = (int) $ticket->ID;
        $sig_ok = $has_sig ? Ticket_Cancel_Link::validate($ticket_code, $ticket_id, $ts, $sig) : false;

        if (! $sig_ok) {
            if ('' === trim($email)) {
                \evt_json_error('evt_missing_email', __('Please enter the email used for the ticket.', 'Event-Tickets-for-Elementor'), 400);
            }

            $stored_email = (string) get_post_meta($ticket_id, '_ticket_email', true);
            if ('' === trim($stored_email) || 0 !== strcasecmp(trim($stored_email), trim($email))) {
                \evt_json_error('evt_ticket_not_owned', __('Ticket code and email do not match.', 'Event-Tickets-for-Elementor'), 403);
            }

            // Email-match path: never cancel based on code+email alone, since
            // both are weak bearer credentials that can be guessed. Instead,
            // send a time-limited signed confirmation link to the attendee's
            // inbox — only the person who can read that email can confirm.
            if (! $this->send_cancel_confirmation_email($ticket_id, $ticket_code, $stored_email)) {
                \evt_json_error('evt_email_failed', __('We could not send the confirmation email. Please try again later.', 'Event-Tickets-for-Elementor'), 500);
            }

            \evt_json_success(
                [
                    'confirmation_sent' => true,
                    'ticket_id'         => $ticket_id,
                    'ticket_code'       => $ticket_code,
                    /* translators: %s is replaced by the cancel page link. */
                    'message'           => __('A confirmation link has been sent to the email on file. Click it to confirm the cancellation.', 'Event-Tickets-for-Elementor'),
                ]
            );
        }

        $refund_requested = ('refund_request' === $mode);
        $result = $this->cancellation->cancel_ticket_by_user($ticket_id, $reason ?: null, $refund_requested);
        if (is_wp_error($result)) {
            \evt_json_error($result->get_error_code(), $result->get_error_message(), 400);
        }

        $data = [
            'ticket_id'      => $ticket_id,
            'ticket_code'    => $ticket_code,
            'status'         => (string) get_post_meta($ticket_id, '_ticket_status', true),
            'cancelled_at'   => (string) get_post_meta($ticket_id, '_ticket_cancelled_at', true),
            'refund_status'  => $this->cancellation->get_refund_status($ticket_id),
        ];

        \evt_json_success($data);
    }

    /**
     * Email a signed cancellation-confirmation link to the attendee.
     *
     * @param int    $ticket_id
     * @param string $ticket_code
     * @param string $attendee_email
     */
    private function send_cancel_confirmation_email(int $ticket_id, string $ticket_code, string $attendee_email): bool
    {
        $ticket_code = trim((string) $ticket_code);
        $attendee_email = sanitize_email($attendee_email);
        if (! $ticket_id || '' === $ticket_code || '' === $attendee_email) {
            return false;
        }

        $confirm_url = Ticket_Cancel_Link::build_signed_url($ticket_code, $ticket_id, Email_Links::cancel_page_url());
        if ('' === $confirm_url) {
            return false;
        }

        $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
        $subject  = sprintf(
            /* translators: %s is the site name. */
            __('[%s] Confirm your ticket cancellation', 'Event-Tickets-for-Elementor'),
            $blogname
        );

        $link_text = esc_html(Email_Links::cancel_link_label());
        $confirm_html  = '<a href="' . esc_url($confirm_url) . '">' . $link_text . '</a>';

        $html = '<p>' . esc_html__('You asked to cancel the ticket below. To confirm, click the link (valid for 90 days):', 'Event-Tickets-for-Elementor') . '</p>';
        $html .= '<p><strong>' . esc_html($ticket_code) . '</strong></p>';
        $html .= '<p>' . $confirm_html . '</p>';
        $html .= '<p>' . esc_html__('If you did not request this, you can ignore this email.', 'Event-Tickets-for-Elementor') . '</p>';

        add_filter('wp_mail_content_type', [$this, 'set_html_mail_content_type']);
        $sent = wp_mail($attendee_email, $subject, $html, [], []);
        remove_filter('wp_mail_content_type', [$this, 'set_html_mail_content_type']);

        return (bool) $sent;
    }

    public function set_html_mail_content_type(): string
    {
        return 'text/html';
    }
}
