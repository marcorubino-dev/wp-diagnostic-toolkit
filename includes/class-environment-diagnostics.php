
<?php
/**
 * Collects read-only WordPress environment diagnostics.
 */

defined('ABSPATH') || exit;

class WPDT_Environment_Diagnostics
{

    /**
     * Collect environment checks.
     *
     * @return array
     */
    public static function run()
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

    /**
     * Register the diagnostics page.
     */
    public static function register_menu()
    {
        add_management_page(
            'WP Diagnostic Toolkit',
            'WP Diagnostic Toolkit',
            'manage_options',
            'wp-diagnostic-toolkit',
            array(__CLASS__, 'render_page')
        );
    }

    /**
     * Render the diagnostics page.
     */
    public static function render_page()
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $checks = self::run();

        echo '<div class="wrap">';
        echo '<h1>WP Diagnostic Toolkit</h1>';
        echo '<p>Read-only environment diagnostics.</p>';

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>Check</th>';
        echo '<th>Value</th>';
        echo '<th>Status</th>';
        echo '<th>Description</th>';
        echo '</tr></thead><tbody>';

        foreach ($checks as $check) {
            echo '<tr>';
            echo '<td>' . esc_html($check['name']) . '</td>';
            echo '<td>' . esc_html($check['value']) . '</td>';
            echo '<td>' . esc_html(strtoupper($check['status'])) . '</td>';
            echo '<td>' . esc_html($check['description']) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    }
