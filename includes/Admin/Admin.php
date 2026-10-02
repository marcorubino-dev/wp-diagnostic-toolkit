<?php

/**
 * WordPress admin interface for WP Diagnostic Toolkit.
 */

namespace WPDT\Admin;

use WPDT\Core\DiagnosticManager;

class Admin
{
    /**
     * Diagnostic manager.
     *
     * @var DiagnosticManager
     */
    private $manager;

    /**
     * Constructor.
     *
     * @param DiagnosticManager $manager Diagnostic manager.
     */
    public function __construct(DiagnosticManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Register admin menu.
     *
     * @return void
     */
    public function register_menu()
    {
        add_management_page(
            'WP Diagnostic Toolkit',
            'WP Diagnostic Toolkit',
            'manage_options',
            'wp-diagnostic-toolkit',
            array($this, 'render_page')
        );
    }

    /**
     * Render diagnostics page.
     *
     * @return void
     */
    public function render_page()
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $diagnostics = $this->manager->run_all();

        echo '<div class="wrap">';
        echo '<h1>WP Diagnostic Toolkit</h1>';
        echo '<p>Read-only environment diagnostics.</p>';

        foreach ($diagnostics as $diagnostic) {
            echo '<h2>' . esc_html($diagnostic['name']) . '</h2>';

            echo '<table class="widefat striped">';
            echo '<thead><tr>';
            echo '<th>Check</th>';
            echo '<th>Value</th>';
            echo '<th>Status</th>';
            echo '<th>Description</th>';
            echo '</tr></thead>';
            echo '<tbody>';

            foreach ($diagnostic['results'] as $check) {
                echo '<tr>';
                echo '<td>' . esc_html($check['name']) . '</td>';
                echo '<td>' . esc_html($check['value']) . '</td>';
                echo '<td>' . esc_html(strtoupper($check['status'])) . '</td>';
                echo '<td>' . esc_html($check['description']) . '</td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
        }

        echo '</div>';
    }
}
