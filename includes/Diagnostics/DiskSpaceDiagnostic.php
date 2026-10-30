<?php

/**
 * Disk space diagnostics.
 */

namespace WPDT\Diagnostics;

use WPDT\Core\DiagnosticInterface;

class DiskSpaceDiagnostic implements DiagnosticInterface
{
    /**
     * Get the diagnostic identifier.
     *
     * @return string
     */
    public function get_id()
    {
        return 'disk_space';
    }

    /**
     * Get the diagnostic name.
     *
     * @return string
     */
    public function get_name()
    {
        return 'Disk Space';
    }

    /**
     * Run disk space checks.
     *
     * @return array
     */
    public function run()
    {
        $path = defined('ABSPATH') ? ABSPATH : __DIR__;
        
        if (! function_exists('disk_free_space') || ! function_exists('disk_total_space')) {
            return array(
                array(
                    'name'           => 'Filesystem disk space',
                    'value'          => 'Not available',
                    'status'         => 'info',
                    'description'    => 'PHP does not provide the filesystem space functions required by this check.',
                    'recommendation' => 'Check disk capacity through the hosting control panel or server monitoring.',
                ),
            );
        }

        $free_space = @disk_free_space($path);
        $total_space = @disk_total_space($path);

        if (
            false === $free_space
            || false === $total_space
            || $total_space <= 0
        ) {
            return array(
                array(
                    'name'           => 'Filesystem disk space',
                    'value'          => 'Not available',
                    'status'         => 'info',
                    'description'    => 'The filesystem did not provide reliable total and free space values for the WordPress installation path.',
                    'evidence'       => 'Checked disk_free_space() and disk_total_space() for the WordPress installation path.',
                    'recommendation' => 'Check disk capacity through the hosting control panel or server monitoring.',
                ),
            );
        }

        $used_space = max(0, $total_space - $free_space);
        $used_percent = ($used_space / $total_space) * 100;
        $free_percent = ($free_space / $total_space) * 100;

        return array(
            array(
                'name'        => 'Filesystem disk space',
                'value'       => $this->format_bytes($free_space) . ' free of ' . $this->format_bytes($total_space),
                'status'      => 'info',
                'description' => 'Available and total capacity reported by the filesystem containing the WordPress installation.',
                'evidence'    => sprintf(
                    'Used: %s (%.1f%%) | Free: %.1f%% | Path: %s',
                    $this->format_bytes($used_space),
                    $used_percent,
                    $free_percent,
                    $path
                ),
            ),
        );
    }

    /**
     * Format a byte count as a human-readable size.
     *
     * @param float|int $bytes Byte count.
     * @return string
     */
    private function format_bytes($bytes)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $size = (float) $bytes;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return number_format($size, 2) . ' ' . $units[$unit];
    }
}
