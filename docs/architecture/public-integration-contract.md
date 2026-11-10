# Public integration contract (proposed v1)

**Status:** Architecture proposal. This contract is not implemented by the current plugin release.

## 1. Purpose and boundary

WP Diagnostic Toolkit (WPDT) is the read-only diagnostic provider. A separately installed operations plugin may consume selected diagnostic information, but it must not depend on WPDT's internal PHP classes, admin templates, presenter output, or private storage.

The integration boundary is a versioned PHP API that returns plain associative data. It is intentionally not a REST API in v1. The consumer must continue to work when WPDT is absent, inactive, or incompatible.

This document describes a target contract. It does not claim that the public function, constants, or selective execution method already exist.

## 2. Compatibility principles

- Keep the plugin's current PHP 7.4 minimum for the first public API release.
- Version the public contract independently from the plugin release.
- Expose data, not internal objects. Consumers must not receive `RuleResult`, `DiagnosticManager`, `DiagnosticResultPresenter`, or module instances.
- Keep module and finding identifiers stable once published.
- Add optional fields compatibly within a contract major version. Do not silently change field meaning, identifier semantics, or value types.
- A breaking change requires a new major contract version and an explicit migration path.
- The consumer must check the contract version and tolerate unknown optional fields and module IDs.

## 3. Proposed public entry point

The proposed public API is:

```php
wpdt_get_diagnostic_snapshot( $args = array() );
```

The function will be guarded by `function_exists()` and documented as the supported integration entry point. Its implementation must be added only after the contract and tests are approved.

The proposed entry point requires an explicit, non-empty list of module IDs:

```php
wpdt_get_diagnostic_snapshot( array $module_ids );
```

There is deliberately no "all modules" default on the public integration boundary. The Toolkit may retain an internal all-modules operation for its own dashboard, but external consumers must request only the modules they need.

- Every item must be a valid registered module ID.
- An empty list, malformed ID, or unknown module ID returns a `WP_Error`; it must never broaden the request.
- The API does not accept caller context, capabilities, or arbitrary execution options. Those concerns belong to the calling plugin and its own authorization layer.

The implementation should return a `WP_Error` for invalid arguments or an unavailable API condition, and an array for a successful snapshot. It must not throw implementation exceptions across the public boundary.

## 4. Snapshot shape

A successful response uses plain PHP arrays and scalar values:

```php
array(
    'contract_version' => '1.0.0',
    'toolkit_version'  => '0.4.0',
    'generated_at'     => '2026-01-01T12:00:00+00:00',
    'modules'          => array(
        'environment' => array(
            'status'   => 'completed',
            'findings' => array(
                array(
                    'finding_id'    => 'environment.php_version',
                    'module_id'     => 'environment',
                    'title'         => 'PHP version',
                    'outcome'       => 'pass',
                    'severity'      => 'none',
                    'confidence'    => 'direct',
                    'description'   => 'The observed PHP version meets the configured requirement.',
                    'value'         => '8.3.0',
                    'recommendation'=> null,
                    'evidence'      => array(),
                ),
            ),
        ),
    ),
);
```

The example is illustrative, not a claim about current runtime output or the current plugin version.

### Top-level fields

- `contract_version`: semantic version of this public data contract, matching the public constant `WPDT_PUBLIC_API_VERSION` exposed by the Toolkit.
- `toolkit_version`: installed WPDT release version.
- `generated_at`: ISO 8601 timestamp with timezone.
- `modules`: map keyed by stable module ID.

### Module fields

- `status`: `completed`, `error`, `skipped`, or `unavailable`.
- `findings`: list of normalized findings. An empty list is valid and does not, by itself, mean that execution failed.

Status semantics:

- `completed`: the module ran to completion, even if one or more individual checks returned an `error` outcome.
- `error`: the module itself failed or returned an invalid module-level result.
- `skipped`: the module was deliberately not executed because a documented prerequisite or policy prevented it.
- `unavailable`: the requested module ID is not registered in this Toolkit version. The public API should normally return a `WP_Error` for unknown requested IDs rather than silently omit them.

### Finding fields

