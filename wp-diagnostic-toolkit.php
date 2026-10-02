<?php

/**
 * Plugin Name: WP Diagnostic Toolkit
 * Description: Read-only diagnostics for WordPress environments.
 * Version: 0.2.0
 * Requires PHP: 7.4
 * Author: Marco Rubino
 * License: GPL-2.0-or-later
 * Text Domain: wp-diagnostic-toolkit
 */

defined('ABSPATH') || exit;

define('WPDT_VERSION', '0.2.0');
define('WPDT_PATH', plugin_dir_path(__FILE__));

spl_autoload_register(
    function ($class) {
        $prefix = 'WPDT\\';

        if (strpos($class, $prefix) !== 0) {
            return;
        }

        $relative_class = substr($class, strlen($prefix));

        $file = WPDT_PATH . 'includes/' . str_replace(
            '\\',
            '/',
            $relative_class
        ) . '.php';

        if (file_exists($file)) {
            require_once $file;
        }
    }
);

$diagnostic_manager = new WPDT\Core\DiagnosticManager();

$diagnostic_manager->register(
    new WPDT\Diagnostics\EnvironmentDiagnostic()
);

$diagnostic_manager->register(
    new WPDT\Diagnostics\PluginDiagnostic()
);

$admin = new WPDT\Admin\Admin($diagnostic_manager);

add_action(
    'admin_menu',
      array($admin, 'register_menu')
);

$diagnostic_manager->register(
    new WPDT\Diagnostics\ServerDiagnostic()
);