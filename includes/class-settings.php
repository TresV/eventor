<?php

namespace EventTicketsElementor;

use EventTicketsElementor\Admin\Admin_Menu;
use EventTicketsElementor\Admin\Dashboard_Page;
use EventTicketsElementor\Admin\Documentation_Page;
use EventTicketsElementor\Admin\Get_Started_Page;
use EventTicketsElementor\Admin\Staff_Page;
use EventTicketsElementor\Admin\Setup_Wizard_Page;
use EventTicketsElementor\Admin\Settings_Page;
use EventTicketsElementor\Admin\Repair_Tools_Page;
use EventTicketsElementor\Admin\WooCommerce_Settings;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Facade for settings access + admin UI wiring.
 */
class Settings
{
    public const OPTION_KEY = Settings_Store::OPTION_KEY;

    private Settings_Store $store;
    private Admin_Menu $admin_menu;
    private Dashboard_Page $dashboard_page;
    private Setup_Wizard_Page $setup_wizard_page;
    private Get_Started_Page $get_started_page;
    private Documentation_Page $documentation_page;
    private Settings_Page $settings_page;
    private Staff_Page $staff_page;
    private Repair_Tools_Page $tools_page;
    private WooCommerce_Settings $woocommerce_settings;
    private Staff_Access_Manager $staff_access_manager;

    public function __construct()
    {
        $this->store          = new Settings_Store();
        $this->staff_access_manager = new Staff_Access_Manager($this->store);
        $this->settings_page  = new Settings_Page($this->store);
        $this->dashboard_page = new Dashboard_Page($this->store);
        $this->setup_wizard_page = new Setup_Wizard_Page($this->store);
        $this->get_started_page = new Get_Started_Page();
        $this->documentation_page = new Documentation_Page();
        $this->staff_page     = new Staff_Page($this->staff_access_manager);
        $this->tools_page     = new Repair_Tools_Page();
        $this->woocommerce_settings = new WooCommerce_Settings();
        $this->admin_menu     = new Admin_Menu($this->dashboard_page, $this->setup_wizard_page, $this->get_started_page, $this->documentation_page, $this->settings_page, $this->staff_page, $this->tools_page);
    }

    /**
     * Proxy to Settings_Store::get for backward compatibility.
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public function get(string $key, $default = '')
    {
        return $this->store->get($key, $default);
    }
}
