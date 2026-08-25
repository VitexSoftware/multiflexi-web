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

namespace MultiFlexi\Ui;

use MultiFlexi\AbraFlexi\Company;
use MultiFlexi\CompanyApp;

/**
 * Description of CompanyAppSelector.
 *
 * @author vitex
 *
 * @no-named-arguments
 */
class CompanyAppSelector extends AppsSelector
{
    protected int $companyId;
    public function __construct($company, $identifier = null, $enabled = [], $optionsPage = 'apps.php')
    {
        $this->companyId = $company->getMyKey();
        parent::__construct($identifier, $enabled, $optionsPage);
    }

    public function availbleApps()
    {
        $currentLang = substr(\Ease\Locale::$localeUsed ?? 'en_US', 0, 2);

        $apps = (new CompanyApp(new Company($this->companyId)))->getAssigned()
            ->leftJoin('apps ON apps.id = companyapp.app_id')
            ->leftJoin('app_translations ON app_translations.app_id = apps.id AND app_translations.lang = ?', $currentLang)
            ->select(['apps.name', 'apps.description', 'apps.id', 'apps.image', 'app_translations.name AS name_localized', 'app_translations.description AS description_localized'], true)
            ->fetchAll();

        return self::applyLocalizedNames($apps);
    }
}
