# Action Engine specification (proposed MVP)

**Status:** Architecture proposal. No write-enabled action engine is implemented in WP Diagnostic Toolkit.

## 1. Product boundary

The system is intentionally split into two independently installable plugins:

- **WP Diagnostic Toolkit (WPDT):** read-only collection, diagnostic rules, evidence, findings, and recommendations.
- **WP Operations Companion (working name):** explicitly authorized operations, previews, execution, audit records, and post-action verification.

The companion must remain usable when WPDT is absent or inactive. WPDT must never execute remediation actions. Diagnostic recommendations are advisory data, not executable commands.

This separation limits the blast radius of write permissions and prevents a diagnostic plugin from quietly becoming a site-maintenance executor.

## 2. MVP scope

The first implementation should be deliberately narrow:

1. A reusable action lifecycle and registry.
2. Capability and nonce validation.
3. Server-side preview records with expiry and integrity binding.
4. Explicit confirmation followed by fresh precondition validation.
5. Execution and post-action verification.
6. A persistent, redacted audit trail.
7. Two narrowly scoped write operations: delete one identified transient, and unschedule one exactly identified WP-Cron event.

Autoload inspection is read-only and belongs in WPDT, not in the companion. Changing autoload flags is outside the MVP.

Out of scope:

- Bulk cleanup by wildcard or heuristic.
- Arbitrary SQL execution.
- Editing arbitrary options or files.
- One-click “repair all”.
- Network-wide multisite operations.
- Generic rollback promises.
- REST endpoints for third-party remote execution.
- Automatic execution from a diagnostic finding.

## 3. Action contract

A proposed PHP 7.4-compatible interface:

```php
namespace WPOC\Core;

interface ActionInterface
{
    public function get_id();
    public function get_label();
    public function get_risk_level();
    public function get_required_capability();
    public function validate( array $args );
    public function preview( array $args );
    public function execute( array $args );
    public function verify( array $args, array $execution_result );
}
```

The interface is conceptual until implemented. Concrete actions must document:

- Stable action ID and version.
- Risk classification and required capability.
- Argument schema, normalization, and size limits.
- Preconditions and target identity.
- Preview contents and expiry policy.
- Whether the operation is idempotent.
- Verification method and possible verification outcomes.
- Whether recovery is possible, and its exact limits.

Do not require a universal `rollback()` method. Many WordPress operations cannot be safely reversed without preserving prior state, and a rollback method that cannot guarantee restoration would create false confidence.

## 4. Lifecycle and state machine

Proposed states:

`draft` → `validated` → `previewed` → `awaiting_confirmation` → `executing` → `completed`

Exceptional or terminal states:

- `failed`: execution did not complete successfully.
- `verification_failed`: execution returned, but the required postcondition was not observed.
- `cancelled`: the user or system cancelled before execution.
- `expired`: the preview expired or its target fingerprint changed.

A completed execution also stores a separate verification result: `verified` or `unknown`. Do not overload the lifecycle state with the verifier's three-valued result.

Expected flow:

1. The user selects one registered action.
2. The server validates and normalizes arguments.
3. The server checks capability and action-specific preconditions.
4. The action builds a preview without performing the mutation.
5. The server stores a short-lived preview record bound to the actor, site, action, normalized arguments, and target.
6. The UI presents the exact target, expected effect, risk, and limitations.
7. The user explicitly confirms that preview.
8. The server reloads the preview and rechecks capability, nonce, expiry, integrity, and current preconditions.
9. The executor uses the server-stored normalized arguments, not arguments resubmitted by the browser.
10. The action executes once under an idempotency/concurrency guard.
11. The result is persisted in the audit log.
12. The verifier checks the postcondition. A confirmed postcondition produces `completed` with verification `verified`; an indeterminate postcondition produces `completed` with verification `unknown`; a disproven required postcondition produces `verification_failed`.

A preview is not a reservation of the target's state. The target may change between preview and confirmation, so all relevant preconditions must be checked again immediately before execution.

## 5. Security model

### Authorization

