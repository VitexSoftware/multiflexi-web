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
 * Six metric cards summarising Task states.
 */
class TaskMetricsCards extends \Ease\TWB4\Row
{
    public function __construct()
    {
        parent::__construct();

        $tasker = new \MultiFlexi\Task();
        $q = $tasker->listingQuery();

        $total = $q->count();
        $fulfilled = $tasker->listingQuery()->where("state = 'fulfilled'")->count();
        $fulfilledLate = $tasker->listingQuery()->where("state = 'fulfilled_late'")->count();
        $failed = $tasker->listingQuery()->where("state = 'failed'")->count();
        $missed = $tasker->listingQuery()->where("state = 'missed'")->count();
        $open = $tasker->listingQuery()->where("state IN ('open','running')")->count();

        $todayFulfilled = $tasker->listingQuery()
            ->where("state IN ('fulfilled','fulfilled_late')")
            ->where('DATE(window_start) = CURDATE()')
            ->count();
        $todayTotal = $tasker->listingQuery()
            ->where("state NOT IN ('open','running')")
            ->where('DATE(window_start) = CURDATE()')
            ->count();
        $todayPct = $todayTotal > 0 ? round(($todayFulfilled / $todayTotal) * 100) : 0;

        // Total
        $c = new \Ease\TWB4\Card(null, ['class' => 'bg-secondary text-white h-100']);
        $b = new \Ease\Html\DivTag(null, ['class' => 'card-body text-center']);
        $b->addItem(new \Ease\Html\H5Tag('📋 '._('Total Tasks'), ['class' => 'card-title']));
        $b->addItem(new \Ease\Html\H2Tag($total, ['class' => 'display-4 mb-2']));
        $b->addItem(new \Ease\Html\ATag('task-dashboard.php', _('All tasks'), ['class' => 'btn btn-light btn-sm']));
        $c->addItem($b);
        $this->addColumn(2, $c);

        // Fulfilled
        $c = new \Ease\TWB4\Card(null, ['class' => 'bg-success text-white h-100']);
        $b = new \Ease\Html\DivTag(null, ['class' => 'card-body text-center']);
        $b->addItem(new \Ease\Html\H5Tag('✅ '._('Fulfilled'), ['class' => 'card-title']));
        $b->addItem(new \Ease\Html\H2Tag($fulfilled, ['class' => 'display-4 mb-2']));
        $b->addItem(new \Ease\Html\SmallTag(sprintf(_('Today: %d%%'), $todayPct), ['class' => 'd-block mb-2']));
        $b->addItem(new \Ease\Html\ATag('task-dashboard.php?state=fulfilled', _('View'), ['class' => 'btn btn-light btn-sm']));
        $c->addItem($b);
        $this->addColumn(2, $c);

        // Fulfilled late
        $c = new \Ease\TWB4\Card(null, ['class' => 'bg-warning text-dark h-100']);
        $b = new \Ease\Html\DivTag(null, ['class' => 'card-body text-center']);
        $b->addItem(new \Ease\Html\H5Tag('⏰ '._('Fulfilled Late'), ['class' => 'card-title']));
        $b->addItem(new \Ease\Html\H2Tag($fulfilledLate, ['class' => 'display-4 mb-2']));
        $b->addItem(new \Ease\Html\SmallTag(_('After deadline'), ['class' => 'd-block mb-2']));
        $b->addItem(new \Ease\Html\ATag('task-dashboard.php?state=fulfilled_late', _('View'), ['class' => 'btn btn-dark btn-sm']));
        $c->addItem($b);
        $this->addColumn(2, $c);

        // Failed
        $c = new \Ease\TWB4\Card(null, ['class' => 'bg-danger text-white h-100']);
        $b = new \Ease\Html\DivTag(null, ['class' => 'card-body text-center']);
        $b->addItem(new \Ease\Html\H5Tag('❌ '._('Failed'), ['class' => 'card-title']));
        $b->addItem(new \Ease\Html\H2Tag($failed, ['class' => 'display-4 mb-2']));
        $b->addItem(new \Ease\Html\SmallTag(_('Budget exhausted'), ['class' => 'd-block mb-2']));
        $b->addItem(new \Ease\Html\ATag('task-dashboard.php?state=failed', _('View'), ['class' => 'btn btn-light btn-sm']));
        $c->addItem($b);
        $this->addColumn(2, $c);

        // Missed
        $c = new \Ease\TWB4\Card(null, ['class' => 'bg-dark text-white h-100']);
        $b = new \Ease\Html\DivTag(null, ['class' => 'card-body text-center']);
        $b->addItem(new \Ease\Html\H5Tag('🕳️ '._('Missed'), ['class' => 'card-title']));
        $b->addItem(new \Ease\Html\H2Tag($missed, ['class' => 'display-4 mb-2']));
        $b->addItem(new \Ease\Html\SmallTag(_('Zero attempts'), ['class' => 'd-block mb-2']));
        $b->addItem(new \Ease\Html\ATag('task-dashboard.php?state=missed', _('View'), ['class' => 'btn btn-light btn-sm']));
        $c->addItem($b);
        $this->addColumn(2, $c);

        // Open / Running
        $c = new \Ease\TWB4\Card(null, ['class' => 'bg-primary text-white h-100']);
        $b = new \Ease\Html\DivTag(null, ['class' => 'card-body text-center']);
        $b->addItem(new \Ease\Html\H5Tag('▶️ '._('Active'), ['class' => 'card-title']));
        $b->addItem(new \Ease\Html\H2Tag($open, ['class' => 'display-4 mb-2']));
        $b->addItem(new \Ease\Html\SmallTag(_('Open + Running'), ['class' => 'd-block mb-2']));
        $b->addItem(new \Ease\Html\ATag('task-dashboard.php?state=open', _('View'), ['class' => 'btn btn-light btn-sm']));
        $c->addItem($b);
        $this->addColumn(2, $c);
    }
}
