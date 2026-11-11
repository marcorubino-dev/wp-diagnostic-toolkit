# Debug Center v1 specification

**Status:** Revised implementation specification  
**Product:** WP Diagnostic Toolkit (WPDT)  
**Scope:** Read-only debug configuration and log-location inspection  
**Compatibility baseline:** PHP 7.4; minimum WordPress version must be declared by the project before implementation  
**Security boundary:** WordPress administrator capability (manage_options)

## 1. Product objective

Debug Center gives an authorized site administrator a concise view of the site's WordPress debugging configuration and whether the expected WordPress debug-log file is present and accessible.

It helps the administrator understand where to look next without exposing potentially sensitive log contents inside wp-admin.

Debug Center is an inspection surface, not a repair tool, log viewer, server observability platform, or PHP debugger.

The v1 user workflow is:

1. Open Debug Center.
2. Review the effective values that WPDT can safely inspect for WordPress and PHP debugging.
3. Review whether the expected debug-log target is configured, detected, and readable within the approved filesystem boundary.
4. If log contents are needed, access the file through the hosting provider's File Manager or FTP/SFTP client.
5. Review and redact sensitive information before sharing any excerpt with another person or tool.

## 2. Product boundary

WPDT remains read-only.

### Included in v1

- Inspect the WordPress debug constants and relevant PHP logging settings.
- Determine the expected WordPress debug-log target from configuration, without accepting a path from the browser.
- Check file existence, regular-file status, and readability only for an approved target within the canonical WP_CONTENT_DIR boundary.
- Display a safe relative location when it can be established.
- Report clear states for disabled logging, missing files, unreadable files, unsupported external paths, and unknown configuration.
- Explain how an administrator can access the log through the hosting File Manager or FTP/SFTP.
- Provide a copy action for the safe relative location and concise diagnostic status, without copying log contents.

### Explicitly excluded from v1

- Reading, previewing, parsing, searching, grouping, or displaying log contents.
- Downloading or exporting log files.
- Reading PHP, web-server, hosting, syslog, or arbitrary external logs.
- Accepting arbitrary filesystem paths from request parameters.
- Editing wp-config.php or changing WordPress debug constants.
- Changing PHP INI settings with ini_set().
- Installing global PHP error or shutdown handlers.
- Starting or stopping capture sessions.
- Clearing, truncating, rotating, or deleting log files.
- Sending configuration or log data to an external service.
- Automatically disabling plugins/themes or applying fixes.

Any future configuration changes or remediation actions belong in the separately installable WP Operations Companion, with its own preview, authorization, confirmation, audit, and verification lifecycle.

## 3. Authorization and request security

### Capability

Use the WordPress capability manage_options for the menu, page renderer, and every server-side data request.

This is a capability boundary, not a hard-coded role-name comparison. WordPress roles are capability containers; checking for a role string such as administrator is less portable and can exclude legitimate custom administrator roles.

The page must not disclose configuration or filesystem metadata if current_user_can( 'manage_options' ) is false. Every AJAX, REST, or other server-side endpoint introduced later must repeat the capability check. A hidden menu item is not authorization.

### Request handling

- v1 should use a normal admin page request. No asynchronous endpoint is required for the initial implementation.
- Do not accept a filesystem path, filename, directory, or log source from the browser.
- Do not use a nonce as a substitute for authorization. If a future request changes state, require both capability and nonce validation.
- Escape all output for its HTML context.
- Do not put absolute filesystem paths or sensitive configuration values into URLs, hidden inputs, inline JavaScript, or HTML attributes.
- The copy action must use only the safe, server-prepared status/location summary. It must never trigger a file-content read.

### Multisite

v1 is limited to the current site's wp-admin context and its site-level manage_options capability. Network-wide log discovery and network-administrator workflows are out of scope.

## 4. Debug configuration overview

Display a compact configuration summary with explicit states, not just raw constant values.

Inspect, when available:

