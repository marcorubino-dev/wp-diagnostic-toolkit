<?php

/**
 * WordPress environment diagnostics.
 */

namespace WPDT\Diagnostics;

use WPDT\Core\DiagnosticInterface;

class EnvironmentDiagnostic implements DiagnosticInterface
{
    /**
     * Get the diagnostic identifier.
     *
     * @return string
     */
    public function get_id()
    {
        return 'environment';
    }

    /**
     * Get the diagnostic name.
     *
     * @return string
     */
    public function get_name()
    {
        return 'WordPress Environment';
    }

    /**
     * Run environment checks.
     *
     * @return array
     */
    public function run()
    {
        global $wpdb;

        $debug_enabled = defined('WP_DEBUG') && WP_DEBUG;

        $memory_limit = defined('WP_MEMORY_LIMIT')
            ? WP_MEMORY_LIMIT
            : 'Not explicitly defined';

        return array(
            array(
                'name'        => 'WordPress version',
                'value'       => get_bloginfo('version'),
                'status'      => 'info',
                'description' => 'Installed WordPress version.',
            ),
            array(
                'name'        => 'PHP version',
                'value'       => PHP_VERSION,
                'status'      => version_compare(PHP_VERSION, '7.4', '>=')
                    ? 'ok'
                    : 'warning',
                'description' => 'PHP version available to WordPress.',
            ),
            array(
                'name'        => 'Database version',
                'value'       => $wpdb->db_version(),
                'status'      => 'info',
                'description' => 'Database server version reported by WordPress.',
            ),
            array(
                'name'        => 'WP_DEBUG',
                'value'       => $debug_enabled ? 'Enabled' : 'Disabled',
                'status'      => $debug_enabled ? 'warning' : 'ok',
                'description' => $debug_enabled
                    ? 'Debug mode is enabled. Check the environment before exposing errors publicly.'
                    : 'WordPress debug mode is disabled.',
            ),
            array(
                'name'        => 'WP_MEMORY_LIMIT',
                'value'       => $memory_limit,
                'status'      => 'info',
                'description' => 'WordPress memory limit constant, if explicitly defined.',
            ),
        );
    }
}
