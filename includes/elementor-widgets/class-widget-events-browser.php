<?php

namespace EventTicketsElementor\Elementor_Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use EventTicketsElementor\Event_Discovery_Meta;
use EventTicketsElementor\Event_Feed_Endpoint;
use EventTicketsElementor\Event_Selector;
use EventTicketsElementor\Event_Taxonomies;
use EventTicketsElementor\Plugin;

if (! defined('ABSPATH')) {
    exit;
}

require_once EVT_TICKETS_PLUGIN_DIR . 'includes/elementor-widgets/concerns/trait-events-browser-style-controls-extended.php';

use EventTicketsElementor\Elementor_Widgets\Concerns\Events_Browser_Style_Controls_Extended;

/**
 * Unified Events Browser widget (calendar + list + grid + map + summary) with a filter sidebar.
 */
class Widget_Events_Browser extends Widget_Base
{
    use Events_Browser_Style_Controls_Extended;

    public function get_name()
    {
        return 'evt_events_browser';
    }

    public function get_title()
    {
        return __('Events Browser', 'Event-Tickets-for-Elementor');
    }

    public function get_icon()
    {
        return 'eicon-search';
    }

    public function get_categories()
    {
        return ['evt-tickets'];
    }

    public function get_style_depends()
    {
        return ['evt-tickets-events-browser', 'evt-tickets-events-browser-mobile', 'leaflet', 'evt-tickets-events-map'];
    }

    public function get_script_depends()
    {
        return ['leaflet', 'evt-tickets-events-browser'];
    }

