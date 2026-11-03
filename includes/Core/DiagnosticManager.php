<?php

/**
 * Manages registered diagnostic modules and normalizes their results.
 */

namespace WPDT\\Core;

class DiagnosticManager
{
    /**
     * Registered diagnostics.
     *
     * @var DiagnosticInterface[]
     */
    private $diagnostics = array();

    /**
     * Legacy result adapter.
     *
     * @var LegacyResultAdapter
     */
    private $result_adapter;

    /**
     * Constructor.
     *
     * @param LegacyResultAdapter|null $result_adapter Optional legacy adapter.
     */
    public function __construct(LegacyResultAdapter $result_adapter = null)
    {
        $this->result_adapter = $result_adapter ?: new LegacyResultAdapter();
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
     * Run all registered diagnostics.
     *
     * Every returned check is a RuleResult. Legacy arrays are adapted
     * individually so one malformed item cannot discard valid siblings.
     *
     * @return array
     */
    public function run_all()
    {
        $results = array();

        foreach ($this->diagnostics as $registered_id => $diagnostic) {
            $module_id = $registered_id;
            $module_name = $registered_id;

            try {
                $module_name = $diagnostic->get_name();
                $legacy_results = $diagnostic->run();

                $normalized_results = is_array($legacy_results)
                    ? $this->normalize_results($module_id, $legacy_results)
                    : array(
                        $this->create_error_result(
                            $module_id,
                            'module_output',
                            'Invalid diagnostic output',
                            'The diagnostic module did not return a result array.',
                            'Expected an array from DiagnosticInterface::run().'
                        ),
                    );
            } catch (\\Throwable $exception) {
                $normalized_results = array(
                    $this->create_error_result(
                        $module_id,
                        'module_execution',
                        'Diagnostic execution failed',
                        'The diagnostic module could not complete its execution.',
                        get_class($exception)
                    ),
                );
            }

            $results[$module_id] = array(
                'id'      => $module_id,
                'name'    => is_string($module_name) && trim($module_name) !== ''
                    ? $module_name
                    : $module_id,
                'results' => $normalized_results,
            );
        }

        return $results;
    }

    /**
     * Normalize legacy arrays and already-normalized RuleResult objects.
     *
     * @param string $module_id Diagnostic module ID.
     * @param array  $legacy_results Raw diagnostic results.
     * @return RuleResult[]
     */
    private function normalize_results($module_id, array $legacy_results)
    {
        $entries = array();
        $reserved_ids = array();
        $seen_ids = array();

        foreach ($legacy_results as $index => $legacy_result) {
            try {
                if ($legacy_result instanceof RuleResult) {
                    $result = $legacy_result;
                } elseif (is_array($legacy_result)) {
                    $result = $this->result_adapter->adapt($module_id, $legacy_result);
                } else {
                    throw new \\InvalidArgumentException(
                        'Each diagnostic result must be an array or RuleResult instance.'
                    );
                }

                if ($result->get('module') !== $module_id) {
                    throw new \\InvalidArgumentException(
                        'The result module does not match its registered diagnostic module.'
                    );
                }

                $result_id = $result->get('id');
                $reserved_ids[$result_id] = true;

                if (isset($seen_ids[$result_id])) {
                    $entries[] = array(
                        'error' => array(
                            'base_id' => 'duplicate_result',
                            'title' => 'Duplicate diagnostic result ID',
                            'description' => 'A diagnostic result reused an ID already emitted by this module. The earlier result was preserved.',
                            'evidence' => $result_id,
                        ),
                    );
                    continue;
                }

                $seen_ids[$result_id] = true;
                $entries[] = array('result' => $result);
            } catch (\\Throwable $exception) {
                $entries[] = array(
                    'error' => array(
                        'base_id' => 'invalid_result_' . $index,
                        'title' => 'Invalid diagnostic result',
                        'description' => 'This result could not be normalized. Other results from the module were retained.',
                        'evidence' => get_class($exception) . ': ' . $exception->getMessage(),
                    ),
                );
            }
        }

        $normalized = array();
        $used_ids = $seen_ids;

        foreach ($entries as $entry) {
            if (isset($entry['result'])) {
                $normalized[] = $entry['result'];
                continue;
            }

            $error = $entry['error'];
            $normalized[] = $this->create_error_result(
                $module_id,
                $error['base_id'],
                $error['title'],
                $error['description'],
                $error['evidence'],
                $reserved_ids,
                $used_ids
            );
        }

        return $normalized;
    }

    /**
     * Create an error result with a unique, stable identifier.
     *
     * @param string $module_id Diagnostic module ID.
     * @param string $suffix    Error identifier suffix.
     * @param string $title     Error title.
     * @param string $description Error description.
     * @param string $evidence  Supporting evidence.
     * @param array  $reserved_ids IDs already present in source results.
     * @param array  $used_ids IDs already emitted.
     * @return RuleResult
     */
    private function create_error_result(
        $module_id,
        $suffix,
        $title,
        $description,
        $evidence,
        array $reserved_ids = array(),
        array &$used_ids = array()
    ) {
        $base_id = $module_id . '.error.' . preg_replace('/[^a-z0-9._-]+/', '_', strtolower($suffix));
        $result_id = $base_id;
        $counter = 2;

        while (isset($reserved_ids[$result_id]) || isset($used_ids[$result_id])) {
            $result_id = $base_id . '.' . $counter;
            $counter++;
        }

        $used_ids[$result_id] = true;

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
