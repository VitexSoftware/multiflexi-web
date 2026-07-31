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

use MultiFlexi\Application;

require_once './init.php';
WebPage::singleton()->onlyForLogged();
WebPage::singleton()->addItem(new PageTop(_('Archived Job Run')));
$jobID = WebPage::singleton()->getRequestValue('id', 'int');
$jobber = new \MultiFlexi\Job($jobID);

if (!$jobber->getMyKey()) {
    WebPage::singleton()->addStatusMessage(_('Job not found'), 'error');
    WebPage::singleton()->redirect('main.php');
}

$runTemplate = new \MultiFlexi\RunTemplate($jobber->getDataValue('runtemplate_id'));

if (!$runTemplate->getMyKey()) {
    // RunTemplate was deleted - show limited job info
    WebPage::singleton()->addStatusMessage(_('Warning: RunTemplate for this job was deleted'), 'warning');

    WebPage::singleton()->addItem(new PageTop(_('Job').' #'.$jobID));

    $jobPanel = new \Ease\TWB4\Panel(_('Job Information'), 'warning');
    $jobPanel->addItem(new \Ease\TWB4\Alert('warning', [
        '⚠️ ',
        _('The RunTemplate associated with this job has been deleted. Job information is limited.'),
    ]));

    $infoDiv = new \Ease\Html\DivTag();
    $infoDiv->addItem(new \Ease\Html\StrongTag(_('Job ID: ')));
    $infoDiv->addItem($jobID);
    $infoDiv->addItem(new \Ease\Html\Tag('br'));
    $infoDiv->addItem(new \Ease\Html\StrongTag(_('RunTemplate ID: ')));
    $infoDiv->addItem($jobber->getDataValue('runtemplate_id').' '._('(deleted)'));
    $infoDiv->addItem(new \Ease\Html\Tag('br'));
    $infoDiv->addItem(new \Ease\Html\StrongTag(_('Status: ')));
    $infoDiv->addItem($jobber->getDataValue('exitcode') !== null ? _('Completed') : _('Pending'));

    $jobPanel->addItem($infoDiv);

    $outputTabs = new \Ease\TWB4\Tabs();
    $stdTerminal = new \Ease\Html\DivTag(nl2br(str_replace('background-color: black; ', '', (new \SensioLabs\AnsiConverter\AnsiToHtmlConverter())->convert($jobber->getOutput()))), ['style' => 'background: black; font-family: monospace;']);
    $errorTerminal = new \Ease\Html\DivTag(nl2br(str_replace('background-color: black; ', '', (new \SensioLabs\AnsiConverter\AnsiToHtmlConverter())->convert($jobber->getErrorOutput()))), ['style' => 'background: #330000; font-family: monospace;']);

    $outputTabs->addTab(_('Output'), [$stdTerminal]);
    $outputTabs->addTab(_('Errors'), [$errorTerminal]);

    $jobPanel->addItem($outputTabs);

    WebPage::singleton()->container->addItem($jobPanel);
    WebPage::singleton()->container->addItem(new \Ease\TWB4\LinkButton('main.php', _('Back to Dashboard'), 'primary'));
    WebPage::singleton()->addItem(new PageBottom());
    WebPage::singleton()->draw();

    exit;
}

$appInfo = $runTemplate->getAppInfo();
$apps = new Application($appInfo['app_id']);
$instanceName = $appInfo['app_name'];

$errorTerminal = new \Ease\Html\DivTag(nl2br(str_replace('background-color: black; ', '', (new \SensioLabs\AnsiConverter\AnsiToHtmlConverter())->convert($jobber->getErrorOutput()))), ['style' => 'background: #330000; font-family: monospace;']);
$stdTerminal = new \Ease\Html\DivTag(nl2br(str_replace('background-color: black; ', '', (new \SensioLabs\AnsiConverter\AnsiToHtmlConverter())->convert($jobber->getOutput()))), ['style' => 'background:  black; font-family: monospace;']);

