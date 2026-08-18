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
 * Description of CustomAppConfigForm.
 *
 * @author vitex
 *
 * @no-named-arguments
 */
class CustomAppConfigForm extends EngineForm
{
    private array $modulesEnv;

    /**
     * @param type $engine
     */
    public function __construct($engine)
    {
        parent::__construct($engine, null, ['method' => 'post', 'action' => 'custserviceconfig.php']);
        $appId = $engine->getDataValue('app_id');

        $job = new \MultiFlexi\Job(['company_id' => $engine->getDataValue('company_id'), 'app_id' => $appId], ['autoload' => false]);
        $values = $job->getFullEnvironment();

        foreach (\MultiFlexi\Conffield::getAppConfigs(new \MultiFlexi\Application($engine->getDataValue('app_id'))) as $fieldInfo) {
            $keyname = $fieldInfo->getCode();
            $currentValue = \array_key_exists($keyname, $values) ? $values[$keyname]['value'] : $fieldInfo->getDefaultValue();

            if ($fieldInfo->getType() === 'bool') {
                $input = new \Ease\Html\DivTag(BoolFieldWidget::toggle($keyname, $currentValue));
            } else {
                $input = new \Ease\Html\InputTag($keyname, $currentValue, ['type' => $fieldInfo->getType()]);
            }

            $this->addInput($input, $keyname.'&nbsp;('.$fieldInfo->getSource().')', $fieldInfo->getDefaultValue(), $fieldInfo->getDescription());
        }

        $this->addItem(new \Ease\Html\InputHiddenTag('app_id', $engine->getDataValue('app_id')));
        $this->addItem(new \Ease\Html\InputHiddenTag('company_id', $engine->getDataValue('company_id')));
        $this->addItem(new \Ease\TWB4\SubmitButton(_('Save'), 'success btn-lg btn-block'));
    }
}
