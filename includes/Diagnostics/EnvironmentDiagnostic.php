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
     * Evaluate a loopback response.
     *
     * @param mixed  $response    WordPress HTTP response or WP_Error.
     * @param string $request_url Loopback request URL.
     *
     * @return array
     */
    private function evaluate_loopback_response($response, $request_url)
    {
        if (is_wp_error($response)) {
            return array(
                'status'         => 'warning',
                'value'          => 'Request failed',
                'description'    => 'WordPress could not successfully reach its own site.',
                'recommendation' => 'Review loopback requests, DNS, firewall, or server configuration.',
                'evidence'       => $response->get_error_message(),
            );
        }

        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code >= 500) {
            return array(
                'status'         => 'critical',
                'value'          => $status_code,
                'description'    => 'WordPress reached its own site but received a server error.',
                'recommendation' => 'Inspect server logs and WordPress errors.',
                'evidence'       => $request_url . ' | HTTP ' . $status_code,
            );
        }

        if ($status_code >= 400) {
            return array(
                'status'         => 'warning',
                'value'          => $status_code,
                'description'    => 'WordPress reached its own site but received an HTTP client error.',
                'recommendation' => 'Review the loopback URL, authentication, and server configuration.',
                'evidence'       => $request_url . ' | HTTP ' . $status_code,
            );
        }

        if ($status_code >= 300) {
            return array(
                'status'         => 'warning',
                'value'          => $status_code,
                'description'    => 'WordPress reached its own site but received an HTTP redirect.',
                'recommendation' => 'Review the loopback URL and redirect configuration.',
                'evidence'       => $request_url . ' | HTTP ' . $status_code,
            );
        }

        return array(
            'status'         => 'pass',
            'value'          => $status_code,
            'description'    => 'WordPress successfully reached its own site.',
            'recommendation' => 'No action required.',
            'evidence'       => $request_url . ' | HTTP ' . $status_code,
        );
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

        $loopback_result = $this->evaluate_loopback_response(
            $loopback_response,
            $loopback_url
        );

        $loopback_status = $loopback_result['status'];
        $loopback_value = $loopback_result['value'];
        $loopback_description = $loopback_result['description'];
        $loopback_recommendation = $loopback_result['recommendation'];
        $loopback_evidence = $loopback_result['evidence'];

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
