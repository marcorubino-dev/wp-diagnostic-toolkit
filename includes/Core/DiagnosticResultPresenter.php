<?php

/**
 * Presents normalized diagnostic results to the existing admin interface.
 *
 * @package WPDT\Core
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
            $status = 'warning';
        } else {
            $status = 'info';
        }

        $data['name'] = $data['title'];
        $data['status'] = $status;

        if (is_array($data['evidence'])) {
            $data['evidence_data'] = $data['evidence'];
            $encoded_evidence = json_encode(
                $data['evidence'],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );

            $data['evidence'] = $encoded_evidence !== false
                ? $encoded_evidence
                : 'Structured evidence could not be encoded.';
        }

        return $data;
    }
}
