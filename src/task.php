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

require_once './init.php';
WebPage::singleton()->onlyForLogged();

$taskId = WebPage::singleton()->getRequestValue('id', 'int');
$task = new \MultiFlexi\Task($taskId);

if (!$task->getMyKey()) {
    WebPage::singleton()->addStatusMessage(_('Task not found'), 'error');
    WebPage::singleton()->redirect('task-dashboard.php');

    exit;
}

$runTemplate = new \MultiFlexi\RunTemplate((int) $task->getDataValue('runtemplate_id'));

if (!$runTemplate->getMyKey()) {
    // RunTemplate was deleted - show limited task info, no company context available
    WebPage::singleton()->addStatusMessage(_('Warning: RunTemplate for this task was deleted'), 'warning');
    WebPage::singleton()->addItem(new PageTop(_('Task').' #'.$taskId));

    $taskPanel = new \Ease\TWB4\Panel(_('Task Information'), 'warning');
    $taskPanel->addItem(new \Ease\TWB4\Alert('warning', [
        '⚠️ ',
        _('The RunTemplate associated with this task has been deleted. Task information is limited.'),
    ]));

    $infoDiv = new \Ease\Html\DivTag();
    $infoDiv->addItem(new \Ease\Html\StrongTag(_('Task ID: ')));
    $infoDiv->addItem((string) $taskId);
    $infoDiv->addItem(new \Ease\Html\Tag('br'));
    $infoDiv->addItem(new \Ease\Html\StrongTag(_('RunTemplate ID: ')));
    $infoDiv->addItem($task->getDataValue('runtemplate_id').' '._('(deleted)'));
    $infoDiv->addItem(new \Ease\Html\Tag('br'));
    $infoDiv->addItem(new \Ease\Html\StrongTag(_('State: ')));
    $infoDiv->addItem((string) $task->getDataValue('state'));

    $taskPanel->addItem($infoDiv);

    WebPage::singleton()->container->addItem($taskPanel);
    WebPage::singleton()->container->addItem(new \Ease\TWB4\LinkButton('task-dashboard.php', _('Back to Tasks'), 'primary'));
    WebPage::singleton()->addItem(new PageBottom());
    WebPage::singleton()->draw();

    exit;
}

WebPage::singleton()->addItem(new PageTop(_('Task').' #'.$taskId));

$stateBadge = [
    'fulfilled' => ['success', '✅'],
    'fulfilled_late' => ['warning', '⏰'],
    'failed' => ['danger', '❌'],
    'missed' => ['dark', '🕳️'],
    'open' => ['info', '🔵'],
    'running' => ['primary', '▶️'],
];

$state = (string) $task->getDataValue('state');
[$badgeType, $icon] = $stateBadge[$state] ?? ['secondary', '?'];

// ── Task info panel ──────────────────────────────────────────────────────────
$windowStart = $task->getDataValue('window_start') ? new \DateTime((string) $task->getDataValue('window_start')) : null;
$windowEnd = $task->getDataValue('window_end') ? new \DateTime((string) $task->getDataValue('window_end')) : null;
$deadline = $task->getDataValue('deadline') ? new \DateTime((string) $task->getDataValue('deadline')) : null;
$deadlineMissed = \in_array($state, ['failed', 'missed'], true);

$infoRows = new \Ease\Html\DivTag(null, ['class' => 'row']);

$addInfo = static function (\Ease\Html\DivTag $container, string $label, $value): void {
    $container->addItem(new \Ease\Html\DivTag([
        new \Ease\Html\DivTag($label, ['class' => 'text-muted small']),
        new \Ease\Html\DivTag($value, ['class' => 'font-weight-bold']),
    ], ['class' => 'col-6 col-md-3 mb-3']));
};

$addInfo($infoRows, _('State'), new \Ease\TWB4\Badge($badgeType, $icon.' '._($state)));
$addInfo($infoRows, _('Window'), ($windowStart ? $windowStart->format('Y-m-d H:i:s') : '—').' → '.($windowEnd ? $windowEnd->format('Y-m-d H:i:s') : '—'));
$addInfo($infoRows, _('Deadline'), new \Ease\Html\SpanTag($deadline ? $deadline->format('Y-m-d H:i:s') : '—', $deadlineMissed ? ['class' => 'text-danger font-weight-bold'] : []));
$addInfo($infoRows, _('Attempts'), (string) $task->getDataValue('attempts'));

