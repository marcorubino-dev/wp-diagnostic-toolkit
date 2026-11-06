<?php

defined('ABSPATH') || exit;

$results = isset($diagnostic['results']) && is_array($diagnostic['results'])
    ? $diagnostic['results']
    : array();

$counts = array(
    'pass'     => 0,
    'info'     => 0,
    'warning'  => 0,
    'critical' => 0,
    'other'    => 0,
);

$report_lines = array(
    'WP Diagnostic Toolkit — ' . $diagnostic['name'] . ' report',
    'Checks: ' . count($results),
    '',
);

foreach ($results as $check) {
    $status = isset($check['status']) && is_string($check['status'])
        ? strtolower(trim($check['status']))
        : 'unclassified';

    if (isset($counts[$status])) {
        $counts[$status]++;
    } else {
        $counts['other']++;
    }

    $report_lines[] = 'Check: ' . (isset($check['name']) ? $check['name'] : 'Unnamed check');
    $report_lines[] = 'Status: ' . strtoupper($status);
    $report_lines[] = 'Value: ' . (isset($check['value']) ? $check['value'] : '');

    foreach (array('description' => 'Description', 'evidence' => 'Evidence', 'recommendation' => 'Recommendation') as $key => $label) {
        if (! empty($check[$key])) {
            $report_lines[] = $label . ': ' . $check[$key];
        }
    }

    $report_lines[] = '';
}

$module_status = $counts['critical'] > 0
    ? 'CRITICAL'
    : ($counts['warning'] > 0 ? 'NEEDS ATTENTION' : 'OK');

$status_class = $counts['critical'] > 0
    ? 'critical'
    : ($counts['warning'] > 0 ? 'warning' : 'pass');

$report_text = implode("\n", $report_lines);
?>

<div class="wrap wpdt-admin">
    <div class="wpdt-header">
        <div>
            <h1><?php echo esc_html($diagnostic['name']); ?></h1>
            <p>Read-only diagnostic results for this module.</p>
        </div>
    </div>

    <div class="wpdt-status-card">
        <div class="wpdt-status-card-header">
            <div>
                <span class="wpdt-label">MODULE STATUS</span>
                <h2 class="wpdt-status wpdt-status-<?php echo esc_attr($status_class); ?>">
                    <?php if ($status_class === 'critical') : ?>
                        <span aria-hidden="true">✕</span>
                    <?php elseif ($status_class === 'warning') : ?>
                        <span aria-hidden="true">⚠</span>
                    <?php else : ?>
                        <span aria-hidden="true">✓</span>
                    <?php endif; ?>
                    <?php echo esc_html($module_status); ?>
                </h2>
                <p class="wpdt-status-description">
                    <?php echo esc_html(count($results)); ?> diagnostic checks.
                </p>
            </div>

            <div class="wpdt-summary">
                <?php foreach (array('pass' => 'passed', 'info' => 'info', 'warning' => 'warnings', 'critical' => 'critical') as $status => $label) : ?>
                    <div class="wpdt-summary-item wpdt-summary-<?php echo esc_attr($status); ?>">
                        <span class="wpdt-summary-icon" aria-hidden="true">
                            <?php echo $status === 'pass' ? '✓' : ($status === 'warning' ? '⚠' : ($status === 'critical' ? '✕' : 'ℹ')); ?>
                        </span>
                        <strong><?php echo esc_html($counts[$status]); ?></strong>
                        <span><?php echo esc_html($label); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <section class="wpdt-section">
        <div class="wpdt-section-header wpdt-section-header-actions">
            <div>
                <h2>Diagnostic Checks</h2>
                <p>Individual findings, observed values, and recommended actions.</p>
            </div>
            <div class="wpdt-copy-control">
                <button type="button" class="button button-secondary wpdt-copy-button" data-wpdt-copy>
                    Copy module report
                </button>
                <textarea class="wpdt-copy-source" hidden readonly tabindex="-1" aria-hidden="true"><?php echo esc_textarea($report_text); ?></textarea>
                <span class="wpdt-copy-feedback" aria-live="polite"></span>
            </div>
        </div>

        <div class="wpdt-module-table">
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Check</th>
                        <th>Value</th>
                        <th>Status</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $check) : ?>
                        <?php
                        $status = isset($check['status']) && is_string($check['status'])
                            ? strtolower(trim($check['status']))
                            : 'unclassified';
                        $icon = $status === 'critical'
                            ? '✕'
                            : ($status === 'warning' ? '⚠' : ($status === 'pass' ? '✓' : 'ℹ'));
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html(isset($check['name']) ? $check['name'] : 'Unnamed check'); ?></strong></td>
                            <td><?php echo esc_html(isset($check['value']) && is_scalar($check['value']) ? (string) $check['value'] : ''); ?></td>
                            <td>
                                <span class="wpdt-check-status wpdt-check-status-<?php echo esc_attr($status); ?>">
                                    <span aria-hidden="true"><?php echo esc_html($icon); ?></span>
                                    <?php echo esc_html(strtoupper($status)); ?>
                                </span>
                            </td>
                            <td>
                                <div><?php echo esc_html(isset($check['description']) ? $check['description'] : ''); ?></div>
                                <?php if (! empty($check['recommendation'])) : ?>
                                    <div class="wpdt-check-recommendation">
                                        <strong>Recommendation:</strong>
                                        <?php echo esc_html($check['recommendation']); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (! empty($check['evidence'])) : ?>
                                    <div class="wpdt-check-evidence">
                                        <strong>Evidence:</strong>
                                        <?php echo esc_html($check['evidence']); ?>
                                        <div class="wpdt-copy-control wpdt-copy-control-inline">
                                            <button type="button" class="button button-small wpdt-copy-button" data-wpdt-copy>Copy evidence</button>
                                            <textarea class="wpdt-copy-source" hidden readonly tabindex="-1" aria-hidden="true"><?php echo esc_textarea($check['evidence']); ?></textarea>
                                            <span class="wpdt-copy-feedback" aria-live="polite"></span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>