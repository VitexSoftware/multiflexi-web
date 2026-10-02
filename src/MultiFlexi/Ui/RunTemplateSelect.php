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
 * Selectize RunTemplate picker with application logo and company context.
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 *
 * @no-named-arguments
 */
class RunTemplateSelect extends \Ease\Html\SelectTag
{
    use \Ease\TWB4\Widgets\Selectizer;

    /**
     * @var array<int, array<string, mixed>> Selectize option payload
     */
    protected array $optionData = [];

    /**
     * @param string   $name         HTML form field name
     * @param null|int $defaultValue Currently selected RunTemplate ID
     * @param bool     $allowEmpty   Offer an empty "none" option
     * @param string   $emptyLabel   Label for the empty option
     * @param array    $properties   Additional HTML properties
     */
    public function __construct(
        string $name,
        ?int $defaultValue = null,
        bool $allowEmpty = false,
        string $emptyLabel = '',
        array $properties = [],
    ) {
        $items = $this->loadItems($allowEmpty, $emptyLabel !== '' ? $emptyLabel : _('None'));

        parent::__construct($name, $items, $defaultValue !== null ? (string) $defaultValue : '0', $properties);

        $selectizeProps = [
            'valueField' => 'value',
            'labelField' => 'text',
            'searchField' => ['text', 'name', 'app_name', 'company_name'],
            'allowEmptyOption' => $allowEmpty,
        ];

        $selectizeProps['render']['item'] = 'function (item, escape) { '
            .'if (!item.value || item.value === "0") { return "<div>" + escape(item.text) + "</div>"; }'
            .'return "<div><img src=\"" + escape(item.logo) + "\" height=20 width=20 '
            .'style=\"margin-right: 8px; vertical-align: middle; object-fit: contain;\"> '
            .'" + escape(item.text) + "</div>"; '
            .'}';

        $selectizeProps['render']['option'] = 'function (item, escape) { '
            .'if (!item.value || item.value === "0") { return "<div>" + escape(item.text) + "</div>"; }'
            .'return "<div style=\"display: flex; align-items: center; padding: 5px 0;\">'
            .'<img src=\"" + escape(item.logo) + "\" height=36 width=36 '
            .'style=\"margin-right: 12px; flex-shrink: 0; object-fit: contain;\">'
            .'<div><strong>" + escape(item.name) + "</strong><br>'
            .'<small style=\"color: #666;\">" + escape(item.app_name) + " · " + escape(item.company_name)'
            .' + " · #" + escape(item.value) + "</small></div></div>"; '
            .'}';

        $this->selectize($selectizeProps, $this->optionData);
    }

    /**
     * @return array<string, string>
     */
    public function loadItems(bool $allowEmpty = false, string $emptyLabel = ''): array
    {
        if ($emptyLabel === '') {
            $emptyLabel = _('None');
        }

        $items = [];

        if ($allowEmpty) {
            $items['0'] = $emptyLabel;
            $this->optionData[] = [
                'value' => '0',
                'text' => $emptyLabel,
                'name' => $emptyLabel,
                'logo' => 'images/apps.svg',
                'app_name' => '',
                'company_name' => '',
            ];
        }

        $runTemplate = new \MultiFlexi\RunTemplate();
        $rows = $runTemplate->listingQuery()
            ->leftJoin('apps ON apps.id = runtemplate.app_id')
            ->leftJoin('company ON company.id = runtemplate.company_id')
            ->select([
                'runtemplate.id',
                'runtemplate.name',
                'apps.name AS app_name',
                'apps.uuid AS app_uuid',
                'company.name AS company_name',
            ])
            ->orderBy('company.name, apps.name, runtemplate.name')
            ->fetchAll();

        foreach ($rows as $row) {
            $id = (string) $row['id'];
            $name = (string) ($row['name'] ?? ('#'.$id));
            $appName = (string) ($row['app_name'] ?? '');
            $companyName = (string) ($row['company_name'] ?? '');
            $label = trim($companyName.' / '.$appName.' — '.$name, ' /—');

            if ($label === '') {
                $label = '#'.$id;
            }

            $items[$id] = $label;
            $this->optionData[] = [
                'value' => $id,
                'text' => $label,
                'name' => $name,
                'logo' => empty($row['app_uuid']) ? 'images/apps.svg' : 'appimage.php?uuid='.$row['app_uuid'],
                'app_name' => $appName,
                'company_name' => $companyName,
            ];
        }

        return $items;
    }
}
