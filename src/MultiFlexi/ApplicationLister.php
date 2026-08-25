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
 * Description of ApplicationLister.
 *
 * @author Vitex <info@vitexsoftware.cz>
 */
class ApplicationLister extends Application
{
    /**
     * @param array $columns
     *
     * @return array
     */
    public function columns($columns = [])
    {
        return parent::columns([
            ['name' => 'id', 'type' => 'text', 'label' => _('ID'),
                'detailPage' => 'app.php', 'valueColumn' => 'apps.id', 'idColumn' => 'apps.id', ],
            ['name' => 'icon', 'type' => 'text', 'label' => _('Icon'), 'searchable' => false],
            ['name' => 'name', 'type' => 'text', 'label' => _('Name')],
            ['name' => 'description', 'type' => 'text', 'label' => _('Description')],
            ['name' => 'version', 'type' => 'text', 'label' => _('Version')],
            ['name' => 'tags', 'type' => 'text', 'label' => _('Tags')],
            ['name' => 'executable', 'type' => 'text', 'label' => _('Executable')],
            ['name' => 'uuid', 'type' => 'text', 'label' => _('UUID')],
            ['name' => 'ociimage', 'type' => 'text', 'label' => _('OCI Image')],
        ]);
    }

    public function listingQuery(): \Envms\FluentPDO\Queries\Select
    {
        $currentLang = substr(\Ease\Locale::$localeUsed ?? 'en_US', 0, 2);

        return parent::listingQuery()
            ->leftJoin('app_translations ON app_translations.app_id = apps.id AND app_translations.lang = ?', $currentLang)
            ->select(['app_translations.name AS name_localized', 'app_translations.description AS description_localized']);
    }

    public function completeDataRow(array $dataRowRaw)
    {
        $name = !empty($dataRowRaw['name_localized']) ? $dataRowRaw['name_localized'] : $dataRowRaw['name'];
        $description = !empty($dataRowRaw['description_localized']) ? $dataRowRaw['description_localized'] : $dataRowRaw['description'];

        $dataRow = $dataRowRaw;
        $dataRow['name'] = '<a title="'.$name.'" href="app.php?id='.$dataRowRaw['id'].'">'.$name.'</a>';
        $dataRow['description'] = $description;
        $dataRow['icon'] = '<a title="'.$name.'" href="app.php?id='.$dataRowRaw['id'].'"><img src="appimage.php?uuid='.$dataRowRaw['uuid'].'" width="50" height="50" style="object-fit: contain;">';

        $topics = new \Ease\Html\DivTag();

        if (empty($dataRow['tags']) === false) {
            foreach (explode(',', $dataRow['tags']) as $topic) {
                $topics->addItem(new \Ease\TWB4\Badge('secondary', $topic));
            }

            $dataRow['tags'] = (string) $topics;
        }

        //        $dataRowRaw['created'] = (new LiveAge((new DateTime($dataRowRaw['created']))))->__toString();

        return parent::completeDataRow($dataRow);
    }
}
