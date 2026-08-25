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

/**
 * Application with localization support.
 *
 * This class extends the base Application class with translation capabilities
 */
class LocalizedApplication extends Application
{
    use ApplicationTranslation;

    /**
     * Return the application name localized to the current locale, falling
     * back to the raw record name when no translation is available.
     */
    public function getRecordName()
    {
        return $this->getLocalizedName() ?? parent::getRecordName();
    }

    /**
     * Localized name for a plain Application instance, without requiring
     * every call site to construct a LocalizedApplication itself.
     */
    public static function nameOf(Application $app): string
    {
        if ($app instanceof self) {
            return (string) $app->getRecordName();
        }

        $localized = new self($app->getMyKey());

        return (string) $localized->getRecordName();
    }

    /**
     * Localized description for a plain Application instance.
     */
    public static function descriptionOf(Application $app): string
    {
        $localized = $app instanceof self ? $app : new self($app->getMyKey());

        return (string) ($localized->getLocalizedDescription() ?? $app->getDataValue('description'));
    }
}
