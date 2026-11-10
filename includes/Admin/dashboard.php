<?php

defined('ABSPATH') || exit;

$total_checks    = 0;
$pass_count      = 0;
$info_count      = 0;
$warning_count   = 0;
$critical_count  = 0;
$diagnostic_error_count = 0;
$unclassified_count = 0;
$issues          = array();

foreach ($diagnostics as $diagnostic) {
    foreach ($diagnostic['results'] as $check) {
        $total_checks++;

        $status = isset($check['status']) && is_string($check['status'])
            ? strtolower(trim($check['status']))
            : '';

        $outcome = isset($check['outcome']) && is_string($check['outcome'])
            ? strtolower(trim($check['outcome']))
            : '';

        if ($status === 'pass') {
            $pass_count++;
        } elseif ($status === 'info') {
            $info_count++;
        } elseif ($status === 'warning') {
            if ($outcome === 'error') {
                $diagnostic_error_count++;
            } else {
                $warning_count++;
            }

            $issues[] = array(
                'module'    => $diagnostic['name'],
                'module_id' => isset($diagnostic['id']) ? $diagnostic['id'] : '',
                'check'     => $check,
            );
        } elseif ($status === 'critical') {
            $critical_count++;

            $issues[] = array(
                'module'    => $diagnostic['name'],
                'module_id' => isset($diagnostic['id']) ? $diagnostic['id'] : '',
                'check'     => $check,
            );
        } else {
            $unclassified_count++;
        }
    }
}

if ($total_checks === 0) {
    $system_status = 'NO DATA';
    $status_class  = 'empty';
} elseif ($critical_count > 0) {
    $system_status = 'CRITICAL';
    $status_class  = 'critical';
} elseif ($diagnostic_error_count > 0) {
    $system_status = 'DIAGNOSTIC ERRORS';
    $status_class  = 'error';
} elseif ($warning_count > 0) {
    $system_status = 'NEEDS ATTENTION';
    $status_class  = 'warning';
} elseif ($unclassified_count > 0) {
    $system_status = 'NEEDS REVIEW';
    $status_class  = 'warning';
} else {
    $system_status = 'OK';
    $status_class  = 'pass';
}

$report_lines = array(
    'WP Diagnostic Toolkit — Diagnostic report',
    'System status: ' . $system_status,
    'Checks: ' . $total_checks,
    'Passed: ' . $pass_count,
    'Info: ' . $info_count,
    'Warnings: ' . $warning_count,
    'Diagnostic errors: ' . $diagnostic_error_count,
    'Critical: ' . $critical_count,
    'Unclassified: ' . $unclassified_count,
    '',
);

foreach ($diagnostics as $diagnostic) {
    $report_lines[] = 'MODULE: ' . $diagnostic['name'];

    foreach ($diagnostic['results'] as $check) {
        $report_lines[] = '';
        $report_lines[] = 'Check: ' . $check['name'];
        $report_status = isset($check['status']) && is_string($check['status'])
            ? strtoupper(trim($check['status']))
            : 'UNCLASSIFIED';
        $report_lines[] = 'Status: ' . $report_status;

        if (isset($check['outcome']) && is_string($check['outcome'])) {
            $report_lines[] = 'Outcome: ' . strtoupper(trim($check['outcome']));
        }

        $report_lines[] = 'Interpretation: ' . $check['description'];

        if (isset($check['value']) && $check['value'] !== '') {
            $report_lines[] = 'Observed value: ' . $check['value'];
        }

        if (! empty($check['evidence'])) {
            $report_lines[] = 'Observed evidence: ' . $check['evidence'];
        }

        if (! empty($check['recommendation'])) {
            $report_lines[] = 'Next step: ' . $check['recommendation'];
        }
    }

    $report_lines[] = '';
}

$report_text = implode("\n", $report_lines);

$module_pages = array(
    'environment'              => 'wpdt-environment',
    'http_ssl'                 => 'wpdt-http-ssl',
    'plugins'                  => 'wpdt-plugins',
    'themes'                   => 'wpdt-themes',
    'server'                   => 'wpdt-server',
    'disk_space'               => 'wpdt-disk-space',
    'multilingual_woocommerce' => 'wpdt-multilingual-woocommerce',
);
?>

