<?php

defined('ABSPATH') || exit;
?>

<div class="wrap">
    <h1>Server</h1>

    <p>Server diagnostics.</p>

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

            <?php foreach ($diagnostic['results'] as $check) : ?>

                <tr>
                    <td><?php echo esc_html($check['name']); ?></td>
                    <td><?php echo esc_html($check['value']); ?></td>
                    <td><?php echo esc_html(strtoupper($check['status'])); ?></td>
                    <td><?php echo esc_html($check['description']); ?></td>
                </tr>

            <?php endforeach; ?>

        </tbody>
    </table>
</div>