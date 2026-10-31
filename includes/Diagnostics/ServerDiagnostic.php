<?php

/**
 * PHP and server configuration diagnostics.
 */

namespace WPDT\Diagnostics;

use WPDT\Core\DiagnosticInterface;

class ServerDiagnostic implements DiagnosticInterface
{
    /**
     * Get the diagnostic identifier.
     *
     * @return string
     */
    public function get_id()
    {
        return 'server';
    }

    /**
     * Get the diagnostic name.
     *
     * @return string
     */
    public function get_name()
    {
        return 'PHP & Server Configuration';
    }

    /**
     * Run server configuration checks.
     *
     * @return array
     */
    public function run()
    {
        $results = array(
            array(
                'name'        => 'PHP memory limit',
                'value'       => ini_get('memory_limit'),
                'status'      => 'info',
                'description' => 'PHP memory limit configured for the current process.',
            ),
            array(
                'name'        => 'Upload max filesize',
                'value'       => ini_get('upload_max_filesize'),
                'status'      => 'info',
                'description' => 'Maximum size allowed for an uploaded file.',
            ),
            array(
                'name'        => 'POST max size',
                'value'       => ini_get('post_max_size'),
                'status'      => 'info',
                'description' => 'Maximum size allowed for POST request data.',
            ),
            array(
                'name'        => 'Maximum execution time',
                'value'       => ini_get('max_execution_time') . ' seconds',
                'status'      => 'info',
                'description' => 'Maximum execution time configured for PHP scripts.',
            ),
        );

        return array_merge(
            $results,
            $this->get_extension_diagnostics()
        );
    }

    /**
     * Get the PHP extension groups inspected by this diagnostic.
     *
     * These groups are a practical subset, not an exhaustive list of
     * every WordPress hosting recommendation.
     *
     * @return array
     */
    private function get_extension_groups()
    {
        return array(
            'core' => array(
                'label'      => 'Core runtime',
                'extensions' => array(
                    'json',
                    'mysqli',
                ),
                'required'   => true,
            ),
            'recommended' => array(
                'label'      => 'Recommended compatibility',
                'extensions' => array(
                    'curl',
                    'mbstring',
                    'openssl',
                    'xml',
                    'zip',
                ),
                'required'   => false,
            ),
        );
    }

    /**
     * Get aggregate and individual PHP extension diagnostics.
     *
     * @return array
     */
    private function get_extension_diagnostics()
    {
        $results = array();
        $groups = $this->get_extension_groups();

        foreach ($groups as $group) {
            $extensions = $group['extensions'];
            $loaded = array();
            $missing = array();

            foreach ($extensions as $extension) {
                if (extension_loaded($extension)) {
                    $loaded[] = $extension;
                } else {
                    $missing[] = $extension;
                }
            }

            $is_required = $group['required'];
            $all_loaded = empty($missing);
            $summary_status = $all_loaded
                ? 'pass'
                : ($is_required ? 'critical' : 'info');

            $results[] = array(
                'name'           => $group['label'] . ' PHP extensions',
                'value'          => count($loaded) . '/' . count($extensions) . ' loaded',
                'status'         => $summary_status,
                'description'    => $all_loaded
                    ? 'All extensions in this group are available.'
                    : ($is_required
                        ? 'One or more baseline runtime extensions are missing.'
                        : 'One or more recommended extensions are not available. Some WordPress or plugin features may have reduced compatibility.'),
                'recommendation' => $all_loaded
                    ? 'No action required.'
                    : ($is_required
                        ? 'Ask the hosting provider to enable the missing extensions: ' . implode(', ', $missing) . '.'
                        : 'Review whether the missing extensions are needed by your site, plugins, or integrations: ' . implode(', ', $missing) . '.'),
                'evidence'       => 'Loaded: ' . (empty($loaded) ? 'none' : implode(', ', $loaded))
                    . ' | Missing: ' . (empty($missing) ? 'none' : implode(', ', $missing)),
            );

            foreach ($extensions as $extension) {
                $is_loaded = extension_loaded($extension);
                $extension_required = $is_required;

                $results[] = array(
                    'name'           => 'PHP extension: ' . $extension,
                    'value'          => $is_loaded ? 'Loaded' : 'Not loaded',
                    'status'         => $is_loaded
                        ? 'pass'
                        : ($extension_required ? 'critical' : 'info'),
                    'description'    => $is_loaded
                        ? 'Extension is available.'
                        : ($extension_required
                            ? 'This extension is part of the baseline runtime checks.'
                            : 'This extension is recommended for compatibility with common WordPress features and integrations.'),
                    'recommendation' => $is_loaded
                        ? 'No action required.'
                        : ($extension_required
                            ? 'Ask the hosting provider to enable the ' . $extension . ' PHP extension.'
                            : 'Check whether your active plugins or integrations require the ' . $extension . ' PHP extension.'),
                    'evidence'       => "extension_loaded('" . $extension . "')",
                );
            }
        }

        return $results;
    }
}
