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
 * Per-RunTemplate task state metrics row.
 *
 * Displays six compact metric cards (Fulfilled, Late, Failed, Missed, Open, Running)
 * filtered to a single RunTemplate.
 */
class RuntemplateTaskMetrics extends \Ease\TWB4\Row
{
    public function __construct(\MultiFlexi\RunTemplate $runtemplate)
    {
        parent::__construct(null, ['class' => 'no-gutters mb-3']);

        $rtId = $runtemplate->getMyKey();

        try {
            $tasker = new \MultiFlexi\Task();

            $fulfilled = (int) $tasker->listingQuery()->where('runtemplate_id', $rtId)->where('state', \MultiFlexi\Task::STATE_FULFILLED)->count();
            $fulfilledLate = (int) $tasker->listingQuery()->where('runtemplate_id', $rtId)->where('state', \MultiFlexi\Task::STATE_FULFILLED_LATE)->count();
            $failed = (int) $tasker->listingQuery()->where('runtemplate_id', $rtId)->where('state', \MultiFlexi\Task::STATE_FAILED)->count();
            $missed = (int) $tasker->listingQuery()->where('runtemplate_id', $rtId)->where('state', \MultiFlexi\Task::STATE_MISSED)->count();
            $open = (int) $tasker->listingQuery()->where('runtemplate_id', $rtId)->where("state IN ('open','running')")->count();
            $total = $fulfilled + $fulfilledLate + $failed + $missed + $open;

            $configs = [
                [\MultiFlexi\Task::STATE_FULFILLED, 'success', '✅', _('Fulfilled'), $fulfilled],
                [\MultiFlexi\Task::STATE_FULFILLED_LATE, 'warning', '⏰', _('Fulfilled Late'), $fulfilledLate],
                [\MultiFlexi\Task::STATE_FAILED, 'danger', '❌', _('Failed'), $failed],
                [\MultiFlexi\Task::STATE_MISSED, 'dark', '🕳️', _('Missed'), $missed],
                [\MultiFlexi\Task::STATE_OPEN, 'info', '▶️', _('Active'), $open],
            ];

            foreach ($configs as [$state, $colour, $icon, $label, $cnt]) {
                $pct = $total > 0 ? round(($cnt / $total) * 100) : 0;
                $href = 'task-dashboard.php?runtemplate_id='.$rtId.'&state='.$state;

                $body = new \Ease\Html\DivTag(null, ['class' => 'card-body py-2 px-3 text-center']);
                $body->addItem(new \Ease\Html\DivTag(
                    new \Ease\Html\ATag($href, "{$icon} {$label}", ['class' => 'text-decoration-none small font-weight-bold text-muted']),
                    ['class' => 'mb-1'],
                ));
                $body->addItem(new \Ease\Html\DivTag((string) $cnt, ['class' => "font-weight-bold text-{$colour}", 'style' => 'font-size: 1.4rem; line-height: 1.2;']));
                $body->addItem(new \Ease\Html\SmallTag($pct.'%', ['class' => 'text-muted']));

                $card = new \Ease\Html\DivTag($body, ['class' => "card border-{$colour} h-100"]);
                $this->addColumn(2, $card);
            }

            // Total card
            $totalBody = new \Ease\Html\DivTag(null, ['class' => 'card-body py-2 px-3 text-center']);
            $totalBody->addItem(new \Ease\Html\DivTag(
                new \Ease\Html\ATag('task-dashboard.php?runtemplate_id='.$rtId, '📋 '._('Total'), ['class' => 'text-decoration-none small font-weight-bold text-muted']),
                ['class' => 'mb-1'],
            ));
            $totalBody->addItem(new \Ease\Html\DivTag((string) $total, ['class' => 'font-weight-bold text-secondary', 'style' => 'font-size: 1.4rem; line-height: 1.2;']));
            $totalBody->addItem(new \Ease\Html\SmallTag('&nbsp;', ['class' => 'text-muted']));
            $this->addColumn(2, new \Ease\Html\DivTag($totalBody, ['class' => 'card border-secondary h-100']));
        } catch (\Exception) {
        }
    }
}
