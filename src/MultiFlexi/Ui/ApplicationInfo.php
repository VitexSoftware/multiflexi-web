<?php

/**
 * MultiFlexi - Application Info Panel.
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 * @copyright  2020-2023 Vitex Software
 */

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

use Ease\TWB4\Panel;
use MultiFlexi\Application;

/**
 * Description of ApplicationInfo.
 *
 * @author vitex
 *
 * @no-named-arguments
 */
class ApplicationInfo extends Panel
{
    public function __construct(Application $application)
    {
        $body = new \Ease\Html\DivTag(null, ['class' => 'p-4']);

        $row = new \Ease\TWB4\Row();
        $row->addColumn(4, new \Ease\Html\DivTag(new AppLogo($application, ['class' => 'img-fluid rounded shadow-sm border p-2']), ['class' => 'text-center mb-4']));

        $infoCol = $row->addColumn(8, $this->metadataTable($application));

        $body->addItem($row);

        $exitCodesTable = $this->exitCodesTable($application);

        if (null !== $exitCodesTable) {
            $body->addItem($exitCodesTable);
        }

        parent::__construct(null, 'default', $body, new AppLastMonthChart($application));
    }

    /**
     * Table of exit codes declared by the application (from app_exit_codes).
     *
     * @return null|\Ease\Html\DivTag null when the application declares no exit codes
     */
    private function exitCodesTable(Application $application): ?\Ease\Html\DivTag
    {
        $exitCodes = $application->getExitCodes();

        if (empty($exitCodes)) {
            return null;
        }

        $severityBadge = ['success' => 'success', 'info' => 'info', 'warning' => 'warning', 'error' => 'danger', 'critical' => 'dark'];

        $table = new \Ease\TWB4\Table(null, ['class' => 'table table-sm']);
        $table->addRowHeaderColumns([_('Exit Code'), _('Meaning'), _('Severity'), _('Retry')]);

        foreach ($exitCodes as $code => $definition) {
            $table->addRowColumns([
                new \Ease\Html\StrongTag((string) $code),
                htmlspecialchars($definition['description'], \ENT_QUOTES | \ENT_HTML5, 'UTF-8'),
                new \Ease\TWB4\Badge($severityBadge[$definition['severity']] ?? 'danger', $definition['severity']),
                $definition['retry'] ? _('Yes') : _('No'),
            ]);
        }

        return new \Ease\Html\DivTag([new \Ease\Html\H4Tag(_('Exit codes')), $table], ['class' => 'mt-4']);
    }

    /**
     * @param Application $application
     *
     * @return \Ease\Html\DivTag
     */
    public function metadataTable($application)
    {
        $metadata = new \Ease\Html\DivTag(null, ['class' => 'application-metadata']);

        $name = $application->getDataValue('name');
        $description = $application->getDataValue('description');

        if (method_exists($application, 'getLocalizedName')) {
            $name = $application->getLocalizedName() ?? $name;
        }

        if (method_exists($application, 'getLocalizedDescription')) {
            $description = $application->getLocalizedDescription() ?? $description;
        }

        $metadata->addItem(new \Ease\Html\H3Tag($name, ['class' => 'border-bottom pb-2 mb-3']));
        $metadata->addItem(new \Ease\Html\PTag($description, ['class' => 'lead']));

        $details = new \Ease\TWB4\Row();

        $col1 = $details->addColumn(6);
        $col1->addItem(self::infoRow('🏠', _('Homepage'), new \Ease\Html\ATag($application->getDataValue('homepage'), $application->getDataValue('homepage'))));
        $col1->addItem(self::infoRow('🆔', _('UUID'), $application->getDataValue('uuid')));
        $col1->addItem(self::infoRow('📦', _('Image'), $application->getDataValue('ociimage')));

        $col2 = $details->addColumn(6);
        $col2->addItem(self::infoRow('📁', _('Binary'), $application->getDataValue('executable')));
        $col2->addItem(self::infoRow('🏷️', _('Version'), $application->getDataValue('version')));
        $col2->addItem(self::infoRow('📜', _('Requirements'), new RequirementsOverview($application->getRequirements())));

        $metadata->addItem($details);

        return $metadata;
    }

    /**
     * Helper for info rows.
     *
     * @param mixed $icon
     * @param mixed $label
     * @param mixed $value
     */
    private static function infoRow($icon, $label, $value)
    {
        if (empty($value)) {
            return null;
        }

        return new \Ease\Html\DivTag([
            new \Ease\Html\SmallTag($icon.' '.$label, ['class' => 'text-muted d-block font-weight-bold text-uppercase small']),
            new \Ease\Html\DivTag($value, ['class' => 'mb-3 font-weight-normal']),
        ]);
    }
}
