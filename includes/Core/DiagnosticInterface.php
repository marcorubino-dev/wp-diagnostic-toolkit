<?php

/**
 * Contract for diagnostic modules.
 */

namespace WPDT\Core;

interface DiagnosticInterface
{
    /**
     * Get the diagnostic identifier.
     *
     * @return string
     */
    public function get_id();

    /**
     * Get the diagnostic name.
     *
     * @return string
     */
    public function get_name();

    /**
     * Run the diagnostic.
     *
     * @return array
     */
    public function run();
}
