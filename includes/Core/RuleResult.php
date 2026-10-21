<?php

/**
 * Normalized result for a single diagnostic check.
 *
 * This class is compatible with PHP 7.4 and intentionally has no WordPress
 * dependencies so its validation can be tested in isolation.
 *
 * @package WPDT\Core
 */

namespace WPDT\Core;

final class RuleResult
{
    const OUTCOME_PASS = 'pass';
    const OUTCOME_FINDING = 'finding';
    const OUTCOME_ERROR = 'error';
    const OUTCOME_SKIPPED = 'skipped';

    const SEVERITY_NONE = 'none';
    const SEVERITY_INFO = 'info';
    const SEVERITY_WARNING = 'warning';
    const SEVERITY_CRITICAL = 'critical';

    /** @var array<string, mixed> */
    private $data;

    /**
     * @param array $data Normalized result data.
     * @throws \InvalidArgumentException When fields or state combinations are invalid.
     */
    public function __construct(array $data)
    {
        $required = array('id', 'title', 'outcome', 'severity', 'description', 'module');

        foreach ($required as $field) {
            if (!isset($data[$field]) || !is_string($data[$field]) || trim($data[$field]) === '') {
                throw new \InvalidArgumentException(sprintf('Field "%s" must be a non-empty string.', $field));
            }
        }

        if (!preg_match('/^[a-z0-9][a-z0-9._-]*$/', $data['id'])) {
            throw new \InvalidArgumentException('Field "id" must be a stable lowercase identifier.');
        }

        $allowed_outcomes = array(
            self::OUTCOME_PASS,
            self::OUTCOME_FINDING,
            self::OUTCOME_ERROR,
            self::OUTCOME_SKIPPED,
        );

        if (!in_array($data['outcome'], $allowed_outcomes, true)) {
            throw new \InvalidArgumentException('Unsupported outcome.');
        }

        $allowed_severities = array(
            self::SEVERITY_NONE,
            self::SEVERITY_INFO,
            self::SEVERITY_WARNING,
            self::SEVERITY_CRITICAL,
        );

        if (!in_array($data['severity'], $allowed_severities, true)) {
            throw new \InvalidArgumentException('Unsupported severity.');
        }

        if ($data['outcome'] === self::OUTCOME_PASS && $data['severity'] !== self::SEVERITY_NONE) {
            throw new \InvalidArgumentException('A passing result must have severity "none".');
        }

        if ($data['outcome'] === self::OUTCOME_FINDING && $data['severity'] === self::SEVERITY_NONE) {
            throw new \InvalidArgumentException('A finding must have a non-none severity.');
        }

        if (in_array($data['outcome'], array(self::OUTCOME_ERROR, self::OUTCOME_SKIPPED), true)
            && $data['severity'] !== self::SEVERITY_NONE
        ) {
            throw new \InvalidArgumentException('Error and skipped results must have severity "none".');
        }

        $value = isset($data['value']) ? $data['value'] : null;
        if (!is_scalar($value) && $value !== null) {
            throw new \InvalidArgumentException('Field "value" must be scalar or null.');
        }

        $recommendation = isset($data['recommendation']) ? $data['recommendation'] : null;
        if ($recommendation !== null && !is_string($recommendation)) {
            throw new \InvalidArgumentException('Field "recommendation" must be a string or null.');
        }

        $evidence = isset($data['evidence']) ? $data['evidence'] : null;
        if ($evidence !== null && !is_string($evidence) && !is_array($evidence)) {
            throw new \InvalidArgumentException('Field "evidence" must be a string, array, or null.');
        }

        $this->data = array(
            'id'             => $data['id'],
            'title'          => $data['title'],
            'outcome'        => $data['outcome'],
            'severity'       => $data['severity'],
            'description'    => $data['description'],
            'value'          => $value,
            'recommendation' => $recommendation,
            'evidence'       => $evidence,
            'module'         => $data['module'],
        );
    }

    /**
     * Return the normalized result as an associative array.
     *
     * @return array<string, mixed>
     */
    public function to_array()
    {
        return $this->data;
    }

    /**
     * Read one normalized field.
     *
     * @param string $field Field name.
     * @return mixed
     * @throws \InvalidArgumentException When the field does not exist.
     */
    public function get($field)
    {
        if (!array_key_exists($field, $this->data)) {
            throw new \InvalidArgumentException(sprintf('Unknown result field "%s".', $field));
        }

        return $this->data[$field];
    }
}
