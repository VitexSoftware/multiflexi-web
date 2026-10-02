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
 * EventRuleLister for DataTable listing.
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
class EventRuleLister extends EventRule
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
                'detailPage' => 'eventrule.php', 'valueColumn' => 'event_rule.id', 'idColumn' => 'event_rule.id'],
            ['name' => 'event_source_id', 'type' => 'text', 'label' => _('Event Source')],
            ['name' => 'evidence', 'type' => 'text', 'label' => _('Evidence')],
            ['name' => 'operation', 'type' => 'text', 'label' => _('Operation')],
            ['name' => 'runtemplate_id', 'type' => 'text', 'label' => _('RunTemplate'),
                'column' => 'runtemplate.name', 'valueColumn' => 'runtemplate.name'],
            ['name' => 'priority', 'type' => 'text', 'label' => _('Priority')],
            ['name' => 'enabled', 'type' => 'boolean', 'label' => _('Enabled')],
        ]);
    }

    #[\Override]
    public function listingQuery(): \Envms\FluentPDO\Queries\Select
    {
        return parent::listingQuery()
            ->leftJoin('runtemplate ON runtemplate.id = event_rule.runtemplate_id')
            ->select(['runtemplate.name AS runtemplate_name'])
            ->leftJoin('apps ON apps.id = runtemplate.app_id')
            ->select(['apps.name AS app_name', 'apps.id AS app_id', 'apps.uuid AS app_uuid']);
    }

    #[\Override]
    public function completeDataRow(array $dataRowRaw): array
    {
        $data = parent::completeDataRow($dataRowRaw);

        if (!empty($data['event_source_id'])) {
            $source = new EventSource((int) $data['event_source_id'], ['autoload' => true]);
            $data['event_source_id'] = (string) new \Ease\Html\ATag('eventsource.php?id='.$data['event_source_id'], $source->getRecordName());
        }

        if (empty($data['event_source_id'])) {
            // Job-chain rule: triggered by completion of another RunTemplate, not by an event source.
            $data['event_source_id'] = empty($data['runtemplate_source_id']) ? '' : (string) new \Ease\Html\ATag('runtemplate.php?id='.$data['runtemplate_source_id'], _('Job').' #'.$data['runtemplate_source_id']);
        }

        // DataTables aborts the whole draw on null cells
        $data['evidence'] ??= '';

        if (!empty($dataRowRaw['runtemplate_id'])) {
            $rtName = $dataRowRaw['runtemplate_name'] ?? ('#'.$dataRowRaw['runtemplate_id']);
            $appName = $dataRowRaw['app_name'] ?? '';
            $data['runtemplate_id'] = (string) new \Ease\Html\ATag(
                'runtemplate.php?id='.$dataRowRaw['runtemplate_id'],
                [
                    self::appIcon($dataRowRaw['app_uuid'] ?? null, $appName !== '' ? $appName : $rtName),
                    '&nbsp;',
                    $rtName,
                ],
                ['title' => $appName !== '' ? $appName.' — '.$rtName : $rtName],
            );
        }

        return $data;
    }

    /**
     * Small App icon for the listing table, same source as \MultiFlexi\Ui\AppLogo.
     */
    private static function appIcon(?string $appUuid, string $appName): \Ease\Html\ImgTag
    {
        return new \Ease\Html\ImgTag(
            empty($appUuid) ? 'images/apps.svg' : 'appimage.php?uuid='.$appUuid,
            $appName,
            ['style' => 'height: 20px; width: 20px; object-fit: contain;'],
        );
    }
}
