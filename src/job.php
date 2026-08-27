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

use MultiFlexi\LocalizedApplication;

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
$apps = new LocalizedApplication($appInfo['app_id']);
$instanceName = $apps->getRecordName() ?: $appInfo['app_name'];

$errorTerminal = new \Ease\Html\DivTag(nl2br(str_replace('background-color: black; ', '', (new \SensioLabs\AnsiConverter\AnsiToHtmlConverter())->convert($jobber->getErrorOutput()))), ['style' => 'background: #330000; font-family: monospace;']);
$stdTerminal = new \Ease\Html\DivTag(nl2br(str_replace('background-color: black; ', '', (new \SensioLabs\AnsiConverter\AnsiToHtmlConverter())->convert($jobber->getOutput()))), ['style' => 'background:  black; font-family: monospace;']);

// Determine whether this job has a realistic chance of starting on its own
// (queued in the schedule table, or already claimed by the executor within
// the grace window) so we know whether to show the "waiting" spinner and
// open a live stream, as opposed to a truly orphaned job that needs manual
// re-scheduling.
$jobBegin = $jobber->getDataValue('begin');
$jobExitcode = $jobber->getDataValue('exitcode');
$withinGrace = false;

if ($jobBegin === null && $jobber->isScheduled() === false) {
    $scheduleTime = $jobber->getDataValue('schedule');
    $gracePeriodSeconds = 300; // 5 minutes — covers slow executor startup

    if ($scheduleTime) {
        $timezone = \MultiFlexi\DateTimeHelper::getConfiguredTimezone();
        $scheduledAt = new \DateTime($scheduleTime, $timezone);
        $now = new \DateTime('now', $timezone);
        $secondsSinceSchedule = $now->getTimestamp() - $scheduledAt->getTimestamp();
        $withinGrace = $secondsSinceSchedule >= 0 && $secondsSinceSchedule < $gracePeriodSeconds;
    }
}

$expectingStart = $jobBegin === null && $jobExitcode === null && ($jobber->isScheduled() || $withinGrace);
$jobAlreadyRunning = $jobBegin !== null && $jobExitcode === null;

