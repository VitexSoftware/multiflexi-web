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

// Public endpoint: credential-type / prototype logos are not sensitive and are
// consumed by external tools (e.g. node-red-contrib-multiflexi) without a session.

$id = (int) WebPage::getRequestValue('id');
$contentType = 'image/svg+xml';
$imageData = false;

// Image search paths (development source tree first, then deb-installed locations)
$imageDirectories = [
    __DIR__.'/images',
    '/usr/share/multiflexi-web/images',
    '/usr/share/multiflexi/images',
];

if ($id) {
    $credential = new \MultiFlexi\Credential($id);
    $type = $credential->getCredentialType();
    $logo = $type ? (string) $type->getLogo() : '';

    if ($logo !== '') {
        if (preg_match('#^data:([^;]+);base64,(.*)$#s', $logo, $m)) {
            $contentType = $m[1];
            $imageData = base64_decode($m[2], true);
        } else {
            // Bare filename (e.g. "csas-authorize.svg") — resolve from image dirs.
            $file = basename($logo);

            foreach ($imageDirectories as $dir) {
                $candidate = $dir.'/'.$file;

                if (is_file($candidate)) {
                    $imageData = file_get_contents($candidate);
                    $contentType = str_ends_with(strtolower($file), '.svg')
                        ? 'image/svg+xml'
                        : (mime_content_type($candidate) ?: 'application/octet-stream');

                    break;
                }
            }
        }
    }
}

if ($imageData === false || $imageData === '') {
    $contentType = 'image/svg+xml';
    $fallback = __DIR__.'/images/CommonCredentialType.svg';
    $imageData = is_file($fallback)
        ? file_get_contents($fallback)
        : file_get_contents(__DIR__.'/images/apps.svg');
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
