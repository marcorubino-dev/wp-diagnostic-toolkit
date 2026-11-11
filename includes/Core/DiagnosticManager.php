<?php

/**
 * Manages registered diagnostic modules and normalizes their results.
 */

namespace WPDT\Core;

class DiagnosticManager
{
    /** @var DiagnosticInterface[] */
    private $diagnostics = array();

    /** @var LegacyResultAdapter */
    private $result_adapter;

    /** @var DiagnosticResultPresenter */
    private $presenter;

    public function __construct(
        LegacyResultAdapter $result_adapter = null,
        DiagnosticResultPresenter $presenter = null
    ) {
        $this->result_adapter = $result_adapter ?: new LegacyResultAdapter();
        $this->presenter = $presenter ?: new DiagnosticResultPresenter();
    }

    /**
     * Register a diagnostic module.
     *
     * @param DiagnosticInterface $diagnostic Diagnostic module.
     * @return void
     * @throws \InvalidArgumentException If the diagnostic ID is already registered.
     */
    public function register(DiagnosticInterface $diagnostic)
    {
        $module_id = $diagnostic->get_id();

        if (array_key_exists($module_id, $this->diagnostics)) {
            throw new \InvalidArgumentException(
                sprintf('A diagnostic module with ID "%s" is already registered.', $module_id)
            );
        }

        $this->diagnostics[$module_id] = $diagnostic;
    }

    /**
     * Run registered diagnostics and return presenter-compatible normalized data.
     *
     * All modules are executed before normalization so synthetic error IDs can
     * avoid IDs declared by valid results in later modules.
     *
     * @return array
     */
    public function run_all()
    {
        return $this->run_modules($this->diagnostics);
    }

    /**
     * Run only the requested registered diagnostic modules.
     *
     * Results are returned in registration order. ID reservation and duplicate
     * detection apply to this execution set; unselected modules are not inspected.
     *
     * @param array $module_ids Registered diagnostic module IDs.
     * @return array
     * @throws \InvalidArgumentException If an ID is invalid, duplicated, or unknown.
     */
    public function run_selected(array $module_ids)
    {
        $requested_ids = array();

        foreach ($module_ids as $module_id) {
            if (! is_string($module_id) || trim($module_id) === '') {
                throw new \InvalidArgumentException(
                    'Diagnostic module IDs must be non-empty strings.'
                );
            }

            if (isset($requested_ids[$module_id])) {
                throw new \InvalidArgumentException(
                    sprintf('Diagnostic module ID "%s" was requested more than once.', $module_id)
                );
            }

            if (! array_key_exists($module_id, $this->diagnostics)) {
                throw new \InvalidArgumentException(
                    sprintf('Diagnostic module "%s" is not registered.', $module_id)
                );
            }

            $requested_ids[$module_id] = true;
        }

        $selected_diagnostics = array();

        foreach ($this->diagnostics as $registered_module_id => $diagnostic) {
            if (isset($requested_ids[$registered_module_id])) {
                $selected_diagnostics[$registered_module_id] = $diagnostic;
            }
        }

        return $this->run_modules($selected_diagnostics);
    }

    /**
     * Execute and normalize the supplied diagnostic execution set.
     *
     * @param DiagnosticInterface[] $diagnostics Modules included in this run.
     * @return array
     */
    private function run_modules(array $diagnostics)
    {
        $module_runs = array();
        $reserved_ids = array();

        foreach ($diagnostics as $module_id => $diagnostic) {
            $module_name = $module_id;
            $legacy_results = null;
            $execution_error = null;

            try {
                $module_name = $diagnostic->get_name();
                $legacy_results = $diagnostic->run();
            } catch (\Throwable $exception) {
                $execution_error = $exception;
            }

            $module_runs[$module_id] = array(
                'name'            => is_string($module_name) && trim($module_name) !== ''
                    ? $module_name
                    : $module_id,
                'legacy_results'  => $legacy_results,
                'execution_error' => $execution_error,
            );

            if (is_array($legacy_results)) {
                foreach ($legacy_results as $legacy_result) {
                    $result_id = null;

                    try {
                        if ($legacy_result instanceof RuleResult) {
                            $result_id = $legacy_result->get('id');
                        } elseif (is_array($legacy_result)) {
                            if (isset($legacy_result['id'])) {
                                $result_id = $legacy_result['id'];
                            } elseif (!array_key_exists('id', $legacy_result)) {
                                // Reserve adapter-generated IDs as well as explicit source IDs.
                                $adapted_result = $this->result_adapter->adapt($module_id, $legacy_result);
                                $result_id = $adapted_result->get('id');
                            }
                        }
                    } catch (\Throwable $exception) {
                        // A malformed result must not prevent other IDs being reserved.
                    }

                    if (is_string($result_id) && trim($result_id) !== '') {
                        $reserved_ids[$result_id] = true;
                    }
                }
            }
        }

        $results = array();
        $seen_ids = array();

        foreach ($module_runs as $module_id => $module_run) {
            if ($module_run['execution_error'] instanceof \Throwable) {
                $module_results = array(
                    $this->create_error_result(
                        $module_id,
                        'module_execution',
                        'Diagnostic execution failed',
                        'The diagnostic module could not complete its execution.',
                        get_class($module_run['execution_error']) . ': ' . $module_run['execution_error']->getMessage(),
                        $seen_ids,
                        $reserved_ids
                    ),
                );
            } elseif (!is_array($module_run['legacy_results'])) {
                $module_results = array(
                    $this->create_error_result(
                        $module_id,
                        'module_output',
                        'Invalid diagnostic output',
                        'The diagnostic module did not return a result array.',
                        'Expected an array from DiagnosticInterface::run().',
                        $seen_ids,
                        $reserved_ids
                    ),
                );
            } else {
                $module_results = $this->normalize_results(
                    $module_id,
                    $module_run['legacy_results'],
                    $seen_ids,
                    $reserved_ids
                );
            }

            $presented_results = array();

            foreach ($module_results as $result) {
                $presented_results[] = $this->presenter->present($result);
            }

            $results[$module_id] = array(
                'id'      => $module_id,
                'name'    => $module_run['name'],
                'results' => $presented_results,
            );
        }

        return $results;
    }

