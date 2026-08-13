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

/**
 * Description of EnvironmentView.
 *
 * @author vitex
 *
 * @no-named-arguments
 */
class EnvironmentView extends \Ease\Html\TableTag
{
    /**
     * @param array<string, string> $properties
     */
    public function __construct(\MultiFlexi\ConfigFields $environment, array $properties = [])
    {
        $properties['class'] = 'table';
        parent::__construct(null, $properties);
        $this->addRowHeaderColumns([_('Name'), _('Value'), _('Source')]);

        foreach ($environment as $key => $field) {
            $this->addRowColumns([new \Ease\Html\SpanTag($key, ['title' => $field->getDescription()]), $field->getValue(), self::sourceView($field->getSource())]);
        }
    }

    public static function sourceView(string $source): \Ease\Html\DivTag
    {
        if (\Ease\Euri::isValid($source)) {
            $origin = \Ease\Euri::toObject($source);

            if (method_exists($origin, 'getObjectName')) {
                $sourceName = $origin->getObjectName();
            } else {
                $sourceName = \Ease\Functions::baseClassName($origin);
            }

            if ($origin instanceof \MultiFlexi\Credential && $origin->getCredentialType()) {
                return new \Ease\Html\DivTag([
                    new CredentialTypeLogo($origin->getCredentialType(), ['class' => 'credential-source-logo', 'height' => 16, 'width' => 16]),
                    ' '.$sourceName,
                ]);
            }

            $source = $sourceName;
        }

        return new \Ease\Html\DivTag($source);
    }
}
