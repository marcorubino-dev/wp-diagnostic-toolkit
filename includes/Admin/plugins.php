<?php

defined('ABSPATH') || exit;

$results = $diagnostic['results'];

$pass_count     = 0;
$info_count     = 0;
$warning_count  = 0;
$critical_count = 0;

foreach ($results as $check) {
    $status = strtolower($check['status']);

    if ($status === 'pass') {
        $pass_count++;
    } elseif ($status === 'info') {
        $info_count++;
    } elseif ($status === 'warning') {
        $warning_count++;
    } elseif ($status === 'critical') {
        $critical_count++;
    }
}

if ($critical_count > 0) {
    $module_status = 'CRITICAL';
    $status_class  = 'critical';
} elseif ($warning_count > 0) {
    $module_status = 'NEEDS ATTENTION';
    $status_class  = 'warning';
} else {
    $module_status = 'OK';
    $status_class  = 'pass';
}
?>

<div class="wrap wpdt-admin">

    <div class="wpdt-header">
        <div>
            <h1><?php echo esc_html($diagnostic['name']); ?></h1>
            <p class="wpdt-page-subtitle">
                Plugin inventory and update diagnostics.
            </p>
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

    </div>

    <section class="wpdt-section">

        <div class="wpdt-section-header">
            <div>
                <h2>Diagnostic Checks</h2>
                <p>Active plugins and plugin-related conditions.</p>
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
                        $status = strtolower($check['status']);

                        if ($status === 'critical') {
                            $icon = '✕';
                        } elseif ($status === 'warning') {
                            $icon = '⚠';
                        } elseif ($status === 'pass') {
                            $icon = '✓';
                        } else {
                            $icon = 'ℹ';
                        }
                        ?>

                        <tr>

                            <td>
                                <strong>
                                    <?php echo esc_html($check['name']); ?>
                                </strong>
                            </td>

                            <td>
                                <?php echo esc_html($check['value']); ?>
                            </td>

                            <td>

                                <span class="wpdt-check-status wpdt-check-status-<?php echo esc_attr($status); ?>">

                                    <span aria-hidden="true">
                                        <?php echo esc_html($icon); ?>
                                    </span>

                                    <?php echo esc_html(strtoupper($status)); ?>

                                </span>

                            </td>

                            <td>

                                <div>
                                    <?php echo esc_html($check['description']); ?>
                                </div>

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