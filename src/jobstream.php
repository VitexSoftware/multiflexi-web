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

$jobId = (int) WebPage::singleton()->getRequestValue('id', 'int');

if ($jobId <= 0) {
    http_response_code(400);

    exit;
}

$jobber = new \MultiFlexi\Job($jobId);

if (!$jobber->getMyKey()) {
    http_response_code(404);

    exit;
}

// SSE headers — must be sent before any output
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');  // disable nginx proxy buffering
header('Connection: keep-alive');

// Disable output buffering so events reach the browser immediately
while (ob_get_level() > 0) {
    ob_end_flush();
}

if (\function_exists('set_time_limit')) {
    set_time_limit(0);
}

$outputLines = new \MultiFlexi\JobOutputLine();
$lastId = 0;
$startTime = time();
$maxRuntime = 1800; // 30 minutes

/**
 * Emit a single SSE event.
 */
$emit = static function (string $event, array $data): void {
    echo "event: {$event}\n";
    echo 'data: '.json_encode($data)."\n\n";
    flush();
};

// Stream loop
while (true) {
    if (time() - $startTime >= $maxRuntime) {
        $emit('timeout', ['message' => 'Stream timeout after 30 minutes']);

        break;
    }

    // Fetch new lines since last seen id
    $rows = $outputLines->getLinesSince($jobId, $lastId);

    foreach ($rows as $row) {
        $emit('output', [
            'id' => (int) $row['id'],
            'seq' => (int) $row['seq'],
            'type' => $row['type'],
            'line' => $row['line'],
            'created_at' => $row['created_at'],
        ]);
        $lastId = (int) $row['id'];
    }

    // Reload job to check if it has finished
    $jobber->loadFromSQL($jobId);
    $exitcode = $jobber->getDataValue('exitcode');

    if ($exitcode !== null) {
        // Drain any remaining lines that arrived after the last fetch
        $remaining = $outputLines->getLinesSince($jobId, $lastId);

        foreach ($remaining as $row) {
            $emit('output', [
                'id' => (int) $row['id'],
                'seq' => (int) $row['seq'],
                'type' => $row['type'],
                'line' => $row['line'],
                'created_at' => $row['created_at'],
            ]);
        }

        $endValue = $jobber->getDataValue('end');

        $emit('done', [
            'exitcode' => (int) $exitcode,
            'end' => $endValue,
            'end_ts' => $endValue ? (new \DateTime($endValue))->getTimestamp() : time(),
        ]);

        break;
    }

    usleep(300000); // 300 ms poll interval
}
