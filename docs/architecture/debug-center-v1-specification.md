# Debug Center v1 specification

**Status:** Proposed implementation specification  
**Product:** WP Diagnostic Toolkit (WPDT)  
**Scope:** Read-only inspection of the WordPress debug log  
**Compatibility baseline:** PHP 7.4; minimum WordPress version must be declared by the project before implementation  
**Security boundary:** WordPress administrator capability (`manage_options`)

## 1. Product objective

Debug Center gives an authorized site administrator a protected place inside wp-admin to inspect an existing WordPress debug log, isolate relevant entries, understand what the available evidence does and does not prove, and copy a sanitized error or technical report for further investigation.

It is an investigation surface, not a repair tool and not a replacement for Query Monitor, server observability, or a full PHP debugger.

The v1 user workflow is:

1. Open Debug Center.
2. Review the current debugging/logging configuration and the detected log source.
3. Inspect a bounded, recent portion of the log.
4. Filter or search the entries available in that bounded window.
5. Open an entry to inspect its message and continuation/stack-trace lines.
6. Copy either the sanitized error or a structured diagnostic report.
7. Use the evidence to investigate the likely component and next verification steps.

The UI must clearly communicate when logging is disabled, the file is missing, the file is unreadable, the configured path is unsupported, or the available log window is incomplete.

## 2. Product boundary

WPDT remains read-only.

### Included in v1

- Inspect the WordPress debug constants and relevant PHP logging settings.
- Resolve and inspect the WordPress debug-log target under the path restrictions in this document.
- Read a bounded tail of an existing log file.
- Parse entries on a best-effort basis.
- Group identical entries within the loaded window.
- Filter by severity, source/component, and text.
- Display entry details and available continuation lines.
- Copy a sanitized individual error.
- Copy a structured, sanitized technical report.
- Explain the limitations of the evidence and suggest concrete verification steps.

### Explicitly excluded from v1

- Editing `wp-config.php`.
- Enabling or disabling `WP_DEBUG`, `WP_DEBUG_LOG`, or `WP_DEBUG_DISPLAY`.
- Changing PHP INI settings with `ini_set()`.
- Installing a global PHP error handler or shutdown handler.
- Starting or stopping a capture session.
- Clearing, truncating, rotating, downloading, or deleting log files.
- Reading arbitrary paths supplied by a request.
- Reading arbitrary PHP/server logs outside the approved WordPress debug-log location.
- Sending log contents to an external service.
- Automatically disabling plugins/themes or applying fixes.
- Claiming root cause solely from a log line.

Any future configuration changes or remediation actions belong in the separately installable WP Operations Companion, with its own preview, authorization, confirmation, audit, and verification lifecycle.

## 3. Authorization and request security

### Capability

Use the WordPress capability `manage_options` for the menu, page renderer, and every server-side data request.

This is the capability boundary, not a hard-coded role-name comparison. WordPress roles are capability containers; checking for a role string such as `administrator` is less portable and can exclude legitimate custom administrator roles.

The page must not render sensitive log content if `current_user_can( 'manage_options' )` is false. Every AJAX, REST, or other server-side endpoint introduced later must repeat the capability check. A hidden menu item is not authorization.

### Request handling

- v1 should prefer normal admin page GET parameters for filters and pagination. These requests are read-only and must not mutate site state.
- Validate and clamp every query parameter: severity against a fixed allowlist, search text to a bounded length, page/offset to a valid integer range.
- Never accept a filesystem path, filename, directory, or log source from the browser.
- Do not use a nonce as a substitute for authorization. If a future request changes state, require both capability and nonce validation.
- Escape all output for its HTML context.
- Do not put raw log entries into URLs, hidden inputs, inline JavaScript, or HTML attributes.
- Copy actions must use the sanitized strings already prepared for display; they must not trigger a second unbounded file read.

### Multisite

v1 is limited to the current site's wp-admin context and its site-level `manage_options` capability. Network-wide log discovery and network-administrator workflows are out of scope.

## 4. Debug configuration overview

Display a compact configuration summary with explicit states, not just raw constant values.

Inspect, when available:

- `WP_DEBUG`
- `WP_DEBUG_LOG`
- `WP_DEBUG_DISPLAY`
- `WP_ENVIRONMENT_TYPE`
- PHP `log_errors`
- PHP `error_log`

Use `defined()` before reading constants. Do not call WordPress core's private `wp_debug_mode()` function. Do not assume that a configured constant proves the effective PHP logger is using that path; hosting configuration, early bootstrap filters, or server configuration can change the effective behavior.

