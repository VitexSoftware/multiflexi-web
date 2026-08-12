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

use Ease\WebPage;
use MultiFlexi\RunTemplate;

require_once './init.php';
WebPage::singleton()->onlyForLogged();

$sourceId = WebPage::getRequestValue('id', 'int');
$cloneName = WebPage::getRequestValue('clonename');

if (empty($sourceId) || empty($cloneName)) {
    WebPage::singleton()->addItem(new PageTop(_('Runtemplate Clone')));
    WebPage::singleton()->addStatusMessage(_('Missing required parameters'), 'error');
    WebPage::singleton()->addItem(new PageBottom());
    WebPage::singleton()->draw();

    exit;
}

try {
    // Load source runtemplate
    $sourceTemplate = new RunTemplate($sourceId);

    // Clone always comes back disabled, regardless of the source's state
    $newId = $sourceTemplate->cloneAs($cloneName);

    if ($newId) {
        WebPage::singleton()->addStatusMessage(
            sprintf(
                _('Runtemplate %s cloned as %s (disabled — review and enable when ready)'),
                $sourceTemplate->getRecordName(),
                $cloneName,
            ),
            'success',
        );
        WebPage::singleton()->redirect('runtemplate.php?id='.$newId);
    } else {
        throw new \Exception(_('Failed to create new runtemplate'));
    }
} catch (\Exception $exc) {
    WebPage::singleton()->addItem(new PageTop(_('Runtemplate Clone')));
    WebPage::singleton()->addStatusMessage(
        sprintf(_('Error cloning runtemplate: %s'), $exc->getMessage()),
        'error',
    );
    WebPage::singleton()->addItem(new PageBottom());
    WebPage::singleton()->draw();
}
