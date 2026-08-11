<?php

namespace EventTicketsElementor\Bootstrap;

use EventTicketsElementor\Admin\Email_Template_Code_Editor_Assets;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Bootstraps the email template code editor without modifying Plugin.
 */
class Email_Template_Code_Editor_Bootstrap
{
    public function __construct()
    {
        add_action('plugins_loaded', [$this, 'init'], 20);
    }

    public function init(): void
    {
        if (is_admin()) {
            new Email_Template_Code_Editor_Assets();
        }
    }
}

new Email_Template_Code_Editor_Bootstrap();

