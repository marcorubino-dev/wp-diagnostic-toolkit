<?php

/**
 * Read-only checks for WooCommerce and multilingual integrations.
 */

namespace WPDT\Diagnostics;

use WPDT\Core\DiagnosticInterface;

class MultilingualWooCommerceDiagnostic implements DiagnosticInterface
{
    /**
     * Known multilingual plugin files.
     *
     * @var array
     */
    private $multilingual_plugins = array(
        'sitepress-multilingual-cms/sitepress.php' => 'WPML',
        'polylang/polylang.php' => 'Polylang',
        'translatepress-multilingual/index.php' => 'TranslatePress',
        'weglot/weglot.php' => 'Weglot',
        'qtranslate-xt/qtranslate.php' => 'qTranslate-XT',
    );

    public function get_id()
    {
        return 'multilingual_woocommerce';
    }

    public function get_name()
    {
        return 'Multilingual & WooCommerce';
    }

    /**
     * Run read-only environment checks.
     *
     * @return array
     */
    public function run()
    {
        $results = array();
        $active_plugins = $this->get_active_plugins();
        $installed_plugins = $this->get_installed_plugins();

        $woocommerce_file = 'woocommerce/woocommerce.php';
        $woocommerce_runtime = class_exists('WooCommerce') || defined('WC_VERSION');
        $woocommerce_installed = isset($installed_plugins[$woocommerce_file]);
        $woocommerce_active = $woocommerce_runtime || in_array($woocommerce_file, $active_plugins, true);

        if ($woocommerce_active) {
            $woocommerce_status = 'pass';
            $woocommerce_description = 'WooCommerce is active on this site.';
            $woocommerce_value = defined('WC_VERSION')
                ? WC_VERSION
                : 'Active; version unavailable';
            $woocommerce_recommendation = 'No action required.';
        } elseif ($woocommerce_installed) {
            $woocommerce_status = 'info';
            $woocommerce_description = 'WooCommerce is installed but is not active.';
            $woocommerce_value = 'Installed; inactive';
            $woocommerce_recommendation = 'Activate WooCommerce only if this site is intended to run an online store.';
        } else {
            $woocommerce_status = 'info';
            $woocommerce_description = 'WooCommerce is not installed. WooCommerce-specific checks are not applicable.';
            $woocommerce_value = 'Not installed';
            $woocommerce_recommendation = 'No action required unless this site is intended to use WooCommerce.';
        }

        $results[] = array(
            'id' => 'woocommerce_detected',
            'name' => 'WooCommerce availability',
            'status' => $woocommerce_status,
            'description' => $woocommerce_description,
            'value' => $woocommerce_value,
            'evidence' => 'Checked the installed plugin inventory, active plugin entries, WooCommerce class, and WC_VERSION constant.',
            'recommendation' => $woocommerce_recommendation,
        );

        $integration_states = array();
        $active_integrations = array();
        $inactive_integrations = array();

        foreach ($this->multilingual_plugins as $plugin_file => $plugin_name) {
            if (in_array($plugin_file, $active_plugins, true)) {
                $active_integrations[] = $plugin_name;
                $integration_states[] = $plugin_name . ': active';
            } elseif (isset($installed_plugins[$plugin_file])) {
                $inactive_integrations[] = $plugin_name;
                $integration_states[] = $plugin_name . ': installed, inactive';
            }
        }

        // Runtime APIs can confirm integrations even when their plugin file is
        // not present in the regular active_plugins option (for example, custom
        // loading or network-level setups).
        if (function_exists('pll_current_language') && ! in_array('Polylang', $active_integrations, true)) {
            $active_integrations[] = 'Polylang';
            $integration_states[] = 'Polylang: runtime API detected';
        }

        if (defined('ICL_SITEPRESS_VERSION') && ! in_array('WPML', $active_integrations, true)) {
            $active_integrations[] = 'WPML';
            $integration_states[] = 'WPML: runtime API detected';
        }

        if (! empty($active_integrations)) {
            $multilingual_status = 'pass';
            $multilingual_description = 'One or more supported multilingual integrations are active. Detection does not verify translation configuration.';
            $multilingual_value = implode(', ', $integration_states);
            $multilingual_recommendation = 'Verify the translation relationship for the affected products and categories.';
        } elseif (! empty($inactive_integrations)) {
            $multilingual_status = 'info';
            $multilingual_description = 'Supported multilingual plugin(s) are installed but inactive.';
            $multilingual_value = implode(', ', $integration_states);
            $multilingual_recommendation = 'Activate the intended multilingual plugin if this site is meant to serve translated content.';
        } else {
            $multilingual_status = 'info';
            $multilingual_description = 'No supported multilingual plugin is installed.';
            $multilingual_value = 'None installed';
            $multilingual_recommendation = 'No action required unless this site is intended to serve multilingual content.';
        }

        $results[] = array(
            'id' => 'multilingual_plugin_detection',
            'name' => 'Multilingual integration detection',
            'status' => $multilingual_status,
            'description' => $multilingual_description,
            'value' => $multilingual_value,
            'evidence' => 'Compared known plugin files against the installed and active plugin inventories, then checked WPML and Polylang runtime APIs.',
            'recommendation' => $multilingual_recommendation,
        );

        $results[] = array(
            'id' => 'cart_session_scope',
            'name' => 'Cart/session diagnostic limitation',
            'status' => 'info',
            'description' => 'The admin snapshot does not represent a visitor’s live WooCommerce cart or prove whether the session survives a language switch.',
            'value' => 'Frontend reproduction and before/after capture required',
            'evidence' => 'This module runs in the WordPress admin request and intentionally does not read or modify customer sessions.',
            'recommendation' => 'Reproduce the issue in a test session and compare product IDs, quantities, prices, language, URL, and cart contents before and after switching languages.',
        );

        $results[] = array(
            'id' => 'translation_mapping',
            'name' => 'Product translation mapping',
            'status' => 'info',
            'description' => 'Translation mappings are not inferred from matching product IDs. Different languages may legitimately use different product IDs.',
            'value' => 'Not inspected in this initial version',
            'evidence' => 'The installed multilingual integration determines how translation relationships are exposed.',
            'recommendation' => 'Next iteration: add adapter-specific checks for the detected multilingual plugin and compare the source product with its translated counterpart.',
        );

        return $results;
    }

    /**
     * Get plugin files active on this site, including network activation.
     *
     * @return array
     */
    private function get_active_plugins()
    {
        $active_plugins = (array) get_option('active_plugins', array());

        if (is_multisite()) {
            $network_plugins = array_keys(
                (array) get_site_option('active_sitewide_plugins', array())
            );

            $active_plugins = array_merge($active_plugins, $network_plugins);
        }

        return array_values(array_unique($active_plugins));
    }

    /**
     * Get the installed plugin inventory.
     *
     * @return array
     */
    private function get_installed_plugins()
    {
        if (! function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if (! function_exists('get_plugins')) {
            return array();
        }

        return get_plugins();
    }
}
