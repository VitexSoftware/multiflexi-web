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
use Ease\TWB4\Checkbox;
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

        // Ease\TWB4\Checkbox forces the checkbox tag's id to equal its $name,
        // so the confirmation checkbox always renders as id="confirm_delete"
        // regardless of any 'id' passed in properties — this page only ever
        // shows a single RunTemplate at a time, so that fixed id is safe here.
        $this->addItem(new Checkbox(
            'confirm_delete',
            'yes',
            sprintf(
                _('I understand this will permanently delete "%s", all its jobs, logs, artifacts, and settings. This cannot be undone.'),
                $runtemplate->getRecordName(),
            ),
            false,
        ));

        $submitId = 'delete_submit_'.$runtemplateId;
        $deleteButton = new SubmitButton('🗑️ '._('Delete this RunTemplate'), 'danger', [
            'id' => $submitId,
            'disabled' => 'disabled',
        ]);
        $this->addItem($deleteButton);

        $this->addJavaScript(<<<EOD
            document.getElementById('confirm_delete').addEventListener('change', function () {
                document.getElementById('{$submitId}').disabled = !this.checked;
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
