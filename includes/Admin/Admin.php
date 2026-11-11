<?php

/**
 * WordPress admin interface for WP Diagnostic Toolkit.
 */

namespace WPDT\Admin;

use WPDT\Core\DiagnosticManager;
use WPDT\Debug\DebugConfigurationInspector;
use WPDT\Debug\DebugLogTargetInspector;

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
            'HTTP & SSL',
            'HTTP & SSL',
            'manage_options',
            'wpdt-http-ssl',
            array($this, 'render_http_ssl')
        );

        add_submenu_page(
            'wp-diagnostic-toolkit',
            'Themes',
            'Themes',
            'manage_options',
            'wpdt-themes',
            array($this, 'render_themes')
        );

        add_submenu_page(
            'wp-diagnostic-toolkit',
            'Disk Space',
            'Disk Space',
            'manage_options',
            'wpdt-disk-space',
            array($this, 'render_disk_space')
        );

        add_submenu_page(
            'wp-diagnostic-toolkit',
            'Multilingual & WooCommerce',
            'Multilingual & WooCommerce',
            'manage_options',
            'wpdt-multilingual-woocommerce',
            array($this, 'render_multilingual_woocommerce')
        );

        add_submenu_page(
            'wp-diagnostic-toolkit',
            'Debug Center',
            'Debug Center',
            'manage_options',
            'wpdt-debug-center',
            array($this, 'render_debug_center')
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

        wp_enqueue_script(
            'wpdt-admin',
            WPDT_URL . 'includes/Admin/assets/admin.js',
            array(),
            WPDT_VERSION,
            true
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
     * Render HTTP and SSL diagnostics.
     *
     * @return void
     */
    public function render_http_ssl()
    {
        $this->render_diagnostic_module('http_ssl');
    }

    /**
     * Render theme diagnostics.
     *
     * @return void
     */
    public function render_themes()
    {
        $this->render_diagnostic_module('themes');
    }

    /**
     * Render disk space diagnostics.
     *
     * @return void
     */
    public function render_disk_space()
    {
        $this->render_diagnostic_module('disk_space');
    }

    /**
     * Render multilingual and WooCommerce diagnostics.
     *
     * @return void
     */
    public function render_multilingual_woocommerce()
    {
        $this->render_diagnostic_module('multilingual_woocommerce');
    }

    /**
     * Render the read-only Debug Center.
     *
     * @return void
     */
    public function render_debug_center()
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $configuration = (new DebugConfigurationInspector())->inspect();
        $debug_log_value = defined('WP_DEBUG_LOG') ? constant('WP_DEBUG_LOG') : null;
        $target = (new DebugLogTargetInspector())->inspect(
            $debug_log_value,
            $configuration['wordpress_debug'],
            defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : ''
        );

        $this->render_template(
            'debug-center',
            array(
                'configuration' => $configuration,
                'target' => $target,
            )
        );
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

        $legacy_templates = array('environment', 'plugins', 'server');
        $template = in_array($module_id, $legacy_templates, true)
            ? $module_id
            : 'module';

        $this->render_template(
            $template,
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
