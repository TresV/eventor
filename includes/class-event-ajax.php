<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * AJAX endpoint to fetch events for calendar/list widgets.
 */
class Event_Ajax
{
    private Event_Query $query;

    public function __construct(Event_Query $query)
    {
        $this->query = $query;
        add_action('wp_ajax_evt_fetch_events', [$this, 'handle']);
        add_action('wp_ajax_nopriv_evt_fetch_events', [$this, 'handle']);
        add_action('wp_ajax_evt_fetch_events_month', [$this, 'handle_month']);
        add_action('wp_ajax_nopriv_evt_fetch_events_month', [$this, 'handle_month']);
    }

    public function handle(): void
    {
        $view           = sanitize_text_field(wp_unslash($_GET['view'] ?? 'month'));
        $now            = current_time('timestamp');
        $year           = isset($_GET['year']) ? (int) $_GET['year'] : (int) wp_date('Y', $now);
        $month          = isset($_GET['month']) ? (int) $_GET['month'] : (int) wp_date('n', $now);
        $day            = isset($_GET['day']) ? (int) $_GET['day'] : (int) wp_date('j', $now);
        $limit          = isset($_GET['limit']) ? (int) $_GET['limit'] : -1;
        $page           = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $hide_cancelled = ! empty($_GET['hide_cancelled']);
        $only_capacity  = ! empty($_GET['only_capacity']);
        $hide_past      = ! empty($_GET['hide_past']);

        $range = $this->range_for_view($view, $year, $month, $day);

        if ($hide_past) {
            $now = current_time('timestamp');
            if ($now > $range['start']) {
                $range['start'] = $now;
            }
        }

        $start_after = isset($_GET['start_after']) ? sanitize_text_field(wp_unslash($_GET['start_after'])) : '';
        $end_before  = isset($_GET['end_before']) ? sanitize_text_field(wp_unslash($_GET['end_before'])) : '';
        if ($start_after) {
            $ts = $this->parse_date_floor($start_after);
            if ($ts && $ts > $range['start']) {
                $range['start'] = $ts;
            }
        }
        if ($end_before) {
            $ts = $this->parse_date_ceil($end_before);
            if ($ts && $ts < $range['end']) {
                $range['end'] = $ts;
            }
        }

        $payload = $this->query->get_paginated_feed(
            [
                'limit'              => $limit,
                'page'               => $page,
                'order'              => 'ASC',
                'start_ts'           => $range['start'],
                'end_ts'             => $range['end'],
                'hide_cancelled'     => $hide_cancelled,
                'hide_postponed'     => ! empty($_GET['hide_postponed']),
                'only_with_capacity' => $only_capacity,
                'category_ids'       => $this->parse_csv_ints(sanitize_text_field(wp_unslash($_GET['category_ids'] ?? ''))),
                'tag_ids'            => $this->parse_csv_ints(sanitize_text_field(wp_unslash($_GET['tag_ids'] ?? ''))),
                'venue_ids'          => $this->parse_csv_ints(sanitize_text_field(wp_unslash($_GET['venue_ids'] ?? ''))),
                'organizer_ids'      => $this->parse_csv_ints(sanitize_text_field(wp_unslash($_GET['organizer_ids'] ?? ''))),
                'day_of_week'        => isset($_GET['day_of_week']) ? (int) $_GET['day_of_week'] : null,
                'country'            => isset($_GET['country']) ? sanitize_text_field(wp_unslash($_GET['country'])) : '',
                'city'               => isset($_GET['city']) ? sanitize_text_field(wp_unslash($_GET['city'])) : '',
                'virtual_mode'       => isset($_GET['virtual_mode']) ? sanitize_text_field(wp_unslash($_GET['virtual_mode'])) : '',
                'cost_min'           => isset($_GET['cost_min']) ? sanitize_text_field(wp_unslash($_GET['cost_min'])) : '',
                'cost_max'           => isset($_GET['cost_max']) ? sanitize_text_field(wp_unslash($_GET['cost_max'])) : '',
                's'                  => isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '',
            ]
        );

        \evt_json_success($payload);
    }

