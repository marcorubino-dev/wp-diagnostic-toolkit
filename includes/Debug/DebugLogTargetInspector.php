<?php

/**
 * Inspects debug-log target metadata without reading file contents.
 */

namespace WPDT\Debug;

class DebugLogTargetInspector
{
    /**
     * Inspect the configured WordPress debug-log target.
     *
     * @param mixed  $debug_log_value WP_DEBUG_LOG value.
     * @param string $debug_state     Normalized WP_DEBUG state.
     * @param string $content_dir     WordPress content directory.
     * @return array
     */
    public function inspect($debug_log_value, $debug_state, $content_dir)
    {
        if ($debug_state === 'disabled') {
            return $this->result('not_configured');
        }

        if ($debug_state !== 'enabled') {
            return $this->result('unknown');
        }

        if ($debug_log_value === false || $debug_log_value === null || $debug_log_value === '') {
            return $this->result('not_configured');
        }

        $content_root = realpath($content_dir);

        if ($content_root === false || ! is_dir($content_root)) {
            return $this->result('unknown');
        }

        $is_default = $debug_log_value === true;

        if ($is_default) {
            $target = $content_root . DIRECTORY_SEPARATOR . 'debug.log';
        } elseif (is_string($debug_log_value) && trim($debug_log_value) !== '') {
            $target = trim($debug_log_value);
        } else {
            return $this->result('unknown');
        }

        if (! $this->is_absolute_path($target)) {
            return $this->result('unsupported_external_path');
        }

        $resolved_target = realpath($target);

        if ($resolved_target !== false) {
            if (! $this->is_within_directory($resolved_target, $content_root)) {
                return $this->result('unsupported_external_path');
            }

            if (! is_file($resolved_target)) {
                return $this->result('unsupported_file_type');
            }

            if (! is_readable($resolved_target)) {
                return $this->result('unreadable');
            }

            return $this->result(
                'detected_readable',
                $this->relative_location($resolved_target, $content_root),
                $is_default ? 'default' : 'custom'
            );
        }

        $parent = realpath(dirname($target));

        if ($parent === false || ! $this->is_within_directory($parent, $content_root)) {
            return $this->result('unsupported_external_path');
        }

        $basename = basename($target);

        if ($basename === '' || $basename === '.' || $basename === '..') {
            return $this->result('unknown');
        }

        return $this->result(
            'missing',
            $this->relative_location($parent . DIRECTORY_SEPARATOR . $basename, $content_root),
            $is_default ? 'default' : 'custom'
        );
    }

    /**
     * Build a normalized result.
     *
     * @param string      $status       Inspection status.
     * @param string|null $relative     Safe relative location.
     * @param string|null $target_type  Target type.
     * @return array
     */
    private function result($status, $relative = null, $target_type = null)
    {
        return array(
            'status' => $status,
            'relative_location' => $relative,
            'target_type' => $target_type,
        );
    }

    /**
     * Check whether a path is absolute.
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
     * Check a canonical path against a canonical directory boundary.
     *
     * @param string $path      Canonical target path.
     * @param string $directory Canonical allowed directory.
     * @return bool
     */
    private function is_within_directory($path, $directory)
    {
        $path = $this->normalize_path($path);
        $directory = rtrim($this->normalize_path($directory), '/');

        if (DIRECTORY_SEPARATOR === '\\') {
            $path = strtolower($path);
            $directory = strtolower($directory);
        }

        return $path === $directory || strpos($path, $directory . '/') === 0;
    }

    /**
     * Return a path relative to the content directory.
     *
     * @param string $path      Path.
     * @param string $directory Content directory.
     * @return string
     */
    private function relative_location($path, $directory)
    {
        $path = $this->normalize_path($path);
        $directory = rtrim($this->normalize_path($directory), '/');

        return 'wp-content/' . ltrim(substr($path, strlen($directory)), '/');
    }

    /**
     * Normalize path separators.
     *
     * @param string $path Path.
     * @return string
     */
    private function normalize_path($path)
    {
        return str_replace('\\', '/', $path);
    }
}
