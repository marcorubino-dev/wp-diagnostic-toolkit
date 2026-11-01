<?php

/**
 * Lightweight CLI tests for environment diagnostics; WordPress APIs are stubbed.
 *
 * Run from the repository root: php tests/EnvironmentDiagnosticTest.php
 */

require_once __DIR__ . '/../includes/Core/DiagnosticInterface.php';
require_once __DIR__ . '/../includes/Diagnostics/EnvironmentDiagnostic.php';

$GLOBALS['wpdb'] = new class {
    public function db_version()
    {
        return '8.0.36';
    }
};

$GLOBALS['wpdt_environment_home_url'] = 'https://example.test';
$GLOBALS['wpdt_environment_site_url'] = 'https://example.test/wp';
$GLOBALS['wpdt_environment_response'] = array('response' => array('code' => 200));

if (! defined('WP_DEBUG')) {
    define('WP_DEBUG', false);
}

function get_home_url()
{
    return $GLOBALS['wpdt_environment_home_url'];
}

function home_url($path = '')
{
    return rtrim($GLOBALS['wpdt_environment_home_url'], '/') . '/' . ltrim($path, '/');
}

function get_site_url()
{
    return $GLOBALS['wpdt_environment_site_url'];
}

function get_bloginfo($show)
{
    return '6.8.2';
}

function wp_parse_url($url, $component = -1)
{
    return parse_url($url, $component);
}

function wp_remote_get($url, $args = array())
{
    $GLOBALS['wpdt_environment_last_request'] = array('url' => $url, 'args' => $args);
    return $GLOBALS['wpdt_environment_response'];
}

function is_wp_error($thing)
{
    return $thing instanceof WP_Error;
}

function wp_remote_retrieve_response_code($response)
{
    return isset($response['response']['code']) ? $response['response']['code'] : 0;
}

class WP_Error
{
    private $message;

    public function __construct($code, $message)
    {
        $this->message = $message;
    }

    public function get_error_message()
    {
        return $this->message;
    }
}

$passed = 0;
$failed = 0;

function wpdt_environment_check($condition, $message)
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

function wpdt_environment_result($results, $name)
{
    foreach ($results as $result) {
        if ($name === $result['name']) {
            return $result;
        }
    }

    return null;
}

$diagnostic = new \WPDT\Diagnostics\EnvironmentDiagnostic();

$GLOBALS['wpdt_environment_response'] = array('response' => array('code' => 200));
$results = $diagnostic->run();

wpdt_environment_check('6.8.2' === wpdt_environment_result($results, 'WordPress version')['value'], 'reports the WordPress version');
wpdt_environment_check('8.0.36' === wpdt_environment_result($results, 'Database version')['value'], 'reports the database version');
wpdt_environment_check('pass' === wpdt_environment_result($results, 'Loopback request')['status'], 'classifies a successful loopback response as pass');
wpdt_environment_check('pass' === wpdt_environment_result($results, 'Home URL HTTPS')['status'], 'detects an HTTPS home URL');
wpdt_environment_check('pass' === wpdt_environment_result($results, 'Site URL HTTPS')['status'], 'detects an HTTPS site URL');
wpdt_environment_check(5 === $GLOBALS['wpdt_environment_last_request']['args']['timeout'], 'uses the configured loopback timeout');

$GLOBALS['wpdt_environment_response'] = array('response' => array('code' => 302));
$results = $diagnostic->run();
wpdt_environment_check('warning' === wpdt_environment_result($results, 'Loopback request')['status'], 'classifies a loopback redirect as warning');

$GLOBALS['wpdt_environment_response'] = array('response' => array('code' => 404));
$results = $diagnostic->run();
wpdt_environment_check('warning' === wpdt_environment_result($results, 'Loopback request')['status'], 'classifies a loopback 4xx response as warning');

$GLOBALS['wpdt_environment_response'] = array('response' => array('code' => 503));
$results = $diagnostic->run();
wpdt_environment_check('critical' === wpdt_environment_result($results, 'Loopback request')['status'], 'classifies a loopback 5xx response as critical');

$GLOBALS['wpdt_environment_response'] = new WP_Error('http_request_failed', 'Connection timed out');
$results = $diagnostic->run();
$loopback = wpdt_environment_result($results, 'Loopback request');
wpdt_environment_check('warning' === $loopback['status'], 'classifies a failed loopback request as warning');
wpdt_environment_check('Connection timed out' === $loopback['evidence'], 'preserves the transport error as evidence');

$GLOBALS['wpdt_environment_home_url'] = 'http://example.test';
$GLOBALS['wpdt_environment_site_url'] = 'http://example.test/wp';
$GLOBALS['wpdt_environment_response'] = array('response' => array('code' => 200));
$results = $diagnostic->run();
wpdt_environment_check('warning' === wpdt_environment_result($results, 'Home URL HTTPS')['status'], 'warns when the home URL does not use HTTPS');
wpdt_environment_check('warning' === wpdt_environment_result($results, 'Site URL HTTPS')['status'], 'warns when the site URL does not use HTTPS');

echo PHP_EOL . sprintf('%d passed, %d failed.', $passed, $failed) . PHP_EOL;
exit($failed === 0 ? 0 : 1);
