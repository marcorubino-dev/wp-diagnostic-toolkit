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
                'status'      => $inactive_count > 0 ? 'info' : 'ok',
                'description' => 'Number of installed plugins that are currently inactive.',
            ),
        );
    }
}