// Connect SSE stream while the job is still running, or is expected to
// start shortly (queued / just claimed by the executor) — the "waiting"
// spinner below stays visible until a 'started' or 'output' event arrives.
if ($jobAlreadyRunning || $expectingStart) {
    WebPage::singleton()->addJavaScript(<<<EOD

(function () {
    var liveOut = document.getElementById('live-output');
    if (!liveOut) { return; }

    var waitSpinner = document.getElementById('job-wait-spinner');
    var hideSpinner = function () {
        if (waitSpinner) { waitSpinner.style.display = 'none'; }
    };

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
    es.addEventListener('started', function () {
        hideSpinner();
    });
    es.addEventListener('output', function (e) {
        hideSpinner();

        var d = JSON.parse(e.data);
        liveOut.innerHTML += ansiToHtml(d.line);
        liveOut.scrollTop = liveOut.scrollHeight;
    });
    es.addEventListener('done', function (e) {
        hideSpinner();
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
    es.addEventListener('timeout', function () { hideSpinner(); es.close(); });
    es.onerror = function () { hideSpinner(); es.close(); };
})();

EOD);
}

// Check if job is orphaned and show warning
$blockedWarning = null;

if (!empty($jobber->getDataValue('block_reason')) && !$jobber->getDataValue('exitcode')) {
    // Set by Job::reportCredentialBlocked() when a required credential fails
    // its availability check — the job never actually ran.
    $blockedWarning = new \Ease\TWB4\Alert('danger', [
        new \Ease\Html\H4Tag(['🚫 ', _('Job Blocked')]),
        new \Ease\Html\PTag($jobber->getDataValue('block_reason')),
        new \Ease\Html\PTag(sprintf(_('Last blocked at: %s'), $jobber->getDataValue('blocked_at'))),
    ], ['style' => 'border-left: 5px solid #dc3545;']);
}

// (uses $jobBegin / $withinGrace computed above, next to the SSE decision)
$orphanedWarning = null;

if ($jobBegin === null && $jobber->isScheduled() === false) {
    if ($withinGrace) {
        // Executor claimed the job and is starting it shortly — the spinner
        // above (fed by the live stream's 'started' event) reflects this.
        $orphanedWarning = new \Ease\TWB4\Alert('info', [
            new \Ease\Html\H4Tag(['⏳ ', _('Job Starting')]),
            new \Ease\Html\PTag(_('The executor has claimed this job and will start it shortly.')),
        ]);
    } else {
        // Job not started and not in schedule queue - it's orphaned
        $orphanedWarning = new \Ease\TWB4\Alert('warning', [
            new \Ease\Html\H4Tag(['⚠️ ', _('Orphaned Job')]),
            new \Ease\Html\PTag(_('This job has not been executed yet and does not have its place in the execution queue. This can happen when the schedule queue is manually cleared or due to system errors.')),
            new \Ease\Html\PTag([_('Use the '), new \Ease\Html\StrongTag(_('Re-schedule')), _(' button below to add this job back to the queue.')]),
        ], ['style' => 'border-left: 5px solid #ff9800;']);
    }
}

WebPage::singleton()->addJavaScript(<<<'EOD'

function copyJobOutput(textareaId, btnEl) {
    var el = document.getElementById(textareaId);
    if (!el) { return; }
    var text = el.value;
    var done = function () {
        var original = btnEl.innerHTML;
        btnEl.innerHTML = '✅ ' + (btnEl.getAttribute('data-copied-label') || 'Copied');
        setTimeout(function () { btnEl.innerHTML = original; }, 1500);
    };
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(done);
    } else {
        el.style.display = 'block';
        el.select();
        document.execCommand('copy');
        el.style.display = 'none';
        done();
    }
}
EOD, '0', false);

$stdOutputRaw = new \Ease\Html\TextareaTag('raw-output-std', htmlspecialchars($jobber->getOutput(), \ENT_QUOTES | \ENT_SUBSTITUTE), ['id' => 'raw-output-std', 'style' => 'display:none;']);
$errOutputRaw = new \Ease\Html\TextareaTag('raw-output-err', htmlspecialchars($jobber->getErrorOutput(), \ENT_QUOTES | \ENT_SUBSTITUTE), ['id' => 'raw-output-err', 'style' => 'display:none;']);

$copyStdButton = new \Ease\TWB4\LinkButton('#', '📋 '._('Copy'), 'secondary btn-block', ['onclick' => "copyJobOutput('raw-output-std', this); return false;", 'data-copied-label' => _('Copied')]);
$copyErrButton = new \Ease\TWB4\LinkButton('#', '📋 '._('Copy'), 'secondary btn-block', ['onclick' => "copyJobOutput('raw-output-err', this); return false;", 'data-copied-label' => _('Copied')]);

$outputTabs = new \Ease\TWB4\Tabs();
$outputTabs->addTab(_('Output').' '.(\strlen($jobber->getOutput()) ? ' <span class="badge badge-secondary">'.substr_count($jobber->getOutput(), "\n").'</span>' : '<span class="badge badge-invers">💭</span>'), [$stdTerminal, $stdOutputRaw, \strlen($jobber->getOutput()) ? $copyStdButton : '', \strlen($jobber->getOutput()) ? new \Ease\TWB4\LinkButton('joboutput.php?id='.$jobID.'&mode=std', _('Download'), 'secondary btn-block') : _('No output'), new \Ease\Html\PreTag('', ['id' => 'live-output'])]);
$outputTabs->addTab(_('Errors').' '.(empty($jobber->getErrorOutput()) ? ' <span class="badge badge-success">0</span>' : '<span class="badge badge-warning">'.substr_count($jobber->getErrorOutput(), "\n").'</span>'), [$errorTerminal, $errOutputRaw, \strlen($jobber->getErrorOutput()) ? $copyErrButton : '', \strlen($jobber->getErrorOutput()) ? new \Ease\TWB4\LinkButton('joboutput.php?id='.$jobID.'&mode=err', _('Download'), 'secondary btn-block') : _('No errors')], empty($jobber->getOutput()));

function multiflexiFormatArtifactBytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $size = (float) $bytes;
    $unit = 0;

    while ($size >= 1024 && $unit < \count($units) - 1) {
        $size /= 1024;
        $unit++;
    }

    return ($unit === 0 ? (string) $bytes : number_format($size, 2)).' '.$units[$unit];
}

