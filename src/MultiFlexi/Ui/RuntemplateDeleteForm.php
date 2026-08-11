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

use Ease\Html\CheckboxTag;
use Ease\Html\DivTag;
use Ease\Html\InputHiddenTag;
use Ease\Html\LabelTag;
use Ease\TWB4\SubmitButton;

/**
 * Danger-zone "delete this RunTemplate" form.
 *
 * Requires the operator to tick a confirmation checkbox before the delete
 * button becomes clickable, as insurance against accidental deletion. The
 * same checkbox field is validated again server-side in runtemplate.php,
 * since an unchecked HTML checkbox simply isn't submitted at all.
 *
 * @author vitex
 *
 * @no-named-arguments
 */
class RuntemplateDeleteForm extends SecureForm
{
    public function __construct(\MultiFlexi\RunTemplate $runtemplate)
    {
        $runtemplateId = $runtemplate->getMyKey();

        parent::__construct(['method' => 'POST', 'action' => 'runtemplate.php?id='.$runtemplateId]);

        $this->addItem(new InputHiddenTag('action', 'delete'));

        // Built from raw CheckboxTag/LabelTag rather than Ease\TWB4\Checkbox:
        // the installed 1.12.0 declares `public CheckboxTag $checkbox = null;`
        // on a non-nullable typed property, which is a fatal PHP error the
        // moment the class is instantiated.
        $checkbox = new CheckboxTag('confirm_delete', false, 'yes', ['class' => 'form-check-input', 'id' => 'confirm_delete']);
        $label = new LabelTag('confirm_delete', sprintf(
            _('I understand this will permanently delete "%s", all its jobs, logs, artifacts, and settings. This cannot be undone.'),
            $runtemplate->getRecordName(),
        ), ['class' => 'form-check-label']);
        $this->addItem(new DivTag([$checkbox, $label], ['class' => 'form-check mb-3']));

        $submitId = 'delete_submit_'.$runtemplateId;
        $hintId = 'delete_hint_'.$runtemplateId;
        $hintChecked = _('Ready — clicking this button deletes the RunTemplate immediately.');
        $hintUnchecked = _('Check the box above to enable this button.');
        $deleteButton = new SubmitButton('🗑️ '._('Delete this RunTemplate'), 'danger', [
            'id' => $submitId,
            'disabled' => 'disabled',
            'title' => $hintUnchecked,
        ]);
        $this->addItem($deleteButton);
        $this->addItem(new \Ease\Html\SmallTag($hintUnchecked, ['id' => $hintId, 'class' => 'text-muted d-block mt-1']));

        $hintCheckedJs = json_encode($hintChecked, \JSON_THROW_ON_ERROR);
        $hintUncheckedJs = json_encode($hintUnchecked, \JSON_THROW_ON_ERROR);

        $this->addJavaScript(<<<EOD
            document.getElementById('confirm_delete').addEventListener('change', function () {
                var btn = document.getElementById('{$submitId}');
                var hint = document.getElementById('{$hintId}');
                var hintChecked = {$hintCheckedJs};
                var hintUnchecked = {$hintUncheckedJs};
                btn.disabled = !this.checked;
                btn.title = this.checked ? hintChecked : hintUnchecked;
                hint.textContent = this.checked ? hintChecked : hintUnchecked;
                hint.classList.toggle('text-danger', this.checked);
                hint.classList.toggle('text-muted', !this.checked);
            });
            EOD);
    }

    /**
     * Move items to element root.
     */
    public function finalize(): void
    {
        $contents = $this->formDiv->pageParts;
        $this->emptyContents();
        $this->pageParts = $contents;
        parent::finalize();
    }
}
