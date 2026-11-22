# RuleResult contract (draft v1)

## Purpose

Normalize diagnostic output so collectors, rules, reporting, and the admin UI do not depend on module-specific associative arrays. This document defines the normalized result contract used by the current migration implementation. Legacy diagnostic arrays are adapted by `LegacyResultAdapter`, normalized results are validated by `RuleResult`, and `DiagnosticResultPresenter` maintains compatibility with the existing admin templates.

## Required fields

- `id`: explicitly declared, stable machine-readable identifier in the form `<module_id>.<check_id>` (for example, `environment.php_version`). The check segment uses lowercase `snake_case`, starts with a letter, and must not be derived from presentation text by native producers.
- `title`: concise human-readable label.
- `outcome`: `pass`, `finding`, `error`, or `skipped`.
- `severity`: `none`, `info`, `warning`, or `critical`.
- `description`: what was checked and what the result means.
- `value`: scalar or null; the observed value, when applicable.
- `recommendation`: string or null; a proposed next step, not an automatic action.
- `evidence`: string, list, or structured data; enough context to explain the result without exposing secrets.
- `module`: stable module identifier such as `environment`, `plugins`, or `server`. Module IDs start with a lowercase letter and use lowercase letters, digits, underscores, and hyphens; dots are reserved as the separator in finding IDs.

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

The legacy mapping is intentionally explicit. The manager represents module execution failures and malformed outputs as `error` results; native `RuleResult` instances may also use `skipped` when a check is intentionally not applicable. The legacy presenter maps both to an informational or warning-compatible status while retaining the normalized `outcome` field.

## Stable identifier rules

1. Every `RuleResult` ID must match `<module_id>.<check_id>` and its prefix must equal the result's `module` field.
2. The check segment must match lowercase `snake_case` (`[a-z][a-z0-9]*(?:_[a-z0-9]+)*`).
3. Native diagnostic producers must declare IDs explicitly. Titles, descriptions, values, severity, and translations must never determine identity.
4. An ID identifies the check, not its current state. It remains unchanged when the outcome or severity changes.
5. IDs are unique across the complete diagnostic snapshot, not merely within one module.
6. Once exposed through a published integration contract, an ID is immutable. A necessary rename requires a documented migration rather than silent replacement.
7. `LegacyResultAdapter` temporarily qualifies old explicit check IDs with their module and can derive a fallback from a legacy title. This is a compatibility bridge only; those fallback IDs are not a stability guarantee and must not be used by new native producers.
8. Synthetic manager errors use the same namespace and check-segment grammar (for example, `environment.error_module_execution`).

## Existing identifier migration

The multilingual and WooCommerce diagnostic previously exposed four explicit, unnamespaced IDs. Their check segments are preserved; only the module namespace is added.

| Previous ID | Stable ID |
| --- | --- |
| `woocommerce_detected` | `multilingual_woocommerce.woocommerce_detected` |
| `multilingual_plugin_detection` | `multilingual_woocommerce.multilingual_plugin_detection` |
| `cart_session_scope` | `multilingual_woocommerce.cart_session_scope` |
| `translation_mapping` | `multilingual_woocommerce.translation_mapping` |

Other integrated diagnostics previously relied on the legacy adapter's title-derived fallback and did not expose explicit check IDs. Their new IDs are now declared by the diagnostic producers and covered by exact-set regression tests.

## Non-goals for v1

- No global health score or severity weighting.
- No database persistence or audit history.
- No automatic remediation.
- No breaking change to the legacy presenter shape while diagnostic IDs are migrated.
- No assumptions that all collectors can run outside WordPress.

## Acceptance criteria

1. The contract is documented before runtime refactoring.
2. Existing diagnostics can be adapted without losing their current label, value, description, recommendation, or evidence.
3. Unknown outcomes/severities are rejected or safely normalized; they are not silently presented as healthy.
4. Reporting can count passed checks, findings by severity, errors, and skipped checks separately.
5. Unit tests cover valid states, invalid combinations, and legacy mapping before the new contract becomes the dashboard's source of truth.
6. Every integrated diagnostic check declares an explicit namespaced ID, including checks generated from bounded runtime inventories.
7. A `RuleResult` with a missing module prefix, mismatched module prefix, or invalid check segment is rejected.
8. Module registration rejects IDs containing the reserved namespace separator.
9. Synthetic error results conform to the same ID grammar.