    /**
     * Month calendar feed grouped by YYYY-MM-DD.
     *
     * Response:
     * {
     *   "2025-11-03": [eventDTO, ...],
     *   ...
     * }
     */
    public function handle_month(): void
    {
        $now   = current_time('timestamp');
        $year  = isset($_GET['year']) ? (int) $_GET['year'] : (int) wp_date('Y', $now);
        $month = isset($_GET['month']) ? (int) $_GET['month'] : (int) wp_date('n', $now);

        $month = max(1, min(12, $month));
        $start = $this->tz_mktime(0, 0, 0, $month, 1, $year);
        $end   = $this->tz_mktime(23, 59, 59, $month, (int) wp_date('t', $start), $year);

        $hide_past = ! empty($_GET['hide_past']);
        if ($hide_past) {
            $now = current_time('timestamp');
            if ($now > $start) {
                $start = $now;
            }
        }

        $start_after = isset($_GET['start_after']) ? sanitize_text_field(wp_unslash($_GET['start_after'])) : '';
        $end_before  = isset($_GET['end_before']) ? sanitize_text_field(wp_unslash($_GET['end_before'])) : '';
        if ($start_after) {
            $ts = $this->parse_date_floor($start_after);
            if ($ts && $ts > $start) {
                $start = $ts;
            }
        }
        if ($end_before) {
            $ts = $this->parse_date_ceil($end_before);
            if ($ts && $ts < $end) {
                $end = $ts;
            }
        }

        $feed = $this->query->get_feed(
            [
                'limit'          => -1,
                'order'          => 'ASC',
                'start_ts'       => $start,
                'end_ts'         => $end,
                'hide_cancelled' => ! empty($_GET['hide_cancelled']),
                'hide_postponed' => ! empty($_GET['hide_postponed']),
                'category_ids'   => $this->parse_csv_ints(sanitize_text_field(wp_unslash($_GET['category_ids'] ?? ''))),
                'tag_ids'        => $this->parse_csv_ints(sanitize_text_field(wp_unslash($_GET['tag_ids'] ?? ''))),
                'venue_ids'      => $this->parse_csv_ints(sanitize_text_field(wp_unslash($_GET['venue_ids'] ?? ''))),
                'organizer_ids'  => $this->parse_csv_ints(sanitize_text_field(wp_unslash($_GET['organizer_ids'] ?? ''))),
                'day_of_week'    => isset($_GET['day_of_week']) ? (int) $_GET['day_of_week'] : null,
                'country'        => isset($_GET['country']) ? sanitize_text_field(wp_unslash($_GET['country'])) : '',
                'city'           => isset($_GET['city']) ? sanitize_text_field(wp_unslash($_GET['city'])) : '',
                'virtual_mode'   => isset($_GET['virtual_mode']) ? sanitize_text_field(wp_unslash($_GET['virtual_mode'])) : '',
                'cost_min'       => isset($_GET['cost_min']) ? sanitize_text_field(wp_unslash($_GET['cost_min'])) : '',
                'cost_max'       => isset($_GET['cost_max']) ? sanitize_text_field(wp_unslash($_GET['cost_max'])) : '',
                's'              => isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '',
            ]
        );

        $grouped = [];
        foreach ($feed as $evt) {
            $day_key = '';
            if (! empty($evt['start_ts'])) {
                $day_key = ! empty($evt['start_local_date']) ? (string) $evt['start_local_date'] : wp_date('Y-m-d', (int) $evt['start_ts']);
            }
            if ('' === $day_key) {
                continue;
            }
            if (! isset($grouped[$day_key])) {
                $grouped[$day_key] = [];
            }
            $grouped[$day_key][] = $evt;
        }

        \evt_json_success(['days' => $grouped]);
    }

    private function range_for_view(string $view, int $year, int $month, int $day): array
    {
        $month = max(1, min(12, $month));
        $day   = max(1, min(31, $day));

        switch ($view) {
            case 'week':
                $anchor = $this->tz_mktime(12, 0, 0, $month, $day, $year);
                $start_of_week = (int) get_option('start_of_week', 1); // 0=Sunday, 1=Monday
                $anchor_w = (int) wp_date('w', $anchor);
                $diff = ($anchor_w - $start_of_week + 7) % 7;
                $start = strtotime('-' . $diff . ' days', $anchor);
                $start = $this->tz_mktime(0, 0, 0, (int) wp_date('n', $start), (int) wp_date('j', $start), (int) wp_date('Y', $start));
                $end   = $start + WEEK_IN_SECONDS - 1;
                break;
            case 'day':
                $start = $this->tz_mktime(0, 0, 0, $month, $day, $year);
                $end   = $start + DAY_IN_SECONDS - 1;
                break;
            case 'list':
                $start = current_time('timestamp');
                $end   = $start + MONTH_IN_SECONDS;
                break;
            case 'month':
            default:
                $start = $this->tz_mktime(0, 0, 0, $month, 1, $year);
                $end   = $this->tz_mktime(23, 59, 59, $month, (int) wp_date('t', $start), $year);
                break;
        }

        return ['start' => $start, 'end' => $end];
    }

    private function tz_mktime(int $hour, int $minute, int $second, int $month, int $day, int $year): int
    {
        $tz = function_exists('wp_timezone') ? wp_timezone() : null;
        if ($tz instanceof \DateTimeZone) {
            $dt = \DateTimeImmutable::createFromFormat(
                'Y-n-j H:i:s',
                sprintf('%d-%d-%d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second),
                $tz
            );
            if ($dt) {
                return (int) $dt->getTimestamp();
            }
        }

        return (int) mktime($hour, $minute, $second, $month, $day, $year);
    }

    private function parse_date_floor(string $ymd): int
    {
        $ymd = trim($ymd);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
            return 0;
        }

        [$y, $m, $d] = array_map('intval', explode('-', $ymd));
        return $this->tz_mktime(0, 0, 0, $m, $d, $y);
    }

    private function parse_date_ceil(string $ymd): int
    {
        $ymd = trim($ymd);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
            return 0;
        }

        [$y, $m, $d] = array_map('intval', explode('-', $ymd));
        return $this->tz_mktime(23, 59, 59, $m, $d, $y);
    }

    /**
     * @param mixed $csv
     * @return array<int,int>
     */
    private function parse_csv_ints($csv): array
    {
        if (! is_string($csv) || '' === trim($csv)) {
            return [];
        }

        $parts = array_map('trim', explode(',', $csv));
        $parts = array_filter(array_map('absint', $parts));
        return array_values(array_unique($parts));
    }
}
