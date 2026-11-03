<?php

/**
 * Presents normalized diagnostic results to the existing admin interface.
 *
 * @package WPDT\\Core
 */

namespace WPDT\Core;

final class DiagnosticResultPresenter
{
    /**
     * Convert a normalized result into the backward-compatible admin shape.
     *
     * @param RuleResult $result Normalized diagnostic result.
     * @return array
     */
    public function present(RuleResult $result)
    {
        $data = $result->to_array();

        if ($data['outcome'] === RuleResult::OUTCOME_PASS) {
            $status = 'pass';
        } elseif ($data['outcome'] === RuleResult::OUTCOME_FINDING) {
            $status = $data['severity'];
        } elseif ($data['outcome'] === RuleResult::OUTCOME_ERROR) {
            $status = 'error';
        } else {
            $status = 'skipped';
        }

        $data['name'] = $data['title'];
        $data['status'] = $status;

        return $data;
    }
}