<div class="wrap wpdt-admin">

    <div class="wpdt-header">
        <div>
            <h1>WP Diagnostic Toolkit</h1>
            <p>Read-only WordPress diagnostics.</p>
        </div>

        <div class="wpdt-header-action">
            <div class="wpdt-copy-control">
                <button type="button" class="button button-secondary wpdt-copy-button" data-wpdt-copy>
                    Copy full report
                </button>
                <textarea class="wpdt-copy-source" hidden readonly tabindex="-1" aria-hidden="true"><?php echo esc_textarea($report_text); ?></textarea>
                <span class="wpdt-copy-feedback" aria-live="polite"></span>
            </div>
            <form method="get" action="">
                <input type="hidden" name="page" value="wp-diagnostic-toolkit" />
                <button type="submit" class="button button-primary">
                    Run Diagnostics
                </button>
            </form>
        </div>
    </div>

    <div class="wpdt-status-card">

        <div class="wpdt-status-card-header">
            <div>
                <span class="wpdt-label">SYSTEM STATUS</span>

                <h2 class="wpdt-status wpdt-status-<?php echo esc_attr($status_class); ?>">
                    <?php if ($status_class === 'critical') : ?>
                        <span aria-hidden="true">✕</span>
                    <?php elseif ($status_class === 'error') : ?>
                        <span aria-hidden="true">!</span>
                    <?php elseif ($system_status === 'NEEDS REVIEW') : ?>
                        <span aria-hidden="true">?</span>
                    <?php elseif ($status_class === 'warning') : ?>
                        <span aria-hidden="true">⚠</span>
                    <?php elseif ($status_class === 'empty') : ?>
                        <span aria-hidden="true">—</span>
                    <?php else : ?>
                        <span aria-hidden="true">✓</span>
                    <?php endif; ?>

                    <?php echo esc_html($system_status); ?>
                </h2>

                <p class="wpdt-status-description">
                    <?php
                    if ($total_checks === 0) {
                        echo esc_html(
                            'No diagnostic checks are available. Run diagnostics again after confirming that the diagnostic modules are registered.'
                        );
                    } elseif ($critical_count > 0) {
                        echo esc_html(
                            sprintf(
                                '%d critical issue(s) require attention.',
                                $critical_count
                            )
                        );
                    } elseif ($diagnostic_error_count > 0) {
                        echo esc_html(
                            sprintf(
                                '%d diagnostic error(s) occurred. Review the affected modules before trusting their results.',
                                $diagnostic_error_count
                            )
                        );
                    } elseif ($warning_count > 0) {
                        echo esc_html(
                            sprintf(
                                '%d issue(s) require attention.%s',
                                $warning_count,
                                $unclassified_count > 0
                                    ? ' ' . $unclassified_count . ' check(s) have an unrecognized or missing status.'
                                    : ''
                            )
                        );
                    } elseif ($unclassified_count > 0) {
                        echo esc_html(
                            sprintf(
                                '%d check(s) have an unrecognized or missing status; review their diagnostic definitions.',
                                $unclassified_count
                            )
                        );
                    } else {
                        echo esc_html(
                            'No warnings or critical issues detected.'
                        );
                    }
                    ?>
                </p>
            </div>

            <div class="wpdt-summary">

                <div class="wpdt-summary-item wpdt-summary-pass">
                    <span class="wpdt-summary-icon" aria-hidden="true">✓</span>
                    <strong><?php echo esc_html($pass_count); ?></strong>
                    <span>passed</span>
                </div>

                <div class="wpdt-summary-item wpdt-summary-info">
                    <span class="wpdt-summary-icon" aria-hidden="true">ℹ</span>
                    <strong><?php echo esc_html($info_count); ?></strong>
                    <span>info</span>
                </div>

                <div class="wpdt-summary-item wpdt-summary-warning">
                    <span class="wpdt-summary-icon" aria-hidden="true">⚠</span>
                    <strong><?php echo esc_html($warning_count); ?></strong>
                    <span>warnings</span>
                </div>

                <div class="wpdt-summary-item wpdt-summary-error">
                    <span class="wpdt-summary-icon" aria-hidden="true">!</span>
                    <strong><?php echo esc_html($diagnostic_error_count); ?></strong>
                    <span>diagnostic errors</span>
                </div>

                <div class="wpdt-summary-item wpdt-summary-critical">
                    <span class="wpdt-summary-icon" aria-hidden="true">✕</span>
                    <strong><?php echo esc_html($critical_count); ?></strong>
                    <span>critical</span>
                </div>

                <div class="wpdt-summary-item wpdt-summary-info">
                    <span class="wpdt-summary-icon" aria-hidden="true">?</span>
                    <strong><?php echo esc_html($unclassified_count); ?></strong>
                    <span>unclassified</span>
                </div>

            </div>

        </div>

        <div class="wpdt-total-checks">
            <?php echo esc_html($total_checks); ?> diagnostic checks
        </div>

    </div>

    <section class="wpdt-section">

        <div class="wpdt-section-header">
            <div>
                <h2>Diagnostic Modules</h2>
                <p>Available diagnostic areas.</p>
            </div>
        </div>

        <div class="wpdt-modules">

            <?php foreach ($diagnostics as $diagnostic) : ?>

                <?php
                $module_counts = array(
                    'pass'          => 0,
                    'info'          => 0,
                    'warning'       => 0,
                    'critical'         => 0,
                    'diagnostic_error' => 0,
                    'unclassified'     => 0,
                );

                foreach ($diagnostic['results'] as $module_check) {
                    $module_check_status = isset($module_check['status']) && is_string($module_check['status'])
                        ? strtolower(trim($module_check['status']))
                        : '';

                    $module_check_outcome = isset($module_check['outcome']) && is_string($module_check['outcome'])
                        ? strtolower(trim($module_check['outcome']))
                        : '';

                    if ($module_check_outcome === 'error') {
                        $module_counts['diagnostic_error']++;
                    } elseif (isset($module_counts[$module_check_status])) {
                        $module_counts[$module_check_status]++;
                    } else {
                        $module_counts['unclassified']++;
                    }
                }

                $module_total = count($diagnostic['results']);

                if ($module_counts['critical'] > 0) {
                    $module_status_class = 'critical';
                    $module_status_label = 'Critical';
                    $module_status_icon  = '✕';
                } elseif ($module_counts['diagnostic_error'] > 0) {
                    $module_status_class = 'error';
                    $module_status_label = 'Diagnostic error';
                    $module_status_icon  = '!';
                } elseif ($module_counts['warning'] > 0) {
                    $module_status_class = 'warning';
                    $module_status_label = 'Needs attention';
                    $module_status_icon  = '⚠';
                } elseif ($module_counts['unclassified'] > 0) {
                    $module_status_class = 'review';
                    $module_status_label = 'Needs review';
                    $module_status_icon  = '?';
                } elseif ($module_total > 0) {
                    $module_status_class = 'pass';
                    $module_status_label = 'No warnings';
                    $module_status_icon  = '✓';
                } else {
                    $module_status_class = 'empty';
                    $module_status_label = 'No checks';
                    $module_status_icon  = '—';
                }

                $module_summary_parts = array();

                if ($module_total > 0) {
                    $module_summary_parts[] = $module_total . ' checks';
                }

                foreach (array('pass', 'info', 'warning', 'diagnostic_error', 'critical', 'unclassified') as $count_key) {
                    if ($module_counts[$count_key] > 0) {
                        $summary_label = $count_key === 'diagnostic_error'
                            ? 'diagnostic errors'
                            : $count_key;
                        $module_summary_parts[] = $module_counts[$count_key] . ' ' . $summary_label;
                    }
                }

                $module_summary = ! empty($module_summary_parts)
                    ? implode(' · ', $module_summary_parts)
                    : 'No diagnostic checks available';
                ?>

                <?php if (isset($diagnostic['id'], $module_pages[$diagnostic['id']])) : ?>
                    <a class="wpdt-module wpdt-module-<?php echo esc_attr($module_status_class); ?>" href="<?php echo esc_url(admin_url('admin.php?page=' . $module_pages[$diagnostic['id']])); ?>">
                <?php else : ?>
                    <div class="wpdt-module wpdt-module-<?php echo esc_attr($module_status_class); ?>">
                <?php endif; ?>

                    <div class="wpdt-module-icon" aria-hidden="true">
                        <?php echo esc_html($module_status_icon); ?>
                    </div>

                    <div class="wpdt-module-content">

                        <div class="wpdt-module-heading">
                            <h3><?php echo esc_html($diagnostic['name']); ?></h3>
                            <span class="wpdt-module-status wpdt-module-status-<?php echo esc_attr($module_status_class); ?>">
                                <?php echo esc_html($module_status_label); ?>
                            </span>
                        </div>

                        <p><?php echo esc_html($module_summary); ?></p>

                    </div>

                    <?php if (isset($diagnostic['id'], $module_pages[$diagnostic['id']])) : ?>
                        <span class="wpdt-module-arrow" aria-hidden="true">→</span>
                    <?php endif; ?>

                <?php if (isset($diagnostic['id'], $module_pages[$diagnostic['id']])) : ?>
                    </a>
                <?php else : ?>
                    </div>
                <?php endif; ?>

            <?php endforeach; ?>

        </div>

    </section>


    <?php if (! empty($issues)) : ?>

        <section class="wpdt-section">

            <div class="wpdt-section-header">
                <div>
                    <h2>Issues Requiring Attention</h2>
                    <p>Warnings, critical findings, and diagnostic execution errors that need review.</p>
                </div>
            </div>

            <div class="wpdt-issues">

                <?php foreach ($issues as $issue) : ?>

                    <?php
                    $check = $issue['check'];
                    $status = isset($check['status']) && is_string($check['status'])
                        ? strtolower(trim($check['status']))
                        : 'warning';
                    $outcome = isset($check['outcome']) && is_string($check['outcome'])
                        ? strtolower(trim($check['outcome']))
                        : '';
                    $is_diagnostic_error = $outcome === 'error';
                    $issue_class = $is_diagnostic_error
                        ? 'wpdt-issue-error'
                        : 'wpdt-issue-' . $status;
                    $issue_label = $is_diagnostic_error
                        ? 'DIAGNOSTIC ERROR'
                        : strtoupper($status);

                    if ($is_diagnostic_error) {
                        $icon = '!';
                    } elseif ($status === 'critical') {
                        $icon = '✕';
                    } else {
                        $icon = '⚠';
                    }
                    ?>

                    <div class="wpdt-issue <?php echo esc_attr($issue_class); ?>">

                        <div class="wpdt-issue-icon" aria-hidden="true">
                            <?php echo esc_html($icon); ?>
                        </div>

                        <div class="wpdt-issue-content">

                            <h3>
                                <?php echo esc_html($check['name']); ?>
                            </h3>

                            <p class="wpdt-issue-module">
                                <?php echo esc_html($issue['module']); ?>
                            </p>

                            <?php if (! empty($issue['module_id']) && isset($module_pages[$issue['module_id']])) : ?>
                                <a class="wpdt-issue-module-link" href="<?php echo esc_url(admin_url('admin.php?page=' . $module_pages[$issue['module_id']])); ?>">
                                    Open diagnostic module <span aria-hidden="true">→</span>
                                </a>
                            <?php endif; ?>

                            <p class="wpdt-issue-interpretation">
                                <strong>Interpretation:</strong>
                                <?php echo esc_html($check['description']); ?>
                            </p>

                            <?php if (isset($check['value']) && $check['value'] !== '') : ?>

                                <p class="wpdt-issue-detail">
                                    <strong>Observed:</strong>
                                    <?php echo esc_html($check['value']); ?>
                                </p>

                            <?php endif; ?>

                            <?php if (! empty($check['evidence'])) : ?>

                                <p class="wpdt-issue-evidence">
                                    <strong>Evidence:</strong>
                                    <?php echo esc_html($check['evidence']); ?>
                                </p>

                            <?php endif; ?>

                            <?php if (! empty($check['recommendation'])) : ?>

                                <p class="wpdt-recommendation">
                                    <strong>Next step:</strong>
                                    <?php echo esc_html($check['recommendation']); ?>
                                </p>

                            <?php endif; ?>

                            <?php
                            $finding_lines = array(
                                'Module: ' . $issue['module'],
                                'Module ID: ' . (isset($issue['module_id']) ? $issue['module_id'] : ''),
                                'Check: ' . $check['name'],
                                'Status: ' . strtoupper($status),
                                'Outcome: ' . ($outcome !== '' ? strtoupper($outcome) : 'LEGACY'),
                                'Interpretation: ' . $check['description'],
                            );

                            if (isset($check['value']) && $check['value'] !== '') {
                                $finding_lines[] = 'Observed value: ' . $check['value'];
                            }

                            if (! empty($check['evidence'])) {
                                $finding_lines[] = 'Observed evidence: ' . $check['evidence'];
                            }

                            if (! empty($check['recommendation'])) {
                                $finding_lines[] = 'Next step: ' . $check['recommendation'];
                            }
                            ?>

                            <div class="wpdt-copy-control wpdt-copy-control-inline">
                                <button type="button" class="button button-small wpdt-copy-button" data-wpdt-copy>
                                    Copy finding
                                </button>
                                <textarea class="wpdt-copy-source" hidden readonly tabindex="-1" aria-hidden="true"><?php echo esc_textarea(implode("\n", $finding_lines)); ?></textarea>
                                <span class="wpdt-copy-feedback" aria-live="polite"></span>
                            </div>

                        </div>

                        <div class="wpdt-issue-status">
                            <?php echo esc_html($issue_label); ?>
                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        </section>

    <?php endif; ?>
</div>