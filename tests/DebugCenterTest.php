<?php

/**
 * CLI regression tests for Debug Center configuration and target inspection.
 *
 * Run from the repository root: php tests/DebugCenterTest.php
 */

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wpdt-debug-' . uniqid();
$content_dir = $root . DIRECTORY_SEPARATOR . 'wp-content';
mkdir($content_dir, 0777, true);

define('ABSPATH', $root . DIRECTORY_SEPARATOR);
define('WP_CONTENT_DIR', $content_dir);
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('WP_ENVIRONMENT_TYPE', 'staging');

function esc_html($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function esc_attr($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

require_once __DIR__ . '/../includes/Debug/DebugConfigurationInspector.php';
require_once __DIR__ . '/../includes/Debug/DebugLogTargetInspector.php';

$passed = 0;
$failed = 0;

function wpdt_debug_check($condition, $message)
{
    global $passed, $failed;

    if ($condition) {
        $passed++;
        echo 'PASS: ' . $message . PHP_EOL;
    } else {
        $failed++;
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
    }
}

$configuration = (new WPDT\Debug\DebugConfigurationInspector())->inspect();

wpdt_debug_check($configuration['wordpress_debug'] === 'enabled', 'detects WP_DEBUG enabled');
wpdt_debug_check($configuration['environment_type'] === 'staging', 'reports an allowlisted environment type');
wpdt_debug_check($configuration['wordpress_debug_log'] === 'configured', 'detects debug logging configured');
wpdt_debug_check($configuration['wordpress_debug_display'] === 'disabled', 'detects debug display disabled');
wpdt_debug_check(! isset($configuration['debug_log_value']), 'does not expose the raw debug-log constant');
wpdt_debug_check(empty($configuration['debug_display_warning']), 'does not warn when debug display is disabled');

$inspector = new WPDT\Debug\DebugLogTargetInspector();

$missing = $inspector->inspect(true, 'enabled', WP_CONTENT_DIR);
wpdt_debug_check($missing['status'] === 'missing', 'reports a missing default log');
wpdt_debug_check($missing['relative_location'] === 'wp-content/debug.log', 'returns a safe relative location');

$log = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'debug.log';
file_put_contents($log, 'SENSITIVE_TEST_CONTENT');

$detected = $inspector->inspect(true, 'enabled', WP_CONTENT_DIR);
wpdt_debug_check($detected['status'] === 'detected_readable', 'detects a regular readable file');
wpdt_debug_check($detected['relative_location'] === 'wp-content/debug.log', 'does not disclose an absolute path');

$configuration = (new WPDT\Debug\DebugConfigurationInspector())->inspect();
$target = $detected;
ob_start();
include __DIR__ . '/../includes/Admin/debug-center.php';
$rendered = ob_get_clean();

wpdt_debug_check(strpos($rendered, $root) === false, 'does not render absolute filesystem paths');
wpdt_debug_check(strpos($rendered, 'SENSITIVE_TEST_CONTENT') === false, 'does not render log contents');
wpdt_debug_check(strpos($rendered, 'wp-content/debug.log') !== false, 'renders the safe relative log location');

$custom_log = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'custom.log';
file_put_contents($custom_log, 'CUSTOM_TEST_CONTENT');

$custom = $inspector->inspect($custom_log, 'enabled', WP_CONTENT_DIR);
wpdt_debug_check($custom['status'] === 'detected_readable', 'supports a custom path inside wp-content');
wpdt_debug_check($custom['relative_location'] === 'wp-content/custom.log', 'returns a relative custom path');

$outside = $root . DIRECTORY_SEPARATOR . 'outside.log';
file_put_contents($outside, 'DO_NOT_READ');

$external = $inspector->inspect($outside, 'enabled', WP_CONTENT_DIR);
wpdt_debug_check($external['status'] === 'unsupported_external_path', 'rejects custom paths outside wp-content');

$disabled = $inspector->inspect(true, 'disabled', WP_CONTENT_DIR);
wpdt_debug_check($disabled['status'] === 'not_configured', 'does not inspect a target when WP_DEBUG is disabled');

$unknown = $inspector->inspect(true, 'unknown', WP_CONTENT_DIR);
wpdt_debug_check($unknown['status'] === 'unknown', 'preserves unknown configuration');

$sibling = $content_dir . '-sibling';
mkdir($sibling);

$sibling_target = $inspector->inspect(
    $sibling . DIRECTORY_SEPARATOR . 'outside.log',
    'enabled',
    WP_CONTENT_DIR
);
wpdt_debug_check($sibling_target['status'] === 'unsupported_external_path', 'rejects sibling-directory prefix collisions');

$directory = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'logs';
mkdir($directory);
$directory_target = $inspector->inspect($directory, 'enabled', WP_CONTENT_DIR);
wpdt_debug_check($directory_target['status'] === 'unsupported_file_type', 'rejects directories as log files');
rmdir($directory);

$symlink = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'linked.log';

if (function_exists('symlink') && @symlink($outside, $symlink)) {
    $linked = $inspector->inspect($symlink, 'enabled', WP_CONTENT_DIR);
    wpdt_debug_check($linked['status'] === 'unsupported_file_type', 'rejects symlinks without following their targets');
} else {
    echo 'SKIP: symlink creation is unavailable in this environment.' . PHP_EOL;
}

$dangling_symlink = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'dangling.log';

if (function_exists('symlink') && @symlink($root . DIRECTORY_SEPARATOR . 'missing-target', $dangling_symlink)) {
    $dangling = $inspector->inspect($dangling_symlink, 'enabled', WP_CONTENT_DIR);
    wpdt_debug_check($dangling['status'] === 'unsupported_file_type', 'rejects dangling symlinks');
} else {
    echo 'SKIP: dangling symlink creation is unavailable in this environment.' . PHP_EOL;
}

$source = file_get_contents(__DIR__ . '/../includes/Debug/DebugLogTargetInspector.php');

wpdt_debug_check(
    strpos($source, 'file_get_contents(') === false
        && strpos($source, 'fopen(') === false
        && strpos($source, 'readfile(') === false
        && strpos($source, 'SplFileObject') === false,
    'target inspector contains no file-content reading API'
);

@unlink($symlink);
@unlink($dangling_symlink);
@unlink($log);
@unlink($custom_log);
@unlink($outside);
@rmdir($sibling);
@rmdir($content_dir);
@rmdir($root);

echo PHP_EOL . sprintf('%d passed, %d failed.', $passed, $failed) . PHP_EOL;
exit($failed === 0 ? 0 : 1);
