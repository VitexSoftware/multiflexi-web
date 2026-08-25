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

/**
 * Description of AppSelector.
 *
 * @author vitex
 *
 * @no-named-arguments
 */
class AppsSelector extends \Ease\Html\InputTextTag
{
    use \Ease\TWB4\Widgets\Selectizer;

    public function __construct($identifier = null, $enabled = [], $optionsPage = 'app.php')
    {
        parent::__construct($identifier, $enabled);

        $properties = [
            'valueField' => 'id',
            'labelField' => 'name',
            'searchField' => ['name', 'description', 'homepage'],
        ];

        $properties['render']['item'] = 'function (item, escape) { return "<div class=container><div class=row> <div class=col-md-2><a href=app.php?id=" + escape(item.id) + "><img height=40 align=left src=\"appimage.php?uuid=" + escape(item.uuid) + "\"></a></div><div class=col-md-7>&nbsp;" + escape(item.name) + "</div><div class=col-md-3><a href='.$optionsPage.'?id=" + escape(item.id) + "&interval='.$identifier.' style=\"font-size: 30px; padding: 5px;\" >🛠️️</a></div> </div></div>" }';
        $properties['render']['option'] = 'function (item, escape) { return "<div><img height=40 align=right src=\"appimage.php?uuid=" + escape(item.uuid) + "\">" + escape(item.name) + "<br><small>" + escape(item.description) + "</small></div>" }';
        $properties['plugins'] = ['remove_button'];

        $this->selectize($properties, self::applyLocalizedNames($this->availbleApps()));
    }

    public function availbleApps()
    {
        $apper = new \MultiFlexi\Application();
        $currentLang = substr(\Ease\Locale::$localeUsed ?? 'en_US', 0, 2);

        return $apper->listingQuery()
            ->leftJoin('app_translations ON app_translations.app_id = apps.id AND app_translations.lang = ?', $currentLang)
            ->select(['id', 'name', 'description', 'homepage', 'uuid', 'app_translations.name AS name_localized', 'app_translations.description AS description_localized'], true)
            ->fetchAll();
    }

    /**
     * Replace name/description with their app_translations-localized value
     * (falling back to the raw value) and drop the helper columns.
     */
    public static function applyLocalizedNames(array $data): array
    {
        foreach ($data as $rowId => $record) {
            if (!empty($record['name_localized'])) {
                $data[$rowId]['name'] = $record['name_localized'];
            }

            if (!empty($record['description_localized'])) {
                $data[$rowId]['description'] = $record['description_localized'];
            }

            unset($data[$rowId]['name_localized'], $data[$rowId]['description_localized']);
        }

        return $data;
    }
}
