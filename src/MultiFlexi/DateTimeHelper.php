<?php

declare(strict_types=1);

/**
 * This file is part of the MultiFlexi package
 *
 * https://multiflexi.eu/
 *
 * (c) Vítězslav Dvořák <http://vitexsoftware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MultiFlexi;

use DateTimeZone;
use Ease\Shared;

/**
 * Helper class for DateTime operations with timezone support
 */
class DateTimeHelper
{
    /**
     * Autodetect the server's timezone from Linux system configuration
     *
     * Attempts to detect timezone in the following order:
     * 1. Read from /etc/timezone file (Debian/Ubuntu)
     * 2. Read symlink from /etc/localtime (most Linux distributions)
     * 3. Use timedatectl command if available
     *
     * @return string|null The detected timezone string or null if detection fails
     */
    public static function autodetectServerTimezone(): ?string
    {
        // Method 1: Read /etc/timezone file (Debian/Ubuntu)
        if (file_exists('/etc/timezone')) {
            $timezone = trim(file_get_contents('/etc/timezone'));

            if (!empty($timezone)) {
                try {
                    new DateTimeZone($timezone);

                    return $timezone;
                } catch (\Exception $e) {
                    error_log('Invalid timezone from /etc/timezone: '.$timezone);
                }
            }
        }

        // Method 2: Read /etc/localtime symlink
        if (is_link('/etc/localtime')) {
            $symlinkPath = readlink('/etc/localtime');

            // Extract timezone from path like /usr/share/zoneinfo/Europe/Prague
            if (preg_match('#zoneinfo/(.+)$#', $symlinkPath, $matches)) {
                $timezone = $matches[1];

                try {
                    new DateTimeZone($timezone);

                    return $timezone;
                } catch (\Exception $e) {
                    error_log('Invalid timezone from /etc/localtime symlink: '.$timezone);
                }
            }
        }

        // Method 3: Use timedatectl command
        if (function_exists('exec')) {
            $output = [];
            $returnVar = 0;
            @exec('timedatectl show --property=Timezone --value 2>/dev/null', $output, $returnVar);

            if ($returnVar === 0 && !empty($output[0])) {
                $timezone = trim($output[0]);

                try {
                    new DateTimeZone($timezone);

                    return $timezone;
                } catch (\Exception $e) {
                    error_log('Invalid timezone from timedatectl: '.$timezone);
                }
            }
        }

        return null;
    }

    /**
     * Get the configured timezone
     *
     * Returns a DateTimeZone object based on the MULTIFLEXI_TIMEZONE configuration
     * from the environment. If not configured, attempts to autodetect from the server.
     * Falls back to UTC if autodetection fails.
     *
     * @return DateTimeZone The configured timezone
     */
    public static function getConfiguredTimezone(): DateTimeZone
    {
        $timezone = Shared::cfg('MULTIFLEXI_TIMEZONE');

        // If not explicitly configured, try to autodetect from server
        if (empty($timezone)) {
            $timezone = self::autodetectServerTimezone();
        }

        // Fallback to UTC if still not set
        if (empty($timezone)) {
            $timezone = 'UTC';
        }

        try {
            return new DateTimeZone($timezone);
        } catch (\Exception $e) {
            // Fallback to UTC if configured timezone is invalid
            error_log('Invalid timezone configured: '.$timezone.'. Falling back to UTC.');

            return new DateTimeZone('UTC');
        }
    }

    /**
     * Get the configured timezone string
     *
     * @return string The configured timezone string (e.g., 'UTC', 'Europe/Prague')
     */
    public static function getConfiguredTimezoneString(): string
    {
        return self::getConfiguredTimezone()->getName();
    }

    /**
     * Create a DateTime object with the configured timezone
     *
     * @param string $datetime The datetime string (e.g., '2024-01-01 12:00:00')
     *
     * @return \DateTime DateTime object with configured timezone
     */
    public static function createDateTime(string $datetime = 'now'): \DateTime
    {
        return new \DateTime($datetime, self::getConfiguredTimezone());
    }
}