- WP_DEBUG
- WP_DEBUG_LOG
- WP_DEBUG_DISPLAY
- WP_ENVIRONMENT_TYPE
- PHP log_errors
- PHP error_log

Use defined() before reading constants. Do not call WordPress core's private wp_debug_mode() function. Do not assume that a configured constant proves the effective PHP logger is writing to that path; hosting configuration, bootstrap behavior, or server configuration can change effective logging.

The UI should distinguish at least:

- **Debugging enabled:** WP_DEBUG is true.
- **Debug logging configured:** WP_DEBUG is true and WP_DEBUG_LOG resolves to a supported enabled value.
- **Debug display enabled:** WP_DEBUG is true and WP_DEBUG_DISPLAY is explicitly true or otherwise observably enabled.
- **PHP logging enabled:** PHP log_errors is enabled.
- **Log target uncertain:** the effective PHP error_log value does not match the expected WordPress debug-log target, or cannot be reliably compared.
- **Logging inactive:** configuration indicates that WordPress debug logging is not active.
- **Configuration unknown:** values cannot be established reliably.

If WP_DEBUG_DISPLAY is enabled while debugging is active, show a security warning that errors may be exposed in rendered responses. Do not change the setting.

The configuration summary must not imply that setting WP_DEBUG_LOG to true is sufficient when WP_DEBUG is false. WordPress core applies its debug-log behavior in the context of WP_DEBUG.

Do not display sensitive PHP error_log values verbatim. Classify them as default, empty, syslog/SAPI, a safe in-content relative path, or external/host-managed. Avoid exposing absolute server paths.

## 5. Log target resolution and filesystem boundary

### Supported target in v1

The only file whose metadata may be inspected is the WordPress debug-log target configured through WP_DEBUG_LOG:

- If the effective value is boolean true (and supported string equivalents are observed), resolve the default target as WP_CONTENT_DIR/debug.log.
- If it is a custom string path, treat it as the configured target for status reporting.
- If the constant is false, null, or another unsupported value, report that WordPress debug logging is not configured.

Supporting a custom path in WordPress configuration does not grant WPDT permission to inspect arbitrary filesystem locations.

### Safe metadata policy

For v1, WPDT may check metadata only for a regular file whose canonical path is inside the canonical WP_CONTENT_DIR boundary. WPDT must not open the file for reading, even when it is approved.

Before checking file metadata:

1. Resolve the configured target without accepting any request-supplied path.
2. Resolve the canonical content directory with realpath().
3. Resolve the target with realpath() when it exists.
4. Confirm that the target is a regular file and its canonical path is within the content-directory boundary, using a path-separator-aware comparison.
5. Reject paths that escape the allowed directory, including symlinks whose resolved target is outside it.
6. Check file existence, file type, and readability only after the boundary check.
7. Do not call fopen(), file_get_contents(), readfile(), file(), SplFileObject, or equivalent content-reading APIs.

For a configured custom path outside WP_CONTENT_DIR, show an unsupported_external_path state and, at most, a generic external-location label. Do not disclose the full absolute path and do not attempt to inspect or read the target.

If the file does not exist, report missing; do not create it. If it is unreadable, report unreadable. If it is not a regular file, report unsupported_file_type.

Do not follow user-controlled paths, globs, directory traversal, or arbitrary symlink targets. Do not expose server filesystem paths in the UI or copied status. Where useful, translate a verified path under WP_CONTENT_DIR to a safe relative path such as wp-content/debug.log.

### PHP/server error logs

PHP error_log may refer to a different file, syslog, or the SAPI/server logger. v1 may classify the effective setting in a sanitized form for diagnostic context, but must not inspect its target or read its contents. Report it as an external or host-managed log source outside v1's scope.

## 6. Status model and user guidance

The source inspector should return a small normalized status model. Suggested states:

- not_configured
- configured_default
- configured_custom_in_content
- unsupported_external_path
- missing
- unreadable
- unsupported_file_type
- detected_readable
- unknown

