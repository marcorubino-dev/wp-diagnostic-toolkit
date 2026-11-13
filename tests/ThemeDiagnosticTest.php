<?php

/**
 * Lightweight CLI tests for theme diagnostics; WordPress theme APIs are stubbed.
 *
 * Run from the repository root: php tests/ThemeDiagnosticTest.php
 */

require_once __DIR__ . '/../includes/Core/DiagnosticInterface.php';
require_once __DIR__ . '/../includes/Diagnostics/ThemeDiagnostic.php';

class WP_Theme
{
    private $stylesheet;
    private $template;
    private $data;
    private $exists;

    public function __construct($stylesheet, $template, array $data, $exists = true)
    {
        $this->stylesheet = $stylesheet;
        $this->template = $template;
        $this->data = $data;
        $this->exists = $exists;
    }

    public function get_stylesheet()
    {
        return $this->stylesheet;
    }

    public function get_template()
    {
        return $this->template;
    }

    public function get($field)
    {
        return isset($this->data[$field]) ? $this->data[$field] : '';
    }

    public function exists()
    {
        return $this->exists;
    }
}

$GLOBALS['wpdt_theme_active'] = null;
$GLOBALS['wpdt_theme_parent'] = null;
$GLOBALS['wpdt_theme_installed'] = array();
$GLOBALS['wpdt_theme_updates'] = false;

function wp_get_themes()
{
    return $GLOBALS['wpdt_theme_installed'];
}

function wp_get_theme($stylesheet = '')
{
    if ('' === $stylesheet) {
        return $GLOBALS['wpdt_theme_active'];
    }

    return $GLOBALS['wpdt_theme_parent'];
}

function get_site_transient($name)
{
    return $GLOBALS['wpdt_theme_updates'];
}

$passed = 0;
$failed = 0;

function wpdt_theme_check($condition, $message)
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

function wpdt_theme_assert_stable_ids($results)
{
    $expected_ids = array(
        'themes.installed_themes',
        'themes.active_theme',
        'themes.active_theme_version',
        'themes.child_theme_parent',
        'themes.active_theme_updates',
    );
    $actual_ids = array_column($results, 'id');
    sort($expected_ids);
    sort($actual_ids);
    wpdt_theme_check($expected_ids === $actual_ids, 'declares the complete stable theme check ID set');
}

function wpdt_theme_result($results, $name)
{
    foreach ($results as $result) {
        if ($name === $result['name']) {
            return $result;
        }
    }

    return null;
}

$GLOBALS['wpdt_theme_installed'] = array('parent' => new stdClass(), 'child' => new stdClass());
$GLOBALS['wpdt_theme_active'] = new WP_Theme(
    'child',
    'parent',
    array('Name' => 'Example Child', 'Version' => '1.2.0')
);
$GLOBALS['wpdt_theme_parent'] = new WP_Theme('parent', 'parent', array('Name' => 'Example Parent'));
$GLOBALS['wpdt_theme_updates'] = (object) array('response' => array());

$diagnostic = new \WPDT\Diagnostics\ThemeDiagnostic();
$results = $diagnostic->run();
wpdt_theme_assert_stable_ids($results);

wpdt_theme_check(2 === wpdt_theme_result($results, 'Installed themes')['value'], 'counts installed themes');
wpdt_theme_check('Example Child' === wpdt_theme_result($results, 'Active theme')['value'], 'reports the active theme name');
wpdt_theme_check('1.2.0' === wpdt_theme_result($results, 'Active theme version')['value'], 'reports the active theme version');
wpdt_theme_check('pass' === wpdt_theme_result($results, 'Child theme parent')['status'], 'recognizes an installed child theme parent');
wpdt_theme_check('pass' === wpdt_theme_result($results, 'Active theme updates')['status'], 'reports no available update when the active theme is absent from update response');

$GLOBALS['wpdt_theme_parent'] = new WP_Theme('parent', 'parent', array(), false);
$results = $diagnostic->run();
$parent_result = wpdt_theme_result($results, 'Child theme parent');
wpdt_theme_check('warning' === $parent_result['status'], 'warns when the child theme parent is missing');
wpdt_theme_check(false !== strpos($parent_result['value'], 'missing'), 'identifies the missing parent stylesheet');

$GLOBALS['wpdt_theme_active'] = new WP_Theme(
    'standalone',
    'standalone',
    array('Name' => 'Standalone Theme', 'Version' => '')
);
$GLOBALS['wpdt_theme_updates'] = false;
$results = $diagnostic->run();
wpdt_theme_check('Not declared' === wpdt_theme_result($results, 'Active theme version')['value'], 'handles an undeclared theme version');
wpdt_theme_check('info' === wpdt_theme_result($results, 'Child theme parent')['status'], 'marks parent checks not applicable for a standalone theme');
wpdt_theme_check('info' === wpdt_theme_result($results, 'Active theme updates')['status'], 'reports update information as unavailable when the transient is missing');

$GLOBALS['wpdt_theme_updates'] = (object) array('response' => array(
    'standalone' => (object) array('new_version' => '2.0.0'),
));
$results = $diagnostic->run();
$update_result = wpdt_theme_result($results, 'Active theme updates');
wpdt_theme_check('warning' === $update_result['status'], 'warns when an active theme update is available');
wpdt_theme_check('2.0.0' === $update_result['value'], 'reports the available theme version');

$GLOBALS['wpdt_theme_updates'] = (object) array('response' => 'invalid');
$results = $diagnostic->run();
wpdt_theme_check('info' === wpdt_theme_result($results, 'Active theme updates')['status'], 'handles malformed theme update response data safely');

echo PHP_EOL . sprintf('%d passed, %d failed.', $passed, $failed) . PHP_EOL;
exit($failed === 0 ? 0 : 1);