- Initial capability: `manage_options`, subject to review for the target WordPress environment.
- A future dedicated capability such as `wpoc_execute_operations` may be introduced, but must not be granted automatically to additional roles.
- Check `current_user_can()` on every preview and execution request.
- A nonce mitigates CSRF; it is not authentication or authorization. Always combine nonce validation with capability checks.
- Never mutate site state through GET requests.
- All user-facing values must be escaped for their output context; all input must be validated and normalized.

### Preview integrity

A preview record must bind at least:

- Random, unguessable preview ID.
- Action ID and action version.
- User ID.
- Site/blog ID.
- Normalized argument hash and target fingerprint.
- Creation and expiry timestamps.
- Preview payload hash.
- One-time execution state.

Store the normalized arguments server-side. The browser should receive only the preview ID and display-safe preview data. Never trust a browser-submitted replacement target or argument set at confirmation time.

Before execution, recompute the target fingerprint and revalidate preconditions. If the target changed, expire the preview and require a new one.

### Concurrency and idempotency

- Prevent simultaneous execution of the same preview.
- Use an atomic state transition or database-level guard, not a check-then-act sequence vulnerable to races.
- Record a unique execution ID/idempotency key and enforce uniqueness in persistent storage.
- Do not rely on transients as the sole idempotency guarantee; WordPress transients may disappear before their nominal expiration.
- A repeated confirmation must return the existing execution state rather than perform the mutation again.

### Input and SQL safety

- Validate exact argument types, lengths, allowed values, and target identity.
- Define the minimum supported WordPress version before implementation. The current Toolkit documents a PHP minimum but does not yet declare a WordPress minimum; the companion must not assume that every helper or function signature is available on every supported installation.
- Isolate WordPress-version differences behind tested adapters. In particular, verify the availability and supported parameters of the cron inspection and unscheduling APIs before choosing the implementation path.
- Use WordPress APIs for WordPress-managed data whenever available.
- Use `$wpdb->prepare()` for SQL when SQL is necessary.
- Never accept SQL fragments, table names, or arbitrary hook names from an untrusted request without strict registry/schema validation.
- Do not serialize arbitrary objects into preview or audit storage.

## 6. Audit trail

Use a dedicated database table created/updated with `dbDelta()`. Do not treat `debug.log` as an operation history.

Minimum record fields:

- Execution ID and preview ID.
- Action ID and action version.
- Actor user ID.
- Site/blog ID.
- Created, started, and completed timestamps.
- Lifecycle status.
- Risk level.
- Redacted target summary.
- Normalized argument hash, not raw sensitive arguments.
- Result summary.
- Verification outcome.
- Error code/category, with safe diagnostic detail.

Initial retention proposal: 90 days, configurable. Retention cleanup must be bounded and itself auditable.

Never store passwords, tokens, cookies, authorization headers, full option values, full cron argument payloads, or unnecessary personal data. Prefer hashes and concise target descriptions.

## 7. Verification semantics

Verification must distinguish:

- `verified`: the intended postcondition was observed.
- `failed`: the postcondition was not observed or the operation clearly failed.
- `unknown`: the system cannot reliably determine the outcome.

A successful return from a WordPress API is not always proof that the desired external or persistent state is correct. The UI must not display “fixed” when verification is unknown.

## 8. Initial actions

### 8.1 Delete one transient

The action accepts an exact transient key and an explicit transient scope (site transient or regular transient). It must not accept wildcard patterns or a “delete all” mode. Validate the key as a bounded string, reject empty values and control characters, and do not silently normalize two different keys into the same target. The exact normalized key must be bound to the preview fingerprint.

Preview:

- Shows the exact key (safely truncated/redacted if necessary).
- Shows the scope.
- Checks whether the transient appears to exist, while explaining that the object-cache backend may affect what can be observed.
- States that deletion is not reversible unless the previous value was separately and safely preserved. The MVP should not preserve arbitrary transient values.

Execution:

- Use `delete_transient()` or `delete_site_transient()`, not direct SQL deletion from `wp_options`.
- Do not assume all transients live in the database; an external object cache may be active.
- Treat “not found” as a distinct no-op outcome, not as a successful deletion.
- Revalidate existence/target state immediately before mutation.

Verification:

- Check the same transient scope through the corresponding WordPress API.
- If the backend prevents reliable verification, record `unknown`, not `verified`.

### 8.2 Unschedule one WP-Cron event

The action identifies one exact event using:

