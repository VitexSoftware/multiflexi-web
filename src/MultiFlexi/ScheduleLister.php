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
 * Description of Scheduler.
 *
 * @author vitex
 */
class ScheduleLister extends DBEngine
{
    public function __construct($identifier = null, $options = [])
    {
        $this->myTable = 'schedule';
        $this->nameColumn = '';
        parent::__construct($identifier, $options);
    }

    /**
     * @see https://datatables.net/examples/advanced_init/column_render.html
     *
     * @return string Column rendering
     */
    public function columnDefs()
    {
        return <<<'EOD'

"columnDefs": [
           // { "visible": false,  "targets": [ 0 ] }
        ]
,

EOD;
    }

    public function columns($columns = [])
    {
        return parent::columns([
            ['name' => 'after', 'type' => 'text', 'label' => _('After')],
            ['name' => 'schedule_type', 'type' => 'text', 'label' => _('Trigger')],
            ['name' => 'job', 'type' => 'text', 'label' => _('Job')],
            ['name' => 'app_name', 'type' => 'text', 'label' => _('App')],
            ['name' => 'runtemplate_name', 'type' => 'text', 'label' => _('Runtemplate')],
            ['name' => 'company_name', 'type' => 'text', 'label' => _('Company')],
        ]);
    }
    public function completeDataRow(array $dataRowRaw): array
    {
        $dataRow['after'] = $dataRowRaw['after'].'<br>'.(string) new \Ease\Html\SmallTag(new \Ease\Html\Widgets\LiveAge(new \DateTime($dataRowRaw['after'])));
        $dataRow['schedule_type'] = self::scheduleTypeLabel($dataRowRaw['schedule_type'] ?? null);
        $dataRow['job'] = (string) new \Ease\Html\ATag('job.php?id='.$dataRowRaw['job'], '🏁&nbsp;'.$dataRowRaw['job']);
        $dataRow['app_name'] = (string) new \Ease\Html\ATag(
            'app.php?id='.$dataRowRaw['app_id'],
            [self::appIcon($dataRowRaw['app_uuid'] ?? null, $dataRowRaw['app_name']), '&nbsp;'.$dataRowRaw['app_name']],
        );
        $dataRow['runtemplate_name'] = (string) new \Ease\Html\ATag('runtemplate.php?id='.$dataRowRaw['runtemplate_id'], '⚗️&nbsp;'.$dataRowRaw['runtemplate_name']);
        $dataRow['company_name'] = (string) new \Ease\Html\ATag(
            'company.php?id='.$dataRowRaw['company_id'],
            [self::companyIcon($dataRowRaw['company_logo'] ?? null, $dataRowRaw['company_name']), '&nbsp;'.$dataRowRaw['company_name']],
        );

        return $dataRow;
    }

    public function listingQuery(): \Envms\FluentPDO\Queries\Select
    {
        return parent::listingQuery()
            ->leftJoin('job ON job.id = schedule.job')->select(['job.schedule_type'])
            ->leftJoin('user ON user.id = job.launched_by')
            ->leftJoin('runtemplate ON runtemplate.id = job.runtemplate_id')->select(['runtemplate.name AS runtemplate_name', 'runtemplate.id AS runtemplate_id'])
            ->leftJoin('apps ON apps.id = runtemplate.app_id')->select(['apps.name AS app_name', 'apps.id AS app_id', 'apps.uuid AS app_uuid'])
            ->leftJoin('company ON company.id = runtemplate.company_id')->select(['company.name AS company_name', 'company.id AS company_id', 'company.logo AS company_logo']);
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

    /**
     * Small Company icon for the listing table, same source as \MultiFlexi\Ui\CompanyLogo.
     */
    private static function companyIcon(?string $companyLogo, string $companyName): \Ease\Html\ImgTag
    {
        return new \Ease\Html\ImgTag(
            empty($companyLogo) ? 'images/company.svg' : $companyLogo,
            $companyName,
            ['style' => 'height: 20px; width: 20px; object-fit: contain; border-radius: 50%;'],
        );
    }

    /**
     * Render job.schedule_type for the "Trigger" column.
     *
     * For cron-scheduler-spawned jobs, schedule_type holds the RunTemplate's
     * interval name (hourly, daily, ...) via Scheduler::codeToInterval().
     * For manually/explicitly triggered jobs it holds one of the
     * Job::SCHEDULE_TYPE_* constants, which get a friendlier label here.
     */
    private static function scheduleTypeLabel(?string $scheduleType): string
    {
        $labels = [
            Job::SCHEDULE_TYPE_ADHOC => _('Ad-hoc'),
            Job::SCHEDULE_TYPE_ADHOC_WEB => _('Ad-hoc (Web)'),
            Job::SCHEDULE_TYPE_ADHOC_CLI => _('Ad-hoc (CLI)'),
            Job::SCHEDULE_TYPE_ADHOC_API => _('Ad-hoc (API)'),
            Job::SCHEDULE_TYPE_COMMAND_LINE => _('Scheduled (CLI/API)'),
        ];

        if ($scheduleType !== null && isset($labels[$scheduleType])) {
            return $labels[$scheduleType];
        }

        if ($scheduleType === null || $scheduleType === '') {
            return _('Unknown');
        }

        $code = Scheduler::intervalToCode($scheduleType);
        $emoji = Scheduler::getIntervalEmoji($code);
        $label = self::intervalLabel($code) ?? ucfirst($scheduleType);

        return ($emoji === '' ? '' : $emoji.'&nbsp;').$label;
    }

    /**
     * Localized label for a Scheduler interval code (see Scheduler::$intervalCode).
     */
    private static function intervalLabel(string $code): ?string
    {
        $labels = [
            'c' => _('Custom'),
            'y' => _('Yearly'),
            'm' => _('Monthly'),
            'w' => _('Weekly'),
            'd' => _('Daily'),
            'h' => _('Hourly'),
            'i' => _('Minutely'),
            'n' => _('Disabled'),
        ];

        return $labels[$code] ?? null;
    }
}
