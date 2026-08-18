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
class RuntemplateConfigForm extends EngineForm
{
    public function __construct(\MultiFlexi\RunTemplate $engine)
    {
        parent::__construct($engine, null, ['method' => 'post', 'action' => 'runtemplate.php', 'enctype' => 'multipart/form-data']);

        $defaults = $engine->getAppEnvironment();
        $appRequirements = $engine->getApplication()->getRequirements();
        $customized = $engine->getRuntemplateEnvironment();

        $fieldsOf = [];
        $fieldSource = [];
        $credSource = [];

        $credentialProvidersAvailable = \MultiFlexi\Requirement::getCredentialProviders();
        $credentialTypesAvailable = \MultiFlexi\Requirement::getCredentialTypes($engine->getCompany());
        $credentialsAvailable = \MultiFlexi\Requirement::getCredentials($engine->getCompany());
        $credentialsAssigned = $engine->getAssignedCredentials();

        $credData = [];

        $this->addCSS(<<<'CSS'
            .runtemplate-config-form .form-group { margin-bottom: 0.75rem; padding: 0.5rem; border-radius: 4px; transition: background-color 0.2s; }
            .runtemplate-config-form .form-group:hover { background-color: #f8f9fa; }
            .runtemplate-config-form label { font-size: 0.9rem; margin-bottom: 0.2rem; display: block; }
            .runtemplate-config-form .form-control-sm { height: calc(1.5em + 0.5rem + 2px); padding: 0.25rem 0.5rem; font-size: 0.875rem; }
            .required-field { border-left: 3px solid #dc3545 !important; }
            .secret-field { border-left: 3px solid #343a40 !important; }
            .expiring-field { border-left: 3px solid #ffc107 !important; }
            .required-field.secret-field { border-left: 3px solid #dc3545 !important; border-right: 3px solid #343a40 !important; }
            .required-field.expiring-field { border-left: 3px solid #dc3545 !important; border-right: 3px solid #ffc107 !important; }
            .field-flags { display: inline; margin-left: 0.4rem; }
            .field-flags .badge { font-size: 0.7rem; margin-left: 0.15rem; vertical-align: middle; }
            .config-category-nav { position: sticky; top: 80px; }
            .config-category-section { scroll-margin-top: 80px; padding-top: 0.5rem; }
            .config-category-section + .config-category-section { margin-top: 1.5rem; }
            .config-category-title { margin: 0.25rem 0 0.75rem; padding-bottom: 0.3rem; border-bottom: 1px solid #dee2e6; }
CSS);
        $this->addTagClass('runtemplate-config-form');

        $this->addItem(new RuntemplateRequirementsChoser($engine));

        $appFields = \MultiFlexi\Conffield::getAppConfigs($engine->getApplication());
        $runTemplateFields = $engine->getEnvironment();

        $appFields->takeValues($customized);

        // Configuration option categories as defined by the application
        // schema (multiflexi-core schema/application.json: environment.*.category).
        $categoryOrder = ['API', 'Database', 'Behavior', 'Security', 'Other'];
        $categoryOf = $this->readFieldCategories($engine->getApplication());
        $categoryBuckets = [];

        foreach ($categoryOrder as $categoryName) {
            $categoryBuckets[$categoryName] = new \Ease\Html\DivTag(null, ['class' => 'config-category-fields']);
        }

        foreach ($appFields as $fieldName => $field) {
            $fieldCategory = $field->getCategory();

            if ($fieldCategory === '' || !isset($categoryBuckets[$fieldCategory])) {
                $fieldCategory = $categoryOf[$fieldName] ?? 'Other';
            }

            if (!isset($categoryBuckets[$fieldCategory])) {
                $fieldCategory = 'Other';
            }

            $bucket = $categoryBuckets[$fieldCategory];

            $inputCaption = new \Ease\Html\StrongTag($fieldName);
            $credential = null;
            $credValue = null;
            $isDisabled = false;

            $runTemplateField = $runTemplateFields->getFieldByCode($fieldName);

            if ($runTemplateField && \Ease\Euri::isValid($runTemplateField->getSource())) {
                $resolved = \Ease\Euri::toObject($runTemplateField->getSource());

                if ($resolved && ($resolved::class === 'MultiFlexi\\Credential')) {
                    $credential = $resolved;
                    $credValue = $credential->getDataValue($fieldName);
                    $isDisabled = true;
                }
            }

            $value = $credValue ?? $field->getValue();

            if ($field->getType() === 'bool') {
                $toggleAttrs = $isDisabled ? ['disabled' => 'disabled'] : [];
                $input = new \Ease\Html\DivTag(BoolFieldWidget::toggle($fieldName, $value, $toggleAttrs));
            } elseif ($field->isMultiLine()) {
                $input = new \Ease\Html\TextareaTag($fieldName, $value, ['class' => 'form-control form-control-sm', 'rows' => 4]);
            } elseif ($field->isRedactable()) {
                $input = new \Ease\Html\InputTag($fieldName, '', [
                    'type' => 'password',
                    'class' => 'form-control form-control-sm',
                    'placeholder' => \MultiFlexi\ConfigField::maskValue($value),
                ]);
            } else {
                $input = new \Ease\Html\InputTag($fieldName, $value, self::inputAttrsForType($field->getType()));
            }

            if ($runTemplateField) { // Filed by Credential
                if ($credential) {
                    $credentialType = $credential->getCredentialType();

                    $credentialLink = new \Ease\Html\ATag('credential.php?id='.$credential->getMyKey(), new \Ease\Html\SmallTag($credential->getRecordName()));

                    $credProtoHelper = new \MultiFlexi\CredentialProtoType();
                    $protoData = $credProtoHelper->listingQuery()->where('code', $credentialType->getDataValue('prototype'))->fetch();
                    $protoLogo = $protoData && !empty($protoData['logo']) ? 'images/'.$protoData['logo'] : 'images/'.$runTemplateField->getLogo();

                    $formIcon = new \Ease\Html\ImgTag($protoLogo, (string) $credentialType->getRecordName(), ['height' => 20, 'title' => $credentialType->getRecordName()]);

                    $credentialTypeLink = new \Ease\Html\ATag('credentialtype.php?id='.$credentialType->getMyKey(), $formIcon);

                    $inputCaption = new \Ease\Html\SpanTag([$credentialTypeLink, new \Ease\Html\StrongTag($fieldName), '&nbsp;', $credentialLink]);

                    if (!$input instanceof \Ease\Html\DivTag) {
                        $input->setTagProperty('disabled', '1');
                    }

                    $field->setDescription($credentialType->getFields()->getField($fieldName)->getDescription());
                }

                $formGroup = $bucket->addItem(new \Ease\TWB4\FormGroup($inputCaption, $input, $runTemplateField->getValue(), $field->getDescription()));
            } else { // Simple Fields
                $formGroup = $bucket->addItem(new \Ease\TWB4\FormGroup($fieldName, $input, $field->getDefaultValue(), $field->getDescription()));
            }

            $flags = new \Ease\Html\SpanTag(null, ['class' => 'field-flags']);

            if ($field->isRequired()) {
                $formGroup->addTagClass('required-field');
                $flags->addItem(new \Ease\TWB4\Badge('danger', _('required')));
            }

            if ($field->isSecret()) {
                $formGroup->addTagClass('secret-field');
                $flags->addItem(new \Ease\TWB4\Badge('dark', '🔒 '._('secret')));
            }

            if ($field->isExpiring()) {
                $formGroup->addTagClass('expiring-field');
                $flags->addItem(new \Ease\TWB4\Badge('warning', '⏳ '._('expiring')));
            }

            if ($field->isMultiLine()) {
                $flags->addItem(new \Ease\TWB4\Badge('info', _('multiline')));
            }

            if (!empty($flags->pageParts)) {
                $formGroup->addItem($flags);
            }

            $hint = $field->getHint();

            if (!empty($hint)) {
                $formGroup->addItem(new \Ease\Html\SmallTag($hint, ['class' => 'form-text text-muted']));
            }
        }

        // $this->addItem( new RuntemplateTopicsChooser('topics', $engine)); //TODO

        // Lay the categorised fields out with a Scrollspy sidebar for navigation.
        $categoryEmoji = [
            'API' => '🔌',
            'Database' => '🗄️',
            'Behavior' => '🎛️',
            'Security' => '🔐',
            'Other' => '➕',
        ];

        $categoryNav = new \Ease\Html\DivTag(null, ['class' => 'list-group', 'id' => 'configCategoryNav']);
        $categorySections = new \Ease\Html\DivTag(null, ['class' => 'config-category-sections']);
        $firstCategory = true;
        $anyCategorized = false;

        foreach ($categoryOrder as $categoryName) {
            if (empty($categoryBuckets[$categoryName]->pageParts)) {
                continue; // no fields in this category
            }

            $anyCategorized = true;
            $sectionId = 'cfg-cat-'.$categoryName;
            $heading = ($categoryEmoji[$categoryName] ?? '').'&nbsp;'._($categoryName);

            $categoryNav->addItem(new \Ease\Html\ATag(
                '#'.$sectionId,
                $heading,
                ['class' => 'list-group-item list-group-item-action'.($firstCategory ? ' active' : '')],
            ));

            $categorySections->addItem(new \Ease\Html\DivTag([
                new \Ease\Html\DivTag($heading, ['class' => 'config-category-title h5']),
                $categoryBuckets[$categoryName],
            ], ['id' => $sectionId, 'class' => 'config-category-section']));

            $firstCategory = false;
        }

        if ($anyCategorized) {
            $layoutRow = new \Ease\TWB4\Row();
            $layoutRow->addColumn(3, new \Ease\Html\DivTag($categoryNav, ['class' => 'config-category-nav']));
            $layoutRow->addColumn(9, $categorySections);
            $this->addItem($layoutRow);

            $this->addJavaScript(<<<'JS'
                $('body').scrollspy({ target: '#configCategoryNav' });
JS);
        }

        $this->addItem(new \Ease\Html\InputHiddenTag('app_id', $engine->getDataValue('app_id')));
        $this->addItem(new \Ease\Html\InputHiddenTag('company_id', $engine->getDataValue('company_id')));

        $saveRow = new \Ease\TWB4\Row();
        $saveColumn = $saveRow->addColumn(8, new \Ease\TWB4\SubmitButton(_('Save'), 'success btn-lg btn-block'));
        $saveRow->addColumn(4, new \Ease\TWB4\LinkButton('actions.php?id='.$engine->getMyKey(), '🛠️&nbsp;'._('Actions'), 'secondary btn-lg btn-block'));

        $appSetupCommand = $engine->getApplication()->getDataValue('setup');

        if (!empty($appSetupCommand)) {
            $saveColumn->addItem(new \Ease\TWB4\Alert('info', 'ℹ️&nbsp;'._('After saving configuration, the following setup command will be executed:').'<br><code>'.htmlspecialchars((string) $appSetupCommand, \ENT_QUOTES | \ENT_HTML5, 'UTF-8').'</code>'));
        }

        $this->addItem($saveRow);
    }

    /**
     * HTML input attributes for a MultiFlexi ConfigField type that isn't
     * bool/multiline/redactable (those are handled separately). Maps
     * MultiFlexi's type vocabulary onto valid HTML5 input types instead of
     * passing the raw type string straight through (e.g. 'integer'/'float'
     * are not valid HTML5 input types).
     *
     * @return array<string, string>
     */
    private static function inputAttrsForType(string $type): array
    {
        $class = 'form-control form-control-sm';

        return match ($type) {
            'integer' => ['type' => 'number', 'step' => '1', 'class' => $class],
            'float' => ['type' => 'number', 'step' => 'any', 'class' => $class],
            'email' => ['type' => 'email', 'class' => $class],
            'url' => ['type' => 'url', 'class' => $class],
            default => ['type' => 'text', 'class' => $class],
        };
    }

    /**
     * Build a field-name → category map from the application definition file.
     *
     * Used as a fallback for applications imported before the conffield
     * category column existed (the category is then read straight from the
     * *.multiflexi.app.json definition on disk).
     *
     * @return array<string, string>
     */
    private function readFieldCategories(\MultiFlexi\Application $application): array
    {
        $categories = [];
        $deffile = (string) $application->getDataValue('deffile');

        if ($deffile === '' || !is_file($deffile)) {
            return $categories;
        }

        $appDef = json_decode((string) file_get_contents($deffile), true);

        if (!\is_array($appDef) || empty($appDef['environment']) || !\is_array($appDef['environment'])) {
            return $categories;
        }

        foreach ($appDef['environment'] as $envKey => $envCfg) {
            if (\is_array($envCfg) && !empty($envCfg['category'])) {
                $categories[$envKey] = (string) $envCfg['category'];
            }
        }

        return $categories;
    }
}
