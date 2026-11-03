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

    /**
     * @param LegacyResultAdapter|null       $result_adapter Optional legacy adapter.
     * @param DiagnosticResultPresenter|null $presenter      Optional result presenter.
     */
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
     */
    public function register(DiagnosticInterface $diagnostic)
    {
        $this->diagnostics[$diagnostic->get_id()] = $diagnostic;
    }

    /**
     * Run registered diagnostics and return presenter-compatible normalized data.
     *
     * @return array
     */
    public function run_all()
    {
        $results = array();
        $seen_ids = array();

        foreach ($this->diagnostics as $module_id => $diagnostic) {
            $module_name = $module_id;

            try {
                $module_name = $diagnostic->get_name();
                $legacy_results = $diagnostic->run();

                if (!is_array($legacy_results)) {
                    $module_results = array(
                        $this->create_error_result(
                            $module_id,
                            'module_output',
                            'Invalid diagnostic output',
                            'The diagnostic module did not return a result array.',
                            'Expected an array from DiagnosticInterface::run().',
                            $seen_ids
                        ),
                    );
                } else {
                    $module_results = $this->normalize_results($module_id, $legacy_results, $seen_ids);
                }
            } catch (\Throwable $exception) {
                $module_results = array(
                    $this->create_error_result(
                        $module_id,
                        'module_execution',
                        'Diagnostic execution failed',
                        'The diagnostic module could not complete its execution.',
                        get_class($exception) . ': ' . $exception->getMessage(),
                        $seen_ids
                    ),
                );
            }

            $presented_results = array();

            foreach ($module_results as $result) {
                $presented_results[] = $this->presenter->present($result);
            }

            $results[$module_id] = array(
                'id'      => $module_id,
                'name'    => is_string($module_name) && trim($module_name) !== ''
                    ? $module_name
                    : $module_id,
                'results' => $presented_results,
            );
        }

        return $results;
    }

    /**
     * Normalize each result independently and detect IDs across all modules.
     *
     * @param string $module_id    Diagnostic module ID.
     * @param array  $legacy_results Raw diagnostic results.
     * @param array  $seen_ids      IDs already accepted or generated.
     * @return RuleResult[]
     */
    private function normalize_results($module_id, array $legacy_results, array &$seen_ids)
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
                        $seen_ids
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
                    $seen_ids
                );
            }
        }

        return $normalized;
    }

    /**
     * Create a unique error result without replacing an existing result.
     *
     * @param string $module_id   Diagnostic module ID.
     * @param string $suffix      Error identifier suffix.
     * @param string $title       Error title.
     * @param string $description Error description.
     * @param string $evidence    Supporting evidence.
     * @param array  $seen_ids    IDs already emitted; updated by reference.
     * @return RuleResult
     */
    private function create_error_result(
        $module_id,
        $suffix,
        $title,
        $description,
        $evidence,
        array &$seen_ids
    ) {
        $safe_suffix = preg_replace('/[^a-z0-9._-]+/', '_', strtolower((string) $suffix));
        $base_id = $module_id . '.error.' . $safe_suffix;
        $result_id = $base_id;
        $counter = 2;

        while (isset($seen_ids[$result_id])) {
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