// Connect SSE stream only while the job is still running (no exitcode yet)
if ($jobber->getDataValue('exitcode') === null && $jobber->getDataValue('begin') !== null) {
    WebPage::singleton()->addJavaScript(<<<EOD

(function () {
    var liveOut = document.getElementById('live-output');
    if (!liveOut) { return; }

    var ansiColors = {
        30: '#000000', 31: '#e74c3c', 32: '#2ecc71', 33: '#f1c40f',
        34: '#3498db', 35: '#9b59b6', 36: '#1abc9c', 37: '#ecf0f1',
        90: '#7f8c8d', 91: '#ff6b6b', 92: '#7bed9f', 93: '#ffd32a',
        94: '#70a1ff', 95: '#c56cf0', 96: '#48dbfb', 97: '#ffffff'
    };

    function escapeHtml(s) {
        return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function ansiToHtml(text) {
        var re = /\\x1b\\[([0-9;]*)m/g;
        var result = '';
        var lastIndex = 0;
        var open = 0;
        var match;

        while ((match = re.exec(text)) !== null) {
            result += escapeHtml(text.slice(lastIndex, match.index));
            lastIndex = re.lastIndex;

            var codes = match[1].split(';').filter(function (c) { return c !== ''; });

            if (codes.length === 0) { codes = ['0']; }

            codes.forEach(function (codeStr) {
                var code = parseInt(codeStr, 10);

                if (code === 0) {
                    while (open > 0) { result += '</span>'; open--; }
                } else if (ansiColors[code]) {
                    result += '<span style="color:' + ansiColors[code] + '">';
                    open++;
                }
            });
        }

        result += escapeHtml(text.slice(lastIndex));

        while (open > 0) { result += '</span>'; open--; }

        return result;
    }

    function exitCodeBadgeType(code) {
        if (code === -1) { return 'secondary'; }
        if (code === 0) { return 'success'; }
        if (code === 127) { return 'warning'; }

        return 'danger';
    }

    var es = new EventSource('jobstream.php?id={$jobID}');
    es.addEventListener('output', function (e) {
        var d = JSON.parse(e.data);
        liveOut.innerHTML += ansiToHtml(d.line);
        liveOut.scrollTop = liveOut.scrollHeight;
    });
    es.addEventListener('done', function (e) {
        es.close();

        var d = JSON.parse(e.data);
        var badge = document.getElementById('live-exitcode');

        if (badge) {
            badge.className = 'badge badge-' + exitCodeBadgeType(d.exitcode);
            badge.innerHTML = '&nbsp;' + d.exitcode + '&nbsp;';
        }

        var endEl = document.getElementById('live-end');

        if (endEl && d.end) {
            endEl.innerHTML = d.end + '&nbsp;<small id="live-end-age"></small>';

            if (typeof updateCountdown === 'function') {
                updateCountdown('live-end-age', d.end_ts);
                setInterval(function () { updateCountdown('live-end-age', d.end_ts); }, 1000);
            }
        }
    });
    es.addEventListener('timeout', function () { es.close(); });
    es.onerror = function () { es.close(); };
})();

EOD);
}

// Check if job is orphaned and show warning
$orphanedWarning = null;

if (!$jobber->getDataValue('begin') && !$jobber->isScheduled()) {
    // Job not started and not in schedule queue - it's orphaned
    $orphanedWarning = new \Ease\TWB4\Alert('warning', [
        new \Ease\Html\H4Tag(['⚠️ ', _('Orphaned Job')]),
        new \Ease\Html\PTag(_('This job has not been executed yet and does not have its place in the execution queue. This can happen when the schedule queue is manually cleared or due to system errors.')),
        new \Ease\Html\PTag([_('Use the '), new \Ease\Html\StrongTag(_('Re-schedule')), _(' button below to add this job back to the queue.')]),
    ], ['style' => 'border-left: 5px solid #ff9800;']);
}

$outputTabs = new \Ease\TWB4\Tabs();
$outputTabs->addTab(_('Output').' '.(\strlen($jobber->getOutput()) ? ' <span class="badge badge-secondary">'.substr_count($jobber->getOutput(), "\n").'</span>' : '<span class="badge badge-invers">💭</span>'), [$stdTerminal, \strlen($jobber->getOutput()) ? new \Ease\TWB4\LinkButton('joboutput.php?id='.$jobID.'&mode=std', _('Download'), 'secondary btn-block') : _('No output'), new \Ease\Html\PreTag('', ['id' => 'live-output'])]);
$outputTabs->addTab(_('Errors').' '.(empty($jobber->getErrorOutput()) ? ' <span class="badge badge-success">0</span>' : '<span class="badge badge-warning">'.substr_count($jobber->getErrorOutput(), "\n").'</span>'), [$errorTerminal, \strlen($jobber->getErrorOutput()) ? new \Ease\TWB4\LinkButton('joboutput.php?id='.$jobID.'&mode=err', _('Download'), 'secondary btn-block') : _('No errors')], empty($jobber->getOutput()));

$artifactor = new \MultiFlexi\Artifact();
$artifacts = $artifactor->listingQuery()->where('job_id', $jobID);

if ($artifacts->count()) {
    WebPage::singleton()->includeJavaScript('js/highlight.min.js');
    WebPage::singleton()->includeCss('css/highlight-default.min.css');
    WebPage::singleton()->addJavaScript('hljs.highlightAll();');
    $artifactsDiv = new \Ease\Html\DivTag();

    foreach ($artifacts->fetchAll() as $artifactData) {
        switch ($artifactData['content_type']) {
            case 'application/json':
                $code = json_encode(json_decode($artifactData['artifact']), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_LINE_TERMINATORS);

                break;

            default:
                $code = $artifactData['artifact'];

                break;
        }

        $artifactsDiv->addItem(new \Ease\TWB4\Panel([new \Ease\Html\ATag('getartifact.php?id='.$artifactData['id'], '💾', ['class' => 'btn btn-info btn-sm']), '&nbsp;'.htmlspecialchars((string) ($artifactData['filename'] ?? ''), \ENT_QUOTES | \ENT_HTML5, 'UTF-8')], 'inverse', new \Ease\Html\DivTag(new \Ease\Html\PreTag('<code>'.htmlspecialchars((string) $code, \ENT_QUOTES | \ENT_HTML5, 'UTF-8').'</code>'), ['style' => 'font-family: monospace; color: black']), htmlspecialchars((string) ($artifactData['note'] ?? ''), \ENT_QUOTES | \ENT_HTML5, 'UTF-8')));
    }

    $outputTabs->addTab(_('Artifacts').' <span class="badge badge-success">'.$artifacts->count().'</span>', $artifactsDiv);
}

$runTemplateButton = new RuntemplateButton($runTemplate);

// $relaunchButton = new \Ease\TWB4\LinkButton('launch.php?id='.$runTemplate->getMyKey().'&app_id='.$appInfo['app_id'].'&company_id='.$appInfo['company_id'], '&lt;'._('Relaunch').'💨', 'success btn-lg btn-block');

if ($jobber->getDataValue('begin')) {
    // Job already started/finished
    $scheduleButton = new \Ease\TWB4\LinkButton('schedule.php?id='.$runTemplate->getMyKey().'&app_id='.$appInfo['app_id'].'&company_id='.$appInfo['company_id'], [_('Schedule').'&nbsp;&nbsp;', new \Ease\Html\ImgTag('images/launchinbackground.svg', _('Launch'), ['height' => '30px'])], 'primary btn-block', ['title' => _('Schedule new run based on this RunTemplate'), 'id' => 'schedulebutton']);
} else {
    // Job not started yet - check if scheduled
    if ($jobber->isScheduled()) {
        // Job is in schedule queue - allow cancellation
        $scheduleButton = new \Ease\TWB4\LinkButton('schedule.php?cancel='.$jobber->getMyKey().'&templateid='.$runTemplate->getMyKey().'&app_id='.$jobber->getDataValue('app_id').'&company_id='.$runTemplate->getDataValue('company_id'), [_('Cancel').'&nbsp;&nbsp;', new \Ease\Html\ImgTag('images/cancel.svg', _('Cancel').'&nbsp;&nbsp;', ['height' => '60px'])], 'warning btn-block');
    } else {
        // Orphaned job - no schedule entry, allow re-scheduling
        $scheduleButton = new \Ease\TWB4\LinkButton('reschedule.php?job_id='.$jobber->getMyKey(), ['⏰ '._('Re-schedule')], 'danger btn-block');
    }
}

// Handle job deletion
$deleteAction = WebPage::getRequestValue('action');

if ($deleteAction === 'delete' && WebPage::isPosted()) {
    $confirmDelete = WebPage::getRequestValue('confirm_delete');

    if ($confirmDelete === 'yes') {
        try {
            // Delete the job
            if ($jobber->deleteFromSQL()) {
                WebPage::singleton()->addStatusMessage(
                    sprintf(_('Job #%d has been deleted'), $jobID),
                    'success',
                );
                WebPage::singleton()->redirect('main.php');

                exit; // Stop execution after redirect
            }

            WebPage::singleton()->addStatusMessage(
                sprintf(_('Failed to delete job #%d'), $jobID),
                'error',
            );
        } catch (\Exception $e) {
            WebPage::singleton()->addStatusMessage(
                sprintf(_('Error deleting job: %s'), $e->getMessage()),
                'error',
            );
        }
    }
}

$previousJobId = $jobber->getPreviousJobId(true, true, true);

if ($previousJobId) {
    $previousButton = new \Ease\TWB4\LinkButton('job.php?id='.$previousJobId, '◀️ '._('Previous').' 🏁', 'info btn-lg btn-block');
} else {
    $previousButton = new \Ease\TWB4\LinkButton('#', '◀️ '._('Previous').' 🏁', 'info btn-lg btn-block disabled');
}

$nextJobId = $jobber->getNextJobId(true, true, true);

if ($nextJobId) {
    $nextButton = new \Ease\TWB4\LinkButton('job.php?id='.$nextJobId, '🏁 '._('Next').' ▶️️', 'info btn-lg btn-block');
} else {
    $nextButton = new \Ease\TWB4\LinkButton('#', '🏁 '._('Next').' ▶️️', 'info btn-lg btn-block disabled');
}

// Delete button with confirmation - using SecureForm for CSRF protection
$deleteForm = new \MultiFlexi\Ui\SecureForm(['method' => 'POST', 'action' => 'job.php?id='.$jobID]);
$deleteForm->addItem(new \Ease\Html\InputHiddenTag('action', 'delete'));
$deleteForm->addItem(new \Ease\Html\InputHiddenTag('confirm_delete', 'yes'));
$deleteButton = new \Ease\TWB4\SubmitButton('🗑️ '._('Delete'), 'danger btn-lg btn-block', ['onclick' => 'return confirm("'.htmlspecialchars(_('Are you sure you want to delete this job? This action cannot be undone.')).'");']);
$deleteForm->addItem($deleteButton);

$jobFoot = new \Ease\TWB4\Row();
$jobFoot->addColumn(2, $previousButton);
$jobFoot->addColumn(2, $nextButton);
$jobFoot->addColumn(2, $scheduleButton);
$jobFoot->addColumn(2, $deleteForm);
$jobFoot->addColumn(4, $runTemplateButton);

// Build panel content - include orphaned warning if present
$panelContent = [];

if ($orphanedWarning) {
    $panelContent[] = $orphanedWarning;
}

$panelContent[] = new JobInfo($jobber);
$panelContent[] = $outputTabs;

$appPanel = new ArchivedJobPanel($jobber, $panelContent, $jobFoot);

WebPage::singleton()->container->addItem(new CompanyPanel(new \MultiFlexi\Company($appInfo['company_id']), $appPanel));

WebPage::singleton()->addItem(new PageBottom('job/'.$jobber->getMyKey()));
WebPage::singleton()->addCss(<<<'EOD'

.iframe-container {
  overflow: hidden;
  padding-top: 56.25%;
  position: relative;
}

.iframe-container iframe {
   border: 0;
   height: 100%;
   left: 0;
   position: absolute;
   top: 0;
   width: 100%;
}

/* 4x3 Aspect Ratio */
.iframe-container-4x3 {
  padding-top: 75%;
}


EOD);
WebPage::singleton()->draw();
