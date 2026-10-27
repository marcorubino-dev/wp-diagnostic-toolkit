<?php

/**
 * Lightweight CLI tests for plugin diagnostics; no WordPress bootstrap needed.
 *
 * Run from the repository root: php tests/PluginDiagnosticTest.php
 */

require_once __DIR__ . '/../includes/Core/DiagnosticInterface.php';
require_once __DIR__ . '/../includes/Diagnostics/PluginDiagnostic.php';

$GLOBALS['wpdt_test_multisite'] = false;
$GLOBALS['wpdt_test_plugins'] = array();
$GLOBALS['wpdt_test_active_plugins'] = array();
$GLOBALS['wpdt_test_network_plugins'] = array();
$GLOBALS['wpdt_test_update_transient'] = false;

function get_plugins()
{
    return $GLOBALS['wpdt_test_plugins'];
}

function get_option($name, $default = false)
{
    if ('active_plugins' === $name) {
        return $GLOBALS['wpdt_test_active_plugins'];
    }

    return $default;
}

function is_multisite()
{
    return $GLOBALS['wpdt_test_multisite'];
}

function get_site_option($name, $default = false)
{
    if ('active_sitewide_plugins' === $name) {
        return $GLOBALS['wpdt_test_network_plugins'];
    }

    return $default;
}

function get_site_transient($name)
{
    return $GLOBALS['wpdt_test_update_transient'];
}

function wpdt_assert_same($expected, $actual, $message)
{
    if ($expected !== $actual) {
        fwrite(
            STDERR,
            'FAIL: ' . $message . ' (expected '
            . var_export($expected, true) . ', got '
            . var_export($actual, true) . ')' . PHP_EOL
        );
        exit(1);
    }

    echo 'PASS: ' . $message . PHP_EOL;
}

function wpdt_plugin_result_value($results, $name)
{
    foreach ($results as $result) {
        if ($name === $result['name']) {
            return $result['value'];
        }
    }

    return null;
}

$GLOBALS['wpdt_test_plugins'] = array(
    'active/active.php' => array('Name' => 'Active plugin'),
    'inactive/inactive.php' => array('Name' => 'Inactive plugin'),
    'network/network.php' => array('Name' => 'Network plugin'),
);

$GLOBALS['wpdt_test_active_plugins'] = array('active/active.php');
$GLOBALS['wpdt_test_update_transient'] = (object) array(
    'response' => array(
        'active/active.php' => (object) array(),
        'inactive/inactive.php' => (object) array(),
        'network/network.php' => (object) array(),
    ),
);

$diagnostic = new \WPDT\Diagnostics\PluginDiagnostic();
$results = $diagnostic->run();

wpdt_assert_same(3, wpdt_plugin_result_value($results, 'Installed plugins'), 'counts installed plugins');
wpdt_assert_same(1, wpdt_plugin_result_value($results, 'Active plugins'), 'counts site-active plugins on single-site');
wpdt_assert_same(2, wpdt_plugin_result_value($results, 'Inactive plugins'), 'counts inactive plugins on single-site');
wpdt_assert_same('1 available', wpdt_plugin_result_value($results, 'Active plugin updates'), 'counts updates for active plugins only');

$GLOBALS['wpdt_test_multisite'] = true;
$GLOBALS['wpdt_test_network_plugins'] = array(
    'network/network.php' => 1234567890,
    'active/active.php' => 1234567891,
);

$results = $diagnostic->run();

wpdt_assert_same(2, wpdt_plugin_result_value($results, 'Active plugins'), 'counts unique site-active and network-active plugins on multisite');
wpdt_assert_same(1, wpdt_plugin_result_value($results, 'Inactive plugins'), 'excludes network-active plugins from inactive count');
wpdt_assert_same('2 available', wpdt_plugin_result_value($results, 'Active plugin updates'), 'counts updates for network-active plugins');

echo 'All plugin diagnostic tests passed.' . PHP_EOL;
