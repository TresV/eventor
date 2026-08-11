<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Recurring events: date parsing, date-shift math, and occurrence-schedule generation.
 */
class Recurrence_Schedule
{
    /**
     * @return array<int,array{index:int,start:string,end:string,label:string}>
     */
    public function build_occurrence_schedule(int $event_id, array $settings): array
    {
        $seed_start = (string) get_post_meta($event_id, '_evt_event_start', true);
        $seed_end = (string) get_post_meta($event_id, '_evt_event_end', true);
        $start = $this->parse_datetime($seed_start);
        $end = $this->parse_datetime($seed_end);
        if (! $start || ! $end || $end <= $start) {
            return [];
        }

        return $this->build_schedule_from_anchor($start, $end, $settings, 1);
    }

    /**
     * @return array<int,array{index:int,start:string,end:string,label:string}>
     */
    public function build_forward_schedule_from_occurrence(int $occurrence_id, array $settings): array
    {
        $index = max(1, (int) get_post_meta($occurrence_id, Event_Recurrence::META_OCCURRENCE_INDEX, true));
        $start = $this->parse_datetime((string) get_post_meta($occurrence_id, '_evt_event_start', true));
        $end = $this->parse_datetime((string) get_post_meta($occurrence_id, '_evt_event_end', true));
        if (! $start || ! $end || $end <= $start) {
            return [];
        }

        if ('count' === $settings['end_type']) {
            $settings['count'] = max(1, $settings['count'] - $index + 1);
        }

        return $this->build_schedule_from_anchor($start, $end, $settings, $index);
    }

    public function shift_datetime_string(string $value, int $delta): string
    {
        $date = $this->parse_datetime($value);
        if (! $date || 0 === $delta) {
            return $value;
        }

        return $date->modify(($delta >= 0 ? '+' : '') . $delta . ' second')->format('Y-m-d H:i');
    }

    public function timestamp_delta(string $from, string $to): int
    {
        $from_dt = $this->parse_datetime($from);
        $to_dt = $this->parse_datetime($to);
        if (! $from_dt || ! $to_dt) {
            return 0;
        }

        return $to_dt->getTimestamp() - $from_dt->getTimestamp();
    }

    public function parse_datetime(string $value): ?\DateTimeImmutable
    {
        $value = trim($value);
        if ('' === $value) {
            return null;
        }

        $timezone = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone('UTC');
        $date = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $value, $timezone);
        if ($date instanceof \DateTimeImmutable) {
            return $date;
        }

        $fallback = date_create_immutable($value, $timezone);
        return $fallback instanceof \DateTimeImmutable ? $fallback : null;
    }

    /**
     * @return array<int,array{index:int,start:string,end:string,label:string}>
     */
    private function build_schedule_from_anchor(\DateTimeImmutable $seed_start, \DateTimeImmutable $seed_end, array $settings, int $index_start): array
    {
        $duration = $seed_end->getTimestamp() - $seed_start->getTimestamp();
        if ($duration <= 0) {
            return [];
        }

        $limit = $settings['count'];
        if ('until' === $settings['end_type'] && '' === $settings['until']) {
            $limit = min($limit, 10);
        }
        $limit = max(1, min(Recurrence_Generator::MAX_OCCURRENCES, $limit));

        $until_end = null;
        if ('until' === $settings['end_type'] && '' !== $settings['until']) {
            $until_end = $this->parse_datetime($settings['until'] . ' 23:59');
        }

        $items = [];
        if ('daily' === $settings['freq']) {
            for ($i = 0; $i < Recurrence_Generator::MAX_OCCURRENCES; $i++) {
                $start = $seed_start->modify('+' . ($i * $settings['interval']) . ' day');
                if (! $start) {
                    break;
                }
                if ($until_end && $start > $until_end) {
                    break;
                }
                $end = $start->modify('+' . $duration . ' second');
                $items[] = $this->schedule_item($index_start + $i, $start, $end);
                if ('count' === $settings['end_type'] && count($items) >= $limit) {
                    break;
                }
            }
            return $items;
        }

        if ('monthly' === $settings['freq']) {
            for ($i = 0; $i < Recurrence_Generator::MAX_OCCURRENCES; $i++) {
                $start = $this->add_months_preserve_day($seed_start, $settings['interval'] * $i);
                if ($until_end && $start > $until_end) {
                    break;
                }
                $end = $start->modify('+' . $duration . ' second');
                $items[] = $this->schedule_item($index_start + $i, $start, $end);
                if ('count' === $settings['end_type'] && count($items) >= $limit) {
                    break;
                }
            }
            return $items;
        }

        $weekdays = ! empty($settings['weekdays']) ? $settings['weekdays'] : [(int) $seed_start->format('w')];
        sort($weekdays);

        $seed_day = $seed_start->setTime(0, 0);
        $seed_week_start = $seed_day->modify('-' . (int) $seed_day->format('w') . ' day');
        $cursor = $seed_day;
        $seen = 0;

        while ($seen < Recurrence_Generator::MAX_OCCURRENCES) {
            $candidate_week_start = $cursor->modify('-' . (int) $cursor->format('w') . ' day');
            $weeks_diff = (int) floor(($candidate_week_start->getTimestamp() - $seed_week_start->getTimestamp()) / WEEK_IN_SECONDS);
            $weekday = (int) $cursor->format('w');

            if ($weeks_diff >= 0 && 0 === ($weeks_diff % $settings['interval']) && in_array($weekday, $weekdays, true)) {
                $start = $cursor->setTime((int) $seed_start->format('H'), (int) $seed_start->format('i'));
                if ($start >= $seed_start) {
                    if ($until_end && $start > $until_end) {
                        break;
                    }
                    $end = $start->modify('+' . $duration . ' second');
                    $items[] = $this->schedule_item($index_start + count($items), $start, $end);
                    if ('count' === $settings['end_type'] && count($items) >= $limit) {
                        break;
                    }
                }
            }

            $cursor = $cursor->modify('+1 day');
            $seen++;
        }

        return $items;
    }

    /**
     * @return array{index:int,start:string,end:string,label:string}
     */
    private function schedule_item(int $index, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $tz_label = Event_Query::timezone_label();
        $label = sprintf(
            '%s %s - %s (%s)',
            wp_date(get_option('date_format'), $start->getTimestamp()),
            wp_date(get_option('time_format'), $start->getTimestamp()),
            wp_date(get_option('time_format'), $end->getTimestamp()),
            $tz_label
        );

        return [
            'index' => $index,
            'start' => $start->format('Y-m-d H:i'),
            'end'   => $end->format('Y-m-d H:i'),
            'label' => $label,
        ];
    }

    private function add_months_preserve_day(\DateTimeImmutable $seed_start, int $months): \DateTimeImmutable
    {
        if ($months <= 0) {
            return $seed_start;
        }

        $year = (int) $seed_start->format('Y');
        $month = (int) $seed_start->format('n') + $months;
        $year += (int) floor(($month - 1) / 12);
        $month = (($month - 1) % 12) + 1;

        $day = (int) $seed_start->format('j');
        $last_day = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $day = min($day, $last_day);

        $timezone = $seed_start->getTimezone();
        return \DateTimeImmutable::createFromFormat(
            'Y-n-j H:i',
            sprintf('%d-%d-%d %02d:%02d', $year, $month, $day, (int) $seed_start->format('H'), (int) $seed_start->format('i')),
            $timezone
        ) ?: $seed_start;
    }
}