    /**
     * Normalize each result independently and detect IDs across all modules.
     *
     * @param string $module_id       Diagnostic module ID.
     * @param array  $legacy_results  Raw diagnostic results.
     * @param array  $seen_ids        IDs already accepted or generated.
     * @param array  $reserved_ids    IDs declared by source results.
     * @return RuleResult[]
     */
    private function normalize_results($module_id, array $legacy_results, array &$seen_ids, array &$reserved_ids)
    {
        $normalized = array();

        foreach ($legacy_results as $index => $legacy_result) {
            try {
                if ($legacy_result instanceof RuleResult) {
                    $result = $legacy_result;
                } elseif (is_array($legacy_result)) {
                    $result = $this->result_adapter->adapt($module_id, $legacy_result);
                } else {
                    throw new \InvalidArgumentException(
                        'Each diagnostic result must be an array or RuleResult instance.'
                    );
                }

                if ($result->get('module') !== $module_id) {
                    throw new \InvalidArgumentException(
                        'The result module does not match its registered diagnostic module.'
                    );
                }

                $result_id = $result->get('id');

                if (isset($seen_ids[$result_id])) {
                    $normalized[] = $this->create_error_result(
                        $module_id,
                        'duplicate_result',
                        'Duplicate diagnostic result ID',
                        'A diagnostic result reused an ID already emitted by this or another module. The earlier result was preserved.',
                        $result_id,
                        $seen_ids,
                        $reserved_ids
                    );
                    continue;
                }

                $seen_ids[$result_id] = true;
                $normalized[] = $result;
            } catch (\Throwable $exception) {
                $normalized[] = $this->create_error_result(
                    $module_id,
                    'invalid_result_' . $index,
                    'Invalid diagnostic result',
                    'This result could not be normalized. Other results from the module were retained.',
                    get_class($exception) . ': ' . $exception->getMessage(),
                    $seen_ids,
                    $reserved_ids
                );
            }
        }

        return $normalized;
    }

    /**
     * Create a unique error result without replacing a source result ID.
     *
     * @param string $module_id    Diagnostic module ID.
     * @param string $suffix       Error identifier suffix.
     * @param string $title        Error title.
     * @param string $description  Error description.
     * @param string $evidence     Supporting evidence.
     * @param array  $seen_ids     IDs already emitted; updated by reference.
     * @param array  $reserved_ids IDs declared by source results.
     * @return RuleResult
     */
    private function create_error_result(
        $module_id,
        $suffix,
        $title,
        $description,
        $evidence,
        array &$seen_ids,
        array &$reserved_ids
    ) {
        $safe_suffix = preg_replace('/[^a-z0-9._-]+/', '_', strtolower((string) $suffix));
        $base_id = $module_id . '.error.' . $safe_suffix;
        $result_id = $base_id;
        $counter = 2;

        while (isset($seen_ids[$result_id]) || isset($reserved_ids[$result_id])) {
            $result_id = $base_id . '.' . $counter;
            $counter++;
        }

        $seen_ids[$result_id] = true;

        return new RuleResult(array(
            'id'             => $result_id,
            'title'          => $title,
            'outcome'        => RuleResult::OUTCOME_ERROR,
            'severity'       => RuleResult::SEVERITY_NONE,
            'confidence'     => RuleResult::CONFIDENCE_UNKNOWN,
            'description'    => $description,
            'value'          => null,
            'recommendation' => 'Review the diagnostic implementation and its returned data.',
            'evidence'       => $evidence,
            'module'         => $module_id,
        ));
    }
}
