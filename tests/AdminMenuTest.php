<?php

/**
 * CLI regression tests for WP Diagnostic Toolkit admin menu registration.
 *
 * Run from the repository root: php tests/AdminMenuTest.php
 */

require_once __DIR__ . '/../includes/Core/DiagnosticInterface.php';
require_once __DIR__ . '/../includes/Core/RuleResult.php';
require_once __DIR__ . '/../includes/Core/LegacyResultAdapter.php';
require_once __DIR__ . '/../includes/Core/DiagnosticResultPresenter.php';
require_once __DIR__ . '/../includes/Core/DiagnosticManager.php';

if (! defined('WPDT_PATH')) {
    define('WPDT_PATH', dirname(__DIR__) . '/');
}

$GLOBALS['wpdt_test_menus'] = array();

function add_menu_page()
{
    return null;
}

function add_submenu_page($parent, $page_title, $menu_title, $capability, $menu_slug, $callback)
{
    $GLOBALS['wpdt_test_menus'][$menu_slug] = array(
        'parent'     => $parent,
        'capability' => $capability,
        'callback'   => $callback,
    );

    return $menu_slug;
}

function wpdt_admin_check($condition, $message)
{
    static $passed = 0;
    static $failed = 0;

    if ($condition) {
        $passed++;
        echo 'PASS: ' . $message . PHP_EOL;
    } else {
        $failed++;
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
    }

    $GLOBALS['wpdt_admin_test_totals'] = array($passed, $failed);
}

require_once __DIR__ . '/../includes/Admin/Admin.php';

$admin = new WPDT\Admin\Admin(new WPDT\Core\DiagnosticManager());
$admin->register_menu();

$expected = array(
    'wpdt-http-ssl' => 'render_http_ssl',
    'wpdt-themes' => 'render_themes',
    'wpdt-disk-space' => 'render_disk_space',
    'wpdt-multilingual-woocommerce' => 'render_multilingual_woocommerce',
);

foreach ($expected as $slug => $callback) {
    $registered = isset($GLOBALS['wpdt_test_menus'][$slug])
        ? $GLOBALS['wpdt_test_menus'][$slug]
        : null;

    wpdt_admin_check(
        is_array($registered),
        'registers the ' . $slug . ' submenu'
    );

    wpdt_admin_check(
        is_array($registered)
            && $registered['parent'] === 'wp-diagnostic-toolkit'
            && $registered['capability'] === 'manage_options',
        'protects ' . $slug . ' with the expected parent and capability'
    );

    wpdt_admin_check(
        is_array($registered)
            && is_array($registered['callback'])
            && $registered['callback'][0] === $admin
            && $registered['callback'][1] === $callback
            && is_callable($registered['callback']),
        'connects ' . $slug . ' to its callable renderer'
    );
}

wpdt_admin_check(
    isset($GLOBALS['wpdt_test_menus']['wpdt-environment'])
        && isset($GLOBALS['wpdt_test_menus']['wpdt-plugins'])
        && isset($GLOBALS['wpdt_test_menus']['wpdt-server']),
    'preserves the existing diagnostic submenus'
);

$totals = $GLOBALS['wpdt_admin_test_totals'];
echo PHP_EOL . sprintf('%d passed, %d failed.', $totals[0], $totals[1]) . PHP_EOL;
exit($totals[1] === 0 ? 0 : 1);
