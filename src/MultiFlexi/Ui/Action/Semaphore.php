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

namespace MultiFlexi\Ui\Action;

use MultiFlexi\Ui\CredentialSelect;

/**
 * Semaphore Action UI Class.
 *
 * Configuration form: Semaphore credential + project ID + template ID.
 *
 * @author vitex
 */
class Semaphore extends \MultiFlexi\Action\Semaphore
{
    public static function logo(): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
            .'<rect x="18" y="6" width="28" height="52" rx="6" fill="#2b2f36"/>'
            .'<circle cx="32" cy="18" r="7" fill="#e5484d"/>'
            .'<circle cx="32" cy="32" r="7" fill="#f5b301"/>'
            .'<circle cx="32" cy="46" r="7" fill="#3f9d4e"/>'
            .'</svg>',
        );
    }

    /**
     * Generate configuration form inputs for Semaphore action.
     *
     * @param string $prefix Form field prefix
     *
     * @return \Ease\Embedable Form field(s)
     */
    public function inputs(string $prefix): \Ease\Embedable
    {
        $companyId = 0;

        if (isset($_SESSION) && \array_key_exists('company', $_SESSION) && $_SESSION['company'] !== null) {
            $companyId = (int) $_SESSION['company'];
        }

        $container = new \Ease\Html\DivTag();
        $container->addItem(new \Ease\TWB4\FormGroup(
            _('Semaphore Credential'),
            new CredentialSelect($prefix.'[Semaphore][credential]', $companyId, 'Semaphore'),
            '',
            _('Semaphore credential prototype (SEMAPHORE_URL + SEMAPHORE_TOKEN)'),
        ));
        $container->addItem(new \Ease\TWB4\FormGroup(
            _('Project ID'),
            new \Ease\Html\InputNumberTag($prefix.'[Semaphore][project_id]'),
            '3',
            _('Semaphore project ID (e.g. 3)'),
        ));
        $container->addItem(new \Ease\TWB4\FormGroup(
            _('Template ID'),
            new \Ease\Html\InputNumberTag($prefix.'[Semaphore][template_id]'),
            '',
            _('Semaphore template (Ansible playbook) to run'),
        ));
        $container->addItem(new \Ease\TWB4\FormGroup(
            _('Forwarded env keys'),
            new \Ease\Html\InputTextTag($prefix.'[Semaphore][env_keys]'),
            '',
            _('Comma-separated job env keys forwarded to the task environment. EVENT_* are always forwarded. Empty = defaults'),
        ));
        $container->addItem(new \Ease\TWB4\FormGroup(
            _('Extra environment (JSON)'),
            new \Ease\Html\InputTextTag($prefix.'[Semaphore][environment]'),
            '',
            _('Optional JSON object merged into the task environment'),
        ));

        return $container;
    }

    /**
     * Configuration form for Semaphore action.
     */
    public function configForm(): \Ease\Embedable
    {
        return new \Ease\TWB4\Badge('info', _('Endpoint and token are read from the selected Semaphore credential'));
    }
}
