<?php

/**
 * Lightweight CLI tests for the diagnostic manager; no WordPress bootstrap needed.
 *
 * Run from the repository root: php tests/DiagnosticManagerTest.php
 */

require_once __DIR__ . '/../includes/Core/DiagnosticInterface.php';
require_once __DIR__ . '/../includes/Core/DiagnosticManager.php';

use WPDT\Core\DiagnosticInterface;
use WPDT\Core\DiagnosticManager;

$passed = 0;
$failed = 0;

function wpdt_manager_check($condition, $message)
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

function wpdt_test_diagnostic($id, $name, $results)
{
    return new class($id, $name, $results) implements DiagnosticInterface {
        private $id;
        private $name;
        private $results;

        public function __construct($id, $name, $results)
        {
            $this->id = $id;
            $this->name = $name;
            $this->results = $results;
        }

        public function get_id()
        {
            return $this->id;
        }

        public function get_name()
        {
            return $this->name;
        }

        public function run()
        {
            return $this->results;
        }
    };
}

$manager = new DiagnosticManager();

wpdt_manager_check(
    array() === $manager->run_all(),
    'returns an empty collection when no diagnostics are registered'
);

$environment = wpdt_test_diagnostic(
    'environment',
    'WordPress Environment',
    array(array('name' => 'PHP version', 'status' => 'pass'))
);
$plugins = wpdt_test_diagnostic(
    'plugins',
    'WordPress Plugins',
    array(array('name' => 'Installed plugins', 'status' => 'info'))
);

$manager->register($environment);
$manager->register($plugins);

$results = $manager->run_all();

wpdt_manager_check(
    array('environment', 'plugins') === array_keys($results),
    'preserves registration order and keys results by diagnostic ID'
);
wpdt_manager_check(
    'WordPress Environment' === $results['environment']['name'],
    'includes the registered diagnostic name'
);
wpdt_manager_check(
    $environment->run() === $results['environment']['results'],
    'includes the diagnostic result payload'
);

$replacement = wpdt_test_diagnostic(
    'environment',
    'Environment Replacement',
    array(array('name' => 'Replacement check', 'status' => 'pass'))
);

$manager->register($replacement);
$replaced_results = $manager->run_all();

wpdt_manager_check(
    2 === count($replaced_results),
    'replaces a diagnostic with the same ID instead of adding a duplicate'
);
wpdt_manager_check(
    'Environment Replacement' === $replaced_results['environment']['name'],
    'uses the most recently registered diagnostic for a duplicate ID'
);
wpdt_manager_check(
    array('environment', 'plugins') === array_keys($replaced_results),
    'keeps the original position when a diagnostic ID is replaced'
);

echo PHP_EOL . sprintf('%d passed, %d failed.', $passed, $failed) . PHP_EOL;
exit($failed === 0 ? 0 : 1);
