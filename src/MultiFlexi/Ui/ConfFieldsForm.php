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
use Ease\Html\InputTextTag;
use Ease\TWB4\SubmitButton;

/**
 * Form for editing all ConfigField properties.
 *
 * @author vitex
 *
 * @no-named-arguments
 */
class ConfFieldsForm extends SecureForm
{
    /**
     * Specify Fields for Application.
     *
     * @param array $conffields
     * @param mixed $formContents
     * @param array $tagProperties
     */
    public function __construct($conffields, $formContents, $tagProperties = [])
    {
        parent::__construct(['method' => 'post', 'action' => 'conffield.php'], $formContents, $tagProperties);

        $this->addCSS(<<<'CSS'
            .conffields-group { margin-bottom: 1rem; }
            .conffields-group .card-header { font-weight: 600; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.03em; color: #6c757d; }
            .conffields-value-group .card-header { color: #155724; background-color: #d4edda; }
            .conffields-flags-group .form-group { display: inline-block; margin-right: 1.5rem; margin-bottom: 0.5rem; vertical-align: top; }
CSS);
        $this->addJavaScript(<<<'JS'
            var typeSelect = $('select[name="type"]');
            var boolDefval = $('.defval-bool');
            var textDefval = $('.defval-text');

            function syncDefvalWidget() {
                var isBool = typeSelect.val() === 'bool';
                boolDefval.toggle(isBool).find('input, textarea').prop('disabled', !isBool);
                textDefval.toggle(!isBool).find('input, textarea').prop('disabled', isBool);
            }

            typeSelect.on('change', syncDefvalWidget);
            syncDefvalWidget();
JS);

        $currentType = \array_key_exists('type', $conffields) ? $conffields['type'] : '';
        $isBool = $currentType === 'bool';
        $isSecret = !empty($conffields['secret']);
        $defval = \array_key_exists('defval', $conffields) ? $conffields['defval'] : '';

        $metaPanel = new \Ease\TWB4\Panel(_('Field definition'), null, null, false);
        $metaPanel->addTagClass('conffields-group');
        $metaPanel->addItem(new \Ease\TWB4\FormGroup(_('Config field type'), new CfgFieldTypeSelect('type', $currentType)));
        $metaPanel->addItem(new \Ease\TWB4\FormGroup(_('Config field Keyword'), new InputTextTag('keyname', \array_key_exists('keyname', $conffields) ? $conffields['keyname'] : '')));
        $metaPanel->addItem(new \Ease\TWB4\FormGroup(_('Description'), new InputTextTag('description', \array_key_exists('description', $conffields) ? $conffields['description'] : '')));
        $metaPanel->addItem(new \Ease\TWB4\FormGroup(_('Hint'), new InputTextTag('hint', \array_key_exists('hint', $conffields) ? $conffields['hint'] : '')));
        $metaPanel->addItem(new \Ease\TWB4\FormGroup(_('Note'), new InputTextTag('note', \array_key_exists('note', $conffields) ? $conffields['note'] : '')));
        $this->addItem($metaPanel);

        // Both widgets are always rendered (server-side correct for the
        // current type on load/edit); JS above swaps which one is visible
        // and enabled when the type <select> changes, and disables the
        // inactive one so only one 'defval' value is ever submitted.
        $valuePanel = new \Ease\TWB4\Panel(_('Default value'), null, null, false);
        $valuePanel->addTagClass('conffields-group conffields-value-group');
        $valuePanel->addItem(new \Ease\Html\DivTag(
            new \Ease\TWB4\FormGroup(_('Default value'), BoolFieldWidget::toggle('defval', $defval, $isBool ? [] : ['disabled' => 'disabled'])),
            ['class' => 'defval-bool', 'style' => $isBool ? '' : 'display:none'],
        ));

        if ($isSecret) {
            $textDefvalInput = new \Ease\Html\InputTag('defval', '', ['type' => 'password', 'placeholder' => \MultiFlexi\ConfigField::maskValue($defval)]);
        } elseif (!empty($conffields['multiline'])) {
            $textDefvalInput = new \Ease\Html\TextareaTag('defval', $defval, ['rows' => 4]);
        } else {
            $textDefvalInput = new InputTextTag('defval', $defval);
        }

        if ($isBool) {
            $textDefvalInput->setTagProperties(['disabled' => 'disabled']);
        }

        $valuePanel->addItem(new \Ease\Html\DivTag(
            new \Ease\TWB4\FormGroup(_('Default value'), $textDefvalInput),
            ['class' => 'defval-text', 'style' => $isBool ? 'display:none' : ''],
        ));
        $this->addItem($valuePanel);

        $flagsPanel = new \Ease\TWB4\Panel(_('Field behavior'), null, null, false);
        $flagsPanel->addTagClass('conffields-group conffields-flags-group');

        $requiredToggle = new \Ease\TWB4\Widgets\Toggle('required');

        if (!empty($conffields['required'])) {
            $requiredToggle->setTagProperties(['checked' => 'checked']);
        }

        $flagsPanel->addItem(new \Ease\TWB4\FormGroup(_('Required'), $requiredToggle));

        $secretToggle = new \Ease\TWB4\Widgets\Toggle('secret');

        if ($isSecret) {
            $secretToggle->setTagProperties(['checked' => 'checked']);
        }

        $flagsPanel->addItem(new \Ease\TWB4\FormGroup(_('Secret'), $secretToggle));

        $multilineToggle = new \Ease\TWB4\Widgets\Toggle('multiline');

        if (!empty($conffields['multiline'])) {
            $multilineToggle->setTagProperties(['checked' => 'checked']);
        }

        $flagsPanel->addItem(new \Ease\TWB4\FormGroup(_('Multiline'), $multilineToggle));

        $expiringToggle = new \Ease\TWB4\Widgets\Toggle('expiring');

        if (!empty($conffields['expiring'])) {
            $expiringToggle->setTagProperties(['checked' => 'checked']);
        }

        $flagsPanel->addItem(new \Ease\TWB4\FormGroup(_('Expiring'), $expiringToggle));
        $this->addItem($flagsPanel);

        if (\array_key_exists('id', $conffields)) {
            $this->addItem(new InputHiddenTag('id', $conffields['id']));
            $this->addItem(new SubmitButton(_('Update'), 'success btn-block'));
        } else {
            $this->addItem(new SubmitButton(_('Add'), 'success btn-block'));
        }
    }
}
