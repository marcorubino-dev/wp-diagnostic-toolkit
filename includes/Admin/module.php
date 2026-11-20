<?php

defined('ABSPATH') || exit;

$results = isset($diagnostic['results']) && is_array($diagnostic['results'])
    ? $diagnostic['results']
    : array();

$counts = array(
    'pass'     => 0,
    'info'     => 0,
    'warning'  => 0,
    'critical'        => 0,
    'diagnostic_error' => 0,
    'unclassified'    => 0,
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

    $outcome = isset($check['outcome']) && is_string($check['outcome'])
        ? strtolower(trim($check['outcome']))
        : '';

    if ($outcome === 'error') {
        $counts['diagnostic_error']++;
    } else {
        if (! isset($counts[$status])) {
            $status = 'unclassified';
        }

        $counts[$status]++;
    }

    $report_lines[] = 'Check: ' . (isset($check['name']) ? $check['name'] : 'Unnamed check');
    $report_lines[] = 'Status: ' . strtoupper($status);
    if ($outcome !== '') {
        $report_lines[] = 'Outcome: ' . strtoupper($outcome);
    }
    $report_lines[] = 'Observed value: ' . (isset($check['value']) ? $check['value'] : '');

    foreach (array('description' => 'Interpretation', 'evidence' => 'Observed evidence', 'recommendation' => 'Next step') as $key => $label) {
        if (! empty($check[$key])) {
            $report_lines[] = $label . ': ' . $check[$key];
        }
    }

    $report_lines[] = '';
}

$module_status = $counts['critical'] > 0
    ? 'CRITICAL'
    : ($counts['diagnostic_error'] > 0
        ? 'DIAGNOSTIC ERRORS'
        : ($counts['warning'] > 0
            ? 'NEEDS ATTENTION'
            : ($counts['unclassified'] > 0 ? 'NEEDS REVIEW' : (empty($results) ? 'NO DATA' : 'OK'))));

$status_class = $counts['critical'] > 0
    ? 'critical'
    : ($counts['diagnostic_error'] > 0
        ? 'error'
        : ($counts['warning'] > 0 || $counts['unclassified'] > 0
            ? 'warning'
            : (empty($results) ? 'empty' : 'pass')));

$report_text = implode("\n", $report_lines);
?>

<div class="wrap wpdt-admin">
    <div class="wpdt-header">
        <div>
            <h1><?php echo esc_html($diagnostic['name']); ?></h1>
            <p><?php echo esc_html(isset($page_description) ? $page_description : 'Read-only diagnostic results for this module.'); ?></p>
        </div>
    </div>

    <div class="wpdt-status-card">
        <div class="wpdt-status-card-header">
            <div>
                <span class="wpdt-label">MODULE STATUS</span>
                <h2 class="wpdt-status wpdt-status-<?php echo esc_attr($status_class); ?>">
                    <?php if ($status_class === 'critical') : ?>
                        <span aria-hidden="true">✕</span>
                    <?php elseif ($status_class === 'error') : ?>
                        <span aria-hidden="true">!</span>
                    <?php elseif ($status_class === 'empty') : ?>
                        <span aria-hidden="true">—</span>
                    <?php elseif ($status_class === 'warning' && $counts['unclassified'] > 0 && $counts['warning'] === 0) : ?>
                        <span aria-hidden="true">?</span>
                    <?php elseif ($status_class === 'warning') : ?>
                        <span aria-hidden="true">⚠</span>
                    <?php else : ?>
                        <span aria-hidden="true">✓</span>
                    <?php endif; ?>
                    <?php echo esc_html($module_status); ?>
                </h2>
                <p class="wpdt-status-description">
                    <?php
                    if ($counts['critical'] > 0) {
                        echo esc_html(sprintf('%d critical finding(s) require attention.', $counts['critical']));
                    } elseif ($counts['diagnostic_error'] > 0) {
                        echo esc_html(sprintf('%d diagnostic error(s) occurred. Review this module before trusting its results.', $counts['diagnostic_error']));
                    } elseif (empty($results)) {
                        echo esc_html('No diagnostic checks are available for this module.');
                    } elseif ($counts['warning'] > 0) {
                        echo esc_html(sprintf('%d warning(s) require attention.', $counts['warning']));
                    } elseif ($counts['unclassified'] > 0) {
                        echo esc_html(sprintf('%d check(s) have an unknown or missing status and need review.', $counts['unclassified']));
                    } else {
                        echo esc_html(count($results) . ' diagnostic checks. No warnings or unclassified results.');
                    }
                    ?>
                </p>
            </div>

            <div class="wpdt-summary">
                <?php foreach (array('pass' => 'passed', 'info' => 'info', 'warning' => 'warnings', 'diagnostic_error' => 'diagnostic errors', 'critical' => 'critical', 'unclassified' => 'unclassified') as $status => $label) : ?>
                    <div class="wpdt-summary-item wpdt-summary-<?php echo $status === 'unclassified' ? 'info' : esc_attr($status); ?>">
                        <span class="wpdt-summary-icon" aria-hidden="true">
                            <?php echo $status === 'pass' ? '✓' : ($status === 'warning' ? '⚠' : ($status === 'diagnostic_error' ? '!' : ($status === 'critical' ? '✕' : ($status === 'unclassified' ? '?' : 'ℹ')))); ?>
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
                <p>What the check observed, what it means, and what to investigate next.</p>
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
                        <th>Interpretation and next step</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($results)) : ?>
                        <tr>
                            <td colspan="4">NO DATA: No diagnostic checks are available for this module.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($results as $check) : ?>
                        <?php
                        $status = isset($check['status']) && is_string($check['status'])
                            ? strtolower(trim($check['status']))
                            : 'unclassified';
                        $outcome = isset($check['outcome']) && is_string($check['outcome'])
                            ? strtolower(trim($check['outcome']))
                            : '';
                        if ($outcome === 'error') {
                            $status = 'error';
                            $icon = '!';
                        } else {
                            if (! isset($counts[$status])) {
                                $status = 'unclassified';
                            }
                            $icon = $status === 'critical'
                                ? '✕'
                                : ($status === 'warning' ? '⚠' : ($status === 'pass' ? '✓' : ($status === 'unclassified' ? '?' : 'ℹ')));
                        }
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
                                <div class="wpdt-check-interpretation">
                                    <strong>Interpretation:</strong>
                                    <?php echo esc_html(isset($check['description']) ? $check['description'] : ''); ?>
                                </div>
                                <?php if (! empty($check['recommendation'])) : ?>
                                    <div class="wpdt-check-recommendation">
                                        <strong>Next step:</strong>
                                        <?php echo esc_html($check['recommendation']); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (! empty($check['evidence'])) : ?>
                                    <div class="wpdt-check-evidence">
                                        <strong>Observed evidence:</strong>
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