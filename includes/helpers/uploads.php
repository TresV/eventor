<?php

/**
 * Helpers for protecting plugin-generated upload directories.
 *
 * @package EventTicketsElementor
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Write web-server guard files (index.php + .htaccess) into a directory so its
 * contents cannot be listed or downloaded directly through the web root.
 *
 * Primary delivery for generated files (e.g. PDF tickets containing attendee
 * PII) must remain the plugin's signed endpoints; this is defense-in-depth for
 * Apache/Nginx setups that serve the uploads directory directly.
 *
 * @param string $dir Absolute path to the directory to protect.
 * @return bool True when the directory exists and guard files are in place.
 */
function evt_protect_upload_dir(string $dir): bool
{
    if ('' === $dir) {
        return false;
    }

    if (! is_dir($dir) && ! wp_mkdir_p($dir)) {
        return false;
    }

    $guard_index = trailingslashit($dir) . 'index.php';
    if (! file_exists($guard_index)) {
        @file_put_contents($guard_index, "<?php\n// Silence is golden.\n");
    }

    $htaccess = trailingslashit($dir) . '.htaccess';
    if (! file_exists($htaccess)) {
        $rules = "# Deny direct web access to plugin-generated files.\n"
            . "<IfModule mod_authz_core.c>\n"
            . "    Require all denied\n"
            . "</IfModule>\n"
            . "<IfModule !mod_authz_core.c>\n"
            . "    Order deny,allow\n"
            . "    Deny from all\n"
            . "</IfModule>\n";
        @file_put_contents($htaccess, $rules);
    }

    return is_dir($dir);
}
