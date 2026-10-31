<?php

/**
 * Lightweight CLI tests for server diagnostics; no WordPress bootstrap needed.
 *
 * Run from the repository root: php tests/ServerDiagnosticTest.php
 */

require_once __DIR__ . '/../includes/Core/DiagnosticInterface.php';
require_once __DIR__ . '/../includes/Diagnostics/ServerDiagnostic.php';

use WPDT\Diagnostics\ServerDiagnostic;

$passed = 0;
$failed = 0;

function wpdt_server_check($condition, $message)
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

$diagnostic = new ServerDiagnostic();

wpdt_server_check(
    $diagnostic instanceof \WPDT\Core\DiagnosticInterface,
    'server diagnostic implements the diagnostic interface'
);
wpdt_server_check(
    'server' === $diagnostic->get_id(),
    'exposes a stable diagnostic identifier'
);
wpdt_server_check(
    'PHP & Server Configuration' === $diagnostic->get_name(),
    'exposes a readable diagnostic name'
);

$results = $diagnostic->run();

wpdt_server_check(
    is_array($results) && ! empty($results),
    'returns a non-empty array of diagnostic results'
);

$allowed_statuses = array('pass', 'info', 'warning', 'critical');
$names = array();

foreach ($results as $index => $result) {
    $has_required_fields = is_array($result)
        && isset($result['name'], $result['value'], $result['status'], $result['description']);

    wpdt_server_check(
        $has_required_fields,
        'result ' . $index . ' contains the required fields'
    );

    if (! $has_required_fields) {
        continue;
    }

    wpdt_server_check(
        in_array($result['status'], $allowed_statuses, true),
        'result ' . $index . ' uses a supported status'
    );

    wpdt_server_check(
        ! in_array($result['name'], $names, true),
        'result name is unique: ' . $result['name']
    );

    $names[] = $result['name'];
}

$expected_settings = array(
    'PHP memory limit',
    'Upload max filesize',
    'POST max size',
    'Maximum execution time',
    'PHP extensions',
);

foreach ($expected_settings as $expected_name) {
    wpdt_server_check(
        in_array($expected_name, $names, true),
        'includes the ' . $expected_name . ' check'
    );
}

$extension_names = array('curl', 'json', 'mbstring', 'mysqli', 'openssl', 'xml', 'zip');

foreach ($extension_names as $extension) {
    $result_name = 'PHP extension: ' . $extension;
    $found = false;

    foreach ($results as $result) {
        if (isset($result['name']) && $result_name === $result['name']) {
            $found = true;
            $expected_value = extension_loaded($extension) ? 'Loaded' : 'Not loaded';

            wpdt_server_check(
                $expected_value === $result['value'],
                'reports the runtime state of ' . $extension
            );
            break;
        }
    }

    wpdt_server_check(
        $found,
        'includes an individual check for ' . $extension
    );
}

echo PHP_EOL . sprintf('%d passed, %d failed.', $passed, $failed) . PHP_EOL;
exit($failed === 0 ? 0 : 1);
