<?php

/**
 * Manages registered diagnostic modules.
 */

namespace WPDT\Core;

class DiagnosticManager
{
    /**
     * Registered diagnostics.
     *
     * @var DiagnosticInterface[]
     */
    private $diagnostics = array();

    /**
     * Register a diagnostic module.
     *
     * @param DiagnosticInterface $diagnostic Diagnostic module.
     * @return void
     */
    public function register(DiagnosticInterface $diagnostic)
    {
        $this->diagnostics[$diagnostic->get_id()] = $diagnostic;
    }

    /**
     * Run all registered diagnostics.
     *
     * @return array
     */
    public function run_all()
    {
        $results = array();

        foreach ($this->diagnostics as $diagnostic) {
            $results[$diagnostic->get_id()] = array(
                'id'      => $diagnostic->get_id(),
                'name'    => $diagnostic->get_name(),
                'results' => $diagnostic->run(),
            );
        }

        return $results;
    }
}
