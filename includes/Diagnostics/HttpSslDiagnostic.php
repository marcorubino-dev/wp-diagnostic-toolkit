<?php

/**
 * HTTP and SSL diagnostics for the WordPress home URL.
 */

namespace WPDT\Diagnostics;

use WPDT\Core\DiagnosticInterface;

class HttpSslDiagnostic implements DiagnosticInterface
{
    public function get_id()
    {
        return 'http_ssl';
    }

    public function get_name()
    {
        return 'HTTP & SSL';
    }

    /**
     * Run read-only HTTP and TLS checks against the configured home URL.
     *
     * @return array
     */
    public function run()
    {
        $url = get_home_url();
        $parts = wp_parse_url($url);
        $scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : '';
        $is_https = 'https' === $scheme;

        $response = wp_remote_head(
            $url,
            array(
                'timeout'     => 8,
                'redirection' => 3,
                'sslverify'   => true,
            )
        );

        $results = array();

        if (is_wp_error($response)) {
            $results[] = array(
                'name'           => 'Home URL HTTP response',
                'value'          => 'Request failed',
                'status'         => 'warning',
                'description'    => 'The configured home URL could not be reached with an HTTP HEAD request.',
                'evidence'       => $url . ' | ' . $response->get_error_message(),
                'recommendation' => 'Check DNS, hosting availability, firewall rules, redirects, and server response.',
            );
        } else {
            $status_code = (int) wp_remote_retrieve_response_code($response);
            $status = $status_code >= 500
                ? 'critical'
                : ($status_code >= 400 || $status_code >= 300 ? 'warning' : 'pass');

            $results[] = array(
                'name'           => 'Home URL HTTP response',
                'value'          => $status_code,
                'status'         => $status,
                'description'    => $status_code >= 500
                    ? 'The home URL returned a server error.'
                    : ($status_code >= 400
                        ? 'The home URL returned a client error.'
                        : ($status_code >= 300
                            ? 'The home URL returned a redirect after the configured redirect limit.'
                            : 'The home URL responded successfully to an HTTP HEAD request.')),
                'evidence'       => $url . ' | HTTP ' . $status_code,
                'recommendation' => 'Review the HTTP response and redirect configuration if this result is not expected.',
            );
        }

        if (! $is_https) {
            $results[] = array(
                'name'           => 'SSL certificate validation',
                'value'          => 'Not applicable',
                'status'         => 'info',
                'description'    => 'The configured home URL does not use HTTPS, so a TLS certificate cannot be validated for this URL.',
                'evidence'       => $url,
                'recommendation' => 'Configure HTTPS if this site is intended to be publicly accessible.',
            );

            return $results;
        }

        if (is_wp_error($response)) {
            $ssl_error = $this->contains_ssl_error($response);

            $results[] = array(
                'name'           => 'SSL certificate validation',
                'value'          => $ssl_error ? 'Validation failed' : 'Not confirmed',
                'status'         => $ssl_error ? 'critical' : 'info',
                'description'    => $ssl_error
                    ? 'The HTTPS request failed during TLS/SSL validation.'
                    : 'The HTTPS request failed, so certificate validity could not be confirmed.',
                'evidence'       => $response->get_error_message(),
                'recommendation' => $ssl_error
                    ? 'Check certificate validity, hostname coverage, certificate chain, and server TLS configuration.'
                    : 'Resolve the connection issue and re-run the diagnostic before drawing conclusions about the certificate.',
            );

            return $results;
        }

        $results[] = array(
            'name'           => 'SSL certificate validation',
            'value'          => 'TLS verification passed',
            'status'         => 'pass',
            'description'    => 'WordPress completed an HTTPS request with certificate verification enabled.',
            'evidence'       => 'WordPress HTTP API request used sslverify=true. This confirms validation for this request, not the certificate expiry date.',
            'recommendation' => 'No action required.',
        );

        return $results;
    }

    /**
     * Determine whether a WP_Error message indicates a TLS/SSL failure.
     *
     * @param \WP_Error $error Request error.
     * @return bool
     */
    private function contains_ssl_error($error)
    {
        $message = strtolower($error->get_error_message());

        foreach (array('ssl', 'certificate', 'cert', 'tls') as $term) {
            if (false !== strpos($message, $term)) {
                return true;
            }
        }

        return false;
    }
}
