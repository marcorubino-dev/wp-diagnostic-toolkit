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
        add_menu_page(
            'WP Diagnostic Toolkit',
            'WP Diagnostic Toolkit',
            'manage_options',
            'wp-diagnostic-toolkit',
            array($this, 'render_dashboard'),
            'dashicons-admin-tools',
            80
        );

        add_submenu_page(
            'wp-diagnostic-toolkit',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'wp-diagnostic-toolkit',
            array($this, 'render_dashboard')
        );

        add_submenu_page(
            'wp-diagnostic-toolkit',
            'Environment',
            'Environment',
            'manage_options',
            'wpdt-environment',
            array($this, 'render_environment')
        );

        add_submenu_page(
            'wp-diagnostic-toolkit',
            'Plugins',
            'Plugins',
            'manage_options',
            'wpdt-plugins',
            array($this, 'render_plugins')
        );

        add_submenu_page(
            'wp-diagnostic-toolkit',
            'Server',
            'Server',
            'manage_options',
            'wpdt-server',
            array($this, 'render_server')
        );

        add_submenu_page(
            'wp-diagnostic-toolkit',
            'Settings',
            'Settings',
            'manage_options',
            'wpdt-settings',
            array($this, 'render_settings')
        );
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook_suffix Current admin page hook.
     * @return void
     */
    public function enqueue_assets($hook_suffix)
    {
        if (
            strpos($hook_suffix, 'wp-diagnostic-toolkit') === false
            && strpos($hook_suffix, 'wpdt-') === false
        ) {
            return;
        }

        wp_enqueue_style(
            'wpdt-admin',
            WPDT_URL . 'includes/Admin/assets/admin.css',
            array(),
            WPDT_VERSION
        );
    }

    /**
     * Render dashboard.
     *
     * @return void
     */
    public function render_dashboard()
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $diagnostics = $this->manager->run_all();

        $this->render_template(
            'dashboard',
            array(
                'diagnostics' => $diagnostics,
            )
        );
    }

    /**
     * Render environment diagnostics.
     *
     * @return void
     */
    public function render_environment()
    {
        $this->render_diagnostic_module('environment');
    }

    /**
     * Render plugin diagnostics.
     *
     * @return void
     */
    public function render_plugins()
    {
        $this->render_diagnostic_module('plugins');
    }

    /**
     * Render server diagnostics.
     *
     * @return void
     */
    public function render_server()
    {
        $this->render_diagnostic_module('server');
    }

    /**
     * Render settings.
     *
     * @return void
     */
    public function render_settings()
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $this->render_template('settings');
    }

    /**
     * Render a diagnostic module.
     *
     * @param string $module_id Diagnostic module ID.
     * @return void
     */
    private function render_diagnostic_module($module_id)
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $diagnostics = $this->manager->run_all();

        if (! isset($diagnostics[$module_id])) {
            wp_die('Diagnostic module not found.');
        }

        $diagnostic = $diagnostics[$module_id];

        $this->render_template(
            $module_id,
            array(
                'diagnostic' => $diagnostic,
            )
        );
    }

    /**
     * Render an admin template.
     *
     * @param string $template Template name.
     * @param array  $data     Template data.
     * @return void
     */
    private function render_template($template, $data = array())
    {
        $template_file = WPDT_PATH . 'includes/Admin/' . $template . '.php';

        if (! file_exists($template_file)) {
            wp_die(
                sprintf(
                    'WP Diagnostic Toolkit template not found: %s',
                    esc_html($template)
                )
            );
        }

        extract($data, EXTR_SKIP);

        include $template_file;
    }
}
