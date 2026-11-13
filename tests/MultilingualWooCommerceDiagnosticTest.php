<?php

/**
 * Lightweight CLI tests for multilingual and WooCommerce diagnostics.
 *
 * Run from the repository root: php tests/MultilingualWooCommerceDiagnosticTest.php
 */

require_once __DIR__ . '/../includes/Core/DiagnosticInterface.php';
require_once __DIR__ . '/../includes/Diagnostics/MultilingualWooCommerceDiagnostic.php';

$GLOBALS['wpdt_multilingual_plugins'] = array();
$GLOBALS['wpdt_multilingual_active_plugins'] = array();

function get_option($name, $default = false)
{
    return 'active_plugins' === $name
        ? $GLOBALS['wpdt_multilingual_active_plugins']
        : $default;
}

function is_multisite()
{
    return false;
}

function get_plugins()
{
    return $GLOBALS['wpdt_multilingual_plugins'];
}

function wpdt_multilingual_check($condition, $message)
{
    if (! $condition) {
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        exit(1);
    }

    echo 'PASS: ' . $message . PHP_EOL;
}

$diagnostic = new \WPDT\Diagnostics\MultilingualWooCommerceDiagnostic();
$results = $diagnostic->run();

$expected_ids = array(
    'multilingual_woocommerce.woocommerce_detected',
    'multilingual_woocommerce.multilingual_plugin_detection',
    'multilingual_woocommerce.cart_session_scope',
    'multilingual_woocommerce.translation_mapping',
);
$actual_ids = array_column($results, 'id');
sort($expected_ids);
sort($actual_ids);

wpdt_multilingual_check(
    $expected_ids === $actual_ids,
    'declares the complete stable multilingual and WooCommerce check ID set'
);
wpdt_multilingual_check(
    'Not installed' === $results[0]['value'],
    'reports WooCommerce as unavailable when it is not installed'
);
wpdt_multilingual_check(
    'None installed' === $results[1]['value'],
    'reports no multilingual integration when none is installed'
);

$GLOBALS['wpdt_multilingual_plugins'] = array(
    'woocommerce/woocommerce.php' => array('Name' => 'WooCommerce'),
    'polylang/polylang.php' => array('Name' => 'Polylang'),
);
$GLOBALS['wpdt_multilingual_active_plugins'] = array(
    'woocommerce/woocommerce.php',
    'polylang/polylang.php',
);

$results = $diagnostic->run();
$actual_ids = array_column($results, 'id');
sort($actual_ids);

wpdt_multilingual_check(
    $expected_ids === $actual_ids,
    'keeps the same stable IDs when integrations are active'
);
wpdt_multilingual_check(
    'pass' === $results[0]['status'],
    'detects active WooCommerce without changing the check identity'
);
wpdt_multilingual_check(
    'pass' === $results[1]['status'],
    'detects an active multilingual integration without changing the check identity'
);

echo 'All multilingual and WooCommerce diagnostic tests passed.' . PHP_EOL;