| Field | Type | Contract |
| --- | --- | --- |
| `finding_id` | string | Stable lowercase identifier, unique within the snapshot. |
| `module_id` | string | Stable ID of the producing module. |
| `title` | string | Human-readable label. |
| `outcome` | string | `pass`, `finding`, `error`, or `skipped`. |
| `severity` | string | `none`, `info`, `warning`, or `critical`. |
| `confidence` | string | `direct`, `inferred`, or `unknown`. |
| `description` | string | What was checked and what the observation means. |
| `value` | scalar or null | Safe observed value, when applicable. |
| `recommendation` | string or null | Advisory next step. It is never an executable instruction. |
| `evidence` | array/string/null | Minimal supporting evidence, redacted before leaving WPDT. |

The public finding model is a projection of the internal normalized result model. Internal field names may differ, and the public API must not expose the presenter-specific shape.

## 5. Outcome semantics

- `pass`: the check ran and its configured condition passed. Severity is `none`.
- `finding`: the check ran and observed a condition worth reporting. Severity is `info`, `warning`, or `critical`.
- `error`: the check could not produce a trustworthy result. This is not proof of a site defect. Severity is `none`.
- `skipped`: the check was intentionally not run or was not applicable. Severity is `none`.

Module status describes execution of the module; finding outcome describes an individual check. They are not interchangeable.

## 6. Selective execution and resource boundaries

The current manager exposes `run_all()`, which returns presenter-compatible data rather than a public integration payload. A future implementation must add a selective execution path (for example, `run_selected( array $module_ids )`) that returns normalized module results before the public projection is built. The public function must not call `run_all()` and then discard unrequested modules: that would still execute their checks and could trigger unnecessary HTTP or filesystem work.

Requirements:

1. Execute only requested, registered modules.
2. Preserve result isolation: one module failure must not discard valid results from another requested module.
3. Do not execute unrequested modules as a side effect of rendering a single-module screen or fulfilling an integration request.
4. Reject or explicitly report unknown IDs.
5. Apply bounded execution and safe error handling to modules that perform HTTP or filesystem checks.
6. Do not let caller-supplied context or module IDs bypass WordPress capability checks in the consumer's own UI.

The public API is not a permission system. A consuming plugin must authorize its own user-facing operation independently.

## 7. Privacy and evidence rules

Public output must be safe to pass between plugins and safe to retain briefly for an operation preview.

- Never expose passwords, authentication tokens, cookies, authorization headers, salts, private keys, or full environment dumps.
- Avoid returning full option values, arbitrary database rows, full filesystem paths where unnecessary, or personally identifiable data.
- Redact URLs that contain credentials or sensitive query parameters.
- Bound evidence length and collection size.
- Do not expose raw exception traces or secrets in public findings. Keep detailed debugging in an appropriately protected development log, with redaction.
- Consumers must treat all returned strings as untrusted data and escape them for the output context.

## 8. Optional integration behavior

A companion plugin may detect WPDT with `function_exists( 'wpdt_get_diagnostic_snapshot' )` and verify the contract version before consuming a snapshot.

- If WPDT is not installed or inactive, the companion remains usable with its own standalone interface.
- If the function exists but the contract major version is unsupported, disable only the integration and show a clear compatibility message.
- Do not instantiate WPDT classes or include its files directly.
- Do not make the companion's core action execution depend on a successful diagnostic snapshot.

## 9. Acceptance criteria

- [ ] The public function and contract version are implemented and documented before external integrations depend on them.
- [ ] The function returns arrays or `WP_Error`, never internal WPDT objects.
- [ ] Selective execution runs only requested modules.
- [ ] Module failures do not erase valid sibling results.
- [ ] IDs, outcomes, severity, confidence, and timestamp formats are validated.
- [ ] Sensitive values are redacted and output sizes are bounded.
- [ ] Compatibility tests cover WPDT absent, inactive, supported, and unsupported contract versions.
- [ ] Tests verify that consumers do not depend on admin presenter output or internal classes.

## 10. Implementation sequence

1. Approve this contract and freeze the v1 field semantics.
2. Define and document the supported minimum WordPress version for the public API.
3. Add and test selective module execution in WPDT.
4. Add the public projection, `WPDT_PUBLIC_API_VERSION` constant, and function.
5. Add contract-level tests and documentation examples.
6. Only then implement the companion adapter.

Until these steps are complete, integrations must treat the API as a design proposal, not a callable interface.
