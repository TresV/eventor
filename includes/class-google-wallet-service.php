<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Google Wallet (Event Ticket) integration.
 *
 * Uses a service account to sign a JWT "Save to Google Wallet" link,
 * and optionally upserts objects for status changes (cancel/check-in).
 */
class Google_Wallet_Service
{
    private Settings $settings;
    private Event_Query $event_query;
    private Ticket_Service $ticket_service;

    public function __construct(Settings $settings, Event_Query $event_query, Ticket_Service $ticket_service)
    {
        $this->settings = $settings;
        $this->event_query = $event_query;
        $this->ticket_service = $ticket_service;
    }

    public function is_enabled(): bool
    {
        // Temporarily disabled until the Wallet flow is production-ready.
        return false;
    }

    public function get_save_url(int $ticket_id): string
    {
        if (! $this->is_enabled()) {
            return '';
        }

        $creds = $this->get_credentials();
        $issuer_id = $this->get_issuer_id();
        if (! $creds || '' === $issuer_id) {
            return '';
        }

        $event_id = (int) get_post_meta($ticket_id, '_ticket_event_id', true);
        if (! $event_id) {
            return '';
        }

        $class = $this->build_class_payload($event_id, $issuer_id);
        $object = $this->build_object_payload($ticket_id, $issuer_id);

        if (! $class || ! $object) {
            return '';
        }

        $jwt = $this->build_save_jwt($creds['client_email'], $creds['private_key'], $class, $object);
        if ('' === $jwt) {
            return '';
        }

        return 'https://pay.google.com/gp/v/save/' . $jwt;
    }

    /**
     * Sync a ticket's Wallet object (best-effort).
     */
    public function sync_ticket_state(int $ticket_id): void
    {
        if (! $this->is_enabled()) {
            return;
        }

        $creds = $this->get_credentials();
        $issuer_id = $this->get_issuer_id();
        if (! $creds || '' === $issuer_id) {
            return;
        }

        $object = $this->build_object_payload($ticket_id, $issuer_id);
        if (! $object) {
            return;
        }

        $object_id = (string) ($object['id'] ?? '');
        if ('' === $object_id) {
            return;
        }

        $token = $this->get_access_token($creds);
        if (! $token) {
            return;
        }

        $this->api_request('PUT', '/eventTicketObject/' . rawurlencode($object_id), $object, $token);
    }

    private function build_class_payload(int $event_id, string $issuer_id): array
    {
        $event = $this->event_query->format_event($event_id);
        if (empty($event)) {
            return [];
        }

        $class_id = $this->build_class_id($issuer_id, $event_id);
        $issuer_name = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
        $event_name = (string) ($event['title'] ?? '');

        $start_iso = (string) ($event['start_iso'] ?? '');
        $end_iso = (string) ($event['end_iso'] ?? '');

        $location = (string) ($event['location'] ?? '');
        $address = trim((string) ($event['city'] ?? '') . ('' !== (string) ($event['country'] ?? '') ? ', ' . (string) ($event['country'] ?? '') : ''));

        $payload = [
            'id' => $class_id,
            'issuerName' => $issuer_name,
            'eventName' => [
                'defaultValue' => [
                    'language' => 'en',
                    'value' => $event_name,
                ],
            ],
            'reviewStatus' => 'UNDER_REVIEW',
        ];

        if ($start_iso) {
            $payload['dateTime'] = [
                'start' => $start_iso,
                'end' => $end_iso,
            ];
        }

        if ($location || $address) {
            $payload['venue'] = [
                'name' => $location ?: $event_name,
                'address' => $address,
            ];
        }

        return apply_filters('evt_google_wallet_class_payload', $payload, $event_id);
    }

    private function build_object_payload(int $ticket_id, string $issuer_id): array
    {
        $ticket = get_post($ticket_id);
        if (! $ticket || CPT_Tickets::POST_TYPE !== $ticket->post_type) {
            return [];
        }

        $ticket_code = (string) get_post_meta($ticket_id, '_ticket_code', true);
        $attendee_name = (string) get_post_meta($ticket_id, '_ticket_name', true);
        $attendee_email = (string) get_post_meta($ticket_id, '_ticket_email', true);
        $event_id = (int) get_post_meta($ticket_id, '_ticket_event_id', true);

        if (! $ticket_code || ! $event_id) {
            return [];
        }

        $status = (string) get_post_meta($ticket_id, '_ticket_status', true);
        $checked_in_at = (string) get_post_meta($ticket_id, '_ticket_checked_in_at', true);

        $state = ('cancelled' === $status) ? 'INACTIVE' : 'ACTIVE';

        $text_modules = [];
        if ($attendee_email) {
            $text_modules[] = [
                'header' => 'Email',
                'body'   => $attendee_email,
            ];
        }
        if ('checked_in' === $status) {
            $text_modules[] = [
                'header' => 'Checked in',
                'body'   => $checked_in_at ? $checked_in_at : 'Yes',
            ];
        }

        $payload = [
            'id' => $this->build_object_id($issuer_id, $ticket_code),
            'classId' => $this->build_class_id($issuer_id, $event_id),
            'state' => $state,
            'ticketHolderName' => $attendee_name,
            'ticketNumber' => $ticket_code,
            'barcode' => [
                'type' => 'QR_CODE',
                'value' => $ticket_code,
                'alternateText' => $ticket_code,
            ],
            'textModulesData' => $text_modules,
        ];

        return apply_filters('evt_google_wallet_object_payload', $payload, $ticket_id);
    }

