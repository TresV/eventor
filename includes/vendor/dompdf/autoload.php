<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Minimal autoloader for vendored dompdf + deps (no Composer).
 */
spl_autoload_register(
    function ($class) {
        // dompdf includes one legacy class in /lib (Cpdf.php) under Dompdf\ namespace.
        if ('Dompdf\\Cpdf' === $class) {
            $cpdf = EVT_TICKETS_PLUGIN_DIR . 'includes/vendor/dompdf/lib/Cpdf.php';
            if (file_exists($cpdf)) {
                require_once $cpdf;
            }
            return;
        }

        $prefixes = [
            'Dompdf\\'  => EVT_TICKETS_PLUGIN_DIR . 'includes/vendor/dompdf/src/',
            'FontLib\\' => EVT_TICKETS_PLUGIN_DIR . 'includes/vendor/php-font-lib/src/FontLib/',
            'Svg\\'     => EVT_TICKETS_PLUGIN_DIR . 'includes/vendor/php-svg-lib/src/Svg/',
        ];

        foreach ($prefixes as $prefix => $base_dir) {
            $len = strlen($prefix);
            if (0 !== strncmp($prefix, $class, $len)) {
                continue;
            }

            $relative = substr($class, $len);
            $file = $base_dir . str_replace('\\', '/', $relative) . '.php';

            if (file_exists($file)) {
                require_once $file;
            }

            return;
        }
    }
);