The status must distinguish configuration from observation. For example, configured_default means configuration points to the default target; detected_readable means the expected file exists, is a regular file, and passes the readability check. Neither state proves that new errors are currently being written.

The page should explain relevant limitations:

- A readable file may be empty or stale; v1 does not inspect its contents or prove that logging is currently writing.
- A missing file may mean no error has been logged yet, logging is disabled, or the host routes errors elsewhere.
- A custom path outside the approved boundary must be checked through the hosting environment.
- PHP/server logs may be controlled by the hosting provider and may not be accessible from WordPress.

### Suggested UI content

**Debug Center**

Configuration:
- WordPress debugging: Enabled / Disabled / Unknown
- WordPress debug logging: Configured / Not configured / Unknown
- Error display: Enabled / Disabled / Unknown
- PHP error logging: Enabled / Disabled / Unknown

Log target:
- File: debug.log
- Location: wp-content/debug.log (only when safely established)
- Status: Detected and readable / Missing / Unreadable / External path / Not configured / Unknown

Security notice:
"WP Diagnostic Toolkit does not display or download log contents. Log files can contain credentials, tokens, email addresses, request data, and internal server paths."

Access instructions:
1. Open your hosting control panel's File Manager, or connect using FTP/SFTP.
2. Navigate to the displayed relative location, when available.
3. Open or download the log using your hosting tool.
4. Review and redact sensitive values before sharing an excerpt.

If the target is external or unknown, direct the administrator to the hosting provider's documentation or support rather than inventing a filesystem location.

### Copy action

A single optional copy action may copy a concise status summary containing:
- Debug configuration states.
- Normalized log target state.
- Safe relative path, only when verified.
- A reminder that WPDT does not inspect log contents.

It must not copy absolute paths, raw INI values, or log contents. Clipboard success/failure feedback must be accurate.

## 7. Privacy and data minimization

The safest log-content handling policy for v1 is not to acquire log contents at all.

- Do not read or buffer log contents.
- Do not store log contents in options, transients, custom tables, user metadata, or caches.
- Do not include log contents in HTML, JavaScript, URLs, or copied output.
- Do not send log contents or configuration data to an external service.
- Avoid disclosing absolute paths, usernames, hostnames, and raw PHP error_log values.
- Escape all displayed values and use allowlisted status labels wherever possible.

Even metadata can reveal deployment structure. Only show a relative path after canonical boundary validation; otherwise use a generic label.

## 8. Future Log Analyzer (not part of v1)

A manual Log Analyzer may be considered as a separate, later feature. It must not be treated as part of the Debug Center v1 implementation or block its release.

The proposed workflow is:

1. The administrator obtains a relevant excerpt independently through the hosting File Manager or FTP/SFTP.
2. The administrator reviews and removes sensitive information before pasting.
3. The administrator pastes the excerpt into a bounded input field.
4. WPDT validates and analyzes the submitted text in memory for that request.
5. WPDT displays a sanitized interpretation that separates observed facts, reported locations, hypotheses, and verification steps.
6. The administrator may copy the resulting report after reviewing it.

Potential safeguards for a future specification:

- Strict total input and line-count limits.
- Per-line length limits and safe truncation.
- No persistence, caching, telemetry, or external transmission.
- Input treated as untrusted data and escaped on output.
- Redaction before display or report generation, with an explicit warning that redaction is heuristic.
- Multiline stack-trace preservation.
- Deterministic pattern rules initially; no claim of AI diagnosis or proven root cause.
- Synthetic fixtures and tests for malformed input, secrets, paths, and parser uncertainty.

The Log Analyzer requires its own product/security review and acceptance criteria before implementation. Do not add its UI, parser, or classes to the Debug Center v1 branch.

## 9. Architecture proposal

Keep v1 responsibilities small:

