<?php

/**
 * Lightweight CLI tests for normalized diagnostic manager output.
 *
 * Run from the repository root: php tests/DiagnosticManagerTest.php
 */

require_once __DIR__ . '/../includes/Core/DiagnosticInterface.php';
require_once __DIR__ . '/../includes/Core/RuleResult.php';
require_once __DIR__ . '/../includes/Core/LegacyResultAdapter.php';
require_once __DIR__ . '/../includes/Core/DiagnosticResultPresenter.php';
require_once __DIR__ . '/../includes/Core/DiagnosticManager.php';

use WPDT\Core\DiagnosticInterface;
use WPDT\Core\DiagnosticManager;
use WPDT\Core\RuleResult;
use WPDT\Core\DiagnosticResultPresenter;

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
            if ($this->results instanceof Exception) {
                throw $this->results;
            }

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
    array(
        array(
            'id' => 'environment.php_version',
            'name' => 'PHP version',
            'status' => 'pass',
            'value' => '8.2.29',
            'description' => 'PHP version meets the minimum requirement.',
        ),
        'malformed result',
        array(
            'id' => 'environment.php_version',
            'name' => 'Duplicate PHP version',
            'status' => 'warning',
        ),
        array(
            'name' => 'Loopback request',
            'status' => 'warning',
            'description' => 'The loopback request returned a warning.',
        ),
    )
);

$plugins = wpdt_test_diagnostic(
    'plugins',
    'WordPress Plugins',
    array(
        array(
            'id' => 'environment.php_version',
            'name' => 'Cross-module duplicate',
            'status' => 'info',
        ),
        array(
            'name' => 'Installed plugins',
            'status' => 'info',
        ),
    )
);

$broken = wpdt_test_diagnostic(
    'broken_module',
    'Broken Module',
    new RuntimeException('Simulated diagnostic failure.')
);

$manager->register($environment);
$manager->register($plugins);
$manager->register($broken);

$results = $manager->run_all();

wpdt_manager_check(
    array('environment', 'plugins', 'broken_module') === array_keys($results),
    'preserves module registration order'
);

$environment_results = $results['environment']['results'];

wpdt_manager_check(
    count($environment_results) === 4,
    'retains every source result position, including isolated errors'
);
wpdt_manager_check(
    $environment_results[0]['id'] === 'environment.php_version',
    'preserves the first valid result ID'
);
wpdt_manager_check(
    $environment_results[0]['name'] === 'PHP version'
        && $environment_results[0]['status'] === 'pass',
    'presents normalized results with legacy admin field names'
);
wpdt_manager_check(
    $environment_results[1]['outcome'] === RuleResult::OUTCOME_ERROR
        && $environment_results[1]['status'] === 'warning',
    'isolates a malformed result as an explicit error'
);
wpdt_manager_check(
    $environment_results[2]['outcome'] === RuleResult::OUTCOME_ERROR
        && $environment_results[2]['title'] === 'Duplicate diagnostic result ID',
    'reports a duplicate ID as an integrity error'
);
wpdt_manager_check(
    $environment_results[0]['value'] === '8.2.29',
    'does not overwrite the first result when a duplicate ID is encountered'
);
wpdt_manager_check(
    $environment_results[3]['name'] === 'Loopback request'
        && $environment_results[3]['status'] === 'warning',
    'continues processing valid siblings after malformed and duplicate results'
);

$plugin_results = $results['plugins']['results'];

wpdt_manager_check(
    $plugin_results[0]['outcome'] === RuleResult::OUTCOME_ERROR,
    'detects duplicate result IDs across diagnostic modules'
);
wpdt_manager_check(
    $plugin_results[1]['name'] === 'Installed plugins'
        && $plugin_results[1]['status'] === 'info',
    'continues processing the module after a cross-module duplicate'
);
wpdt_manager_check(
    $results['broken_module']['results'][0]['outcome'] === RuleResult::OUTCOME_ERROR,
    'converts a module exception into an explicit error result'
);
wpdt_manager_check(
    $results['broken_module']['results'][0]['evidence'] === 'RuntimeException: Simulated diagnostic failure.',
    'retains exception class and message as diagnostic evidence'
);

$native_result = new RuleResult(array(
    'id' => 'native.check',
    'title' => 'Native result',
    'outcome' => RuleResult::OUTCOME_FINDING,
    'severity' => RuleResult::SEVERITY_CRITICAL,
    'confidence' => RuleResult::CONFIDENCE_DIRECT,
    'description' => 'A native normalized result.',
    'module' => 'native',
    'evidence' => array('source' => 'unit-test', 'count' => 1),
));

$native_manager = new DiagnosticManager();
$native_manager->register(wpdt_test_diagnostic('native', 'Native module', array($native_result)));
$native_output = $native_manager->run_all();

wpdt_manager_check(
    $native_output['native']['results'][0]['id'] === 'native.check'
        && $native_output['native']['results'][0]['status'] === 'critical',
    'accepts native RuleResult instances and presents their severity'
);
wpdt_manager_check(
    $native_output['native']['results'][0]['evidence'] === '{"source":"unit-test","count":1}'
        && $native_output['native']['results'][0]['evidence_data']['count'] === 1,
    'serializes structured evidence for legacy templates while retaining the original data'
);

$presenter = new DiagnosticResultPresenter();
$skipped_result = new RuleResult(array(
    'id' => 'native.optional_check',
    'title' => 'Optional check',
    'outcome' => RuleResult::OUTCOME_SKIPPED,
    'severity' => RuleResult::SEVERITY_NONE,
    'description' => 'The check was not applicable.',
    'module' => 'native',
));
wpdt_manager_check(
    $presenter->present($skipped_result)['status'] === 'info',
    'presents skipped checks as informational without changing their normalized outcome'
);

echo PHP_EOL . sprintf('%d passed, %d failed.', $passed, $failed) . PHP_EOL;
exit($failed === 0 ? 0 : 1);
