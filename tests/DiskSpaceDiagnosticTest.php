<?php

/**
 * Lightweight CLI tests for filesystem diagnostics; no WordPress bootstrap needed.
 *
 * Run from the repository root: php tests/DiskSpaceDiagnosticTest.php
 */

require_once __DIR__ . '/../includes/Core/DiagnosticInterface.php';
require_once __DIR__ . '/../includes/Diagnostics/DiskSpaceDiagnostic.php';

use WPDT\Diagnostics\DiskSpaceDiagnostic;

$passed = 0;
$failed = 0;

function wpdt_disk_check($condition, $message)
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

$diagnostic = new DiskSpaceDiagnostic();

wpdt_disk_check(
    $diagnostic instanceof \WPDT\Core\DiagnosticInterface,
    'disk space diagnostic implements the diagnostic interface'
);
wpdt_disk_check(
    'disk_space' === $diagnostic->get_id(),
    'exposes a stable diagnostic identifier'
);
wpdt_disk_check(
    'Disk Space' === $diagnostic->get_name(),
    'exposes a readable diagnostic name'
);

$results = $diagnostic->run();

wpdt_disk_check(
    is_array($results) && 1 === count($results),
    'returns exactly one filesystem capacity result'
);

$result = isset($results[0]) && is_array($results[0])
    ? $results[0]
    : array();

$required_fields = array('name', 'value', 'status', 'description');

foreach ($required_fields as $field) {
    wpdt_disk_check(
        isset($result[$field]),
        'result includes the ' . $field . ' field'
    );
}

if (isset($result['status'])) {
    wpdt_disk_check(
        in_array($result['status'], array('pass', 'info', 'warning', 'critical'), true),
        'uses a supported diagnostic status'
    );
}

if (
    isset($result['evidence'])
    && preg_match('/Free: ([0-9]+(?:\\.[0-9]+)?)%/', $result['evidence'], $matches)
) {
    $free_percent = (float) $matches[1];
    $expected_status = $free_percent < 5
        ? 'critical'
        : ($free_percent < 10 ? 'warning' : 'info');

    wpdt_disk_check(
        $expected_status === $result['status'],
        'classifies filesystem capacity using the documented thresholds'
    );
} else {
    wpdt_disk_check(
        'Not available' === $result['value'],
        'reports unavailable capacity when filesystem metrics cannot be read'
    );
}

echo PHP_EOL . sprintf('%d passed, %d failed.', $passed, $failed) . PHP_EOL;
exit($failed === 0 ? 0 : 1);
