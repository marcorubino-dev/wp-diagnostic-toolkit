<?php

/**
 * WordPress plugin diagnostics.
 */

namespace WPDT\Diagnostics;

use WPDT\Core\DiagnosticInterface;

class PluginDiagnostic implements DiagnosticInterface
{
    /**
     * Get the diagnostic identifier.
     *
     * @return string
     */
    public function get_id()
    {
        return 'plugins';
    }

    /**
     * Get the diagnostic name.
     *
     * @return string
     */
    public function get_name()
    {
        return 'WordPress Plugins';
    }

    /**
     * Run plugin checks.
     *
     * @return array
     */
    public function run()
    {
        $all_plugins    = get_plugins();
        $active_plugins = get_option('active_plugins', array());

        $active_count   = count($active_plugins);
        $inactive_count = count($all_plugins) - $active_count;

        $update_status = $this->get_update_status();

        return array(
            array(
                'name'        => 'Installed plugins',
                'value'       => count($all_plugins),
                'status'      => 'info',
                'description' => 'Total number of installed plugins.',
            ),
            array(
                'name'        => 'Active plugins',
                'value'       => $active_count,
                'status'      => 'info',
                'description' => 'Number of currently active plugins.',
            ),
            array(
                'name'        => 'Inactive plugins',
                'value'       => $inactive_count,
                'status'      => 'info',
                'description' => 'Number of installed plugins that are currently inactive.',
            ),
            array(
                'name'           => 'Active plugin updates',
                'value'          => $update_status['value'],
                'status'         => $update_status['status'],
                'description'    => $update_status['description'],
                'recommendation' => $update_status['recommendation'],
            ),
        );
    }

    /**
     * Get the current plugin update status reported by WordPress.
     *
     * @return array
     */
    private function get_update_status()
    {
        $update_plugins = get_site_transient('update_plugins');

        if (
            false === $update_plugins
            || ! isset($update_plugins->response)
            || ! is_array($update_plugins->response)
        ) {
            return array(
                'value'          => 'Not available',
                'status'         => 'info',
                'description'    => 'Plugin update information is not currently available.',
                'recommendation' => 'Refresh WordPress update information and re-run the audit.',
            );
        }

        $active_plugins = get_option('active_plugins', array());
        $updates        = 0;

        foreach ($update_plugins->response as $plugin_file => $plugin_update) {
            if (in_array($plugin_file, $active_plugins, true)) {
                $updates++;
            }
        }

        if (0 === $updates) {
            return array(
                'value'          => '0 available',
                'status'         => 'pass',
                'description'    => 'No updates are currently available for active plugins.',
                'recommendation' => 'No action required.',
            );
        }

        return array(
            'value'          => $updates . ' available',
            'status'         => 'warning',
            'description'    => $updates . ' active plugin'
                . (1 === $updates ? ' has' : 's have')
                . ' an available update.',
            'recommendation' => 'Review and apply available plugin updates.',
        );
    }
}