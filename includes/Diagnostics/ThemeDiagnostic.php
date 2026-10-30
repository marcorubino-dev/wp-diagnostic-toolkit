<?php

/**
 * WordPress theme diagnostics.
 */

namespace WPDT\Diagnostics;

use WPDT\Core\DiagnosticInterface;

class ThemeDiagnostic implements DiagnosticInterface
{
    /**
     * Get the diagnostic identifier.
     *
     * @return string
     */
    public function get_id()
    {
        return 'themes';
    }

    /**
     * Get the diagnostic name.
     *
     * @return string
     */
    public function get_name()
    {
        return 'WordPress Themes';
    }

    /**
     * Run theme checks.
     *
     * @return array
     */
    public function run()
    {
        $themes = wp_get_themes();
        $active_theme = wp_get_theme();
        $stylesheet = $active_theme->get_stylesheet();
        $template = $active_theme->get_template();
        $is_child_theme = $stylesheet !== $template;

        $results = array(
            array(
                'name'        => 'Installed themes',
                'value'       => count($themes),
                'status'      => 'info',
                'description' => 'Total number of themes installed on this site.',
            ),
            array(
                'name'        => 'Active theme',
                'value'       => $active_theme->get('Name'),
                'status'      => 'info',
                'description' => 'Theme currently active on this site.',
                'evidence'    => 'Stylesheet: ' . $stylesheet,
            ),
            array(
                'name'        => 'Active theme version',
                'value'       => $active_theme->get('Version')
                    ? $active_theme->get('Version')
                    : 'Not declared',
                'status'      => 'info',
                'description' => 'Version declared by the active theme stylesheet.',
            ),
        );

        if ($is_child_theme) {
            $parent_theme = wp_get_theme($template);
            $parent_exists = $parent_theme->exists();

            $results[] = array(
                'name'           => 'Child theme parent',
                'value'          => $parent_exists
                    ? $parent_theme->get('Name') . ' (' . $template . ')'
                    : $template . ' (missing)',
                'status'         => $parent_exists ? 'pass' : 'warning',
                'description'    => $parent_exists
                    ? 'The active child theme parent is installed.'
                    : 'The active child theme references a parent theme that is not installed.',
                'recommendation' => $parent_exists
                    ? 'No action required.'
                    : 'Install the required parent theme or correct the child theme Template header.',
            );
        } else {
            $results[] = array(
                'name'        => 'Child theme parent',
                'value'       => 'Not applicable',
                'status'      => 'info',
                'description' => 'The active theme is not a child theme.',
            );
        }

        $update_result = $this->get_active_theme_update($stylesheet);

        $results[] = array(
            'name'           => 'Active theme updates',
            'value'          => $update_result['value'],
            'status'         => $update_result['status'],
            'description'    => $update_result['description'],
            'recommendation' => $update_result['recommendation'],
        );

        return $results;
    }

    /**
     * Check whether WordPress reports an update for the active theme.
     *
     * @param string $stylesheet Active theme stylesheet directory.
     * @return array
     */
    private function get_active_theme_update($stylesheet)
    {
        $updates = get_site_transient('update_themes');

        if (
            false === $updates
            || ! isset($updates->response)
            || ! is_array($updates->response)
        ) {
            return array(
                'value'          => 'Not available',
                'status'         => 'info',
                'description'    => 'Theme update information is not currently available.',
                'recommendation' => 'Refresh WordPress update information and re-run the audit.',
            );
        }

        if (! isset($updates->response[$stylesheet])) {
            return array(
                'value'          => 'No update available',
                'status'         => 'pass',
                'description'    => 'WordPress reports no available update for the active theme.',
                'recommendation' => 'No action required.',
            );
        }

        $update = $updates->response[$stylesheet];
        $new_version = is_object($update) && isset($update->new_version)
            ? $update->new_version
            : 'Available';

        return array(
            'value'          => $new_version,
            'status'         => 'warning',
            'description'    => 'An update is available for the active theme.',
            'recommendation' => 'Review the update and test it in a staging environment before applying it.',
        );
    }
}
