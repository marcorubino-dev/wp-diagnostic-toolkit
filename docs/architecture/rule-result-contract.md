# RuleResult contract (draft v1)

## Purpose

Normalize diagnostic output so collectors, rules, reporting, and the admin UI do not depend on module-specific associative arrays. This is a design contract; existing runtime behavior is unchanged until an adapter and tests are added.

## Required fields

- `id`: stable machine-readable identifier for the check (for example, `environment.php_version`).
- `title`: concise human-readable label.
- `outcome`: `pass`, `finding`, `error`, or `skipped`.
- `severity`: `none`, `info`, `warning`, or `critical`.
- `description`: what was checked and what the result means.
- `value`: scalar or null; the observed value, when applicable.
- `recommendation`: string or null; a proposed next step, not an automatic action.
- `evidence`: string, list, or structured data; enough context to explain the result without exposing secrets.
- `module`: stable module identifier such as `environment`, `plugins`, or `server`.

## Semantics

- `pass` means the check ran and its configured condition passed. Severity must be `none`.
- `finding` means the check ran and found a condition worth reporting. Severity must be `info`, `warning`, or `critical`.
- `error` means the check could not produce a trustworthy result because its execution failed. It must not be reported as a confirmed site defect; include safe diagnostic evidence where possible.
- `skipped` means the check was intentionally not run, for example because a prerequisite was unavailable.
- Severity describes technical impact; priority is a later report-level decision and must not be inferred from severity alone.
- A recommendation is advisory. The plugin remains read-only and must not change site configuration or execute repairs.

## Legacy mapping

The current Toolkit uses `status` values `pass`, `info`, `warning`, and `critical` in result arrays.

| Legacy status | New outcome | New severity |
| --- | --- | --- |
| `pass` | `pass` | `none` |
| `info` | `finding` | `info` |
| `warning` | `finding` | `warning` |
| `critical` | `finding` | `critical` |

The legacy mapping is intentionally explicit. Execution failures and skipped checks will be introduced only where the implementation can reliably distinguish them from actual findings.

## Non-goals for v1

- No global health score or severity weighting.
- No database persistence or audit history.
- No automatic remediation.
- No breaking migration of existing diagnostic modules in the same change as the contract.
- No assumptions that all collectors can run outside WordPress.

## Acceptance criteria

1. The contract is documented before runtime refactoring.
2. Existing diagnostics can be adapted without losing their current label, value, description, recommendation, or evidence.
3. Unknown outcomes/severities are rejected or safely normalized; they are not silently presented as healthy.
4. Reporting can count passed checks, findings by severity, errors, and skipped checks separately.
5. Unit tests cover valid states, invalid combinations, and legacy mapping before the new contract becomes the dashboard's source of truth.
