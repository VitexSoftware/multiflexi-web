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
 * Daily task-fulfilment percentage line chart (last 14 days).
 */
class TaskSuccessChart extends \Ease\Html\DivTag
{
    public function __construct()
    {
        parent::__construct();

        $this->addItem(new \Ease\Html\H4Tag('📈 '._('Daily Fulfilment Rate (Last 14 Days)')));

        try {
            $tasker = new \MultiFlexi\Task();
            $since = (new \DateTime())->modify('-14 days')->format('Y-m-d');

            $rows = $tasker->getFluentPDO()
                ->from('task')
                ->select(<<<'EOD'
DATE(window_end) as day,
                     COUNT(*) as total,
                     SUM(CASE WHEN state IN ('fulfilled','fulfilled_late') THEN 1 ELSE 0 END) as ok,
                     SUM(CASE WHEN state = 'fulfilled' THEN 1 ELSE 0 END) as on_time,
                     SUM(CASE WHEN state = 'fulfilled_late' THEN 1 ELSE 0 END) as late,
                     SUM(CASE WHEN state IN ('failed','missed') THEN 1 ELSE 0 END) as bad
EOD,)
                ->where('window_end >= ?', $since)
                ->where("state NOT IN ('open','running')")
                ->groupBy('DATE(window_end)')
                ->orderBy('day ASC')
                ->fetchAll();

            if (!empty($rows)) {
                $dates = [];
                $pctData = [];
                $onTimeData = [];
                $lateData = [];
                $failData = [];

                foreach ($rows as $row) {
                    $label = $row['day'];
                    $dates[] = $label;
                    $pct = $row['total'] > 0 ? round(($row['ok'] / $row['total']) * 100) : 0;
                    $pctData[$label] = $pct;
                    $onTimeData[$label] = (int) $row['on_time'];
                    $lateData[$label] = (int) $row['late'];
                    $failData[$label] = (int) $row['bad'];
                }

                $graph = new \Goat1000\SVGGraph\SVGGraph(900, 320, [
                    'back_colour' => '#ffffff',
                    'stroke_colour' => '#000',
                    'back_stroke_width' => 0,
                    'axis_colour' => '#555',
                    'axis_font' => 'Arial',
                    'axis_font_size' => 10,
                    'grid_colour' => '#e8e8e8',
                    'pad_right' => 30,
                    'pad_left' => 50,
                    'pad_bottom' => 30,
                    'pad_top' => 10,
                    'show_data_labels' => true,
                    'data_label_font_size' => 9,
                    'legend_entries' => [_('Fulfilment %'), _('On time'), _('Late'), _('Failed/Missed')],
                    'legend_font_size' => 11,
                    'legend_position' => 'top left 10 10',
                    'legend_colour' => '#333',
                    'line_stroke_width' => 2,
                    'marker_type' => 'circle',
                    'marker_size' => 4,
                ]);

                $graph->colours(['#2ecc71', '#3498db', '#f39c12', '#e74c3c']);
                $graph->values([
                    'pct' => $pctData,
                    'ontime' => $onTimeData,
                    'late' => $lateData,
                    'fail' => $failData,
                ]);

                $this->addItem(new \Ease\Html\DivTag($graph->fetch('MultiLineGraph'), ['class' => 'chart-container']));
            } else {
                $this->addItem(new \Ease\TWB4\Alert('info', _('No completed tasks in the last 14 days yet.')));
            }
        } catch (\Exception $e) {
            $this->addItem(new \Ease\TWB4\Badge('danger', _('Chart error: ').$e->getMessage()));
        }
    }
}
