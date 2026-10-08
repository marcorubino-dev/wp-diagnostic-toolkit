<?php

/**
 * WordPress environment diagnostics.
 */

namespace WPDT\Diagnostics;

use WPDT\Core\DiagnosticInterface;

class EnvironmentDiagnostic implements DiagnosticInterface
{
    /**
     * Get the diagnostic identifier.
     *
     * @return string
     */
    public function get_id()
    {
        return 'environment';
    }

    /**
     * Get the diagnostic name.
     *
     * @return string
     */
    public function get_name()
    {
        return 'WordPress Environment';
    }

    /**
     * Run environment checks.
     *
     * @return array
     */
    public function run()
    {
        global $wpdb;

        $debug_enabled = defined('WP_DEBUG') && WP_DEBUG;

        $memory_limit = defined('WP_MEMORY_LIMIT')
            ? WP_MEMORY_LIMIT
            : 'Not explicitly defined';

        $home_url = get_home_url();
        $site_url = get_site_url();

        $home_url_https = wp_parse_url($home_url, PHP_URL_SCHEME) === 'https';
        $site_url_https = wp_parse_url($site_url, PHP_URL_SCHEME) === 'https';

        /*
     * Loopback request.
     */
        $loopback_url = home_url('/');

        $loopback_response = wp_remote_get(
            $loopback_url,
            array(
                'timeout' => 5,
            )
        );

        $loopback_status_code = null;

        if (! is_wp_error($loopback_response)) {
            $loopback_status_code = wp_remote_retrieve_response_code(
                $loopback_response
            );
        }

        if (is_wp_error($loopback_response)) {
            $loopback_status = 'warning';
            $loopback_value = 'Request failed';
            $loopback_description = 'WordPress could not successfully reach its own site.';
            $loopback_recommendation = 'Review loopback requests, DNS, firewall, or server configuration.';
            $loopback_evidence = $loopback_response->get_error_message();
        } elseif ($loopback_status_code >= 500) {
            $loopback_status = 'critical';
            $loopback_value = $loopback_status_code;
            $loopback_description = 'WordPress reached its own site but received a server error.';
            $loopback_recommendation = 'Inspect server logs and WordPress errors.';
            $loopback_evidence = $loopback_url . ' | HTTP ' . $loopback_status_code;
        } elseif ($loopback_status_code >= 400) {
            $loopback_status = 'warning';
            $loopback_value = $loopback_status_code;
            $loopback_description = 'WordPress reached its own site but received an HTTP client error.';
            $loopback_recommendation = 'Review the loopback URL, authentication, and server configuration.';
            $loopback_evidence = $loopback_url . ' | HTTP ' . $loopback_status_code;
        } elseif ($loopback_status_code >= 300) {
            $loopback_status = 'warning';
            $loopback_value = $loopback_status_code;
            $loopback_description = 'WordPress reached its own site but received an HTTP redirect.';
            $loopback_recommendation = 'Review the loopback URL and redirect configuration.';
            $loopback_evidence = $loopback_url . ' | HTTP ' . $loopback_status_code;
        } else {
            $loopback_status = 'pass';
            $loopback_value = $loopback_status_code;
            $loopback_description = 'WordPress successfully reached its own site.';
            $loopback_recommendation = 'No action required.';
            $loopback_evidence = $loopback_url . ' | HTTP ' . $loopback_status_code;
        }

        /*
     * PHP version.
     */
        $php_version = PHP_VERSION;
        $minimum_php_version = '7.4';

        $php_version_supported = version_compare(
            $php_version,
            $minimum_php_version,
            '>='
        );

        return array(
            array(
                'name'        => 'WordPress version',
                'value'       => get_bloginfo('version'),
                'status'      => 'info',
                'description' => 'Installed WordPress version.',
            ),
            array(
                'name'           => 'PHP version',
                'value'          => $php_version,
                'status'         => $php_version_supported ? 'pass' : 'warning',
                'description'    => $php_version_supported
                    ? 'PHP version meets the configured minimum requirement.'
                    : 'PHP version is below the configured minimum requirement.',
                'recommendation' => $php_version_supported
                    ? 'No action required.'
                    : 'Upgrade PHP to a supported version.',
                'evidence'       => 'PHP_VERSION',
            ),
            array(
                'name'        => 'Database version',
                'value'       => $wpdb->db_version(),
                'status'      => 'info',
                'description' => 'Database server version reported by WordPress.',
            ),
            array(
                'name'        => 'WP_DEBUG',
                'value'       => $debug_enabled ? 'Enabled' : 'Disabled',
                'status'      => $debug_enabled ? 'warning' : 'pass',
                'description' => $debug_enabled
                    ? 'Debug mode is enabled. Check the environment before exposing errors publicly.'
                    : 'WordPress debug mode is disabled.',
            ),
            array(
                'name'        => 'WP_MEMORY_LIMIT',
                'value'       => $memory_limit,
                'status'      => 'info',
                'description' => 'WordPress memory limit constant, if explicitly defined.',
            ),
            array(
                'name'           => 'Home URL HTTPS',
                'value'          => $home_url_https ? 'Enabled' : 'Disabled',
                'status'         => $home_url_https ? 'pass' : 'warning',
                'description'    => $home_url_https
                    ? 'The WordPress home URL uses HTTPS.'
                    : 'The WordPress home URL does not use HTTPS.',
                'recommendation' => $home_url_https
                    ? 'No action required.'
                    : 'Configure the home URL to use HTTPS.',
                'evidence'       => $home_url,
            ),
            array(
                'name'           => 'Site URL HTTPS',
                'value'          => $site_url_https ? 'Enabled' : 'Disabled',
                'status'         => $site_url_https ? 'pass' : 'warning',
                'description'    => $site_url_https
                    ? 'The WordPress site URL uses HTTPS.'
                    : 'The WordPress site URL does not use HTTPS.',
                'recommendation' => $site_url_https
                    ? 'No action required.'
                    : 'Configure the site URL to use HTTPS.',
                'evidence'       => $site_url,
            ),
            array(
                'name'           => 'Loopback request',
                'value'          => $loopback_value,
                'status'         => $loopback_status,
                'description'    => $loopback_description,
                'recommendation' => $loopback_recommendation,
                'evidence'       => $loopback_evidence,
            ),
        );
    }
}