- **DebugConfigurationInspector:** reads constants and relevant PHP INI values; no mutation.
- **DebugLogTargetResolver:** resolves the configured target and enforces the approved canonical path boundary.
- **DebugLogMetadataInspector:** checks only existence, regular-file status, and readability for an approved target; never opens or reads the file.
- **DebugCenterPresenter:** prepares display-safe configuration and status data.
- **DebugCenter admin screen:** renders the summary, safe location, access instructions, and optional copy-status action.

Do not introduce a log reader, parser, grouper, content sanitizer, error-pattern analyzer, or public API for raw log entries in v1.

Avoid a monolithic class that resolves paths, inspects metadata, and renders HTML.

## 10. Testing requirements

### Configuration and target resolution

- WP_DEBUG true, false, and undefined.
- WP_DEBUG_LOG true, false, supported string path, and unsupported value.
- WP_DEBUG_DISPLAY true, false, null, and undefined behavior as observable.
- PHP log_errors enabled/disabled.
- PHP error_log empty, default, syslog/SAPI, safe in-content path, and external path.
- Default target exists/missing/empty/unreadable.
- Custom path inside content directory.
- Custom path outside content directory is rejected without inspection.
- Symlink inside content pointing outside is rejected.
- Directory/non-regular target is rejected.
- No request parameter can change the resolved target.
- Canonical path comparison handles directory-prefix collisions safely.

### No-content-access guarantee

- Assert that no file-content reading API is invoked.
- Assert that no file handle is opened for the log target.
- Assert that log contents never appear in rendered HTML, JavaScript, URLs, or copied status.
- Assert that no log content is persisted or transmitted.

### Authorization and presentation

- User without manage_options cannot access the page or its data.
- Direct requests cannot bypass page/menu checks.
- Absolute paths and raw PHP error_log values are not disclosed.
- Only verified safe relative paths are displayed.
- All output is escaped.
- Clipboard failure is surfaced accurately.
- Multisite site-level behavior is tested and network-wide access remains unavailable.

## 11. Acceptance criteria

- [ ] Debug Center is accessible only to users with manage_options.
- [ ] The page is read-only and never changes debug constants, PHP settings, or log files.
- [ ] Configuration values are represented with explicit enabled/disabled/unknown states.
- [ ] Only the configured WordPress debug-log target within the canonical content-directory boundary may have metadata inspected.
- [ ] No log file is opened or read, regardless of whether it is inside the approved directory.
- [ ] Custom external log paths are reported generically and are not inspected.
- [ ] Absolute server paths and raw PHP error_log values are not disclosed.
- [ ] The UI distinguishes configured state from observed file metadata and does not claim that logging is actively writing.
- [ ] The administrator receives practical File Manager or FTP/SFTP access instructions.
- [ ] No log content is rendered, copied, exported, persisted, cached, or transmitted.
- [ ] Tests prove the absence of file-content access and cover path traversal/symlink escape, configuration, metadata, authorization, and output escaping.
- [ ] The future manual Log Analyzer remains explicitly out of scope for v1.

## 12. Implementation sequence

1. Review and approve this revised scope.
2. Add unit tests for configuration interpretation and safe target resolution.
3. Implement metadata-only inspection with a strict canonical path boundary.
4. Implement the Debug Center admin screen and safe status presentation.
5. Add access instructions and optional copy-status action.
6. Test on LocalWP and at least one environment with a custom debug-log configuration.
7. Conduct a security review confirming that no file-content access occurs.

Do not add log viewing, parsing, downloading, capture, configuration editing, or remediation actions to the Debug Center v1 implementation.

## 13. Official references

- WordPress: [Debugging in WordPress](https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/)
- WordPress: [wp_debug_mode() reference](https://developer.wordpress.org/reference/functions/wp_debug_mode/) — core implementation reference; WPDT must not call this function.
- WordPress: [wp-config.php common APIs](https://developer.wordpress.org/apis/wp-config-php/)
- PHP: [Runtime configuration for error handling](https://www.php.net/manual/en/errorfunc.configuration.php)
- PHP: [error_log()](https://www.php.net/manual/en/function.error-log.php)
