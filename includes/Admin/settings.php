<?php

defined('ABSPATH') || exit;
?>

<div class="wrap wpdt-admin">

    <div class="wpdt-header">
        <div>
            <h1>Settings</h1>
            <p>Review toolkit preferences and diagnostic behavior.</p>
        </div>
        <div class="wpdt-header-action"></div>
    </div>

    <div class="wpdt-settings-grid">

        <section class="wpdt-settings-card">
            <span class="wpdt-settings-card-label">Appearance</span>
            <h2>Interface theme</h2>
            <p>
                Choose System, Light, or Dark from the Appearance control in the
                page header. System follows your operating system preference.
            </p>
            <div class="wpdt-settings-card-meta">
                <span class="wpdt-settings-tag">System</span>
                <span class="wpdt-settings-tag">Light</span>
                <span class="wpdt-settings-tag">Dark</span>
            </div>
            <ul class="wpdt-settings-list">
                <li>
                    <span>Preference storage</span>
                    <strong>This browser</strong>
                </li>
                <li>
                    <span>WordPress database changes</span>
                    <strong>None</strong>
                </li>
            </ul>
        </section>

        <section class="wpdt-settings-card">
            <span class="wpdt-settings-card-label">Diagnostics</span>
            <h2>Read-only execution</h2>
            <p>
                Diagnostic modules inspect the current WordPress environment
                when you open the dashboard or a module page. They do not
                intentionally modify site content or configuration.
            </p>
            <div class="wpdt-settings-card-meta">
                <span class="wpdt-settings-tag">Read-only</span>
                <span class="wpdt-settings-tag">On demand</span>
            </div>
        </section>

        <section class="wpdt-settings-card wpdt-settings-card-wide">
            <span class="wpdt-settings-card-label">Configuration status</span>
            <h2>What can be configured today?</h2>
            <p>
                The toolkit currently exposes an interface preference. Diagnostic
                rules and execution behavior do not have user-configurable options yet.
            </p>
            <ul class="wpdt-settings-list">
                <li>
                    <span>Interface appearance</span>
                    <strong>Available</strong>
                </li>
                <li>
                    <span>Diagnostic rules and thresholds</span>
                    <strong>Not configurable</strong>
                </li>
                <li>
                    <span>Scheduled scans and notifications</span>
                    <strong>Not available</strong>
                </li>
            </ul>
        </section>

    </div>

</div>
