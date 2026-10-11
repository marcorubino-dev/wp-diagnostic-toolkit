<?php

/**
 * Lightweight CLI tests for RuleResult; no PHPUnit or WordPress bootstrap needed.
 *
 * Run from the repository root: php tests/RuleResultTest.php
 */

require_once __DIR__ . '/../includes/Core/RuleResult.php';

use WPDT\Core\RuleResult;

$passed = 0;
$failed = 0;

function check_result($condition, $message)
{
    global $passed, $failed;

    if ($condition) {
        $passed++;
        echo "PASS: " . $message . PHP_EOL;
    } else {
        $failed++;
        echo "FAIL: " . $message . PHP_EOL;
    }
}

function valid_result_data($overrides = array())
{
    return array_merge(
        array(
            'id'             => 'environment.php_version',
            'title'          => 'PHP version',
            'outcome'        => RuleResult::OUTCOME_PASS,
            'severity'       => RuleResult::SEVERITY_NONE,
            'description'    => 'The PHP version meets the configured requirement.',
            'value'          => '8.2.29',
            'recommendation' => null,
            'evidence'       => array('source' => 'PHP_VERSION'),
            'module'         => 'environment',
        ),
        $overrides
    );
}

function expect_invalid_result($data, $message)
{
    try {
        new RuleResult($data);
        check_result(false, $message);
    } catch (InvalidArgumentException $exception) {
        check_result(true, $message);
    }
}

$result = new RuleResult(valid_result_data());
check_result($result->get('outcome') === 'pass', 'accepts a valid passing result');
check_result($result->get('severity') === 'none', 'passing result has no severity');
check_result($result->to_array()['id'] === 'environment.php_version', 'exports the normalized fields');
check_result($result->get('evidence')['source'] === 'PHP_VERSION', 'preserves structured evidence');

$finding = new RuleResult(valid_result_data(array(
    'outcome' => RuleResult::OUTCOME_FINDING,
    'severity' => RuleResult::SEVERITY_WARNING,
)));
check_result($finding->get('outcome') === 'finding', 'accepts a finding with a severity');

expect_invalid_result(
    valid_result_data(array('outcome' => 'healthy-ish')),
    'rejects an unknown outcome'
);
expect_invalid_result(
    valid_result_data(array('severity' => 'urgent')),
    'rejects an unknown severity'
);
expect_invalid_result(
    valid_result_data(array('outcome' => 'pass', 'severity' => 'warning')),
    'rejects a passing result with warning severity'
);
expect_invalid_result(
    valid_result_data(array('outcome' => 'finding', 'severity' => 'none')),
    'rejects a finding with no severity'
);
expect_invalid_result(
    valid_result_data(array('outcome' => 'error', 'severity' => 'critical')),
    'rejects an error marked as a confirmed critical finding'
);
expect_invalid_result(
    valid_result_data(array('id' => 'PHP Version')),
    'rejects an unstable identifier format'
);
expect_invalid_result(
    valid_result_data(array('value' => array('unexpected'))),
    'rejects a non-scalar value'
);

try {
    $result->get('missing');
    check_result(false, 'rejects access to an unknown field');
} catch (InvalidArgumentException $exception) {
    check_result(true, 'rejects access to an unknown field');
}

echo PHP_EOL . sprintf('%d passed, %d failed.', $passed, $failed) . PHP_EOL;
exit($failed === 0 ? 0 : 1);
