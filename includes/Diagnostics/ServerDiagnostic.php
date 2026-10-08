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
        $extension_status = $this->get_extension_status();

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
            array(
                'name'           => 'PHP extensions',
                'value'          => $extension_status['value'],
                'status'         => $extension_status['status'],
                'description'    => $extension_status['description'],
                'recommendation' => $extension_status['recommendation'],
            ),
        );

        return array_merge(
            $results,
            $this->get_extension_diagnostics()
        );
    }

    /**
     * Get the aggregate status of required PHP extensions.
     *
     * @return array
     */
    private function get_extension_status()
    {
        $extensions = $this->get_required_extensions();

        $loaded = 0;

        foreach ($extensions as $extension) {
            if (extension_loaded($extension)) {
                $loaded++;
            }
        }

        $total = count($extensions);

        if ($loaded === $total) {
            return array(
                'value'          => $loaded . '/' . $total . ' loaded',
                'status'         => 'pass',
                'description'    => 'All required PHP extensions are available.',
                'recommendation' => 'No action required.',
            );
        }

        return array(
            'value'          => $loaded . '/' . $total . ' loaded',
            'status'         => 'warning',
            'description'    => 'One or more required PHP extensions are not available.',
            'recommendation' => 'Enable the missing PHP extensions and re-run the audit.',
        );
    }

    /**
     * Get individual PHP extension diagnostics.
     *
     * @return array
     */
    private function get_extension_diagnostics()
    {
        $extensions = $this->get_required_extensions();

        $results = array();

        foreach ($extensions as $extension) {
            $loaded = extension_loaded($extension);

            $results[] = array(
                'name'           => 'PHP extension: ' . $extension,
                'value'          => $loaded ? 'Loaded' : 'Not loaded',
                'status'         => $loaded ? 'pass' : 'warning',
                'description'    => $loaded
                    ? 'PHP extension is available.'
                    : 'PHP extension is not available.',
                'recommendation' => $loaded
                    ? 'No action required.'
                    : 'Enable the ' . $extension . ' PHP extension and re-run the audit.',
                'evidence'       => "extension_loaded('{$extension}')",
            );
        }

        return $results;
    }

    /**
     * Get PHP extensions required by the toolkit.
     *
     * @return array
     */
    private function get_required_extensions()
    {
        return array(
            'curl',
            'json',
            'mbstring',
            'mysqli',
            'openssl',
            'xml',
            'zip',
        );
    }
}
