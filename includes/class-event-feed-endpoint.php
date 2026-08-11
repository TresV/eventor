<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Public (non-admin-ajax) JSON endpoints for event feeds used by widgets.
 *
 * - /evt-events-feed.json
 * - /evt-events-month.json
 *
 * Falls back to query args if pretty permalinks are disabled.
 */
class Event_Feed_Endpoint
{
    private Event_Query $query;

    public function __construct(Event_Query $query)
    {
        $this->query = $query;

        add_action('init', [__CLASS__, 'register_rewrites']);
        add_filter('query_vars', [$this, 'add_query_vars']);
        add_action('template_redirect', [$this, 'maybe_output']);
    }

    public static function register_rewrites(): void
    {
        add_rewrite_rule('^evt-events-feed\\.json$', 'index.php?evt_events_feed=1', 'top');
        add_rewrite_rule('^evt-events-month\\.json$', 'index.php?evt_events_month=1', 'top');
    }

    public static function calendar_feed_url(): string
    {
        // Use query-arg endpoint by default so it works even if rewrite rules
        // haven't been flushed or hosts block unknown pretty paths.
        return add_query_arg(['evt_events_feed' => '1'], home_url('/'));
    }

    public static function month_feed_url(): string
    {
        return add_query_arg(['evt_events_month' => '1'], home_url('/'));
    }

    public function add_query_vars($vars)
    {
        $vars[] = 'evt_events_feed';
        $vars[] = 'evt_events_month';
        return $vars;
    }

    public function maybe_output(): void
    {
        if (get_query_var('evt_events_feed')) {
            $this->output_calendar_feed();
            exit;
        }

        if (get_query_var('evt_events_month')) {
            $this->output_month_feed();
            exit;
        }
    }

    private function output_calendar_feed(): void
    {
        $view = isset($_GET['view']) ? sanitize_text_field(wp_unslash($_GET['view'])) : 'month';
        $full = ! empty($_GET['full']);
        $is_list_like = in_array($view, ['list', 'grid', 'summary', 'map'], true);

        $now = current_time('timestamp');
        $year  = isset($_GET['year']) ? (int) $_GET['year'] : (int) wp_date('Y', $now);
        $month = isset($_GET['month']) ? (int) $_GET['month'] : (int) wp_date('n', $now);
        $day   = isset($_GET['day']) ? (int) $_GET['day'] : (int) wp_date('j', $now);

        $limit          = isset($_GET['limit']) ? (int) $_GET['limit'] : -1;
        $hide_cancelled = ! empty($_GET['hide_cancelled']);
        $hide_postponed = ! empty($_GET['hide_postponed']);
        $only_capacity  = ! empty($_GET['only_capacity']);
        $hide_past      = ! empty($_GET['hide_past']);

        $list_days = isset($_GET['list_days']) ? min(730, absint($_GET['list_days'])) : 0;
        $range = $this->range_for_view($view, $year, $month, $day, $list_days);

        if ($hide_past) {
            $now = current_time('timestamp');
            if ($now > $range['start']) {
                $range['start'] = $now;
            }
        }

        $start_after = isset($_GET['start_after']) ? sanitize_text_field(wp_unslash($_GET['start_after'])) : '';
        $end_before  = isset($_GET['end_before']) ? sanitize_text_field(wp_unslash($_GET['end_before'])) : '';

        $start_after_ts = $start_after ? $this->parse_date_floor($start_after) : 0;
        $end_before_ts  = $end_before ? $this->parse_date_ceil($end_before) : 0;

        // For list-like views, explicit from/to should define the range even if it is in the past.
        if ($is_list_like && ($start_after_ts || $end_before_ts)) {
            $days = $list_days > 0 ? $list_days : 90;

            if ($start_after_ts) {
                $range['start'] = $start_after_ts;
                if (! $end_before_ts) {
                    $range['end'] = $start_after_ts + ($days * DAY_IN_SECONDS) - 1;
                }
            }

            if ($end_before_ts) {
                $range['end'] = $end_before_ts;
                if (! $start_after_ts) {
                    $range['start'] = max(0, $end_before_ts - ($days * DAY_IN_SECONDS) + 1);
                }
            }
        } else {
            if ($start_after_ts && $start_after_ts > $range['start']) {
                $range['start'] = $start_after_ts;
            }
            if ($end_before_ts && $end_before_ts < $range['end']) {
                $range['end'] = $end_before_ts;
            }
        }

        if ($range['start'] > $range['end']) {
            $tmp = $range['start'];
            $range['start'] = $range['end'];
            $range['end'] = $tmp;
        }

        $s = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $s = trim((string) $s);

        $category_ids  = $this->parse_csv_ints(sanitize_text_field(wp_unslash($_GET['category_ids'] ?? '')));
        $tag_ids       = $this->parse_csv_ints(sanitize_text_field(wp_unslash($_GET['tag_ids'] ?? '')));
        $venue_ids     = $this->parse_csv_ints(sanitize_text_field(wp_unslash($_GET['venue_ids'] ?? '')));
        $organizer_ids = $this->parse_csv_ints(sanitize_text_field(wp_unslash($_GET['organizer_ids'] ?? '')));
        $day_of_week   = isset($_GET['day_of_week']) ? (int) $_GET['day_of_week'] : null;
        $country       = isset($_GET['country']) ? sanitize_text_field(wp_unslash($_GET['country'])) : '';
        $city          = isset($_GET['city']) ? sanitize_text_field(wp_unslash($_GET['city'])) : '';
        $virtual_mode  = isset($_GET['virtual_mode']) ? sanitize_text_field(wp_unslash($_GET['virtual_mode'])) : '';
        $cost_min      = isset($_GET['cost_min']) ? sanitize_text_field(wp_unslash($_GET['cost_min'])) : '';
        $cost_max      = isset($_GET['cost_max']) ? sanitize_text_field(wp_unslash($_GET['cost_max'])) : '';

        $query_args = [
            'limit'              => $limit,
            'order'              => 'ASC',
            'start_ts'           => $range['start'],
            'end_ts'             => $range['end'],
            'hide_cancelled'     => $hide_cancelled,
            'hide_postponed'     => $hide_postponed,
            'only_with_capacity' => $only_capacity,
            'category_ids'       => $category_ids,
            'tag_ids'            => $tag_ids,
            'venue_ids'          => $venue_ids,
            'organizer_ids'      => $organizer_ids,
            'day_of_week'        => $day_of_week,
            'country'            => $country,
            'city'               => $city,
            'virtual_mode'       => $virtual_mode,
            'cost_min'           => $cost_min,
            'cost_max'           => $cost_max,
        ];
        if ('' !== $s) {
            $query_args['s'] = $s;
        }

        $feed = $this->query->get_feed($query_args);

        if ($full) {
            $events = $feed;
        } else {
            $events = array_map([$this, 'map_event_for_calendar'], $feed);
            $events = array_values(array_filter($events));
        }

        nocache_headers();
        wp_send_json_success(['events' => $events], 200);
    }

