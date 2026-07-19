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

$oPage = WebPage::singleton();
$oPage->onlyForLogged();

$stateFilter = $oPage->getRequestValue('state') ?: 'all';
$runtemplateId = (int) ($oPage->getRequestValue('runtemplate_id') ?: 0) ?: null;

$stateLabels = [
    'all' => _('Tasks Overview'),
    'fulfilled' => _('Fulfilled Tasks'),
    'fulfilled_late' => _('Fulfilled Late Tasks'),
    'failed' => _('Failed Tasks'),
    'missed' => _('Missed Tasks'),
    'open' => _('Active Tasks'),
    'running' => _('Running Tasks'),
];
$pageTitle = $stateLabels[$stateFilter] ?? _('Tasks Overview');

$oPage->addItem(new PageTop($pageTitle));

// ── Metric cards ──────────────────────────────────────────────────────────────
$oPage->container->addItem(new TaskMetricsCards());

// ── Charts row ────────────────────────────────────────────────────────────────
$chartsRow = new \Ease\TWB4\Row();
$chartsRow->addColumn(8, new TaskSuccessChart());
$chartsRow->addColumn(4, new TaskStateChart());
$oPage->container->addItem($chartsRow);

// ── State filter pills ────────────────────────────────────────────────────────
$filterBar = new \Ease\Html\DivTag(null, ['class' => 'mb-3 d-flex flex-wrap align-items-center']);
$filterBar->addItem(new \Ease\Html\SpanTag(_('Filter:'), ['class' => 'font-weight-bold mr-2']));

$pills = [
    'all' => ['secondary', '📋 '._('All')],
    'open' => ['info', '🔵 '._('Active')],
    'fulfilled' => ['success', '✅ '._('Fulfilled')],
    'fulfilled_late' => ['warning', '⏰ '._('Late')],
    'failed' => ['danger', '❌ '._('Failed')],
    'missed' => ['dark', '🕳️ '._('Missed')],
];

foreach ($pills as $state => $pill) {
    [$colour, $label] = $pill;
    $href = 'task-dashboard.php?state='.$state.($runtemplateId ? '&runtemplate_id='.$runtemplateId : '');
    $isActive = ($stateFilter === $state);
    $cls = 'btn btn-sm mr-2 mb-2 btn-'.($isActive ? '' : 'outline-').$colour;
    $filterBar->addItem(new \Ease\Html\ATag($href, $label, ['class' => $cls]));
}

$oPage->container->addItem($filterBar);

// ── RunTemplate filter notice ─────────────────────────────────────────────────
if ($runtemplateId) {
    $rt = new \MultiFlexi\RunTemplate($runtemplateId);
    $rtName = htmlspecialchars($rt->getRecordName() ?: '#'.$runtemplateId);
    $clearHref = 'task-dashboard.php?state='.$stateFilter;
    $oPage->container->addItem(new \Ease\TWB4\Alert('info',
        sprintf(_('Filtered to RunTemplate: <strong>%s</strong>'), $rtName)
        .' &nbsp;<a href="'.$clearHref.'" class="alert-link">'._('Remove filter').'</a>',
    ));
}

// ── Tasks table ───────────────────────────────────────────────────────────────
$oPage->container->addItem(new TasksTable($stateFilter, $runtemplateId));

// ── CSS ───────────────────────────────────────────────────────────────────────
$oPage->addCSS(DashboardStyles::getStyles());

$oPage->addItem(new PageBottom('tasks'));
$oPage->draw();
