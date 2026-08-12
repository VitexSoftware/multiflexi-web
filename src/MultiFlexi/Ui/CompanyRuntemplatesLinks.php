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
 * Description of CompanyRuntemplatesLinks.
 *
 * @author Vitex <info@vitexsoftware.cz>
 *
 * @no-named-arguments
 */
class CompanyRuntemplatesLinks extends \Ease\Html\DivTag
{
    public function __construct(\MultiFlexi\Company $company, \MultiFlexi\Application $application, array $properties = [])
    {
        $runTemplater = new \MultiFlexi\RunTemplate();
        $runtemplatesRaw = $runTemplater->listingQuery()->where('active', true)->where('app_id', $application->getMyKey())->where('company_id', $company->getMyKey())->fetchAll();
        $jobber = new \MultiFlexi\Job();

        $runtemplatesDiv = new \Ease\Html\DivTag();

        if ($runtemplatesRaw) {
            WebPage::singleton()->addCss(<<<'CSS'
                .runtemplate-compact-group .btn { padding: 0.1rem 0.4rem; font-size: 0.75rem; line-height: 1.5; }
                .runtemplate-compact-id { font-weight: 500; background-color: #f1f3f5; color: #495057; border-color: #dee2e6; }
                .runtemplate-compact-status { min-width: 24px; text-align: center; }
                .runtemplate-compact-queued { padding: 0.1rem 0.3rem !important; }
CSS);

            $runtemplateIds = array_map(static fn (array $rt): int => (int) $rt['id'], $runtemplatesRaw);

            // Batched instead of 2 queries per RunTemplate (was N+1 on pages embedding
            // ApplicationPanel for an app shared by many companies). Capped at 200 rows
            // per lookup: for the normal case of a handful of active RunTemplates per
            // company+app pair this still finds every group's most recent job; only a
            // company with an unusually large number of RunTemplates for one app could
            // see a stale/empty status for its least-recently-run ones.
            $lastFinishedJobs = [];

            foreach ($jobber->listingQuery()
                ->select(['id', 'exitcode', 'runtemplate_id'], true)
                ->where('runtemplate_id', $runtemplateIds)
                ->where('exitcode IS NOT NULL')
                ->order('id DESC')
                ->limit(200) as $job) {
                $lastFinishedJobs[$job['runtemplate_id']] ??= $job;
            }

            $queuedJobs = [];

            foreach ($jobber->listingQuery()
                ->select(['id', 'schedule', 'runtemplate_id'], true)
                ->where('runtemplate_id', $runtemplateIds)
                ->where('begin', null)
                ->order('id DESC')
                ->limit(200) as $job) {
                $queuedJobs[$job['runtemplate_id']] ??= $job;
            }

            foreach ($runtemplatesRaw as $runtemplateData) {
                $lastFinishedJob = $lastFinishedJobs[$runtemplateData['id']] ?? null;
                $queuedJob = $queuedJobs[$runtemplateData['id']] ?? null;

                $group = new \Ease\Html\DivTag(null, ['class' => 'btn-group runtemplate-compact-group shadow-sm mr-2 mb-2', 'role' => 'group']);

                // RunTemplate Link
                $group->addItem(new \Ease\Html\ATag(
                    'runtemplate.php?id='.$runtemplateData['id'],
                    '⚗️ #'.$runtemplateData['id'],
                    [
                        'class' => 'btn runtemplate-compact-id',
                        'data-toggle' => 'popover',
                        'data-trigger' => 'manual',
                        'data-html' => 'true',
                        'data-placement' => 'top',
                        'data-content' => $runtemplateData['name'],
                    ],
                ));

                // Exit Code / Last Status
                if ($lastFinishedJob) {
                    $group->addItem(new \Ease\Html\ATag(
                        'job.php?id='.$lastFinishedJob['id'],
                        new ExitCode($lastFinishedJob['exitcode'], ['class' => 'badge-pill']),
                        [
                            'class' => 'btn btn-outline-secondary runtemplate-compact-status',
                            'data-toggle' => 'popover',
                            'data-trigger' => 'manual',
                            'data-html' => 'true',
                            'data-placement' => 'top',
                            'data-content' => _('Last finished job exit code').': '.$lastFinishedJob['exitcode'],
                        ],
                    ));
                } else {
                    $group->addItem(new \Ease\Html\SpanTag('🪤', [
                        'class' => 'btn btn-outline-light disabled runtemplate-compact-status',
                        'data-toggle' => 'popover',
                        'data-trigger' => 'manual',
                        'data-html' => 'true',
                        'data-placement' => 'top',
                        'data-content' => _('No jobs yet'),
                    ]));
                }

                // Queued Status (Hourglass)
                if ($queuedJob) {
                    $scheduledAt = new \DateTime($queuedJob['schedule']);
                    $now = new \DateTime();
                    $diff = $now->diff($scheduledAt);
                    $timeInfo = $diff->invert ? _('Late by') : _('Scheduled for');
                    $group->addItem(new \Ease\Html\ATag(
                        'job.php?id='.$queuedJob['id'],
                        '⌛',
                        [
                            'class' => 'btn btn-info runtemplate-compact-queued',
                            'data-toggle' => 'popover',
                            'data-trigger' => 'manual',
                            'data-html' => 'true',
                            'data-placement' => 'top',
                            'data-content' => sprintf(_('%s: %s (in %s)'), _('Job is scheduled in queue'), $queuedJob['schedule'], self::secondsToHuman((int) abs($scheduledAt->getTimestamp() - $now->getTimestamp()))),
                        ],
                    ));
                }

                $runtemplatesDiv->addItem($group);
            }

            WebPage::singleton()->addJavaScript(<<<'JS'
                $('.runtemplate-compact-group [data-toggle="popover"]').popover({
                    trigger: 'manual',
                    delay: { show: 100, hide: 300 }
                }).on('mouseenter', function() {
                    var _this = this;
                    $(this).popover('show');
                    $('.popover').on('mouseleave', function() {
                        $(_this).popover('hide');
                    });
                }).on('mouseleave', function() {
                    var _this = this;
                    setTimeout(function() {
                        if (!$('.popover:hover').length) {
                            $(_this).popover('hide');
                        }
                    }, 300);
                });
JS);
        } else {
            $runtemplatesDiv->addItem(new \Ease\Html\ATag('runtemplate.php?new=1&app_id='.$application->getMyKey().'&company_id='.$company->getMyKey(), '➕', ['class' => 'btn btn-outline-primary btn-sm rounded-circle']));
        }

        parent::__construct($runtemplatesDiv, $properties);
    }

    /**
     * Convert seconds to human readable string.
     */
    public static function secondsToHuman(int $seconds): string
    {
        $dtF = new \DateTime('@0');
        $dtT = new \DateTime("@{$seconds}");

        return $dtF->diff($dtT)->format('%ad %hh %im %ss');
    }

    public function count(): int
    {
        if (isset($this->pageParts[0]) && \is_object($this->pageParts[0]) && method_exists($this->pageParts[0], 'getItemsCount')) {
            return $this->pageParts[0]->getItemsCount();
        }

        return 0;
    }
}
