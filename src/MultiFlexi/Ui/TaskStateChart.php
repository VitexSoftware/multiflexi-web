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
 * Pie chart showing task state distribution.
 */
class TaskStateChart extends \Ease\Html\DivTag
{
    public function __construct()
    {
        parent::__construct();

        $this->addItem(new \Ease\Html\H4Tag('🥧 '._('Task State Distribution')));

        try {
            $tasker = new \MultiFlexi\Task();

            $rows = $tasker->getFluentPDO()
                ->from('task')
                ->select('state, COUNT(*) as cnt')
                ->groupBy('state')
                ->orderBy('cnt DESC')
                ->fetchAll();

            if (!empty($rows)) {
                $labels = [
                    'fulfilled' => _('Fulfilled'),
                    'fulfilled_late' => _('Fulfilled Late'),
                    'failed' => _('Failed'),
                    'missed' => _('Missed'),
                    'open' => _('Open'),
                    'running' => _('Running'),
                ];
                $colours = [
                    'fulfilled' => '#2ecc71',
                    'fulfilled_late' => '#f39c12',
                    'failed' => '#e74c3c',
                    'missed' => '#555555',
                    'open' => '#3498db',
                    'running' => '#9b59b6',
                ];

                $chartData = [];
                $chartColours = [];
                $legend = [];

                foreach ($rows as $row) {
                    $label = $labels[$row['state']] ?? $row['state'];
                    $chartData[$label] = (int) $row['cnt'];
                    $chartColours[] = $colours[$row['state']] ?? '#aaaaaa';
                    $legend[] = $label;
                }

                $graph = new \Goat1000\SVGGraph\SVGGraph(400, 320, [
                    'back_colour' => '#ffffff',
                    'stroke_colour' => '#fff',
                    'stroke_width' => 2,
                    'pad_right' => 10,
                    'pad_left' => 10,
                    'pad_bottom' => 10,
                    'pad_top' => 10,
                    'show_data_labels' => true,
                    'data_label_font_size' => 10,
                    'legend_entries' => $legend,
                    'legend_font_size' => 11,
                    'legend_position' => 'bottom centre 0 0',
                    'legend_colour' => '#333',
                ]);

                $graph->colours($chartColours);
                $graph->values($chartData);
                $this->addItem(new \Ease\Html\DivTag($graph->fetch('PieGraph'), ['class' => 'chart-container']));
            } else {
                $this->addItem(new \Ease\TWB4\Alert('info', _('No task data yet.')));
            }
        } catch (\Exception $e) {
            $this->addItem(new \Ease\TWB4\Badge('danger', _('Chart error: ').$e->getMessage()));
        }
    }
}
