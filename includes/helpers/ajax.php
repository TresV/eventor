<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Standard JSON error response helper.
 *
 * @param string $code
 * @param string $message
 * @param int    $http_status
 * @param array  $extra
 */
function evt_json_error(string $code, string $message, int $http_status = 400, array $extra = []): void
{
    wp_send_json_error(
        array_merge(
            [
                'code'    => $code,
                'message' => $message,
            ],
            $extra
        ),
        $http_status
    );
}

/**
 * Standard JSON success response helper.
 *
 * @param array $data
 */
function evt_json_success(array $data = []): void
{
    wp_send_json_success($data);
}

