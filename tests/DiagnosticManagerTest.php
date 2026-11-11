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

function wpdt_test_diagnostic($id, $name, $results, $on_run = null)
{
    return new class($id, $name, $results, $on_run) implements DiagnosticInterface {
        private $id;
        private $name;
        private $results;
        private $on_run;

        public function __construct($id, $name, $results, $on_run)
        {
            $this->id = $id;
            $this->name = $name;
            $this->results = $results;
            $this->on_run = $on_run;
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
            if (is_callable($this->on_run)) {
                call_user_func($this->on_run);
            }

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
    'rejects a result ID whose namespace belongs to a different module'
);
wpdt_manager_check(
    $plugin_results[1]['name'] === 'Installed plugins'
        && $plugin_results[1]['status'] === 'info',
    'continues processing the module after rejecting a foreign namespace'
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

$mismatched_native_result = new RuleResult(array(
    'id' => 'environment.php_version',
    'title' => 'PHP version',
    'outcome' => RuleResult::OUTCOME_PASS,
    'severity' => RuleResult::SEVERITY_NONE,
    'description' => 'A native result registered under the wrong module.',
    'module' => 'environment',
));
$mismatched_native_manager = new DiagnosticManager();
$mismatched_native_manager->register(wpdt_test_diagnostic(
    'plugins',
    'Plugins',
    array($mismatched_native_result)
));
$mismatched_native_output = $mismatched_native_manager->run_all();

wpdt_manager_check(
    RuleResult::OUTCOME_ERROR === $mismatched_native_output['plugins']['results'][0]['outcome'],
    'rejects a native RuleResult whose module differs from its registered diagnostic'
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


$collision_manager = new DiagnosticManager();
$collision_manager->register(wpdt_test_diagnostic(
    'environment',
    'Environment',
    array(
        'malformed result',
        array(
            'id' => 'environment.error_invalid_result_0',
            'name' => 'Valid result after malformed output',
            'status' => 'pass',
        ),
    )
));
$collision_output = $collision_manager->run_all();
$environment_collision_results = $collision_output['environment']['results'];
$synthetic_id = $environment_collision_results[0]['id'];
$valid_later_result = $environment_collision_results[1];

wpdt_manager_check(
    $synthetic_id === 'environment.error_invalid_result_0_2',
    'avoids an explicit source ID reserved by a later result in the same module'
);
wpdt_manager_check(
    $valid_later_result['id'] === 'environment.error_invalid_result_0'
        && $valid_later_result['name'] === 'Valid result after malformed output'
        && $valid_later_result['status'] === 'pass',
    'preserves a valid source result when its ID matches a potential synthetic error ID'
);

$generated_collision_manager = new DiagnosticManager();
$generated_collision_manager->register(wpdt_test_diagnostic(
    'environment',
    'Environment',
    array(
        'malformed result',
        array(
            'name' => 'error invalid result 0',
            'status' => 'pass',
        ),
    )
));
$generated_collision_output = $generated_collision_manager->run_all();
$generated_environment_results = $generated_collision_output['environment']['results'];
$generated_synthetic_id = $generated_environment_results[0]['id'];
$generated_valid_result = $generated_environment_results[1];

wpdt_manager_check(
    $generated_synthetic_id === 'environment.error_invalid_result_0_2',
    'avoids an adapter-generated ID reserved by a later result in the same module'
);
wpdt_manager_check(
    $generated_valid_result['id'] === 'environment.error_invalid_result_0'
        && $generated_valid_result['name'] === 'error invalid result 0'
        && $generated_valid_result['status'] === 'pass',
    'preserves a valid adapter-generated ID that collides with a potential synthetic error ID'
);

$invalid_module_manager = new DiagnosticManager();
$invalid_module_id_thrown = false;

try {
    $invalid_module_manager->register(wpdt_test_diagnostic(
        'invalid.module',
        'Invalid module ID',
        array()
    ));
} catch (\InvalidArgumentException $exception) {
    $invalid_module_id_thrown = true;
}

wpdt_manager_check(
    $invalid_module_id_thrown,
    'rejects module IDs containing the reserved namespace separator'
);

$registration_manager = new DiagnosticManager();
$numeric_module_manager = new DiagnosticManager();
$numeric_module_id_thrown = false;

try {
    $numeric_module_manager->register(wpdt_test_diagnostic(
        '123',
        'Numeric module ID',
        array()
    ));
} catch (\InvalidArgumentException $exception) {
    $numeric_module_id_thrown = true;
}

wpdt_manager_check(
    $numeric_module_id_thrown,
    'rejects numeric-only module IDs that PHP would coerce to integer array keys'
);



$registration_manager->register(wpdt_test_diagnostic(
    'duplicate_module',
    'Original module',
    array(
        array(
            'name' => 'Original result',
            'status' => 'pass',
        ),
    )
));

$duplicate_registration_thrown = false;

try {
    $registration_manager->register(wpdt_test_diagnostic(
        'duplicate_module',
        'Replacement module',
        array(
            array(
                'name' => 'Replacement result',
                'status' => 'warning',
            ),
        )
    ));
} catch (\InvalidArgumentException $exception) {
    $duplicate_registration_thrown = true;
}

$registration_output = $registration_manager->run_all();
$registered_module = $registration_output['duplicate_module'];

wpdt_manager_check(
    $duplicate_registration_thrown,
    'rejects registration of a module with an already registered ID'
);

wpdt_manager_check(
    $registered_module['name'] === 'Original module'
        && $registered_module['results'][0]['name'] === 'Original result',
    'preserves the original module when duplicate registration is rejected'
);

$selective_counts = array(
    'environment' => 0,
    'plugins' => 0,
);

$selective_manager = new DiagnosticManager();
$selective_manager->register(wpdt_test_diagnostic(
    'environment',
    'Environment',
    array(array('name' => 'Environment check', 'status' => 'pass')),
    function () use (&$selective_counts) {
        $selective_counts['environment']++;
    }
));
$selective_manager->register(wpdt_test_diagnostic(
    'plugins',
    'Plugins',
    array(array('name' => 'Plugin check', 'status' => 'pass')),
    function () use (&$selective_counts) {
        $selective_counts['plugins']++;
    }
));

$selected_plugins = $selective_manager->run_selected(array('plugins'));

wpdt_manager_check(
    array('plugins') === array_keys($selected_plugins),
    'returns only the requested registered module'
);
wpdt_manager_check(
    $selective_counts['plugins'] === 1 && $selective_counts['environment'] === 0,
    'does not execute unrequested modules'
);

$selected_in_registration_order = $selective_manager->run_selected(array('plugins', 'environment'));

wpdt_manager_check(
    array('environment', 'plugins') === array_keys($selected_in_registration_order),
    'returns selected modules in registration order rather than request order'
);

wpdt_manager_check(
    $selective_counts['plugins'] === 2 && $selective_counts['environment'] === 1,
    'executes each requested module exactly once'
);

wpdt_manager_check(
    array() === $selective_manager->run_selected(array()),
    'returns an empty collection for an empty selection without executing modules'
);

$counts_before_invalid_selection = $selective_counts;
$unknown_id_thrown = false;

try {
    $selective_manager->run_selected(array('missing_module'));
} catch (\InvalidArgumentException $exception) {
    $unknown_id_thrown = true;
}

wpdt_manager_check(
    $unknown_id_thrown && $selective_counts === $counts_before_invalid_selection,
    'rejects unknown IDs before executing any selected module'
);

$duplicate_selection_thrown = false;

try {
    $selective_manager->run_selected(array('environment', 'environment'));
} catch (\InvalidArgumentException $exception) {
    $duplicate_selection_thrown = true;
}

wpdt_manager_check(
    $duplicate_selection_thrown && $selective_counts === $counts_before_invalid_selection,
    'rejects duplicate requested IDs before executing any module'
);

$invalid_selection_thrown = false;

try {
    $selective_manager->run_selected(array(123));
} catch (\InvalidArgumentException $exception) {
    $invalid_selection_thrown = true;
}

wpdt_manager_check(
    $invalid_selection_thrown && $selective_counts === $counts_before_invalid_selection,
    'rejects malformed module IDs before executing any module'
);

$selective_failure_manager = new DiagnosticManager();
$selective_failure_manager->register(wpdt_test_diagnostic(
    'broken_selected',
    'Broken selected module',
    new RuntimeException('Selected module failure.')
));
$selective_failure_manager->register(wpdt_test_diagnostic(
    'healthy_selected',
    'Healthy selected module',
    array(array('name' => 'Healthy check', 'status' => 'pass'))
));
$selective_failure_output = $selective_failure_manager->run_selected(
    array('broken_selected', 'healthy_selected')
);

wpdt_manager_check(
    $selective_failure_output['broken_selected']['results'][0]['outcome'] === RuleResult::OUTCOME_ERROR
        && $selective_failure_output['healthy_selected']['results'][0]['name'] === 'Healthy check',
    'isolates a selected module exception and continues with other selected modules'
);

$selective_scope_manager = new DiagnosticManager();
$selective_scope_manager->register(wpdt_test_diagnostic(
    'environment',
    'Environment',
    array('malformed result')
));
$unselected_runs = 0;
$selective_scope_manager->register(wpdt_test_diagnostic(
    'other_module',
    'Other module',
    array(array(
        'id' => 'other_module.unselected_source_id',
        'name' => 'Unselected source ID',
        'status' => 'pass',
    )),
    function () use (&$unselected_runs) {
        $unselected_runs++;
    }
));
$selective_scope_output = $selective_scope_manager->run_selected(array('environment'));

wpdt_manager_check(
    $selective_scope_output['environment']['results'][0]['id'] === 'environment.error_invalid_result_0'
        && $unselected_runs === 0,
    'uses a valid module-scoped synthetic error ID without running unselected modules'
);

echo PHP_EOL . sprintf('%d passed, %d failed.', $passed, $failed) . PHP_EOL;
exit($failed === 0 ? 0 : 1);