function multiflexiImageMetadataBody(array $artifactData): \Ease\Html\DlTag
{
    $bytes = (string) $artifactData['artifact'];
    $list = new \Ease\Html\DlTag(null, ['class' => 'row']);
    $list->addDef(_('Filename'), htmlspecialchars((string) ($artifactData['filename'] ?? ''), \ENT_QUOTES | \ENT_HTML5, 'UTF-8'));
    $list->addDef(_('Content type'), htmlspecialchars((string) $artifactData['content_type'], \ENT_QUOTES | \ENT_HTML5, 'UTF-8'));
    $list->addDef(_('Size'), multiflexiFormatArtifactBytes(\strlen($bytes)));

    $dataUri = 'data://application/octet-stream;base64,'.base64_encode($bytes);
    $info = @getimagesize($dataUri);

    if ($info === false) {
        $list->addDef(_('Note'), _('Image dimensions could not be read'));

        return $list;
    }

    $list->addDef(_('Dimensions'), $info[0].' × '.$info[1].' px');
    $list->addDef(_('Detected type'), htmlspecialchars((string) ($info['mime'] ?? ''), \ENT_QUOTES | \ENT_HTML5, 'UTF-8'));

    if (($info[2] ?? null) === \IMAGETYPE_JPEG && \function_exists('exif_read_data')) {
        $stream = @fopen($dataUri, 'rb');

        if ($stream !== false) {
            $exif = @exif_read_data($stream, null, true);
            fclose($stream);

            foreach (['EXIF', 'IFD0'] as $section) {
                if (isset($exif[$section]) && \is_array($exif[$section])) {
                    foreach ($exif[$section] as $tag => $value) {
                        if (\is_scalar($value)) {
                            $list->addDef((string) $tag, htmlspecialchars((string) $value, \ENT_QUOTES | \ENT_HTML5, 'UTF-8'));
                        }
                    }
                }
            }
        }
    }

    return $list;
}

/**
 * Nested Preview/Metadata (or Rendered/Source) mini-tabs for one artifact.
 *
 * \Ease\TWB4\Tabs derives DOM ids purely from the tab label text with no
 * per-instance salt, so nesting one per artifact on this page would collide
 * across artifacts sharing the same labels. Ids here are salted with $uid.
 *
 * @param array<string, \Ease\Html\DivTag> $tabs label => tab body
 */
function multiflexiRenderMiniTabs(string $uid, array $tabs, string $activeLabel): \Ease\Html\DivTag
{
    $nav = new \Ease\Html\UlTag(null, ['class' => 'nav nav-tabs']);
    $content = new \Ease\Html\DivTag(null, ['class' => 'tab-content']);

    foreach ($tabs as $label => $body) {
        $tabId = $uid.'-'.preg_replace('/[^A-Za-z0-9_\\-]/', '', $label);
        $isActive = $label === $activeLabel;

        $nav->addItemSmart(new \Ease\Html\ATag('#'.$tabId, $label, [
            'class' => 'nav-link'.($isActive ? ' active' : ''),
            'id' => $tabId.'-tab',
            'data-toggle' => 'tab',
            'role' => 'tab',
            'aria-controls' => $tabId,
            'aria-selected' => $isActive ? 'true' : 'false',
        ]), ['class' => 'nav-item']);

        $content->addItem(new \Ease\Html\DivTag($body, [
            'class' => 'tab-pane fade'.($isActive ? ' show active' : ''),
            'id' => $tabId,
            'role' => 'tabpanel',
            'aria-labelledby' => $tabId.'-tab',
        ]));
    }

    return new \Ease\Html\DivTag([$nav, $content]);
}

$artifactor = new \MultiFlexi\Artifact();
$artifacts = $artifactor->listingQuery()->where('job_id', $jobID);

