<?php

/**
 * Inspects WordPress and PHP debug configuration without changing it.
 */

namespace WPDT\Debug;

class DebugConfigurationInspector
{
    /**
     * Return a normalized, disclosure-safe configuration summary.
     *
     * @return array
     */
    public function inspect()
    {
        $debug = $this->constant_boolean('WP_DEBUG');
        $debug_log = $this->constant_value('WP_DEBUG_LOG');
        $debug_display = $this->constant_boolean('WP_DEBUG_DISPLAY');

        $log_errors = $this->ini_boolean('log_errors');
        $error_log = $this->classify_error_log(ini_get('error_log'));

        if ($debug['state'] === 'disabled') {
            $logging_state = 'not_configured';
        } elseif ($debug['state'] === 'enabled') {
            $logging_state = $this->is_debug_log_configured($debug_log)
                ? 'configured'
                : 'not_configured';
        } else {
            $logging_state = 'unknown';
        }

        return array(
            'wordpress_debug' => $debug['state'],
            'wordpress_debug_log' => $logging_state,
            'wordpress_debug_display' => $debug_display['state'],
            'php_log_errors' => $log_errors,
            'php_error_log' => $error_log,
            'debug_display_warning' => (
                $debug['state'] === 'enabled'
                && $debug_display['state'] === 'enabled'
            ),
            'debug_log_value' => $debug_log['value'],
        );
    }

    /**
     * Read a constant only when it is defined.
     *
     * @param string $name Constant name.
     * @return array
     */
    private function constant_value($name)
    {
        if (! defined($name)) {
            return array(
                'defined' => false,
                'value' => null,
            );
        }

        return array(
            'defined' => true,
            'value' => constant($name),
        );
    }

    /**
     * Normalize a boolean-like WordPress constant.
     *
     * @param string $name Constant name.
     * @return array
     */
    private function constant_boolean($name)
    {
        $constant = $this->constant_value($name);

        if (! $constant['defined']) {
            return array('state' => 'unknown');
        }

        if ($constant['value'] === true || $constant['value'] === 1 || $constant['value'] === '1') {
            return array('state' => 'enabled');
        }

        if ($constant['value'] === false || $constant['value'] === 0 || $constant['value'] === '0' || $constant['value'] === '') {
            return array('state' => 'disabled');
        }

        return array('state' => 'unknown');
    }

    /**
     * Normalize a PHP INI boolean value.
     *
     * @param string $name INI setting name.
     * @return string
     */
    private function ini_boolean($name)
    {
        $value = ini_get($name);

        if ($value === false || $value === '') {
            return 'unknown';
        }

        $normalized = strtolower(trim((string) $value));

        if (in_array($normalized, array('1', 'on', 'yes', 'true'), true)) {
            return 'enabled';
        }

        if (in_array($normalized, array('0', 'off', 'no', 'false'), true)) {
            return 'disabled';
        }

        return 'unknown';
    }

    /**
     * Determine whether WP_DEBUG_LOG is configured without exposing its value.
     *
     * @param array $debug_log Constant metadata.
     * @return bool
     */
    private function is_debug_log_configured($debug_log)
    {
        if (! $debug_log['defined']) {
            return false;
        }

        $value = $debug_log['value'];

        if ($value === true) {
            return true;
        }

        return is_string($value) && trim($value) !== '';
    }

    /**
     * Classify PHP's error_log setting without disclosing its raw value.
     *
     * @param mixed $value PHP INI value.
     * @return string
     */
    private function classify_error_log($value)
    {
        if ($value === false) {
            return 'unknown';
        }

        $value = trim((string) $value);

        if ($value === '') {
            return 'default';
        }

        if (strtolower($value) === 'syslog') {
            return 'syslog_or_sapi';
        }

        if (defined('WP_CONTENT_DIR') && $this->is_path_within_content_directory($value)) {
            return 'in_content_path';
        }

        return 'external_or_host_managed';
    }

    /**
     * Check whether a configured PHP log path is lexically under WP_CONTENT_DIR.
     *
     * This is classification only. It does not inspect or open the path.
     *
     * @param string $path Configured PHP error log value.
     * @return bool
     */
    private function is_path_within_content_directory($path)
    {
        $content_dir = realpath(WP_CONTENT_DIR);

        if ($content_dir === false || ! $this->is_absolute_path($path)) {
            return false;
        }

        $normalized_path = $this->normalize_path($path);
        $normalized_root = rtrim($this->normalize_path($content_dir), '/');

        if (DIRECTORY_SEPARATOR === '\\') {
            $normalized_path = strtolower($normalized_path);
            $normalized_root = strtolower($normalized_root);
        }

        return strpos($normalized_path, $normalized_root . '/') === 0;
    }

    /**
     * Determine whether a path is absolute.
     *
     * @param string $path Path.
     * @return bool
     */
    private function is_absolute_path($path)
    {
        return isset($path[0]) && (
            $path[0] === '/'
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path)
        );
    }

    /**
     * Normalize separators for path comparison.
     *
     * @param string $path Path.
     * @return string
     */
    private function normalize_path($path)
    {
        return str_replace('\\', '/', $path);
    }
}
