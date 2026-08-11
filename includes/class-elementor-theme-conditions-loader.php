<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Registers Elementor Pro theme/display conditions for Event content.
 */
class Elementor_Theme_Conditions_Loader
{
    public function __construct()
    {
        add_action('elementor/theme/register_conditions', [$this, 'register_conditions']);
    }

    /**
     * @param mixed $conditions_manager Elementor Pro conditions manager.
     */
    public function register_conditions($conditions_manager): void
    {
        if (
            ! class_exists('\ElementorPro\Modules\ThemeBuilder\Conditions\Condition_Base')
            || ! is_object($conditions_manager)
            || ! method_exists($conditions_manager, 'get_condition')
        ) {
            return;
        }

        $singular_condition = $conditions_manager->get_condition('singular');
        if (! is_object($singular_condition) || ! method_exists($singular_condition, 'register_sub_condition')) {
            return;
        }

        require_once EVT_TICKETS_PLUGIN_DIR . 'includes/elementor-conditions/class-event-condition-base.php';
        require_once EVT_TICKETS_PLUGIN_DIR . 'includes/elementor-conditions/class-event-singular-condition.php';
        require_once EVT_TICKETS_PLUGIN_DIR . 'includes/elementor-conditions/class-event-posts-condition.php';
        require_once EVT_TICKETS_PLUGIN_DIR . 'includes/elementor-conditions/class-event-category-condition.php';
        require_once EVT_TICKETS_PLUGIN_DIR . 'includes/elementor-conditions/class-event-child-categories-condition.php';
        require_once EVT_TICKETS_PLUGIN_DIR . 'includes/elementor-conditions/class-event-tag-condition.php';
        require_once EVT_TICKETS_PLUGIN_DIR . 'includes/elementor-conditions/class-event-author-condition.php';

        $singular_condition->register_sub_condition(
            new \EventTicketsElementor\Elementor_Conditions\Event_Singular_Condition()
        );
    }
}
