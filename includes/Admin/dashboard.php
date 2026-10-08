<?php

defined('ABSPATH') || exit;

$total_checks    = 0;
$pass_count      = 0;
$info_count      = 0;
$warning_count   = 0;
$critical_count  = 0;
$issues          = array();

foreach ($diagnostics as $diagnostic) {
    foreach ($diagnostic['results'] as $check) {
        $total_checks++;

        $status = strtolower($check['status']);

        if ($status === 'pass') {
            $pass_count++;
        } elseif ($status === 'info') {
            $info_count++;
        } elseif ($status === 'warning') {
            $warning_count++;

            $issues[] = array(
                'module' => $diagnostic['name'],
                'check'  => $check,
            );
        } elseif ($status === 'critical') {
            $critical_count++;

            $issues[] = array(
                'module' => $diagnostic['name'],
                'check'  => $check,
            );
        }
    }
}

if ($critical_count > 0) {
    $system_status = 'CRITICAL';
    $status_class  = 'critical';
} elseif ($warning_count > 0) {
    $system_status = 'NEEDS ATTENTION';
    $status_class  = 'warning';
} else {
    $system_status = 'OK';
    $status_class  = 'pass';
}
?>

<div class="wrap wpdt-admin">

    <div class="wpdt-header">
        <div>
            <h1>WP Diagnostic Toolkit</h1>
            <p>Read-only WordPress diagnostics.</p>
        </div>

        <div class="wpdt-header-action">
            <button type="button" class="button button-primary">
                Run Diagnostics
            </button>
        </div>
    </div>

    <div class="wpdt-status-card">

        <div class="wpdt-status-card-header">
            <div>
                <span class="wpdt-label">SYSTEM STATUS</span>

                <h2 class="wpdt-status wpdt-status-<?php echo esc_attr($status_class); ?>">
                    <?php if ($status_class === 'critical') : ?>
                        <span aria-hidden="true">✕</span>
                    <?php elseif ($status_class === 'warning') : ?>
                        <span aria-hidden="true">⚠</span>
                    <?php else : ?>
                        <span aria-hidden="true">✓</span>
                    <?php endif; ?>

                    <?php echo esc_html($system_status); ?>
                </h2>

                <p class="wpdt-status-description">
                    <?php
                    if ($critical_count > 0) {
                        echo esc_html(
                            sprintf(
                                '%d critical issue(s) require attention.',
                                $critical_count
                            )
                        );
                    } elseif ($warning_count > 0) {
                        echo esc_html(
                            sprintf(
                                '%d issue(s) require attention.',
                                $warning_count
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

                <div class="wpdt-summary-item wpdt-summary-critical">
                    <span class="wpdt-summary-icon" aria-hidden="true">✕</span>
                    <strong><?php echo esc_html($critical_count); ?></strong>
                    <span>critical</span>
                </div>

            </div>

        </div>

        <div class="wpdt-total-checks">
            <?php echo esc_html($total_checks); ?> diagnostic checks
        </div>

    </div>

    <?php if (! empty($issues)) : ?>

        <section class="wpdt-section">

            <div class="wpdt-section-header">
                <div>
                    <h2>Issues Requiring Attention</h2>
                    <p>Checks that returned a warning or critical status.</p>
                </div>
            </div>

            <div class="wpdt-issues">

                <?php foreach ($issues as $issue) : ?>

                    <?php
                    $check = $issue['check'];
                    $status = strtolower($check['status']);

                    if ($status === 'critical') {
                        $icon = '✕';
                    } else {
                        $icon = '⚠';
                    }
                    ?>

                    <div class="wpdt-issue wpdt-issue-<?php echo esc_attr($status); ?>">

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

                            <p>
                                <?php echo esc_html($check['description']); ?>
                            </p>

                            <?php if (! empty($check['recommendation'])) : ?>

                                <p class="wpdt-recommendation">
                                    <strong>Recommendation:</strong>
                                    <?php echo esc_html($check['recommendation']); ?>
                                </p>

                            <?php endif; ?>

                        </div>

                        <div class="wpdt-issue-status">
                            <?php echo esc_html(strtoupper($status)); ?>
                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        </section>

    <?php endif; ?>

    <section class="wpdt-section">

        <div class="wpdt-section-header">
            <div>
                <h2>Diagnostic Modules</h2>
                <p>Available diagnostic areas.</p>
            </div>
        </div>

        <div class="wpdt-modules">

            <?php foreach ($diagnostics as $diagnostic) : ?>

                <div class="wpdt-module">

                    <div class="wpdt-module-icon" aria-hidden="true">
                        ✓
                    </div>

                    <div class="wpdt-module-content">

                        <h3>
                            <?php echo esc_html($diagnostic['name']); ?>
                        </h3>

                        <p>
                            <?php
                            echo esc_html(
                                count($diagnostic['results'])
                            );
                            ?>
                            checks
                        </p>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    </section>

</div>