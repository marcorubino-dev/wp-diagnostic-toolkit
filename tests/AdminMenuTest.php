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

if (! defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

function current_user_can($capability)
{
    return 'manage_options' === $capability;
}

function esc_html($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function esc_attr($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function esc_textarea($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
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

class WPDT_Admin_Test_Diagnostic_Manager extends WPDT\Core\DiagnosticManager
{
    public $run_all_calls = 0;
    public $run_selected_calls = array();

    public function run_all()
    {
        $this->run_all_calls++;
        return array();
    }

    public function run_selected(array $module_ids)
    {
        $this->run_selected_calls[] = $module_ids;
        $module_id = $module_ids[0];

        $module_names = array(
            'environment' => 'Environment',
            'plugins' => 'Plugins',
            'server' => 'Server',
            'http_ssl' => 'HTTP & SSL',
            'themes' => 'Themes',
            'disk_space' => 'Disk Space',
            'multilingual_woocommerce' => 'Multilingual & WooCommerce',
        );

        return array(
            $module_id => array(
                'id' => $module_id,
                'name' => isset($module_names[$module_id]) ? $module_names[$module_id] : $module_id,
                'results' => array(),
            ),
        );
    }
}

$manager = new WPDT_Admin_Test_Diagnostic_Manager();
$admin = new WPDT\Admin\Admin($manager);
$admin->register_menu();

$expected = array(
    'wpdt-http-ssl' => 'render_http_ssl',
    'wpdt-themes' => 'render_themes',
    'wpdt-disk-space' => 'render_disk_space',
    'wpdt-multilingual-woocommerce' => 'render_multilingual_woocommerce',
    'wpdt-debug-center' => 'render_debug_center',
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

$expected_renderers = array(
    'render_environment' => array('environment', 'Environment'),
    'render_plugins' => array('plugins', 'Plugins'),
    'render_server' => array('server', 'Server'),
    'render_http_ssl' => array('http_ssl', 'HTTP &amp; SSL'),
    'render_themes' => array('themes', 'Themes'),
    'render_disk_space' => array('disk_space', 'Disk Space'),
    'render_multilingual_woocommerce' => array('multilingual_woocommerce', 'Multilingual &amp; WooCommerce'),
);

foreach ($expected_renderers as $renderer => $expected) {
    ob_start();
    call_user_func(array($admin, $renderer));
    $output = ob_get_clean();

    wpdt_admin_check(
        false !== strpos($output, '<h1>' . $expected[1] . '</h1>'),
        'renders the expected module template for ' . $renderer
    );
}

$expected_selected_calls = array();

foreach ($expected_renderers as $expected) {
    $expected_selected_calls[] = array($expected[0]);
}

wpdt_admin_check(
    $expected_selected_calls === $manager->run_selected_calls,
    'routes each individual diagnostic renderer to exactly its matching module'
);
wpdt_admin_check(
    0 === $manager->run_all_calls,
    'does not invoke run_all() when rendering individual diagnostic screens'
);


function wpdt_render_module_template($diagnostic)
{
    $page_description = 'Test module description.';
    ob_start();
    include __DIR__ . '/../includes/Admin/module.php';
    return ob_get_clean();
}

$empty_module_output = wpdt_render_module_template(array(
    'name' => 'Empty module',
    'results' => array(),
));
wpdt_admin_check(
    strpos($empty_module_output, 'NO DATA') !== false,
    'shows NO DATA for a module without diagnostic results'
);
wpdt_admin_check(
    strpos($empty_module_output, 'No diagnostic checks are available for this module.') !== false,
    'explains why an empty module has no status'
);
wpdt_admin_check(
    strpos($empty_module_output, 'wpdt-status-empty') !== false,
    'uses the empty status style for a module without results'
);

$error_module_output = wpdt_render_module_template(array(
    'name' => 'Failed module',
    'results' => array(
        array(
            'name' => 'Execution failure',
            'status' => 'warning',
            'outcome' => 'error',
            'description' => 'The diagnostic could not complete.',
            'evidence' => 'RuntimeException: simulated failure',
        ),
    ),
));
wpdt_admin_check(
    strpos($error_module_output, 'DIAGNOSTIC ERRORS') !== false,
    'distinguishes diagnostic execution errors from functional warnings'
);
wpdt_admin_check(
    strpos($error_module_output, 'wpdt-summary-diagnostic_error') !== false,
    'includes a separate diagnostic-error counter in the module summary'
);
wpdt_admin_check(
    strpos($error_module_output, 'wpdt-check-status-error') !== false,
    'renders execution errors with their own table status style'
);
wpdt_admin_check(
    strpos($error_module_output, 'Outcome: ERROR') !== false,
    'preserves the execution-error outcome in the copied module report'
);

$totals = $GLOBALS['wpdt_admin_test_totals'];
echo PHP_EOL . sprintf('%d passed, %d failed.', $totals[0], $totals[1]) . PHP_EOL;
exit($totals[1] === 0 ? 0 : 1);