    protected function register_controls()
    {
        $this->start_controls_section(
            'section_content',
            [
                'label' => __('Content', 'Event-Tickets-for-Elementor'),
                'tab'   => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'initial_view',
            [
                'label'   => __('Initial View', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::SELECT,
                'default' => 'month',
                'options' => [
                    'month'   => __('Month', 'Event-Tickets-for-Elementor'),
                    'week'    => __('Week', 'Event-Tickets-for-Elementor'),
                    'day'     => __('Day', 'Event-Tickets-for-Elementor'),
                    'list'    => __('List', 'Event-Tickets-for-Elementor'),
                    'grid'    => __('Grid', 'Event-Tickets-for-Elementor'),
                    'map'     => __('Map', 'Event-Tickets-for-Elementor'),
                    'summary' => __('Summary', 'Event-Tickets-for-Elementor'),
                ],
            ]
        );

        $this->add_control(
            'default_hide_cancelled',
            [
                'label'        => __('Hide Cancelled Events (default)', 'Event-Tickets-for-Elementor'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => '',
            ]
        );

        $this->add_control(
            'default_hide_postponed',
            [
                'label'        => __('Hide Postponed Events (default)', 'Event-Tickets-for-Elementor'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => '',
            ]
        );

        $this->add_control(
            'only_capacity',
            [
                'label'        => __('Only Events With Capacity (default)', 'Event-Tickets-for-Elementor'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => '',
            ]
        );

        $this->add_control(
            'show_status_badges',
            [
                'label'        => __('Show Status Badges', 'Event-Tickets-for-Elementor'),
                'description'  => __('Displays resolved cancelled, postponed, and over badges in browser views.', 'Event-Tickets-for-Elementor'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'items_per_list',
            [
                'label'   => __('Items in List/Grid/Summary', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::NUMBER,
                'default' => 50,
                'min'     => 1,
                'max'     => 500,
            ]
        );

        $this->add_control(
            'list_range_days',
            [
                'label'   => __('Default Range (days) for List/Grid/Summary/Map', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::NUMBER,
                'default' => 90,
                'min'     => 1,
                'max'     => 3650,
            ]
        );

        $this->add_control(
            'default_range_mode',
            [
                'label'   => __('Default Date Range', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::SELECT,
                'default' => 'today_to_eoy',
                'options' => [
                    'today_to_eoy' => __('Today → End of Year', 'Event-Tickets-for-Elementor'),
                    'custom'       => __('Custom', 'Event-Tickets-for-Elementor'),
                ],
            ]
        );

        $this->add_control(
            'default_from',
            [
                'label'       => __('Default From (YYYY-MM-DD)', 'Event-Tickets-for-Elementor'),
                'type'        => Controls_Manager::TEXT,
                'placeholder' => '2026-01-01',
                'condition'   => [
                    'default_range_mode' => 'custom',
                ],
            ]
        );

        $this->add_control(
            'default_to',
            [
                'label'       => __('Default To (YYYY-MM-DD)', 'Event-Tickets-for-Elementor'),
                'type'        => Controls_Manager::TEXT,
                'placeholder' => '2026-12-31',
                'condition'   => [
                    'default_range_mode' => 'custom',
                ],
            ]
        );

        $this->add_control(
            'grid_columns',
            [
                'label'   => __('Grid Columns', 'Event-Tickets-for-Elementor'),
                'type'    => Controls_Manager::NUMBER,
                'default' => 3,
                'min'     => 1,
                'max'     => 6,
            ]
        );

        $this->add_control(
            'show_filters_category',
            [
                'label'        => __('Show Filter: Event Category', 'Event-Tickets-for-Elementor'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'show_filters_cost',
            [
                'label'        => __('Show Filter: Cost', 'Event-Tickets-for-Elementor'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'show_filters_tags',
            [
                'label'        => __('Show Filter: Tags', 'Event-Tickets-for-Elementor'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'show_filters_venues',
            [
                'label'        => __('Show Filter: Venues', 'Event-Tickets-for-Elementor'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'show_filters_organizers',
            [
                'label'        => __('Show Filter: Organizers', 'Event-Tickets-for-Elementor'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'show_filters_day',
            [
                'label'        => __('Show Filter: Day of Week', 'Event-Tickets-for-Elementor'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'show_filters_country_city',
            [
                'label'        => __('Show Filter: Country/City', 'Event-Tickets-for-Elementor'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'show_filters_status',
            [
                'label'        => __('Show Filter: Event Status', 'Event-Tickets-for-Elementor'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'show_filters_virtual',
            [
                'label'        => __('Show Filter: Virtual Events', 'Event-Tickets-for-Elementor'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->end_controls_section();
        $this->register_events_browser_style_controls_extended();
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();
        $is_editor = Event_Selector::is_elementor_editor_context();
        $term_limit = $is_editor ? 25 : 0;

        $terms = [
            'categories' => $this->get_terms_for_tax(Event_Taxonomies::TAX_CATEGORY, $term_limit),
            'tags'       => $this->get_terms_for_tax(Event_Taxonomies::TAX_TAG, $term_limit),
            'venues'     => $this->get_terms_for_tax(Event_Taxonomies::TAX_VENUE, $term_limit),
            'organizers' => $this->get_terms_for_tax(Event_Taxonomies::TAX_ORGANIZER, $term_limit),
        ];

        $filters_source = Plugin::instance()->event_filters_source;
        $countries = $is_editor ? [] : $filters_source->get_distinct_meta_values(Event_Discovery_Meta::COUNTRY_META, 200);
        $cities    = $is_editor ? [] : $filters_source->get_distinct_meta_values(Event_Discovery_Meta::CITY_META, 200);
        $cost_range = $is_editor ? ['min' => 0.0, 'max' => 0.0] : $filters_source->get_cost_range();

        $now = current_time('timestamp');
        $year  = (int) wp_date('Y', $now);
        $month = (int) wp_date('n', $now);
        $day   = (int) wp_date('j', $now);

        $default_range_mode = (string) ($settings['default_range_mode'] ?? 'today_to_eoy');
        $default_from = (string) ($settings['default_from'] ?? '');
        $default_to = (string) ($settings['default_to'] ?? '');

        $data = [
            'initialView'     => (string) ($settings['initial_view'] ?? 'month'),
            'hideCancelled'   => ('yes' === ($settings['default_hide_cancelled'] ?? '')),
            'hidePostponed'   => ('yes' === ($settings['default_hide_postponed'] ?? '')),
            'onlyCapacity'    => ('yes' === ($settings['only_capacity'] ?? '')),
            'showStatusBadges' => ('yes' === ($settings['show_status_badges'] ?? 'yes')),
            'itemsPerList'    => max(1, (int) ($settings['items_per_list'] ?? 50)),
            'listRangeDays'   => max(1, (int) ($settings['list_range_days'] ?? 90)),
            'gridColumns'     => max(1, min(6, (int) ($settings['grid_columns'] ?? 3))),
            'defaultRangeMode' => $default_range_mode,
            'defaultFrom'     => $default_from,
            'defaultTo'       => $default_to,
            'terms'           => $terms,
            'countries'       => $countries,
            'cities'          => $cities,
            'costMin'         => (float) ($cost_range['min'] ?? 0),
            'costMax'         => (float) ($cost_range['max'] ?? 0),
            'filtersEnabled'  => [
                'category'   => ('yes' === ($settings['show_filters_category'] ?? 'yes')),
                'cost'       => ('yes' === ($settings['show_filters_cost'] ?? 'yes')),
                'tags'       => ('yes' === ($settings['show_filters_tags'] ?? 'yes')),
                'venues'     => ('yes' === ($settings['show_filters_venues'] ?? 'yes')),
                'organizers' => ('yes' === ($settings['show_filters_organizers'] ?? 'yes')),
                'day'        => ('yes' === ($settings['show_filters_day'] ?? 'yes')),
                'countryCity' => ('yes' === ($settings['show_filters_country_city'] ?? 'yes')),
                'status'     => ('yes' === ($settings['show_filters_status'] ?? 'yes')),
                'virtual'    => ('yes' === ($settings['show_filters_virtual'] ?? 'yes')),
            ],
        ];
?>
        <div
            class="evt-events-browser evt-browser"
            data-year="<?php echo esc_attr($year); ?>"
            data-month="<?php echo esc_attr($month); ?>"
            data-day="<?php echo esc_attr($day); ?>"
            data-config="<?php echo esc_attr(wp_json_encode($data)); ?>">

            <div class="evt-browser__header">
                <div class="evt-browser__nav">
                    <button type="button" class="evt-browser__iconbtn" data-nav="prev" aria-label="<?php esc_attr_e('Previous', 'Event-Tickets-for-Elementor'); ?>">&lt;</button>
                    <button type="button" class="evt-browser__iconbtn" data-nav="next" aria-label="<?php esc_attr_e('Next', 'Event-Tickets-for-Elementor'); ?>">&gt;</button>
                    <button type="button" class="evt-browser__btn" data-nav="today"><?php esc_html_e('Today', 'Event-Tickets-for-Elementor'); ?></button>

                    <button type="button" class="evt-browser__iconbtn" data-action="search" aria-label="<?php esc_attr_e('Search', 'Event-Tickets-for-Elementor'); ?>">
                        <span aria-hidden="true">⌕</span>
                    </button>
                    <button type="button" class="evt-browser__iconbtn" data-action="filters" aria-label="<?php esc_attr_e('Filters', 'Event-Tickets-for-Elementor'); ?>">
                        <span aria-hidden="true">≡</span>
                    </button>
                    <div class="evt-browser__viewtrigger" aria-label="<?php esc_attr_e('Views', 'Event-Tickets-for-Elementor'); ?>">
                        <span class="evt-browser__iconbtn evt-browser__iconbtn--static" aria-hidden="true">▦</span>
                        <select class="evt-browser__view evt-browser__view--mobile" aria-label="<?php esc_attr_e('View', 'Event-Tickets-for-Elementor'); ?>">
                            <option value="month"><?php esc_html_e('Month', 'Event-Tickets-for-Elementor'); ?></option>
                            <option value="week"><?php esc_html_e('Week', 'Event-Tickets-for-Elementor'); ?></option>
                            <option value="day"><?php esc_html_e('Day', 'Event-Tickets-for-Elementor'); ?></option>
                            <option value="list"><?php esc_html_e('List', 'Event-Tickets-for-Elementor'); ?></option>
                            <option value="grid"><?php esc_html_e('Grid', 'Event-Tickets-for-Elementor'); ?></option>
                            <option value="map"><?php esc_html_e('Map', 'Event-Tickets-for-Elementor'); ?></option>
                            <option value="summary"><?php esc_html_e('Summary', 'Event-Tickets-for-Elementor'); ?></option>
                        </select>
                    </div>
                </div>

                <div class="evt-browser__period">
                    <input type="month" class="evt-browser__monthpick" aria-label="<?php esc_attr_e('Month', 'Event-Tickets-for-Elementor'); ?>" />
                    <input type="date" class="evt-browser__from" aria-label="<?php esc_attr_e('From', 'Event-Tickets-for-Elementor'); ?>" />
                    <input type="date" class="evt-browser__to" aria-label="<?php esc_attr_e('To', 'Event-Tickets-for-Elementor'); ?>" />
                </div>

                <div class="evt-browser__search">
                    <input type="search" class="evt-browser__searchinput" placeholder="<?php esc_attr_e('Search events…', 'Event-Tickets-for-Elementor'); ?>" />
                    <button type="button" class="evt-browser__btn evt-browser__searchbtn"><?php esc_html_e('Search', 'Event-Tickets-for-Elementor'); ?></button>
                    <select class="evt-browser__view">
                        <option value="month"><?php esc_html_e('Month', 'Event-Tickets-for-Elementor'); ?></option>
                        <option value="week"><?php esc_html_e('Week', 'Event-Tickets-for-Elementor'); ?></option>
                        <option value="day"><?php esc_html_e('Day', 'Event-Tickets-for-Elementor'); ?></option>
                        <option value="list"><?php esc_html_e('List', 'Event-Tickets-for-Elementor'); ?></option>
                        <option value="grid"><?php esc_html_e('Grid', 'Event-Tickets-for-Elementor'); ?></option>
                        <option value="map"><?php esc_html_e('Map', 'Event-Tickets-for-Elementor'); ?></option>
                        <option value="summary"><?php esc_html_e('Summary', 'Event-Tickets-for-Elementor'); ?></option>
                    </select>
                </div>
            </div>

            <div class="evt-browser__layout">
                <aside class="evt-browser__sidebar">
                    <div class="evt-sidebar__title"><?php esc_html_e('Filters', 'Event-Tickets-for-Elementor'); ?></div>

                    <div class="evt-sidebar__selections" data-selections>
                        <div class="evt-sidebar__selections-head">
                            <div class="evt-sidebar__selections-label"><?php esc_html_e('Your selections', 'Event-Tickets-for-Elementor'); ?></div>
                            <button type="button" class="evt-sidebar__clear" data-clear>
                                <?php esc_html_e('Clear', 'Event-Tickets-for-Elementor'); ?>
                            </button>
                        </div>
                        <div class="evt-sidebar__pills" data-pills></div>
                    </div>

                    <div class="evt-acc" data-acc="category">
                        <button type="button" class="evt-acc__head" aria-expanded="false">
                            <span><?php esc_html_e('Event Category', 'Event-Tickets-for-Elementor'); ?></span>
                            <span class="evt-acc__icon">+</span>
                        </button>
                        <div class="evt-acc__panel" hidden data-panel="category"></div>
                    </div>

                    <div class="evt-acc" data-acc="cost">
                        <button type="button" class="evt-acc__head" aria-expanded="false">
                            <span><?php esc_html_e('Cost', 'Event-Tickets-for-Elementor'); ?></span>
                            <span class="evt-acc__icon">+</span>
                        </button>
                        <div class="evt-acc__panel" hidden data-panel="cost"></div>
                    </div>

                    <div class="evt-acc" data-acc="tags">
                        <button type="button" class="evt-acc__head" aria-expanded="false">
                            <span><?php esc_html_e('Tags', 'Event-Tickets-for-Elementor'); ?></span>
                            <span class="evt-acc__icon">+</span>
                        </button>
                        <div class="evt-acc__panel" hidden data-panel="tags"></div>
                    </div>

                    <div class="evt-acc" data-acc="venues">
                        <button type="button" class="evt-acc__head" aria-expanded="false">
                            <span><?php esc_html_e('Venues', 'Event-Tickets-for-Elementor'); ?></span>
                            <span class="evt-acc__icon">+</span>
                        </button>
                        <div class="evt-acc__panel" hidden data-panel="venues"></div>
                    </div>

                    <div class="evt-acc" data-acc="organizers">
                        <button type="button" class="evt-acc__head" aria-expanded="false">
                            <span><?php esc_html_e('Organizers', 'Event-Tickets-for-Elementor'); ?></span>
                            <span class="evt-acc__icon">+</span>
                        </button>
                        <div class="evt-acc__panel" hidden data-panel="organizers"></div>
                    </div>

                    <div class="evt-acc" data-acc="day">
                        <button type="button" class="evt-acc__head" aria-expanded="false">
                            <span><?php esc_html_e('Day', 'Event-Tickets-for-Elementor'); ?></span>
                            <span class="evt-acc__icon">+</span>
                        </button>
                        <div class="evt-acc__panel" hidden data-panel="day"></div>
                    </div>

                    <div class="evt-acc" data-acc="country-city">
                        <button type="button" class="evt-acc__head" aria-expanded="false">
                            <span><?php esc_html_e('Country / City', 'Event-Tickets-for-Elementor'); ?></span>
                            <span class="evt-acc__icon">+</span>
                        </button>
                        <div class="evt-acc__panel" hidden data-panel="country-city"></div>
                    </div>

                    <div class="evt-acc" data-acc="status">
                        <button type="button" class="evt-acc__head" aria-expanded="false">
                            <span><?php esc_html_e('Event Status', 'Event-Tickets-for-Elementor'); ?></span>
                            <span class="evt-acc__icon">+</span>
                        </button>
                        <div class="evt-acc__panel" hidden data-panel="status"></div>
                    </div>

                    <div class="evt-acc" data-acc="virtual">
                        <button type="button" class="evt-acc__head" aria-expanded="false">
                            <span><?php esc_html_e('Virtual Events', 'Event-Tickets-for-Elementor'); ?></span>
                            <span class="evt-acc__icon">+</span>
                        </button>
                        <div class="evt-acc__panel" hidden data-panel="virtual"></div>
                    </div>

                    <div class="evt-acc evt-acc--date" data-acc="date">
                        <button type="button" class="evt-acc__head" aria-expanded="false">
                            <span><?php esc_html_e('Date Range', 'Event-Tickets-for-Elementor'); ?></span>
                            <span class="evt-acc__icon">+</span>
                        </button>
                        <div class="evt-acc__panel" hidden data-panel="date"></div>
                    </div>
                </aside>

                <main class="evt-browser__main">
                    <div class="evt-browser__body" data-body>
                        <p class="evt-browser__loading"><?php esc_html_e('Loading…', 'Event-Tickets-for-Elementor'); ?></p>
                    </div>

                    <div class="evt-browser__pagination" data-pagination hidden>
                        <button type="button" class="evt-browser__btn evt-browser__loadmore" data-load-more>
                            <?php esc_html_e('Load more events', 'Event-Tickets-for-Elementor'); ?>
                        </button>
                    </div>
                </main>
            </div>

            <div class="evt-browser__overlay" data-overlay hidden></div>
        </div>
<?php
    }

    /**
     * @return array<int,array{id:int,name:string,slug:string}>
     */
    private function get_terms_for_tax(string $taxonomy, int $limit = 0): array
    {
        $args = [
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
        ];

        if ($limit > 0) {
            $args['number'] = $limit;
        }

        $terms = get_terms($args);
        if (is_wp_error($terms) || empty($terms)) {
            return [];
        }

        $out = [];
        foreach ($terms as $t) {
            $out[] = [
                'id'   => (int) $t->term_id,
                'name' => (string) $t->name,
                'slug' => (string) $t->slug,
            ];
        }
        return $out;
    }
}
