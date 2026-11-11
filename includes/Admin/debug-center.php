<?php
defined('ABSPATH') || exit;
$labels = array('enabled'=>'Enabled','disabled'=>'Disabled','unknown'=>'Unknown','configured'=>'Configured','not_configured'=>'Not configured','default'=>'Default / host-managed','syslog_or_sapi'=>'Syslog / SAPI','external_or_host_managed'=>'External / host-managed');
$target_labels = array('not_configured'=>'Not configured','unsupported_external_path'=>'External path (not inspected)','missing'=>'Missing','unreadable'=>'Unreadable','unsupported_file_type'=>'Unsupported file type','detected_readable'=>'Detected and readable','unknown'=>'Unknown');
?>
<div class="wrap wpdt-admin">
<header class="wpdt-header"><div><h1>Debug Center</h1><p>Read-only overview of WordPress and PHP debugging configuration.</p></div></header>
<section class="wpdt-section"><div class="wpdt-section-header"><div><h2>Debug configuration</h2><p>Values are inspected without changing site or server settings.</p></div></div>
<table class="widefat striped"><tbody>
<tr><th scope="row">WordPress debugging (WP_DEBUG)</th><td><?php echo esc_html($labels[$configuration['wordpress_debug']] ?? 'Unknown'); ?></td></tr>
<tr><th scope="row">WordPress debug logging (WP_DEBUG_LOG)</th><td><?php echo esc_html($labels[$configuration['wordpress_debug_log']] ?? 'Unknown'); ?></td></tr>
<tr><th scope="row">Error display (WP_DEBUG_DISPLAY)</th><td><?php echo esc_html($labels[$configuration['wordpress_debug_display']] ?? 'Unknown'); ?></td></tr>
<tr><th scope="row">PHP error logging (log_errors)</th><td><?php echo esc_html($labels[$configuration['php_log_errors']] ?? 'Unknown'); ?></td></tr>
<tr><th scope="row">PHP error log target</th><td><?php echo esc_html($labels[$configuration['php_error_log']] ?? 'Unknown'); ?></td></tr>
</tbody></table>
<?php if (! empty($configuration['debug_display_warning'])) : ?><div class="notice notice-warning inline"><p><strong>Security notice:</strong> Debug display is enabled. PHP errors may be exposed in rendered responses.</p></div><?php endif; ?>
</section>
<section class="wpdt-section"><div class="wpdt-section-header"><div><h2>WordPress debug log</h2><p>Metadata checks are restricted to targets inside the canonical wp-content directory.</p></div></div>
<table class="widefat striped"><tbody><tr><th scope="row">File</th><td>debug.log</td></tr>
<tr><th scope="row">Relative location</th><td><?php echo ! empty($target['relative_location']) ? esc_html($target['relative_location']) : 'Not available'; ?></td></tr>
<tr><th scope="row">Status</th><td><?php echo esc_html($target_labels[$target['status']] ?? 'Unknown'); ?></td></tr></tbody></table>
<div class="notice notice-info inline"><p><strong>Privacy by design:</strong> WP Diagnostic Toolkit does not open, read, display, or download log contents. Log files can contain credentials, tokens, email addresses, request data, and internal server paths.</p></div>
</section>
<section class="wpdt-section"><div class="wpdt-section-header"><div><h2>How to access the log</h2><p>Use your hosting environment to inspect the file manually.</p></div></div>
<ol><li>Open your hosting control panel's File Manager, or connect using FTP/SFTP.</li><li>Navigate to the relative location shown above, when available.</li><li>Open or download the log using your hosting tool.</li><li>Review and redact sensitive values before sharing an excerpt.</li></ol>
<p>A readable file may be empty or stale. This screen does not inspect its contents or prove that new errors are currently being written. PHP and server logs may be managed separately by your hosting provider.</p></section>
</div>
