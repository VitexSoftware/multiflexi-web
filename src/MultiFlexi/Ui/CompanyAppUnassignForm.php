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

use Ease\Html\InputHiddenTag;
use Ease\TWB4\SubmitButton;

/**
 * "Remove App from Company" form.
 *
 * Only ever rendered when no RunTemplate references the app/company pair
 * anymore (see CompanyApplicationPanel), so there is no data loss risk.
 * Still submitted as a CSRF-protected POST (via SecureForm) rather than a
 * bare link, since companyapp.php's global CSRF middleware only validates
 * POST requests.
 *
 * @author vitex
 *
 * @no-named-arguments
 */
class CompanyAppUnassignForm extends SecureForm
{
    public function __construct(\MultiFlexi\Company $company, \MultiFlexi\Application $application)
    {
        parent::__construct([
            'method' => 'POST',
            'action' => 'companyapp.php?company_id='.$company->getMyKey().'&app_id='.$application->getMyKey(),
            'class' => 'd-inline',
        ], null, ['class' => 'd-inline']);

        $this->addItem(new InputHiddenTag('action', 'unassign'));

        $confirmMessage = sprintf(_('Remove %s from %s?'), \MultiFlexi\LocalizedApplication::nameOf($application), $company->getRecordName());

        $this->addItem(new SubmitButton('🗑️&nbsp;'._('Remove from Company'), 'outline-danger btn-lg', [
            'onclick' => 'return confirm('.json_encode($confirmMessage, \JSON_THROW_ON_ERROR).');',
        ]));
    }
}
