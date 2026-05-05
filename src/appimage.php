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

// Prevent PHP session from overriding our cache headers
session_cache_limiter('');

require_once __DIR__.'/init.php';

WebPage::singleton()->onlyForLogged();

$uuid = WebPage::getRequestValue('uuid');
$contentType = 'image/svg+xml';

if (file_exists('images/'.$uuid.'.svg')) {
    $imageData = file_get_contents('images/'.$uuid.'.svg');
} elseif (file_exists('/usr/share/multiflexi/images/'.$uuid.'.svg')) {
    $imageData = file_get_contents('/usr/share/multiflexi/images/'.$uuid.'.svg');
} else {
    $app = new \MultiFlexi\Application();
    $image = $app->listingQuery()->select('image', true)->where('uuid', $uuid)->limit(1)->fetch('image');

    // Extract content/type from data URI
    if (strstr($image, ',')) {
        [$contentType, $base64Data] = explode(',', $image);
        [, $contentType] = explode(':', $contentType);
        // Convert base64 data to original format
        $imageData = base64_decode($base64Data, true);
    } else {
        $imageData = file_get_contents('images/apps.svg');
    }
}

$etag = '"'.md5($imageData).'"';

// Return 304 if client already has the current version
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === $etag) {
    http_response_code(304);

    exit;
}

header('Content-Type: '.str_replace(';base64', '', $contentType));
header('Cache-Control: private, max-age=86400');
header('ETag: '.$etag);

echo $imageData;
