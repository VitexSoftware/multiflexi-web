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

$ruleId = WebPage::getRequestValue('id', 'int');

$eventRule = new \MultiFlexi\EventRule($ruleId, ['autoload' => true]);

$delete = WebPage::getRequestValue('delete', 'int');

if (null !== $delete) {
    $eventRule->loadFromSQL($delete);

    if ($eventRule->deleteFromSQL($delete)) {
        $eventRule->addStatusMessage(_('Event Rule removed'), 'success');
    } else {
        $eventRule->addStatusMessage(_('Error removing Event Rule'), 'error');
    }

    WebPage::singleton()->redirect('eventrules.php');
}

if (WebPage::singleton()->isPosted()) {
    $posted = $_POST;

    // Select "0" / empty means NULL for optional FK columns
    foreach (['event_source_id', 'runtemplate_source_id'] as $optionalFk) {
        if (!isset($posted[$optionalFk]) || $posted[$optionalFk] === '' || $posted[$optionalFk] === '0') {
            $posted[$optionalFk] = null;
        } else {
            $posted[$optionalFk] = (int) $posted[$optionalFk];
        }
    }

    if (empty($posted['runtemplate_id']) || (int) $posted['runtemplate_id'] <= 0) {
        $eventRule->addStatusMessage(_('Target RunTemplate is required'), 'error');
    } elseif ($posted['event_source_id'] === null && $posted['runtemplate_source_id'] === null) {
        $eventRule->addStatusMessage(_('Set either an Event Source or a Source RunTemplate'), 'error');
    } else {
        $eventRule->takeData($posted);

        // Validate env_mapping JSON
        $envMapping = $eventRule->getDataValue('env_mapping');

        if (!empty($envMapping) && null === json_decode($envMapping, true)) {
            $eventRule->addStatusMessage(_('Invalid JSON in Environment Variable Mapping'), 'error');
        } else {
            if (null !== $eventRule->dbsync()) {
                $eventRule->addStatusMessage(_('Event Rule saved'), 'success');
            } else {
                $eventRule->addStatusMessage(_('Error saving Event Rule'), 'error');
            }
        }
    }
}

$pageTitle = $eventRule->getMyKey()
    ? sprintf(_('Event Rule #%s'), $eventRule->getMyKey())
    : _('New Event Rule');

WebPage::singleton()->addItem(new PageTop($pageTitle));

WebPage::singleton()->container->addItem(new EventRuleForm($eventRule));

WebPage::singleton()->addItem(new PageBottom());

WebPage::singleton()->draw();