    private function build_save_jwt(string $client_email, string $private_key, array $class, array $object): string
    {
        $now = time();
        $payload = [
            'iss' => $client_email,
            'aud' => 'google',
            'typ' => 'savetowallet',
            'iat' => $now,
            'exp' => $now + 3600,
            'payload' => [
                'eventTicketClasses' => [$class],
                'eventTicketObjects' => [$object],
            ],
        ];

        return $this->jwt_encode($payload, $private_key);
    }

    private function jwt_encode(array $payload, string $private_key): string
    {
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $segments = [];
        $segments[] = $this->base64url(json_encode($header));
        $segments[] = $this->base64url(json_encode($payload));

        $signing_input = implode('.', $segments);
        $signature = '';

        $ok = openssl_sign($signing_input, $signature, $private_key, OPENSSL_ALGO_SHA256);
        if (! $ok) {
            return '';
        }

        $segments[] = $this->base64url($signature);
        return implode('.', $segments);
    }

    private function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function get_issuer_id(): string
    {
        return trim((string) $this->settings->get('google_wallet_issuer_id', ''));
    }

    /**
     * @return array{client_email:string,private_key:string}|null
     */
    private function get_credentials(): ?array
    {
        $path = trim((string) $this->settings->get('google_wallet_service_account_path', ''));
        $json = trim((string) $this->settings->get('google_wallet_service_account_json', ''));

        if ($path && file_exists($path)) {
            $json = (string) file_get_contents($path);
        }

        if ('' === $json) {
            return null;
        }

        $data = json_decode($json, true);
        if (! is_array($data)) {
            return null;
        }

        $client_email = isset($data['client_email']) ? (string) $data['client_email'] : '';
        $private_key  = isset($data['private_key']) ? (string) $data['private_key'] : '';

        if ('' === $client_email || '' === $private_key) {
            return null;
        }

        return [
            'client_email' => $client_email,
            'private_key'  => $private_key,
        ];
    }

    private function build_class_id(string $issuer_id, int $event_id): string
    {
        return $issuer_id . '.evt_' . $event_id;
    }

    private function build_object_id(string $issuer_id, string $ticket_code): string
    {
        $suffix = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $ticket_code));
        return $issuer_id . '.tkt_' . $suffix;
    }

    /**
     * @param array{client_email:string,private_key:string} $creds
     */
    private function get_access_token(array $creds): ?string
    {
        $cache = get_transient('evt_gw_access_token');
        if (is_array($cache) && ! empty($cache['token']) && ! empty($cache['expires'])) {
            if ((int) $cache['expires'] > time() + 60) {
                return (string) $cache['token'];
            }
        }

        $now = time();
        $payload = [
            'iss' => $creds['client_email'],
            'scope' => 'https://www.googleapis.com/auth/wallet_object.issuer',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        $assertion = $this->jwt_encode($payload, $creds['private_key']);
        if ('' === $assertion) {
            return null;
        }

        $response = wp_remote_post(
            'https://oauth2.googleapis.com/token',
            [
                'timeout' => 15,
                'body' => [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $assertion,
                ],
            ]
        );

        if (is_wp_error($response)) {
            return null;
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if (! is_array($body) || empty($body['access_token'])) {
            return null;
        }

        $token = (string) $body['access_token'];
        $expires_in = isset($body['expires_in']) ? (int) $body['expires_in'] : 3600;

        set_transient('evt_gw_access_token', ['token' => $token, 'expires' => $now + $expires_in], $expires_in);
        return $token;
    }

    private function api_request(string $method, string $path, array $body, string $token): void
    {
        $url = 'https://walletobjects.googleapis.com/walletobjects/v1' . $path;
        $args = [
            'timeout' => 15,
            'method'  => $method,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ];

        $response = wp_remote_request($url, $args);
        if (is_wp_error($response)) {
            return;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code >= 400) {
            // Best-effort only; failures are non-fatal for ticket flow.
            return;
        }
    }
}
