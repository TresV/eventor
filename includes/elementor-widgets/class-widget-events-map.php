<?php
namespace EventTicketsElementor\Elementor_Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use EventTicketsElementor\Plugin;

if (! defined('ABSPATH')) {
    exit;
}


/**
 * Map view widget using Leaflet.
 */
class Widget_Events_Map extends Widget_Base {

    public function get_name() {
        return 'evt_events_map';
    }

    public function get_title() {
        return __( 'Events Map', 'Event-Tickets-for-Elementor');
    }

    public function get_icon() {
        return 'eicon-google-maps';
    }

    public function get_categories() {
        return [ 'evt-tickets' ];
    }

    /**
     * Legacy widget kept for backward compatibility.
     * Use Events Browser for new pages.
     */
    public function show_in_panel(): bool {
        return false;
    }

    public function get_style_depends() {
        return [ 'leaflet', 'evt-tickets-events-map' ];
    }

    public function get_script_depends() {
        return [ 'leaflet', 'evt-tickets-events-map' ];
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
            'range_mode',
            [
                'label'   => __( 'Time Frame', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::SELECT,
                'default' => 'upcoming',
                'options' => [
                    'all'      => __( 'All events', 'Event-Tickets-for-Elementor'),
                    'upcoming' => __( 'Upcoming (next X days)', 'Event-Tickets-for-Elementor'),
                    'week'     => __( 'This week', 'Event-Tickets-for-Elementor'),
                    'month'    => __( 'This month', 'Event-Tickets-for-Elementor'),
                    'custom'   => __( 'Custom range', 'Event-Tickets-for-Elementor'),
                ],
            ]
        );

        $this->add_control(
            'upcoming_days',
            [
                'label'     => __( 'Upcoming Days', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::NUMBER,
                'default'   => 30,
                'min'       => 1,
                'max'       => 3650,
                'condition' => [
                    'range_mode' => 'upcoming',
                ],
            ]
        );

        $this->add_control(
            'custom_from',
            [
                'label'     => __( 'From', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::DATE_TIME,
                'condition' => [
                    'range_mode' => 'custom',
                ],
            ]
        );

        $this->add_control(
            'custom_to',
            [
                'label'     => __( 'To', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::DATE_TIME,
                'condition' => [
                    'range_mode' => 'custom',
                ],
            ]
        );

        $this->add_control(
            'limit',
            [
                'label'   => __( 'Number of events', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::NUMBER,
                'default' => 20,
            ]
        );

        $this->add_control(
            'enable_geocoding',
            [
                'label'       => __( 'Geocode locations (no coordinates)', 'Event-Tickets-for-Elementor'),
                'type'        => Controls_Manager::SWITCHER,
                'return_value'=> 'yes',
                'default'     => '',
                'description' => __( 'If an event has only a location text or a Google Maps link without coordinates, try geocoding to place a pin (uses OpenStreetMap Nominatim).', 'Event-Tickets-for-Elementor'),
            ]
        );

        $this->add_control(
            'hide_cancelled',
            [
                'label'        => __( 'Hide Cancelled', 'Event-Tickets-for-Elementor'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'labels_heading',
            [
                'label'     => __( 'Labels / Text', 'Event-Tickets-for-Elementor'),
                'type'      => Controls_Manager::RAW_HTML,
                'separator' => 'before',
                'raw'       => '<strong>' . esc_html__( 'Labels / Text', 'Event-Tickets-for-Elementor') . '</strong>',
            ]
        );
        $this->add_control(
            'heading',
            [
                'label'   => __( 'Heading', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::TEXT,
                'default' => '',
            ]
        );
        $this->add_control(
            'text_empty',
            [
                'label'   => __( 'Empty state', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::TEXT,
                'default' => __( 'No events with map coordinates or links.', 'Event-Tickets-for-Elementor'),
            ]
        );
        $this->add_control(
            'text_links_heading',
            [
                'label'   => __( 'Links section heading', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::TEXT,
                'default' => __( 'Events with map links:', 'Event-Tickets-for-Elementor'),
            ]
        );
        $this->add_control(
            'label_view_event',
            [
                'label'   => __( 'Label: View event', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::TEXT,
                'default' => __( 'View event', 'Event-Tickets-for-Elementor'),
            ]
        );
        $this->add_control(
            'label_open_map',
            [
                'label'   => __( 'Label: Open map', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::TEXT,
                'default' => __( 'Open map', 'Event-Tickets-for-Elementor'),
            ]
        );
        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $heading = is_string($settings['heading'] ?? '') ? trim((string) $settings['heading']) : '';
        $text_empty = is_string($settings['text_empty'] ?? '') && trim((string) $settings['text_empty']) !== ''
            ? trim((string) $settings['text_empty'])
            : __('No events with map coordinates or links.', 'Event-Tickets-for-Elementor');
        $text_links_heading = is_string($settings['text_links_heading'] ?? '') && trim((string) $settings['text_links_heading']) !== ''
            ? trim((string) $settings['text_links_heading'])
            : __('Events with map links:', 'Event-Tickets-for-Elementor');
        $label_open_map = is_string($settings['label_open_map'] ?? '') && trim((string) $settings['label_open_map']) !== ''
            ? trim((string) $settings['label_open_map'])
            : __('Open map', 'Event-Tickets-for-Elementor');

        $i18n = [
            'view_event' => is_string($settings['label_view_event'] ?? '') && trim((string) $settings['label_view_event']) !== ''
                ? trim((string) $settings['label_view_event'])
                : __('View event', 'Event-Tickets-for-Elementor'),
            'open_map'   => $label_open_map,
        ];

        $limit = (int) ($settings['limit'] ?? 20);
        $hide_cancelled = ('yes' === ($settings['hide_cancelled'] ?? 'yes'));
        $range_mode = (string) ($settings['range_mode'] ?? 'upcoming');

        $start_ts = 0;
        $end_ts = 0;

        $now = current_time('timestamp');

        if ('upcoming' === $range_mode) {
            $days = max(1, (int) ($settings['upcoming_days'] ?? 30));
            $start_ts = $now;
            $end_ts = $now + ($days * DAY_IN_SECONDS);
        } elseif ('week' === $range_mode) {
            $start_of_week = (int) get_option('start_of_week', 1);
            $anchor_w = (int) wp_date('w', $now);
            $diff = ($anchor_w - $start_of_week + 7) % 7;
            $start = strtotime('-' . $diff . ' days', $now);
            $start_ts = (int) mktime(0, 0, 0, (int) wp_date('n', $start), (int) wp_date('j', $start), (int) wp_date('Y', $start));
            $end_ts = $start_ts + WEEK_IN_SECONDS - 1;
        } elseif ('month' === $range_mode) {
            $start_ts = (int) mktime(0, 0, 0, (int) wp_date('n', $now), 1, (int) wp_date('Y', $now));
            $end_ts   = (int) mktime(23, 59, 59, (int) wp_date('n', $now), (int) wp_date('t', $now), (int) wp_date('Y', $now));
        } elseif ('custom' === $range_mode) {
            $from = isset($settings['custom_from']) ? (string) $settings['custom_from'] : '';
            $to   = isset($settings['custom_to']) ? (string) $settings['custom_to'] : '';
            $from_ts = $from ? strtotime($from) : 0;
            $to_ts   = $to ? strtotime($to) : 0;
            if ($from_ts) {
                $start_ts = (int) $from_ts;
            }
            if ($to_ts) {
                $end_ts = (int) $to_ts;
            }
        } else {
            // all: no range
        }

        $args = [
            'limit'          => $limit,
            'hide_cancelled' => $hide_cancelled,
        ];

        if ($start_ts) {
            $args['start_ts'] = $start_ts;
        }
        if ($end_ts) {
            $args['end_ts'] = $end_ts;
        }

        $events = Plugin::instance()->event_query->get_feed($args);

        $geocoder = new \EventTicketsElementor\Event_Geocoder();
        $geocode_enabled = ('yes' === ($settings['enable_geocoding'] ?? ''));

        $markers = array_values(array_filter(array_map(function ($event) {
            $lat = $event['lat'] ?? '';
            $lng = $event['lng'] ?? '';
            $start_ts = ! empty($event['start_ts']) ? (int) $event['start_ts'] : 0;
            $date_time = '';
            if ($start_ts) {
                $date_time = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $start_ts);
            }
            return [
                'id'    => $event['id'],
                'title' => $event['title'],
                'url'   => $event['permalink'],
                'lat'   => $lat,
                'lng'   => $lng,
                'map_url' => (string) ($event['map_url'] ?? ''),
                'date_time' => $date_time,
                'location' => (string) ($event['location'] ?? ''),
            ];
        }, $events)));

        // Geocode only when coordinates are missing and user enabled it.
        if ($geocode_enabled) {
            foreach ($markers as &$m) {
                if (! empty($m['lat']) && ! empty($m['lng'])) {
                    continue;
                }

                $query = '';
                if (! empty($m['map_url'])) {
                    $parts = wp_parse_url((string) $m['map_url']);
                    if (is_array($parts) && ! empty($parts['query'])) {
                        parse_str($parts['query'], $q);
                        foreach (['q', 'query', 'destination'] as $k) {
                            if (! empty($q[$k]) && is_string($q[$k])) {
                                $query = trim((string) $q[$k]);
                                break;
                            }
                        }
                    }
                }

                if ('' === $query && ! empty($m['location'])) {
                    $query = (string) $m['location'];
                }

                if ('' === trim($query)) {
                    continue;
                }

                $coords = $geocoder->get_or_geocode((int) $m['id'], $query, 60);
                if ($coords) {
                    $m['lat'] = (string) $coords['lat'];
                    $m['lng'] = (string) $coords['lng'];
                }
            }
            unset($m);
        }

        $markers_with_coords = array_values(array_filter($markers, function ($m) {
            return ! empty($m['lat']) && ! empty($m['lng']);
        }));

        $events_with_links_only = array_values(array_filter($markers, function ($m) {
            return (empty($m['lat']) || empty($m['lng'])) && ! empty($m['map_url']);
        }));
?>
        <div class="evt-events-map"
            data-markers='<?php echo wp_json_encode($markers_with_coords); ?>'
            data-link-markers='<?php echo wp_json_encode($events_with_links_only); ?>'
            data-i18n="<?php echo esc_attr(wp_json_encode($i18n)); ?>">
            <?php if ($heading) : ?>
                <h3 class="evt-events-map__heading"><?php echo esc_html($heading); ?></h3>
            <?php endif; ?>
            <div class="evt-events-map__canvas" style="height:400px;"></div>
            <?php if (empty($markers_with_coords) && empty($events_with_links_only)) : ?>
                <p class="evt-events-map__empty"><?php echo esc_html($text_empty); ?></p>
            <?php endif; ?>

            <?php if (! empty($events_with_links_only)) : ?>
                <div class="evt-events-map__links">
                    <p class="evt-events-map__links-title"><?php echo esc_html($text_links_heading); ?></p>
                    <ul class="evt-events-map__links-list">
                        <?php foreach ($events_with_links_only as $m) : ?>
                            <li>
                                <a href="<?php echo esc_url($m['url']); ?>"><?php echo esc_html($m['title']); ?></a>
                                <?php if (! empty($m['date_time'])) : ?>
                                    <span class="evt-events-map__links-date"><?php echo esc_html($m['date_time']); ?></span>
                                <?php endif; ?>
                                &nbsp;—&nbsp;<a href="<?php echo esc_url($m['map_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($label_open_map); ?></a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
<?php
    }
}
