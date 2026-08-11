<?php

namespace EventTicketsElementor\Bootstrap;

use EventTicketsElementor\Admin\Pdf_Template_Previews;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Bootstraps PDF previews on settings page without modifying Plugin.
 */
class Pdf_Template_Previews_Bootstrap
{
    public function __construct()
    {
        add_action('plugins_loaded', [$this, 'init'], 20);
    }

    public function init(): void
    {
        if (is_admin()) {
            new Pdf_Template_Previews();
        }
    }
}

new Pdf_Template_Previews_Bootstrap();

