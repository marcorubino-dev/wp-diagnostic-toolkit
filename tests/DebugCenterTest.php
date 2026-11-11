<?php
/**
 * CLI regression tests for Debug Center target inspection.
 * Run: php tests/DebugCenterTest.php
 */
$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wpdt-debug-' . uniqid();
$content = $root . DIRECTORY_SEPARATOR . 'wp-content';
mkdir($content, 0777, true);
define('WP_CONTENT_DIR', $content);
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('WP_ENVIRONMENT_TYPE', 'staging');
require_once __DIR__ . '/../includes/Debug/DebugConfigurationInspector.php';
require_once __DIR__ . '/../includes/Debug/DebugLogTargetInspector.php';
$passed=0; $failed=0;
function wpdt_debug_check($ok,$label){global $passed,$failed;if($ok){$passed++;echo "PASS: $label\n";}else{$failed++;fwrite(STDERR,"FAIL: $label\n");}}
$config=(new WPDT\Debug\DebugConfigurationInspector())->inspect();
wpdt_debug_check($config['wordpress_debug']==='enabled','detects WP_DEBUG enabled');
wpdt_debug_check($config['environment_type']==='staging','reports an allowlisted WordPress environment type');
wpdt_debug_check($config['wordpress_debug_log']==='configured','detects debug logging configured');
wpdt_debug_check($config['wordpress_debug_display']==='disabled','detects debug display disabled');
wpdt_debug_check(!isset($config['debug_log_value']),'does not expose raw debug-log constant');
wpdt_debug_check(empty($config['debug_display_warning']),'does not warn when debug display is disabled');
$inspector=new WPDT\Debug\DebugLogTargetInspector();
$missing=$inspector->inspect(true,'enabled',WP_CONTENT_DIR);
wpdt_debug_check($missing['status']==='missing','reports missing default log');
wpdt_debug_check($missing['relative_location']==='wp-content/debug.log','returns safe relative location');
$log=WP_CONTENT_DIR.DIRECTORY_SEPARATOR.'debug.log'; file_put_contents($log,'SENSITIVE_TEST_CONTENT');
$found=$inspector->inspect(true,'enabled',WP_CONTENT_DIR);
wpdt_debug_check($found['status']==='detected_readable','detects regular readable file');
wpdt_debug_check($found['relative_location']==='wp-content/debug.log','does not disclose absolute path');
$outside=$root.DIRECTORY_SEPARATOR.'outside.log'; file_put_contents($outside,'DO_NOT_READ');
wpdt_debug_check($inspector->inspect($outside,'enabled',WP_CONTENT_DIR)['status']==='unsupported_external_path','rejects external custom path');
wpdt_debug_check($inspector->inspect(true,'disabled',WP_CONTENT_DIR)['status']==='not_configured','does not inspect when WP_DEBUG disabled');
wpdt_debug_check($inspector->inspect(true,'unknown',WP_CONTENT_DIR)['status']==='unknown','preserves unknown configuration');
$sibling=$content.'-sibling'; mkdir($sibling);\nwpdt_debug_check($inspector->inspect($sibling.DIRECTORY_SEPARATOR.'outside.log','enabled',WP_CONTENT_DIR)['status']==='unsupported_external_path','rejects sibling-directory prefix collisions');\n$link=WP_CONTENT_DIR.DIRECTORY_SEPARATOR.'linked.log';
if(function_exists('symlink') && @symlink($outside,$link)){wpdt_debug_check($inspector->inspect($link,'enabled',WP_CONTENT_DIR)['status']==='unsupported_external_path','rejects symlink escape');}else{echo "SKIP: symlink creation unavailable\n";}
$source=file_get_contents(__DIR__.'/../includes/Debug/DebugLogTargetInspector.php');
wpdt_debug_check(strpos($source,'file_get_contents(')===false && strpos($source,'fopen(')===false && strpos($source,'readfile(')===false && strpos($source,'SplFileObject')===false,'target inspector has no file-content reader');
@unlink($link); @unlink($log); @unlink($outside); @rmdir($sibling); @rmdir($content); @rmdir($root);
echo "\n$passed passed, $failed failed.\n"; exit($failed===0?0:1);
