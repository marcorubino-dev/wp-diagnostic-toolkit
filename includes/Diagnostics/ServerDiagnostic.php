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
        return array(
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
                'name'        => 'PHP extensions',
                'value'       => $this->get_extension_status(),
                'status'      => 'info',
                'description' => 'Availability of commonly used PHP extensions.',
            ),
        );
    }

    /**
     * Get the status of commonly used PHP extensions.
     *
     * @return string
     */
    private function get_extension_status()
    {
        $extensions = array(
            'curl',
            'json',
            'mbstring',
            'mysqli',
            'openssl',
            'xml',
            'zip',
        );

        $loaded = array();

        foreach ($extensions as $extension) {
            if (extension_loaded($extension)) {
                $loaded[] = $extension;
            }
        }

        return count($loaded) . '/' . count($extensions) . ' loaded';
    }
}
