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
     * IDs are derived from the existing label only during this migration phase.
     * They should become explicit constants in each check before labels change.
     *
     * @param string $module_id Stable diagnostic module identifier.
     * @param array  $legacy   Existing diagnostic result array.
     * @return RuleResult
     * @throws \InvalidArgumentException When a legacy result cannot be mapped safely.
     */
    public function adapt($module_id, array $legacy)
    {
        if (!is_string($module_id) || trim($module_id) === '') {
            throw new \InvalidArgumentException('Module ID must be a non-empty string.');
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

        $slug = strtolower($legacy['name']);
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug);
        $slug = trim($slug, '_');

        if ($slug === '') {
            throw new \InvalidArgumentException('Legacy result name cannot produce a stable identifier.');
        }

        return new RuleResult(array(
            'id'             => $module_id . '.' . $slug,
            'title'          => $legacy['name'],
            'outcome'        => $status_map[$legacy['status']][0],
            'severity'       => $status_map[$legacy['status']][1],
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
            $results[$result->get('id')] = $result;
        }

        return $results;
    }
}
