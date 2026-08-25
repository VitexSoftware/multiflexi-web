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

use MultiFlexi\Company;

/**
 * Description of CompanyAppChooser.
 *
 * @author vitex
 *
 * @no-named-arguments
 */
class CompanyAppChooser extends \Ease\Html\SelectTag
{
    /**
     * Choose from applications Assigned to given company.
     *
     * @param string                $name       form input name
     * @param array<string, string> $properties
     */
    public function __construct(string $name, Company $company, string $defaultValue = '', array $properties = [])
    {
        $companyApp = new \MultiFlexi\CompanyApp($company);
        $currentLang = substr(\Ease\Locale::$localeUsed ?? 'en_US', 0, 2);
        $assignedRaw = $companyApp->getAssigned()
            ->leftJoin('apps ON apps.id = companyapp.app_id')
            ->leftJoin('app_translations ON app_translations.app_id = apps.id AND app_translations.lang = ?', $currentLang)
            ->select(['apps.name', 'app_translations.name AS name_localized'])
            ->fetchAll('app_id');

        foreach ($assignedRaw as $appId => $appProperties) {
            $assignedRaw[$appId] = !empty($appProperties['name_localized']) ? $appProperties['name_localized'] : $appProperties['name'];
        }

        $assigned = empty($assignedRaw) ? [] : $assignedRaw;

        parent::__construct($name, $assigned, $defaultValue, $properties);
    }
}
