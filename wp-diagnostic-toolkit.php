
<?php
/**
 * Plugin Name: WP Diagnostic Toolkit
 * Description: Read-only diagnostics for WordPress environments.
 * Version: 0.1.0
 * Requires PHP: 7.4
 * Author: Marco Rubino
 * License: GPL-2.0-or-later
 * Text Domain: wp-diagnostic-toolkit
 */

defined('ABSPATH') || exit;

define('WPDT_VERSION', '0.1.0');
define('WPDT_PATH', plugin_dir_path(__FILE__));

require_once WPDT_PATH . 'includes/class-environment-diagnostics.php';

add_action(
    'admin_menu',
    array('WPDT_Environment_Diagnostics', 'register_menu')
);