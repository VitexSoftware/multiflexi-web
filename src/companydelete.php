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

use Ease\Html\ATag;
use Ease\TWB4\Row;
use MultiFlexi\Company;

require_once './init.php';
WebPage::singleton()->onlyForLogged();
WebPage::singleton()->addItem(new PageTop(_('Company')));

$companies = new Company(WebPage::getRequestValue('id', 'int'));

$_SESSION['company'] = $companies->getMyKey();

$companyEnver = new \MultiFlexi\CompanyEnv($companies);

if (WebPage::singleton()->isPosted()) {
    $companyEnver->deleteFromSQL(['company_id' => $companies->getMyKey()]);

    $logger = new \MultiFlexi\Logger();
    $logger->deleteFromSQL(['company_id' => $companies->getMyKey()]);

    $jobber = new \MultiFlexi\Job();
    $jobber->deleteFromSQL(['company_id' => $companies->getMyKey()]);

    $companyRuntemplates = new \MultiFlexi\RunTemplate();
    $companyRuntemplates->deleteFromSQL(['company_id' => $companies->getMyKey()]);

    $confer = new \MultiFlexi\Configuration();
    $confer->deleteFromSQL(['company_id' => $companies->getMyKey()]);

    $appToCompany = new \MultiFlexi\CompanyApp();
    $appToCompany->deleteFromSQL(['company_id' => $companies->getMyKey()]);

    if ($companies->deleteFromSQL(['id' => $companies->getMyKey()])) {
        $companies->addStatusMessage(_('Company Deleted'), 'success');
        WebPage::singleton()->redirect('companies.php');
    } else {
        $companies->addStatusMessage(_('Error deleting Company').' '.$companies->getDataValue('name'), 'error');
    }

    $companies->unsetDataValue('name');
}

$instanceName = $companies->getDataValue('name');

if (empty($instanceName) === false) {
    $instanceLink = new ATag($companies->getDataValue('company'), $companies->getDataValue('company'));
} else {
    $instanceName = _('New Company');
    $instanceLink = null;
}

$leftColumn = [new DeleteCompanyForm($companies, null, ['action' => 'companydelete.php'])];

$runtemplatesToRemove = (new \MultiFlexi\RunTemplate())->listingQuery()->where('company_id', $companies->getMyKey())->fetchAll('id');
$credentialsToRemove = (new \MultiFlexi\Credential())->listingQuery()->where('company_id', $companies->getMyKey())->fetchAll('id');

if ($runtemplatesToRemove || $credentialsToRemove) {
    $removalPanel = new \Ease\TWB4\Panel(_('The following records will also be removed'), 'warning');

    if ($runtemplatesToRemove) {
        $removalPanel->addItem(new \Ease\Html\StrongTag(_('Run Templates')));
        $rtplList = $removalPanel->addItem(new \Ease\Html\UlTag());

        foreach ($runtemplatesToRemove as $runtemplateRow) {
            $rtplList->addItem(new \Ease\Html\LiTag($runtemplateRow['name']));
        }
    }

    if ($credentialsToRemove) {
        $removalPanel->addItem(new \Ease\Html\StrongTag(_('Credentials')));
        $credList = $removalPanel->addItem(new \Ease\Html\UlTag());

        foreach ($credentialsToRemove as $credentialRow) {
            $credList->addItem(new \Ease\Html\LiTag($credentialRow['name']));
        }
    }

    $leftColumn[] = $removalPanel;
}

$instanceRow = new Row();
$instanceRow->addColumn(4, $leftColumn);

if (empty($companies->getDataValue('logo')) === false) {
    $rightColumn[] = new \Ease\Html\ImgTag($companies->getDataValue('logo'), 'logo', ['class' => 'img-fluid']);
}

$rightColumn[] = new EnvironmentView($companyEnver);
$instanceRow->addColumn(8, $rightColumn);
WebPage::singleton()->container->addItem(new CompanyPanel($companies, $instanceRow));
WebPage::singleton()->addItem(new PageBottom());
WebPage::singleton()->draw();
