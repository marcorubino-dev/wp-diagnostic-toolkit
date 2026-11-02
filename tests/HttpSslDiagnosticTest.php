<?php

/**
 * Lightweight CLI tests for HTTP/SSL diagnostics; WordPress APIs are stubbed.
 *
 * Run from the repository root: php tests/HttpSslDiagnosticTest.php
 */

require_once __DIR__ . '/../includes/Core/DiagnosticInterface.php';
require_once __DIR__ . '/../includes/Diagnostics/HttpSslDiagnostic.php';

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

$GLOBALS['wpdt_http_home_url'] = 'https://example.test';
$GLOBALS['wpdt_http_head_response'] = array('response' => array('code' => 200), 'headers' => array());
$GLOBALS['wpdt_http_get_response'] = array('response' => array('code' => 200), 'headers' => array());
$GLOBALS['wpdt_http_head_calls'] = 0;
$GLOBALS['wpdt_http_get_calls'] = 0;
$GLOBALS['wpdt_http_last_args'] = array();

function get_home_url()
{
    return $GLOBALS['wpdt_http_home_url'];
}

function wp_parse_url($url)
{
    return parse_url($url);
}

function wp_remote_head($url, $args = array())
{
    $GLOBALS['wpdt_http_head_calls']++;
    $GLOBALS['wpdt_http_last_args'] = $args;
    return $GLOBALS['wpdt_http_head_response'];
}

function wp_remote_get($url, $args = array())
{
    $GLOBALS['wpdt_http_get_calls']++;
    $GLOBALS['wpdt_http_last_args'] = $args;
    return $GLOBALS['wpdt_http_get_response'];
}

function is_wp_error($thing)
{
    return $thing instanceof WP_Error;
}

function wp_remote_retrieve_response_code($response)
{
    return isset($response['response']['code']) ? $response['response']['code'] : 0;
}

function wp_remote_retrieve_header($response, $header)
{
    $key = strtolower($header);
    return isset($response['headers'][$key]) ? $response['headers'][$key] : '';
}

$passed = 0;
$failed = 0;

function wpdt_http_check($condition, $message)
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

function wpdt_http_result($results, $name)
{
    foreach ($results as $result) {
        if ($name === $result['name']) {
            return $result;
        }
    }

    return null;
}

$diagnostic = new \WPDT\Diagnostics\HttpSslDiagnostic();

$results = $diagnostic->run();
wpdt_http_check('pass' === wpdt_http_result($results, 'Home URL HTTP response')['status'], 'classifies a successful HTTPS response as pass');
wpdt_http_check('pass' === wpdt_http_result($results, 'SSL certificate validation')['status'], 'reports TLS verification as passed after a successful HTTPS request');
wpdt_http_check(1 === $GLOBALS['wpdt_http_head_calls'], 'uses HEAD for the initial request');
wpdt_http_check(true === $GLOBALS['wpdt_http_last_args']['sslverify'], 'enables certificate verification');
wpdt_http_check(8 === $GLOBALS['wpdt_http_last_args']['timeout'], 'uses the configured request timeout');

$GLOBALS['wpdt_http_head_response'] = array('response' => array('code' => 301), 'headers' => array('location' => 'https://www.example.test/'));
$results = $diagnostic->run();
$http_result = wpdt_http_result($results, 'Home URL HTTP response');
wpdt_http_check('warning' === $http_result['status'], 'classifies an HTTP redirect as warning');
wpdt_http_check(false !== strpos($http_result['evidence'], 'Location: https://www.example.test/'), 'includes redirect location in evidence');

$GLOBALS['wpdt_http_head_response'] = array('response' => array('code' => 404), 'headers' => array());
$results = $diagnostic->run();
wpdt_http_check('warning' === wpdt_http_result($results, 'Home URL HTTP response')['status'], 'classifies an HTTP 404 response as warning');

$GLOBALS['wpdt_http_head_response'] = array('response' => array('code' => 503), 'headers' => array());
$results = $diagnostic->run();
wpdt_http_check('critical' === wpdt_http_result($results, 'Home URL HTTP response')['status'], 'classifies an HTTP 5xx response as critical');

$GLOBALS['wpdt_http_head_response'] = array('response' => array('code' => 0), 'headers' => array());
$results = $diagnostic->run();
wpdt_http_check('warning' === wpdt_http_result($results, 'Home URL HTTP response')['status'], 'does not treat a missing HTTP status as a successful response');
wpdt_http_check('info' === wpdt_http_result($results, 'SSL certificate validation')['status'], 'does not claim TLS validation without a valid HTTP response');

$GLOBALS['wpdt_http_head_response'] = array('response' => array('code' => 103), 'headers' => array());
$results = $diagnostic->run();
wpdt_http_check('warning' === wpdt_http_result($results, 'Home URL HTTP response')['status'], 'does not treat an informational HTTP status as a completed response');

$GLOBALS['wpdt_http_head_response'] = array('response' => array('code' => 405), 'headers' => array());
$GLOBALS['wpdt_http_get_response'] = array('response' => array('code' => 200), 'headers' => array());
$before_get = $GLOBALS['wpdt_http_get_calls'];
$results = $diagnostic->run();
$http_result = wpdt_http_result($results, 'Home URL HTTP response');
wpdt_http_check(1 === $GLOBALS['wpdt_http_get_calls'] - $before_get, 'falls back to GET when HEAD returns 405');
wpdt_http_check('pass' === $http_result['status'], 'classifies a successful GET fallback as pass');
wpdt_http_check(false !== strpos($http_result['evidence'], 'GET fallback used'), 'documents the GET fallback in evidence');

$GLOBALS['wpdt_http_head_response'] = new WP_Error('http_request_failed', 'Connection timed out');
$results = $diagnostic->run();
wpdt_http_check('warning' === wpdt_http_result($results, 'Home URL HTTP response')['status'], 'classifies a generic transport error as warning');
wpdt_http_check('info' === wpdt_http_result($results, 'SSL certificate validation')['status'], 'does not claim certificate failure for a generic connection error');

$GLOBALS['wpdt_http_head_response'] = new WP_Error('http_request_failed', 'SSL certificate verify failed');
$results = $diagnostic->run();
wpdt_http_check('critical' === wpdt_http_result($results, 'SSL certificate validation')['status'], 'classifies a TLS verification error as critical');

$GLOBALS['wpdt_http_home_url'] = 'http://example.test';
$GLOBALS['wpdt_http_head_response'] = array('response' => array('code' => 200), 'headers' => array());
$results = $diagnostic->run();
$ssl_result = wpdt_http_result($results, 'SSL certificate validation');
wpdt_http_check('info' === $ssl_result['status'], 'marks SSL validation not applicable for an HTTP URL');
wpdt_http_check('Not applicable' === $ssl_result['value'], 'does not claim TLS validation for an HTTP URL');

echo PHP_EOL . sprintf('%d passed, %d failed.', $passed, $failed) . PHP_EOL;
exit($failed === 0 ? 0 : 1);
