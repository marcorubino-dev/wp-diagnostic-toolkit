<?php

defined('ABSPATH') || exit;
?>

<div class="wrap wpdt-admin">
    <header class="wpdt-header">
        <div>
            <h1>Settings</h1>
            <p>Manage the preferences that control the WP Diagnostic Toolkit interface.</p>
        </div>
    </header>

    <section class="wpdt-section">
        <div class="wpdt-section-header">
            <h2>Appearance</h2>
            <p>Choose how the toolkit interface is displayed in this browser.</p>
        </div>

        <div class="wpdt-settings-card">
            <h2>Color theme</h2>
            <p>
                Select System to follow your operating system preference, or choose
                Light or Dark to keep a consistent appearance. This preference is
                stored locally in your browser and does not change your WordPress
                site or affect diagnostic results.
            </p>

            <div class="wpdt-settings-appearance-control"></div>
        </div>
    </section>
</div>
