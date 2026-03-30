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

use Ease\TWB4\LinkButton;
use Ease\TWB4\Panel;
use Ease\TWB4\Row;
use MultiFlexi\Application;

/**
 * Description of ApplicationPanel.
 *
 * @author vitex
 *
 * @no-named-arguments
 */
class ArchivedJobPanel extends Panel
{
    public Row $headRow;

    public function __construct(\MultiFlexi\Job $job, $content = null, $footer = null)
    {
        $cid = $job->getApplication()->getMyKey();
        $this->headRow = new Row();
        $this->headRow->addColumn(4, [new \Ease\Html\ATag('app.php?id='.$cid, new AppLogo($job->getApplication(), ['style' => 'height: 120px'])), '&nbsp;', $job->getApplication()->getRecordName()]);
        //            new LinkButton('joblist.php?app_id='.$cid, '🧑‍💻&nbsp;'._('Jobs history'), 'secondary btn-lg')]);

        $ca = new \MultiFlexi\CompanyApp(null);
        $usedIncompanies = $ca->listingQuery()->select(['companyapp.company_id', 'company.name', 'company.slug', 'company.logo'], true)->leftJoin('company ON company.id = companyapp.company_id')->where('app_id', $cid)->fetchAll('company_id');

        if ($usedIncompanies) {
            $usedByCompany = new \Ease\Html\DivTag(_('Used by').': ', ['class' => 'card-group']);

            foreach ($usedIncompanies as $companyInfo) {
                $companyInfo['id'] = $companyInfo['company_id'];
                $kumpan = new \MultiFlexi\Company($companyInfo, ['autoload' => false]);
                $calb = new CompanyAppLink($kumpan, $job->getApplication(), ['class' => 'card-img-top']);
                $crls = new \MultiFlexi\Ui\CompanyRuntemplatesLinks($kumpan, $job->getApplication(), [], ['class' => 'btn btn-outline-secondary btn-sm']);

                $usedByCompany->addItem(new \Ease\TWB4\Card([new \Ease\Html\DivTag([new \Ease\Html\H5Tag($calb, ['class' => 'card-title']), $crls], ['class' => 'card-body'])], ['style' => 'width: 6rem;']));
            }

            $this->headRow->addColumn(6, $usedByCompany);
        } else {
            $this->headRow->addColumn(6, new LinkButton('?id='.$cid.'&action=delete', '🪦&nbsp;'._('Remove'), 'danger'));
        }

        $this->headRow->addItem(new RuntemplateButton($job->getRunTemplate()));

        //        $headRow->addColumn(2, new \Ease\TWB4\LinkButton('tasks.php?application_id=' . $cid, '🔧&nbsp;' . _('Setup tasks'), 'secondary btn-lg btn-block'));
        //        $headRow->addColumn(2, new \Ease\TWB4\LinkButton('adhoc.php?application_id=' . $cid, '🚀&nbsp;' . _('Application launcher'), 'secondary btn-lg btn-block'));
        //        $headRow->addColumn(2, new \Ease\TWB4\LinkButton('periodical.php?application_id=' . $cid, '🔁&nbsp;' . _('Periodical Tasks'), 'secondary btn-lg btn-block'));
        parent::__construct($this->headRow, 'default', $content, $footer);
    }
}