    private function output_month_feed(): void
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

        nocache_headers();
        wp_send_json_success(['days' => $grouped], 200);
    }

    private function map_event_for_calendar(array $evt): array
    {
        $start = (int) ($evt['start_ts'] ?? 0);
        if (! $start) {
            return [];
        }

        $end = (int) ($evt['end_ts'] ?? 0);
        if (! $end || $end <= $start) {
            $end = $start + HOUR_IN_SECONDS;
        }

        return [
            'id'       => (int) ($evt['id'] ?? 0),
            'title'    => (string) ($evt['title'] ?? ''),
            'start'    => $start,
            'end'      => $end,
            'location' => (string) ($evt['location'] ?? ''),
            'url'      => (string) ($evt['permalink'] ?? ''),
        ];
    }

    private function range_for_view(string $view, int $year, int $month, int $day, int $list_days = 0): array
    {
        $month = max(1, min(12, $month));
        $day   = max(1, min(31, $day));

        switch ($view) {
            case 'week':
                $anchor = $this->tz_mktime(12, 0, 0, $month, $day, $year);
                $start_of_week = (int) get_option('start_of_week', 1);
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
            case 'grid':
            case 'summary':
            case 'map':
            case 'list':
                $start = current_time('timestamp');
                $days = $list_days > 0 ? $list_days : 90;
                $end  = $start + ($days * DAY_IN_SECONDS);
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
     * @param mixed $raw
     * @return int[]
     */
    private function parse_csv_ints($raw): array
    {
        if (is_array($raw)) {
            $raw = implode(',', array_map('strval', $raw));
        }
        $raw = is_string($raw) ? trim($raw) : '';
        if ('' === $raw) {
            return [];
        }
        $parts = preg_split('/\s*,\s*/', $raw);
        if (! is_array($parts)) {
            return [];
        }
        return array_values(array_filter(array_map('absint', $parts)));
    }
}
