<?php
defined('ABSPATH') || exit;

$labels = array(
    'enabled' => 'Enabled',
    'disabled' => 'Disabled',
    'unknown' => 'Unknown',
    'configured' => 'Configured',
    'not_configured' => 'Not configured',
    'default' => 'Default / host-managed',
    'syslog_or_sapi' => 'Syslog / SAPI',
    'external_or_host_managed' => 'External / host-managed',
);

$target_file_name = ! empty($target['relative_location'])
    ? basename($target['relative_location'])
    : 'Not available';

$target_labels = array(
    'not_configured' => 'Not configured',
    'unsupported_external_path' => 'External path (not inspected)',
    'missing' => 'Missing',
    'unreadable' => 'Unreadable',
    'unsupported_file_type' => 'Unsupported file type',
    'detected_readable' => 'Detected and readable',
    'unknown' => 'Unknown',
);

$configuration_rows = array(
    array('label' => 'WordPress environment', 'value' => $configuration['environment_type'], 'class' => 'neutral'),
    array('label' => 'WordPress debugging (WP_DEBUG)', 'value' => $labels[$configuration['wordpress_debug']] ?? 'Unknown', 'class' => $configuration['wordpress_debug'] === 'enabled' ? 'info' : 'neutral'),
    array('label' => 'WordPress debug logging (WP_DEBUG_LOG)', 'value' => $labels[$configuration['wordpress_debug_log']] ?? 'Unknown', 'class' => 'neutral'),
    array('label' => 'Error display (WP_DEBUG_DISPLAY)', 'value' => $labels[$configuration['wordpress_debug_display']] ?? 'Unknown', 'class' => $configuration['wordpress_debug_display'] === 'enabled' ? 'warning' : 'success'),
    array('label' => 'PHP error logging (log_errors)', 'value' => $labels[$configuration['php_log_errors']] ?? 'Unknown', 'class' => 'neutral'),
    array('label' => 'PHP error log target', 'value' => $labels[$configuration['php_error_log']] ?? 'Unknown', 'class' => 'neutral'),
);

$summary_rows = array(
    'WordPress environment' => $configuration['environment_type'],
    'WP_DEBUG' => $labels[$configuration['wordpress_debug']] ?? 'Unknown',
    'WP_DEBUG_LOG' => $labels[$configuration['wordpress_debug_log']] ?? 'Unknown',
    'WP_DEBUG_DISPLAY' => $labels[$configuration['wordpress_debug_display']] ?? 'Unknown',
    'PHP log_errors' => $labels[$configuration['php_log_errors']] ?? 'Unknown',
    'PHP error log target' => $labels[$configuration['php_error_log']] ?? 'Unknown',
    'WordPress debug log status' => $target_labels[$target['status'] ?? 'unknown'] ?? 'Unknown',
    'WordPress debug log location' => ! empty($target['relative_location'])
        ? $target['relative_location']
        : 'Not available',
);
$copy_summary = "WordPress Debug Summary\n";
foreach ($summary_rows as $label => $value) {
    $copy_summary .= $label . ': ' . $value . "\n";
}

