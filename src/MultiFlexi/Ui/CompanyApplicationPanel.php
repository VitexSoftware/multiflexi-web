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
use MultiFlexi\Company;

/**
 * Description of ApplicationPanel.
 *
 * @author vitex
 *
 * @no-named-arguments
 */
class CompanyApplicationPanel extends Panel
{
    public Row $headRow;
    private Application $application;
    private Company $company;

    /**
     * Application Panel.
     *
     * @param null|mixed $content
     * @param null|mixed $footer
     */
    public function __construct(\MultiFlexi\CompanyApp $companyApp, $content = null, $footer = null)
    {
        $company = $companyApp->getCompany();
        $this->company = $company;
        $this->application = $companyApp->getApplication();
        $this->headRow = new Row();

        $logoCol = $this->headRow->addColumn(2, new \Ease\Html\ATag('app.php?id='.$this->application->getMyKey(), [new AppLogo($this->application, ['style' => 'height: 80px', 'class' => 'img-thumbnail shadow-sm'])]));
        $logoCol->addTagClass('text-center my-auto');

        $titleCol = $this->headRow->addColumn(4, [
            new \Ease\Html\H2Tag(\MultiFlexi\LocalizedApplication::nameOf($this->application), ['class' => 'mb-0']),
            new \Ease\Html\SmallTag($this->application->getDataValue('uuid'), ['class' => 'text-muted d-block small']),
        ]);
        $titleCol->addTagClass('my-auto');

        $crls = new \MultiFlexi\Ui\CompanyRuntemplatesLinks($company, $this->application, [], ['class' => 'btn btn-outline-secondary btn-sm p-0 px-1', 'style' => 'font-size: 0.7rem;']);

        $usageDiv = new \Ease\Html\DivTag(null, ['class' => 'p-2 bg-light rounded shadow-sm border']);
        $usageDiv->addItem(new \Ease\Html\SmallTag(_('Active RunTemplates').': ', ['class' => 'font-weight-bold mb-1 d-block text-uppercase small text-secondary']));

        $usageTable = new \Ease\TWB4\Table(null, ['class' => 'table table-sm table-hover mb-0', 'style' => 'font-size: 0.85rem;']);
        $usageTable->addRowColumns([
            [new \Ease\Html\SmallTag(_('Count'), ['class' => 'text-muted']), '&nbsp;', new \Ease\TWB4\Badge('primary', (string) $crls->count())],
            $crls,
        ]);

        $usageDiv->addItem($usageTable);
        $this->headRow->addColumn(6, $usageDiv);

        parent::__construct($this->headRow, 'default', $content, $footer);
    }

    #[\Override]
    public function finalize(): void
    {
        $this->footer->addItem(new LinkButton('jobs.php?app_id='.$this->application->getMyKey(), '🧑‍💻&nbsp;'._('App Jobs'), 'secondary btn-lg'));

        $runtemplateCount = \count((new \MultiFlexi\RunTemplate())->getFluentPDO()->from('runtemplate')
            ->where('app_id', $this->application->getMyKey())
            ->where('company_id', $this->company->getMyKey())
            ->fetchAll());

        if ($runtemplateCount > 0) {
            $hint = sprintf(_('%d RunTemplate(s) must be removed before removing this application from the company'), $runtemplateCount);
            $removeButton = new LinkButton('#', '🗑️&nbsp;'._('Remove from Company'), 'outline-danger btn-lg disabled', [
                'aria-disabled' => 'true',
                'tabindex' => '-1',
                'title' => $hint,
            ]);
            $this->footer->addItem($removeButton);
            $this->footer->addItem(new \Ease\TWB4\Badge('warning', (string) $runtemplateCount, ['class' => 'ml-1', 'title' => $hint]));
        } else {
            $this->footer->addItem(new CompanyAppUnassignForm($this->company, $this->application));
        }
        parent::finalize();
    }
}
