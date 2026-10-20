<?php

/**
 * Read-only checks for WooCommerce and multilingual integrations.
 */

namespace WPDT\Diagnostics;

use WPDT\Core\DiagnosticInterface;

class MultilingualWooCommerceDiagnostic implements DiagnosticInterface
{
    /**
     * Known multilingual plugin slugs.
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
        $active_plugins = (array) get_option('active_plugins', array());

        if (is_multisite()) {
            $network_plugins = array_keys((array) get_site_option('active_sitewide_plugins', array()));
            $active_plugins = array_unique(array_merge($active_plugins, $network_plugins));
        }

        $woocommerce_active = class_exists('WooCommerce') || defined('WC_VERSION');

        $results[] = array(
            'id' => 'woocommerce_detected',
            'name' => 'WooCommerce availability',
            'status' => $woocommerce_active ? 'pass' : 'warning',
            'description' => $woocommerce_active
                ? 'WooCommerce is loaded in this request.'
                : 'WooCommerce was not detected as loaded. Some checks require WooCommerce to be active.',
            'value' => defined('WC_VERSION') ? WC_VERSION : ($woocommerce_active ? 'Class available; version constant unavailable' : 'Not detected'),
            'evidence' => 'Checked the WooCommerce class and WC_VERSION constant.',
            'recommendation' => $woocommerce_active ? '' : 'Confirm WooCommerce is installed and active, then reload this diagnostic page.',
        );

        $detected = array();
        foreach ($this->multilingual_plugins as $plugin_file => $plugin_name) {
            if (in_array($plugin_file, $active_plugins, true)) {
                $detected[] = $plugin_name;
            }
        }

        if (function_exists('pll_current_language') && ! in_array('Polylang', $detected, true)) {
            $detected[] = 'Polylang (runtime API detected)';
        }

        if (defined('ICL_SITEPRESS_VERSION') && ! in_array('WPML', $detected, true)) {
            $detected[] = 'WPML (runtime API detected)';
        }

        $results[] = array(
            'id' => 'multilingual_plugin_detection',
            'name' => 'Multilingual integration detection',
            'status' => empty($detected) ? 'info' : 'pass',
            'description' => empty($detected)
                ? 'No supported multilingual plugin was detected by the current checks.'
                : 'Detected multilingual plugin(s). Detection does not prove that translations are configured correctly.',
            'value' => empty($detected) ? 'None detected' : implode(', ', $detected),
            'evidence' => 'Checked active plugin entries and selected runtime APIs.',
            'recommendation' => empty($detected)
                ? 'If this site uses a multilingual plugin, verify its plugin slug and add a detection rule if needed.'
                : 'Verify the translation relationship for the affected product and category in the multilingual plugin.',
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
}