if ($task->getDataValue('fulfilled_by_job_id')) {
    $fulfilledAt = $task->getDataValue('fulfilled_at') ? (new \DateTime((string) $task->getDataValue('fulfilled_at')))->format('Y-m-d H:i:s') : '';
    $addInfo($infoRows, _('Fulfilled by'), [
        new \Ease\Html\ATag('job.php?id='.$task->getDataValue('fulfilled_by_job_id'), '🏁 #'.$task->getDataValue('fulfilled_by_job_id')),
        new \Ease\Html\SpanTag(' '.$fulfilledAt, ['class' => 'text-muted small d-block']),
    ]);
} else {
    $addInfo($infoRows, _('Fulfilled by'), '—');
}

$addInfo($infoRows, _('RunTemplate'), new \Ease\Html\ATag('runtemplate.php?id='.$runTemplate->getMyKey(), '⚗️ '.htmlspecialchars($runTemplate->getRecordName() ?: '#'.$runTemplate->getMyKey())));

$appInfo = $runTemplate->getAppInfo();
$addInfo($infoRows, _('Application'), $appInfo['app_name'] ?? '—');

// ── Streak: consecutive tasks of this RunTemplate ending in the same state ────
$streakRows = $task->getFluentPDO()
    ->from('task')
    ->select('id, state')
    ->where('runtemplate_id', $runTemplate->getMyKey())
    ->orderBy('window_start DESC')
    ->limit(50)
    ->fetchAll();

$streakCount = 0;
$streakStarted = false;

foreach ($streakRows as $row) {
    if ((int) $row['id'] === $taskId) {
        $streakStarted = true;
    }

    if ($streakStarted) {
        if ($row['state'] === $state) {
            ++$streakCount;
        } else {
            break;
        }
    }
}

$streakBadge = new \Ease\TWB4\Badge($badgeType, '🔥 '.sprintf(_('%d %s in a row'), $streakCount, _($state)));

// ── Task panel ──────────────────────────────────────────────────────────────
$taskPanel = new \Ease\TWB4\Panel([_('Task Information'), ' ', $streakBadge], $badgeType, $infoRows);

// ── Jobs table: every attempt made against this task ──────────────────────────
$jobs = $task->getJobs();

if (empty($jobs)) {
    $jobsContent = new \Ease\TWB4\Alert('info', _('No jobs recorded for this task yet.'));
} else {
    $jobsTable = new \Ease\TWB4\Table(null, ['class' => 'table table-bordered table-hover table-sm w-100']);
    $jobsTable->addRowHeaderColumns([
        _('Job'),
        _('Exit Code'),
        _('Begin'),
        _('End'),
        _('Duration'),
    ]);

    $fulfilledByJobId = (int) $task->getDataValue('fulfilled_by_job_id');

    foreach ($jobs as $jobRow) {
        $rowCls = ((int) $jobRow['id'] === $fulfilledByJobId) ? 'table-success' : '';
        $tr = new \Ease\Html\TrTag(null, ['class' => $rowCls]);

        $tr->addItem(new \Ease\Html\TdTag(new \Ease\Html\ATag('job.php?id='.$jobRow['id'], '🏁 #'.$jobRow['id'])));
        $tr->addItem(new \Ease\Html\TdTag(new ExitCode($jobRow['exitcode'])));
        $tr->addItem(new \Ease\Html\TdTag($jobRow['begin'] ?? '—'));
        $tr->addItem(new \Ease\Html\TdTag($jobRow['end'] ?? '—'));

        if (!empty($jobRow['begin']) && !empty($jobRow['end'])) {
            $duration = (new \DateTime((string) $jobRow['end']))->getTimestamp() - (new \DateTime((string) $jobRow['begin']))->getTimestamp();
            $tr->addItem(new \Ease\Html\TdTag($duration.' s'));
        } else {
            $tr->addItem(new \Ease\Html\TdTag('—'));
        }

        $jobsTable->addItem($tr);
    }

    $jobsContent = $jobsTable;
}

$jobsPanel = new \Ease\TWB4\Panel(_('Jobs (attempts until success)'), 'default', $jobsContent);

// ── Footer ─────────────────────────────────────────────────────────────────
$taskFoot = new \Ease\TWB4\Row();
$taskFoot->addColumn(4, new \Ease\TWB4\LinkButton('task-dashboard.php?runtemplate_id='.$runTemplate->getMyKey(), '📋 '._('All Tasks for this RunTemplate'), 'info btn-lg btn-block'));
$taskFoot->addColumn(4, new RuntemplateButton($runTemplate));

$content = [$taskPanel, $jobsPanel];

WebPage::singleton()->container->addItem(new CompanyPanel(new \MultiFlexi\Company($runTemplate->getDataValue('company_id')), $content, $taskFoot));

WebPage::singleton()->addItem(new PageBottom('task/'.$taskId));
WebPage::singleton()->draw();
