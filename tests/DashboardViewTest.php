<?php

/**
 * CLI regression tests for the admin dashboard template.
 *
 * Run from the repository root: php tests/DashboardViewTest.php
 */

define('ABSPATH', __DIR__ . '/');

function esc_attr($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function esc_html($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function esc_textarea($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function esc_url($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function admin_url($path)
{
    return 'https://example.test/wp-admin/' . ltrim($path, '/');
}

$passed = 0;
$failed = 0;

function wpdt_dashboard_check($condition, $message)
{
    global $passed, $failed;

    if ($condition) {
        $passed++;
        echo 'PASS: ' . $message . PHP_EOL;
        return;
    }

    $failed++;
    fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
}

function wpdt_render_dashboard($diagnostics)
{
    $dashboard_path = __DIR__ . '/../includes/Admin/dashboard.php';

    ob_start();
    include $dashboard_path;
    return ob_get_clean();
}

$empty_output = wpdt_render_dashboard(array());

wpdt_dashboard_check(
    strpos($empty_output, 'NO DATA') !== false,
    'shows NO DATA when no diagnostic checks are available'
);
wpdt_dashboard_check(
    strpos($empty_output, 'wpdt-status-pass') === false
        && strpos($empty_output, '>OK<') === false,
    'does not report an empty dashboard as healthy'
);

$critical_output = wpdt_render_dashboard(array(
    array(
        'id' => 'environment',
        'name' => 'WordPress Environment',
        'results' => array(
            array(
                'name' => 'PHP version',
                'status' => 'critical',
                'description' => 'The installed PHP version is unsupported.',
                'value' => '5.6',
            ),
        ),
    ),
));

wpdt_dashboard_check(
    strpos($critical_output, 'wpdt-issue-critical') !== false,
    'renders critical findings with the critical issue style'
);
wpdt_dashboard_check(
    strpos($critical_output, 'page=wpdt-environment') !== false,
    'keeps the originating module link on critical findings'
);
wpdt_dashboard_check(
    strpos($critical_output, 'Module ID: environment') !== false,
    'includes the module ID when copying a critical finding'
);

$error_output = wpdt_render_dashboard(array(
    array(
        'id' => 'plugins',
        'name' => 'WordPress Plugins',
        'results' => array(
            array(
                'id' => 'plugins.error.invalid_result_0',
                'name' => 'Diagnostic execution error',
                'status' => 'warning',
                'outcome' => 'error',
                'description' => 'The diagnostic module failed to execute.',
                'evidence' => 'RuntimeException: simulated failure',
            ),
        ),
    ),
));

wpdt_dashboard_check(
    strpos($error_output, 'DIAGNOSTIC ERRORS') !== false,
    'surfaces diagnostic execution errors in the global system status'
);
wpdt_dashboard_check(
    strpos($error_output, 'wpdt-issue-error') !== false
        && strpos($error_output, 'DIAGNOSTIC ERROR') !== false,
    'distinguishes execution errors from functional warnings'
);
wpdt_dashboard_check(
    strpos($error_output, 'wpdt-module-error') !== false,
    'marks a module with an execution error distinctly'
);
wpdt_dashboard_check(
    strpos($error_output, 'diagnostic errors') !== false,
    'includes diagnostic errors in the module summary'
);
wpdt_dashboard_check(
    strpos($error_output, 'Outcome: ERROR') !== false,
    'includes the normalized outcome in the copied full report'
);

$arrow_output = wpdt_render_dashboard(array(
    array(
        'id' => 'environment',
        'name' => 'WordPress Environment',
        'results' => array(),
    ),
));

wpdt_dashboard_check(
    substr_count($arrow_output, 'class="wpdt-module-arrow"') === 1,
    'renders one HTML navigation arrow per linked module card'
);

echo PHP_EOL . sprintf('%d passed, %d failed.', $passed, $failed) . PHP_EOL;
exit($failed === 0 ? 0 : 1);