The UI should distinguish at least:

- **Debugging enabled:** `WP_DEBUG` is true.
- **Debug logging configured:** `WP_DEBUG` is true and `WP_DEBUG_LOG` resolves to a supported enabled value.
- **Debug display enabled:** `WP_DEBUG` is true and `WP_DEBUG_DISPLAY` is true or defaults to visible according to the observed configuration.
- **PHP logging enabled:** PHP `log_errors` is enabled.
- **Log target mismatch/uncertain:** the effective PHP `error_log` value does not match the resolved WP debug-log target, or cannot be reliably compared.
- **Logging inactive:** configuration indicates that WP debug logging is not active.
- **Configuration unknown:** values cannot be established reliably.

If `WP_DEBUG_DISPLAY` is enabled while debugging is active, show a security warning that errors may be exposed in rendered responses. Do not change the setting.

The configuration summary must not imply that setting `WP_DEBUG_LOG` to true is sufficient when `WP_DEBUG` is false. WordPress core only applies the debug-log behavior when debugging is enabled.

## 5. Log source resolution and filesystem boundary

### Supported source in v1

The only log source read in v1 is the WordPress debug log configured through `WP_DEBUG_LOG`:

- If the effective value is boolean true (and WordPress core's supported string equivalents are observed), resolve the default target as `WP_CONTENT_DIR/debug.log`.
- If it is a custom string path, treat that as the configured target for status reporting.
- If the constant is false, null, or another unsupported value, report that WP debug logging is not configured.

The WordPress debugging handbook documents both the default `wp-content/debug.log` target and a custom path. However, supporting a custom path in configuration does not grant WPDT permission to read arbitrary filesystem locations.

### Safe path policy

For v1, WPDT may read only a regular file whose canonical path is inside the canonical `WP_CONTENT_DIR` boundary.

Before opening the file:

1. Resolve the configured target without accepting any request-supplied path.
2. Resolve the canonical content directory with `realpath()`.
3. Resolve the target with `realpath()` when it exists.
4. Confirm the target is a regular file and its canonical path is within the content directory boundary, using a path-separator-aware comparison.
5. Reject paths that escape the allowed directory, including symlinks whose resolved target is outside it.
6. Check readability and file size before reading.
7. Open in binary read mode only (`rb`).

For a configured custom path outside `WP_CONTENT_DIR`, show a clear `unsupported_external_path` state and a safe basename or generic location label. Do not disclose the full absolute path and do not attempt to read it.

If the file does not exist, report `missing`; do not create it. If it is unreadable, report `unreadable`. If it is not a regular file, report `unsupported_file_type`.

Do not follow user-controlled paths, globs, directory traversal, or arbitrary symlink targets. Do not expose server filesystem paths in the UI or copied reports. Where useful, translate known WordPress paths to relative paths such as `wp-content/plugins/example/file.php`.

### PHP/server error logs

PHP's `error_log` INI directive may refer to a different file, syslog, or the SAPI/server logger. v1 may display the effective setting in a sanitized form for diagnostic context, but must not read that target. Report it as an external or host-managed log source that is outside v1's read scope.

## 6. Bounded log reader

The log reader must never load the entire file into memory.

Proposed v1 hard limits (constants, not user-configurable settings):

- Maximum bytes read per request: **512 KiB**.
- Maximum entries returned per page: **100**.
- Maximum rendered message/continuation content per entry: **32 KiB**.
- Maximum individual physical line accepted into the parser: **16 KiB** before safe truncation.
- Maximum search query length: **120 characters**.

Implementation guidance:

- Use `filesize()`, `fopen()`, `fseek()`, and bounded `fgets()` reads.
- Start from a byte offset near the end of the file to inspect recent entries.
- If the start offset falls in the middle of a physical line, discard the partial first line and mark the loaded window as truncated at its beginning.
- Stop reading when the byte budget is exhausted.
- Do not read beyond the captured file-size boundary for that request.
- Detect obvious truncation/rotation when the current file size is smaller than a requested older offset; reset pagination and show a notice rather than silently returning misleading entries.
- Treat a file that changes while being read as an unstable snapshot. Show a refresh notice and avoid claiming that counts or ordering are exhaustive.
- Do not persist raw log contents in options, transients, custom tables, or user metadata.
- Do not cache raw entries across users or requests in v1.

Pagination cursors must be validated integers bounded by the file size captured for the current request. The cursor controls only a byte offset within the already resolved approved file; it never controls the path.

If the file is larger than the configured read window, show that only the most recent bounded portion is available in the UI. Do not imply that search results cover the entire historical file.

## 7. Parser and entry model

Log formats vary by PHP version, hosting stack, and custom logging code. Parsing is best-effort and must preserve uncertainty.

### Severity categories

Normalize recognized messages into:

- `fatal`
- `error`
- `warning`
- `notice`
- `deprecated`
- `parse`
- `unknown`

Do not collapse unknown formats into a false positive. Always retain an `unknown` category for entries that cannot be classified confidently.

### Entry boundaries and multiline messages

- Recognize common PHP/WordPress timestamp-prefixed entry starts.
- Treat subsequent non-entry-start lines as continuation lines of the current entry, including stack traces.
- Preserve continuation ordering.
- If the parser cannot confidently identify a new entry boundary, retain the text as continuation or an unclassified entry rather than dropping it.
- Support common PHP error labels such as fatal error, parse error, warning, notice, deprecated, and recoverable fatal error.
- Do not assume every line contains a timestamp or that every host uses the same timestamp format.
- Mark an entry as truncated when the read boundary or per-entry limit cuts off its content.

### Internal normalized entry fields

The parser should produce an internal data structure with fields equivalent to:

- `entry_id`: stable only within the current file snapshot/window; not a permanent event ID.
- `timestamp`: parsed timestamp or null.
- `timestamp_raw`: sanitized original timestamp text when parsing is uncertain.
- `severity`: normalized category.
- `message`: sanitized primary message.
- `continuation`: sanitized continuation/stack-trace lines.
- `source_path`: safe relative path when recognized; otherwise null.
- `source_line`: parsed line number when recognized; otherwise null.
- `component_type`: `plugin`, `theme`, `mu-plugin`, `wordpress-core`, `unknown`, or `external`.
- `component_slug`: recognized plugin/theme directory slug when safe to infer.
- `is_truncated`: boolean.
- `parse_confidence`: `high`, `medium`, or `low`.

Do not expose internal parser objects as a public API in v1.

## 8. Component attribution and grouping

### Component attribution

Attribution is a heuristic based on a recognizable source path:

- `wp-content/plugins/{slug}/...` → plugin.
- `wp-content/mu-plugins/...` → must-use plugin.
- `wp-content/themes/{slug}/...` → theme.
- `wp-includes/...` or `wp-admin/...` → WordPress core.
- A path outside recognized WordPress roots → external or unknown, depending on available evidence.

A source path identifies where PHP reported the error, not necessarily the original cause. For example, a plugin file can surface an incompatibility caused by another component. The UI must label this as **reported location** or **probable component**, never as proven root cause.

### Grouping

Group repeated entries only within the currently loaded bounded window.

The initial grouping signature should include normalized severity, message, source path, and source line. Do not group solely by message text, because unrelated call sites may emit similar messages.

For each group, show:

- occurrence count in the loaded window;
- earliest and latest parsed timestamps when available;
- reported source;
- representative sanitized message.

Label the count as **occurrences in loaded log window**, not total site occurrences. Do not imply that grouping is exhaustive beyond the bytes read.

## 9. Filters and navigation

v1 filters:

- Severity: all, fatal, error, warning, notice, deprecated, parse, unknown.
- Component: all, recognized plugin/theme/core/mu-plugin, unknown/external.
- Text search: case-insensitive search over sanitized message and continuation content in the loaded window.
- Grouping: grouped or individual entries.

Filters operate only on the bounded set already read for the request. They must not trigger an unbounded scan of the full log.

The page should include:

- Configuration summary.
- Log source/status summary.
- Last-read/last-modified information where reliable.
- Loaded-window notice and truncation/rotation notices.
- Filter controls.
- Entry list with severity, timestamp, reported component, message excerpt, and occurrence count.
- Expandable or detail view for the full sanitized entry.
- Clear empty states for no file, empty file, no matches, unsupported path, and unreadable file.

## 10. Sanitization and privacy

Treat log contents as untrusted and potentially sensitive. Sanitization must occur before content is rendered or copied.

At minimum, redact recognizable forms of:

- Authorization headers and bearer/basic credentials.
- Password, secret, token, API key, access token, refresh token, nonce, cookie, and session values.
- URL query parameters whose names indicate credentials or session material.
- Database connection credentials if present in a logged string.
- Email addresses where they appear in copied diagnostic output, unless a future explicit reveal workflow is designed and authorized.

Path handling:

- Replace known absolute WordPress roots with safe relative paths.
- Do not disclose absolute server paths outside recognized WordPress roots.
- Do not include the complete configured custom log path in copy output.
- Do not expose raw log content in HTML data attributes or JavaScript source.

Sanitization is heuristic and cannot guarantee removal of every secret. The UI must include a short notice that copied diagnostic output should be reviewed before sharing publicly.

Do not offer a raw-copy or raw-download option in v1. A raw export would bypass the privacy boundary and substantially increase accidental disclosure risk.

## 11. Copy actions

Two explicit copy actions are required.

### Copy error

Copies one sanitized entry as plain text, including only available fields:

- Severity.
- Timestamp (or unavailable).
- Message.
- Reported source path and line (safe relative path only).
- Continuation/stack-trace lines, sanitized.
- Truncation notice when applicable.

### Copy technical report

Copies a structured plain-text report:

- Report title and generation timestamp.
- WordPress version and PHP version, if already available safely from the environment.
- Environment type, if available.
- Debug configuration summary.
- Log source status (never the full absolute path).
- Error severity and parsed timestamp.
- Occurrence count in the loaded window.
- Sanitized message and continuation.
- Reported component and source location.
- Parser confidence and truncation status.
- Evidence limitations.
- Suggested verification steps.

Suggested report structure:

```text
WP Diagnostic Toolkit — Debug Report

Severity: Fatal
Timestamp: 2026-11-10 14:32:08 UTC
Occurrences in loaded window: 3
Reported component: Plugin (example-plugin)
Source: wp-content/plugins/example-plugin/includes/file.php:128
Parser confidence: High

Error:
Call to undefined function example_function()

Context:
[Sanitized continuation lines]

Interpretation:
The log identifies the reported source location. It does not by itself prove
that this component is the root cause.

Suggested verification:
1. Confirm whether the function exists in the installed plugin version.
2. Check whether the required dependency is active and loaded.
3. Reproduce the request with the relevant plugin/dependency versions recorded.

Privacy:
Sensitive values were redacted heuristically. Review before sharing.
```

The report generator must not invent missing timestamps, source locations, versions, or causal explanations. Suggested verification steps should be generated from recognized error patterns and source context, or use a generic safe fallback when no reliable rule exists.

### Clipboard behavior

Use a small admin JavaScript helper to copy sanitized strings. Prefer the Clipboard API where available and provide a safe fallback for supported admin browsers. Show clear success/failure feedback. Copy buttons must not silently claim success when the browser denies clipboard access.

## 12. Interpretation and cause-finding

The first version should provide evidence-led guidance, not an opaque AI-style diagnosis.

Each recognized pattern may map to a deterministic rule containing:

- Pattern identifier.
- Applicable severity/error signature.
- Evidence required.
- Interpretation text.
- Confidence level.
- Suggested verification steps.
- Known limitations.

Rules must distinguish:

- **Observed fact:** what the log explicitly says.
- **Reported location:** the file/line PHP identifies.
- **Hypothesis:** a possible contributing component or condition.
- **Verification:** the next test required to confirm or reject the hypothesis.

Do not label a component as the root cause based only on its appearance in a stack trace. A stack frame is evidence of execution context, not proof of responsibility.

When no rule matches, show the raw sanitized evidence with a generic instruction to inspect the reported file, line, plugin/theme version, and request context.

## 13. Architecture proposal

Keep the implementation separated into small responsibilities:

- **DebugConfigurationInspector:** reads constants and relevant PHP INI values; no mutation.
- **DebugLogSourceResolver:** resolves the configured target and enforces the approved path boundary.
- **BoundedLogReader:** reads only a bounded byte window and reports file instability/truncation.
- **LogEntryParser:** recognizes entry boundaries, severity, timestamps, source paths, and continuation lines.
- **LogSanitizer:** redacts sensitive values and normalizes paths before presentation/copy.
- **LogEntryGrouper:** groups repeated entries within the loaded window.
- **ErrorPatternAnalyzer:** maps recognized signatures to evidence-led interpretations and verification steps.
- **DebugCenterPresenter:** prepares display-safe view data.
- **DebugCenter admin screen:** owns filters, navigation, entry detail, and copy controls.

Avoid a monolithic class that resolves paths, reads files, parses entries, analyzes causes, and renders HTML.

Do not introduce a public integration API for raw log entries in v1. If external consumers need diagnostic findings later, expose a separately reviewed, redacted summary contract rather than raw log contents.

## 14. Testing requirements

### Configuration and source resolution

- `WP_DEBUG` true/false/undefined.
- `WP_DEBUG_LOG` true, false, supported string path, and unsupported value.
- `WP_DEBUG_DISPLAY` true/false/null/undefined behavior as observable.
- PHP `log_errors` enabled/disabled.
- PHP `error_log` matching, different, empty, or syslog.
- Default file exists/missing/empty/unreadable.
- Custom path inside content directory.
- Custom path outside content directory is rejected without reading.
- Symlink inside content pointing outside is rejected.
- Directory/non-regular target is rejected.
- No request parameter can change the resolved path.

### Bounded reader

- Small file below the byte limit.
- Large file above the byte limit.
- Partial first line at the beginning of the read window.
- Long physical line.
- File truncated between pagination requests.
- File appended while being read.
- Invalid UTF-8 or unexpected binary bytes.
- Invalid/out-of-range cursor.
- No unbounded `file_get_contents()` call.

### Parser

- PHP fatal, parse, warning, notice, deprecated, and recoverable fatal formats.
- Timestamp present/absent/unrecognized.
- Multiline stack trace.
- Continuation lines that resemble plain text.
- Unknown host-specific format.
- Truncated entry.
- Multiple entries with identical messages from different source locations.

### Grouping and attribution

- Repeated identical entries in one loaded window.
- Same message from different files/lines remains separate.
- Plugin, mu-plugin, theme, WordPress core, external, and unknown paths.
- Counts are explicitly scoped to the loaded window.

### Privacy and copy

- Bearer/basic credentials.
- Password/token/API key/session/cookie/nonce patterns.
- Sensitive URL query parameters.
- Database credentials.
- Email addresses.
- Absolute WordPress and external server paths.
- Sanitized display and copy outputs match.
- No raw log content appears in HTML attributes, URLs, or persistent storage.
- Clipboard failure is surfaced accurately.

### Authorization

- User without `manage_options` cannot access the page or data.
- Direct requests cannot bypass page/menu checks.
- Filters cannot change the file path or exceed resource limits.
- Multisite site-level behavior is tested and network-wide access remains unavailable.

## 15. Acceptance criteria

- [ ] Debug Center is accessible only to users with `manage_options`.
- [ ] The page is read-only and never changes debug constants, PHP settings, or log files.
- [ ] Only the approved WordPress debug-log path inside the canonical content directory can be read.
- [ ] Custom external log paths are reported but not opened.
- [ ] File reads are bounded by fixed byte and entry limits.
- [ ] Large, rotated, changing, malformed, and unreadable files produce explicit states.
- [ ] Parser uncertainty and incomplete windows are visible to the user.
- [ ] Repeated entries are grouped only within the loaded window.
- [ ] Component attribution is presented as reported location/probable component, not proven root cause.
- [ ] All rendered and copied log content is sanitized.
- [ ] Both Copy error and Copy technical report are available.
- [ ] No raw log export or external transmission exists in v1.
- [ ] Tests cover path traversal/symlink escape, bounded reads, parsing, redaction, authorization, and clipboard failure.
- [ ] No runtime code is merged before the specification and test fixtures are reviewed.

## 16. Implementation sequence

1. Review and approve this specification, especially the path allowlist and redaction policy.
2. Add parser fixtures and unit tests using synthetic log samples.
3. Implement configuration inspection and safe source resolution.
4. Implement the bounded reader with rotation/truncation handling.
5. Implement parser, sanitizer, and grouping.
6. Implement the admin screen and filters.
7. Implement Copy error and Copy technical report.
8. Add deterministic interpretation rules only after parser output is reliable.
9. Run security review and test on LocalWP plus at least one environment with a custom debug-log path.

Do not add temporary capture or configuration editing until the read-only viewer has passed its security and compatibility tests.

## 17. Official references

- WordPress: [Debugging in WordPress](https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/)
- WordPress: [wp_debug_mode() reference](https://developer.wordpress.org/reference/functions/wp_debug_mode/) — core-private implementation reference; WPDT must not call this function.
- WordPress: [wp-config.php common APIs](https://developer.wordpress.org/apis/wp-config-php/)
- PHP: [Runtime configuration for error handling](https://www.php.net/manual/en/errorfunc.configuration.php)
- PHP: [error_log()](https://www.php.net/manual/en/function.error-log.php)
