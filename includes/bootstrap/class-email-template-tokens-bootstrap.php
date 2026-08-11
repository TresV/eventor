<?php

namespace EventTicketsElementor\Bootstrap;

use EventTicketsElementor\Admin\Email_Content_Token_Legend_Hider;
use EventTicketsElementor\Admin\Email_Template_Tokens;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Bootstraps email template token helpers without touching large plugin files.
 */
class Email_Template_Tokens_Bootstrap
{
    public function __construct()
    {
        add_action('plugins_loaded', [$this, 'init'], 20);
    }

    public function init(): void
    {
        if (! is_admin()) {
            return;
        }

        new Email_Template_Tokens();
        new Email_Content_Token_Legend_Hider();
    }
}

new Email_Template_Tokens_Bootstrap();

