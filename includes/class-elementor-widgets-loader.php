<?php

namespace EventTicketsElementor;

use EventTicketsElementor\Elementor_Widgets\Widget_Checkin;
use EventTicketsElementor\Elementor_Widgets\Widget_Ticket_View;
use EventTicketsElementor\Elementor_Widgets\Widget_Ticket_View_PDF;
use EventTicketsElementor\Elementor_Widgets\Widget_Resend_Ticket;
use EventTicketsElementor\Elementor_Widgets\Widget_Ticket_Box;
use EventTicketsElementor\Elementor_Widgets\Widget_Ticket_Cancel;
use EventTicketsElementor\Elementor_Widgets\Widget_Ticket_View_Virtual;
use EventTicketsElementor\Elementor_Widgets\Widget_Events_Calendar;
use EventTicketsElementor\Elementor_Widgets\Widget_Events_List;
use EventTicketsElementor\Elementor_Widgets\Widget_Events_Summary;
use EventTicketsElementor\Elementor_Widgets\Widget_Events_Map;
use EventTicketsElementor\Elementor_Widgets\Widget_Events_Month_Calendar;
use EventTicketsElementor\Elementor_Widgets\Widget_Events_Filter_Bar;
use EventTicketsElementor\Elementor_Widgets\Widget_Events_Browser_Labeled;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Registers Elementor widgets and category for Event Tickets.
 */
class Elementor_Widgets_Loader
{

    public function __construct()
    {
        add_action('elementor/widgets/register', [$this, 'register_widgets']);
        add_action('elementor/elements/categories_registered', [$this, 'register_category']);
        new Elementor_Dynamic_Tags_Loader();
    }

    public function register_category($elements_manager): void
    {
        $elements_manager->add_category(
            'evt-tickets',
            [
                'title' => __('Event Tickets', 'Event-Tickets-for-Elementor'),
                'icon'  => 'fa fa-ticket',
            ]
        );
    }

    public function register_widgets($widgets_manager): void
    {
        require_once EVT_TICKETS_PLUGIN_DIR . 'includes/elementor-widgets/class-widget-checkin.php';
        require_once EVT_TICKETS_PLUGIN_DIR . 'includes/elementor-widgets/class-widget-ticket-view.php';
        require_once EVT_TICKETS_PLUGIN_DIR . 'includes/elementor-widgets/class-widget-ticket-view-pdf.php';
        require_once EVT_TICKETS_PLUGIN_DIR . 'includes/elementor-widgets/class-widget-ticket-view-virtual.php';
        require_once EVT_TICKETS_PLUGIN_DIR . 'includes/elementor-widgets/class-widget-resend-ticket.php';
        require_once EVT_TICKETS_PLUGIN_DIR . 'includes/elementor-widgets/class-widget-ticket-box.php';
        require_once EVT_TICKETS_PLUGIN_DIR . 'includes/elementor-widgets/class-widget-ticket-cancel.php';
        require_once EVT_TICKETS_PLUGIN_DIR . 'includes/elementor-widgets/class-widget-events-calendar.php';
        require_once EVT_TICKETS_PLUGIN_DIR . 'includes/elementor-widgets/class-widget-events-list.php';
        require_once EVT_TICKETS_PLUGIN_DIR . 'includes/elementor-widgets/class-widget-events-summary.php';
        require_once EVT_TICKETS_PLUGIN_DIR . 'includes/elementor-widgets/class-widget-events-map.php';
        require_once EVT_TICKETS_PLUGIN_DIR . 'includes/elementor-widgets/class-widget-events-month-calendar.php';
        require_once EVT_TICKETS_PLUGIN_DIR . 'includes/elementor-widgets/class-widget-events-filter-bar.php';
        require_once EVT_TICKETS_PLUGIN_DIR . 'includes/elementor-widgets/class-widget-events-browser.php';
        require_once EVT_TICKETS_PLUGIN_DIR . 'includes/elementor-widgets/class-widget-events-browser-labeled.php';

        $widgets_manager->register(new Widget_Checkin());
        $widgets_manager->register(new Widget_Ticket_View());
        $widgets_manager->register(new Widget_Ticket_View_PDF());
        $widgets_manager->register(new Widget_Ticket_View_Virtual());
        $widgets_manager->register(new Widget_Resend_Ticket());
        $widgets_manager->register(new Widget_Ticket_Box());
        $widgets_manager->register(new Widget_Ticket_Cancel());
        $widgets_manager->register(new Widget_Events_Calendar());
        $widgets_manager->register(new Widget_Events_List());
        $widgets_manager->register(new Widget_Events_Summary());
        $widgets_manager->register(new Widget_Events_Map());
        $widgets_manager->register(new Widget_Events_Month_Calendar());
        $widgets_manager->register(new Widget_Events_Filter_Bar());
        $widgets_manager->register(new Widget_Events_Browser_Labeled());
    }
}