if ($artifacts->count()) {
    WebPage::singleton()->includeJavaScript('js/highlight.min.js');
    WebPage::singleton()->includeCss('css/highlight-default.min.css');
    WebPage::singleton()->addJavaScript('hljs.highlightAll();');
    $artifactsDiv = new \Ease\Html\DivTag();

    foreach ($artifacts->fetchAll() as $artifactData) {
        $contentType = (string) $artifactData['content_type'];
        $filename = (string) ($artifactData['filename'] ?? '');
        $isImage = str_starts_with($contentType, 'image/') || (bool) preg_match('/\.svg$/i', $filename);
        $isHtml = $contentType === 'text/html';

        if ($contentType === 'application/pdf') {
            $artifactBody = new \Ease\Html\DivTag(new \Ease\Html\Tag('embed', [
                'src' => 'getartifact.php?id='.$artifactData['id'].'&inline=1',
                'type' => 'application/pdf',
                'style' => 'width: 100%; height: 600px; border: 0;',
            ]));
        } elseif ($isImage) {
            $previewBody = new \Ease\Html\DivTag(new \Ease\Html\ImgTag('getartifact.php?id='.$artifactData['id'].'&inline=1', $filename, ['style' => 'max-width: 100%; height: auto;']));
            $metaBody = new \Ease\Html\DivTag(multiflexiImageMetadataBody($artifactData), ['style' => 'padding: 10px;']);
            $artifactBody = multiflexiRenderMiniTabs('artifact-'.$artifactData['id'], [_('Preview') => $previewBody, _('Metadata') => $metaBody], _('Preview'));
        } elseif ($isHtml) {
            $renderedBody = new \Ease\Html\DivTag(new \Ease\Html\IframeTag('getartifact.php?id='.$artifactData['id'].'&inline=1', ['style' => 'width: 100%; height: 600px; border: 0;', 'sandbox' => 'allow-same-origin']));
            $sourceBody = new \Ease\Html\DivTag(new \Ease\Html\PreTag('<code>'.htmlspecialchars((string) $artifactData['artifact'], \ENT_QUOTES | \ENT_HTML5, 'UTF-8').'</code>'), ['style' => 'font-family: monospace; color: black']);
            $artifactBody = multiflexiRenderMiniTabs('artifact-'.$artifactData['id'], [_('Rendered') => $renderedBody, _('Source') => $sourceBody], _('Rendered'));
        } else {
            switch ($contentType) {
                case 'application/json':
                    $code = json_encode(json_decode($artifactData['artifact']), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_LINE_TERMINATORS);

                    break;

                default:
                    $code = $artifactData['artifact'];

                    break;
            }

            $artifactBody = new \Ease\Html\DivTag(new \Ease\Html\PreTag('<code>'.htmlspecialchars((string) $code, \ENT_QUOTES | \ENT_HTML5, 'UTF-8').'</code>'), ['style' => 'font-family: monospace; color: black']);
        }

        $artifactsDiv->addItem(new \Ease\TWB4\Panel([new \Ease\Html\ATag('getartifact.php?id='.$artifactData['id'], '💾', ['class' => 'btn btn-info btn-sm']), '&nbsp;'.htmlspecialchars((string) ($artifactData['filename'] ?? ''), \ENT_QUOTES | \ENT_HTML5, 'UTF-8')], 'inverse', $artifactBody, htmlspecialchars((string) ($artifactData['note'] ?? ''), \ENT_QUOTES | \ENT_HTML5, 'UTF-8')));
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

if ($blockedWarning) {
    $panelContent[] = $blockedWarning;
}

if ($orphanedWarning) {
    $panelContent[] = $orphanedWarning;
}

if ($expectingStart) {
    $panelContent[] = new \Ease\Html\DivTag([
        new \Ease\Html\DivTag('', ['class' => 'spinner-border text-primary', 'role' => 'status']),
        new \Ease\Html\DivTag(_('Waiting for the job to start…'), ['class' => 'mt-2']),
    ], ['id' => 'job-wait-spinner', 'class' => 'text-center my-4']);
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
