<?php

/**
 * Converts existing diagnostic arrays into the normalized result contract.
 *
 * @package WPDT\Core
 */

namespace WPDT\Core;

final class LegacyResultAdapter
{
    /**
     * Adapt one legacy diagnostic result.
     *
     * Explicit legacy IDs are qualified with their module when necessary. A deterministic
     * ID derived from the module and label remains a compatibility fallback only.
     *
     * @param string $module_id Stable diagnostic module identifier.
     * @param array  $legacy   Existing diagnostic result array.
     * @return RuleResult
     * @throws \InvalidArgumentException When a legacy result cannot be mapped safely.
     */
    public function adapt($module_id, array $legacy)
    {
        if (!is_string($module_id) || !preg_match('/^[a-z][a-z0-9]*(?:[_-][a-z0-9]+)*$/', $module_id)) {
            throw new \InvalidArgumentException('Module ID must be a stable lowercase identifier without dots.');
        }

        if (!isset($legacy['name']) || !is_string($legacy['name']) || trim($legacy['name']) === '') {
            throw new \InvalidArgumentException('Legacy result must include a non-empty name.');
        }

        if (!isset($legacy['status']) || !is_string($legacy['status'])) {
            throw new \InvalidArgumentException('Legacy result must include a recognized status.');
        }

        $status_map = array(
            'pass' => array(RuleResult::OUTCOME_PASS, RuleResult::SEVERITY_NONE),
            'info' => array(RuleResult::OUTCOME_FINDING, RuleResult::SEVERITY_INFO),
            'warning' => array(RuleResult::OUTCOME_FINDING, RuleResult::SEVERITY_WARNING),
            'critical' => array(RuleResult::OUTCOME_FINDING, RuleResult::SEVERITY_CRITICAL),
        );

        if (!isset($status_map[$legacy['status']])) {
            throw new \InvalidArgumentException(
                sprintf('Unsupported legacy diagnostic status "%s".', $legacy['status'])
            );
        }

        if (array_key_exists('id', $legacy)) {
            if (!is_string($legacy['id']) || trim($legacy['id']) === '') {
                throw new \InvalidArgumentException('Explicit legacy result ID must be a non-empty string.');
            }

            $legacy_id = $legacy['id'];

            if (preg_match('/^' . preg_quote($module_id, '/') . '\.[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/', $legacy_id)) {
                $result_id = $legacy_id;
            } elseif (preg_match('/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/', $legacy_id)) {
                // Qualify pre-contract legacy IDs without changing their check segment.
                $result_id = $module_id . '.' . $legacy_id;
            } else {
                throw new \InvalidArgumentException(
                    'Explicit legacy result ID must be a valid check ID for its module.'
                );
            }
        } else {
            $slug = strtolower($legacy['name']);
            $slug = preg_replace('/[^a-z0-9]+/', '_', $slug);
            $slug = trim($slug, '_');

            if (!preg_match('/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/', $slug)) {
                throw new \InvalidArgumentException('Legacy result name cannot produce a valid compatibility identifier.');
            }

            // Compatibility fallback only; integrated diagnostics must declare IDs explicitly.
            $result_id = $module_id . '.' . $slug;
        }

        return new RuleResult(array(
            'id'             => $result_id,
            'title'          => $legacy['name'],
            'outcome'        => $status_map[$legacy['status']][0],
            'severity'       => $status_map[$legacy['status']][1],
            'confidence'     => RuleResult::CONFIDENCE_UNKNOWN,
            'description'    => isset($legacy['description']) && is_string($legacy['description'])
                ? $legacy['description']
                : 'No description provided by the legacy diagnostic.',
            'value'          => isset($legacy['value']) ? $legacy['value'] : null,
            'recommendation' => isset($legacy['recommendation']) ? $legacy['recommendation'] : null,
            'evidence'       => isset($legacy['evidence']) ? $legacy['evidence'] : null,
            'module'         => $module_id,
        ));
    }

    /**
     * Adapt a list of legacy result arrays.
     *
     * This strict convenience method throws when an item is invalid or an ID
     * repeats. The DiagnosticManager must use adapt() per item so it can isolate
     * malformed entries and continue processing the remaining results.
     *
     * @param string $module_id Stable diagnostic module identifier.
     * @param array  $legacy_results List of legacy diagnostic result arrays.
     * @return RuleResult[]
     */
    public function adapt_all($module_id, array $legacy_results)
    {
        $results = array();

        foreach ($legacy_results as $legacy_result) {
            if (!is_array($legacy_result)) {
                throw new \InvalidArgumentException('Each legacy diagnostic result must be an array.');
            }

            $result = $this->adapt($module_id, $legacy_result);
            $result_id = $result->get('id');

            if (isset($results[$result_id])) {
                throw new \InvalidArgumentException(
                    sprintf('Duplicate legacy diagnostic ID "%s".', $result_id)
                );
            }

            $results[$result_id] = $result;
        }

        return $results;
    }
}