- Hook name.
- Scheduled timestamp.
- Exact normalized argument set.
- Site/blog scope.

Cron arguments are part of event identity, not merely display metadata. The implementation must preserve their PHP types and array structure. For the MVP, accept only bounded, recursively nested arrays of scalar/null values; reject objects, resources, closures, and unsupported values. Do not reorder associative keys or coerce numeric strings to integers, because that can change the serialized argument identity used by WordPress. Store a safe summary for display and a server-side representation for execution; never place the full argument payload in the audit log.

Preview must display the hook, timestamp, and safe argument summary. It must not infer that an event is orphaned solely because a callback is not currently visible; callbacks may be registered dynamically.

Execution:

- Use `wp_unschedule_event()` with the exact timestamp, hook, and argument array. Pass optional parameters such as the `$wp_error` flag only when supported by the minimum WordPress version established for the companion.
- Treat a false return, `WP_Error`, and an already-missing event as distinct outcomes; never interpret a failed lookup as permission to unschedule a different event.
- Do not use a broad hook-clearing operation in the MVP.
- Revalidate that the exact event still exists and matches the preview immediately before unscheduling.
- If the event changed or disappeared, expire the preview or record a no-op; do not unschedule a different matching event.

Verification:

- Reinspect the scheduled event identity and confirm that the exact event is absent.
- Do not claim that every event for the hook was removed.

## 9. WordPress compatibility and platform boundaries

- Maintain PHP 7.4 compatibility in the first release.
- Declare and test an explicit minimum WordPress version before implementing the cron action.
- Use documented WordPress APIs where available and isolate version-dependent behavior behind adapters.
- Operate only on the current site/blog in the MVP.
- Multisite network-wide operations require a separate design, explicit network capability checks, and dedicated tests.
- Admin-post or AJAX handlers must enforce capability and nonce checks server-side.
- Do not expose execution endpoints to unauthenticated visitors.

## 10. Testing requirements

### Lifecycle and security

- Unauthorized user cannot preview or execute.
- Missing/invalid nonce blocks the request.
- Expired preview cannot execute.
- Modified preview ID, target, or arguments cannot change the server-stored operation.
- Double confirmation does not execute twice.
- Concurrent requests cannot execute the same preview twice.
- Capability revoked after preview blocks execution.
- Preconditions changed after preview require a new preview.
- Audit records are written for success, failure, and verification failure.
- Sensitive values are redacted.

### Transients

- Existing and missing regular transients.
- Existing and missing site transients.
- External object cache enabled.
- Transient expires between preview and confirmation.
- Repeated execution and verification uncertainty.

### WP-Cron

- Single and recurring events.
- Empty and complex argument arrays.
- Callback currently registered and not currently registered.
- Event removed or changed between preview and confirmation.
- Exact event is removed without clearing other events for the same hook.

### Integration

- Companion installed without WPDT.
- WPDT installed without companion.
- Both plugins active.
- WPDT deactivated after preview.
- Unsupported public API contract version.
- Diagnostic API failure does not prevent standalone action UI or corrupt action execution.

## 11. Acceptance criteria

- [ ] WPDT remains read-only; all mutations live in the companion.
- [ ] No action executes without authorization, valid nonce, explicit confirmation, and fresh precondition checks.
- [ ] Preview and execution use server-stored normalized arguments.
- [ ] One preview can produce at most one execution.
- [ ] Every execution has a persistent, redacted audit record.
- [ ] Verification distinguishes verified, failed, and unknown.
- [ ] No broad transient or cron cleanup is included.
- [ ] No universal rollback guarantee is presented.
- [ ] The companion works independently of WPDT.
- [ ] Automated tests cover the security, lifecycle, and operation cases above.

## 12. Implementation order

1. Review this threat model and narrow MVP scope.
2. Implement the action interface, registry, and result model without any write actions.
3. Implement preview persistence, confirmation, and state transitions.
4. Implement audit persistence and redaction.
5. Add a harmless test action to exercise the full lifecycle.
6. Add transient deletion with tests.
7. Add exact-event WP-Cron unscheduling with tests.
8. Build the admin UI only after lifecycle and security tests pass.

The Action Engine must be tested as a security-sensitive execution system before it is presented as a convenience feature.