$target_status = isset($target['status']) ? $target['status'] : 'unknown';
$target_status_class = 'neutral';
if ($target_status === 'detected_readable') {
    $target_status_class = 'success';
} elseif (in_array($target_status, array('missing', 'unreadable', 'unsupported_file_type'), true)) {
    $target_status_class = 'warning';
}
?>
<div class="wrap wpdt-admin wpdt-debug-center">
    <header class="wpdt-header">
        <div>
            <span class="wpdt-label">WORDPRESS DIAGNOSTICS</span>
            <h1>Debug Center</h1>
            <p>Understand how WordPress and PHP debugging are configured on this site.</p>
        </div>
        <div class="wpdt-header-action">
            <div class="wpdt-copy-control">
                <textarea class="wpdt-copy-source" hidden readonly><?php echo esc_textarea($copy_summary); ?></textarea>
                <button type="button" class="button button-secondary wpdt-copy-button" data-wpdt-copy>Copy summary</button>
                <span class="wpdt-copy-feedback" aria-live="polite"></span>
            </div>
            <span class="wpdt-readonly-badge"><span aria-hidden="true">●</span> Read-only</span>
        </div>
    </header>

    <section class="wpdt-section">
        <div class="wpdt-section-header">
            <h2>Debug configuration</h2>
            <p>These values are inspected without changing site or server settings.</p>
        </div>

        <div class="wpdt-debug-config-grid">
            <?php foreach ($configuration_rows as $row) : ?>
                <article class="wpdt-debug-config-item">
                    <span class="wpdt-debug-config-label"><?php echo esc_html($row['label']); ?></span>
                    <span class="wpdt-debug-value wpdt-debug-value-<?php echo esc_attr($row['class']); ?>">
                        <span class="wpdt-debug-value-dot" aria-hidden="true"></span>
                        <?php echo esc_html($row['value']); ?>
                    </span>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if (! empty($configuration['debug_display_warning'])) : ?>
            <div class="wpdt-debug-callout wpdt-debug-callout-warning">
                <strong>Potential information exposure</strong>
                <p>Debug display is enabled. PHP errors may be exposed in rendered responses.</p>
            </div>
        <?php endif; ?>
    </section>

    <section class="wpdt-section">
        <div class="wpdt-section-header">
            <h2>WordPress debug log</h2>
            <p>File metadata is checked only for supported targets inside the canonical wp-content directory.</p>
        </div>

        <article class="wpdt-debug-file-card">
            <div class="wpdt-debug-file-heading">
                <div class="wpdt-debug-file-icon" aria-hidden="true">LOG</div>
                <div class="wpdt-debug-file-name">
                    <h3><?php echo esc_html($target_file_name); ?></h3>
                    <p>WordPress debug log target</p>
                </div>
                <span class="wpdt-debug-value wpdt-debug-value-<?php echo esc_attr($target_status_class); ?>">
                    <span class="wpdt-debug-value-dot" aria-hidden="true"></span>
                    <?php echo esc_html($target_labels[$target_status] ?? 'Unknown'); ?>
                </span>
            </div>

            <div class="wpdt-debug-file-location">
                <span class="wpdt-debug-config-label">RELATIVE LOCATION</span>
                <code><?php echo ! empty($target['relative_location']) ? esc_html($target['relative_location']) : 'Not available'; ?></code>
            </div>
        </article>

        <div class="wpdt-debug-callout wpdt-debug-callout-info">
            <div class="wpdt-debug-callout-icon" aria-hidden="true">i</div>
            <div>
                <strong>Privacy by design</strong>
                <p>WP Diagnostic Toolkit checks file metadata only. It never opens, reads, displays, or downloads log contents. Log files can contain credentials, tokens, email addresses, request data, and internal server paths.</p>
            </div>
        </div>
    </section>

    <section class="wpdt-section">
        <div class="wpdt-section-header">
            <h2>How to access the log</h2>
            <p>Use your hosting environment to inspect the file manually.</p>
        </div>

        <div class="wpdt-debug-access-card">
            <ol class="wpdt-debug-steps">
                <li><span>1</span><p>Open your hosting control panel's File Manager, or connect using FTP/SFTP.</p></li>
                <li><span>2</span><p>Navigate to the relative location shown above, when available.</p></li>
                <li><span>3</span><p>Open or download the log using your hosting tool.</p></li>
                <li><span>4</span><p>Review and redact sensitive values before sharing an excerpt.</p></li>
            </ol>
        </div>

        <p class="wpdt-debug-footnote">A readable file may be empty or stale. This screen does not inspect its contents or prove that new errors are currently being written. PHP and server logs may be managed separately by your hosting provider.</p>
    </section>
</div>
