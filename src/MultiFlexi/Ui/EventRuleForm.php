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
 * Form for editing an EventRule (event-to-RunTemplate mapping).
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 *
 * @no-named-arguments
 */
class EventRuleForm extends SecureForm
{
    /**
     * @param \MultiFlexi\EventRule $eventRule      EventRule object
     * @param array                 $formProperties Additional form properties
     */
    public function __construct(\MultiFlexi\EventRule $eventRule, array $formProperties = [])
    {
        $eventSourceId = (int) ($eventRule->getDataValue('event_source_id') ?: 0);
        $runtemplateSourceId = (int) ($eventRule->getDataValue('runtemplate_source_id') ?: 0);
        $runtemplateId = (int) ($eventRule->getDataValue('runtemplate_id') ?: 0);

        $intro = new \Ease\Html\DivTag([
            new \Ease\Html\PTag(_('Map an external event or a completed job to a RunTemplate. Set either an Event Source or a Source RunTemplate (job chain), then choose the target RunTemplate to launch.')),
            new \Ease\TWB4\LinkButton('eventrules.php', '📋 '._('Back to Event Rules'), 'secondary btn-sm'),
        ], ['class' => 'mb-3']);

        $triggerHint = new \Ease\Html\SmallTag(
            _('Leave Event Source empty when this rule is a job-chain binding triggered by Source RunTemplate completion.'),
            ['class' => 'form-text text-muted'],
        );

        $rowTrigger = new \Ease\TWB4\Row();
        $rowTrigger->addColumn(6, [
            new \Ease\TWB4\FormGroup(_('Event Source'), new EventSourceSelect('event_source_id', $eventSourceId ?: null)),
            $triggerHint,
        ]);
        $rowTrigger->addColumn(6, new \Ease\TWB4\FormGroup(
            _('Source RunTemplate (job chain)'),
            new RunTemplateSelect(
                'runtemplate_source_id',
                $runtemplateSourceId ?: null,
                true,
                _('None (external event source)'),
            ),
        ));

        $rowMatch = new \Ease\TWB4\Row();
        $rowMatch->addColumn(6, new \Ease\TWB4\FormGroup(
            _('Evidence Pattern'),
            new \Ease\Html\InputTextTag(
                'evidence',
                $eventRule->getDataValue('evidence'),
                ['placeholder' => 'faktura-vydana', 'title' => _('Evidence name filter, or leave empty for any')],
            ),
        ));
        $rowMatch->addColumn(6, new \Ease\TWB4\FormGroup(
            _('Operation'),
            new OperationSelect('operation', $eventRule->getDataValue('operation') ?: 'any'),
        ));

        $rowAction = new \Ease\TWB4\Row();
        $rowAction->addColumn(6, new \Ease\TWB4\FormGroup(
            _('Target RunTemplate'),
            new RunTemplateSelect('runtemplate_id', $runtemplateId ?: null),
        ));
        $rowAction->addColumn(3, new \Ease\TWB4\FormGroup(
            _('Priority'),
            new \Ease\Html\InputNumberTag('priority', (string) ($eventRule->getDataValue('priority') ?: '0')),
        ));
        $rowAction->addColumn(3, new \Ease\TWB4\FormGroup(
            _('Enabled'),
            new \Ease\Html\SelectTag('enabled', ['0' => _('No'), '1' => _('Yes')], (string) ($eventRule->getDataValue('enabled') ?? '1')),
        ));

        $envHelp = new \Ease\Html\SmallTag(
            _('JSON object: keys are environment variable names for the target job; values are field selectors from the event/produces payload (e.g. "firma", "$.customer.id", or "@file:invoices").'),
            ['class' => 'form-text text-muted'],
        );

        $envDefault = $eventRule->getDataValue('env_mapping');

        if ($envDefault === null || $envDefault === '') {
            $envDefault = "{\n  \"RECORD_ID\": \"recordid\",\n  \"EVIDENCE\": \"evidence\",\n  \"OPERATION\": \"operation\"\n}";
        }

        $rowEnv = new \Ease\TWB4\Row();
        $rowEnv->addColumn(12, [
            new \Ease\TWB4\FormGroup(
                _('Environment Variable Mapping (JSON)'),
                new \Ease\Html\TextareaTag('env_mapping', $envDefault, ['rows' => '6', 'class' => 'form-control', 'style' => 'font-family: monospace;']),
            ),
            $envHelp,
        ]);

        $triggerPanel = new \Ease\TWB4\Panel(_('Trigger'), 'primary', [$rowTrigger]);
        $matchPanel = new \Ease\TWB4\Panel(_('Match criteria'), 'info', [$rowMatch]);
        $actionPanel = new \Ease\TWB4\Panel(_('Action'), 'success', [$rowAction]);
        $envPanel = new \Ease\TWB4\Panel(_('Environment overrides'), 'secondary', [$rowEnv]);

        $formContents = [$intro, $triggerPanel, $matchPanel, $actionPanel, $envPanel];

        parent::__construct(['action' => 'eventrule.php', 'method' => 'POST'], $formContents);

        $submitRow = new \Ease\TWB4\Row();

        $submitRow->addColumn(10, new \Ease\TWB4\SubmitButton('🍏 '._('Apply'), 'primary btn-lg btn-block', ['title' => _('Apply changes')]));

        if (null === $eventRule->getMyKey()) {
            $submitRow->addColumn(2, new \Ease\TWB4\SubmitButton('⚰️ '._('Remove').' !', 'disabled btn-lg btn-block', ['disabled' => 'true']));
        } else {
            $this->addItem(new \Ease\Html\InputHiddenTag('id', $eventRule->getMyKey()));

            if (WebPage::getRequestValue('remove') === 'true') {
                $submitRow->addColumn(2, new \Ease\TWB4\LinkButton('eventrule.php?delete='.$eventRule->getMyKey(), '⚰️ '._('Remove').' !', 'danger btn-lg btn-block'));
            } else {
                $submitRow->addColumn(2, new \Ease\TWB4\LinkButton('eventrule.php?id='.$eventRule->getMyKey().'&remove=true', '⚰️ '._('Remove').' ?', 'warning btn-lg btn-block'));
            }
        }

        $this->addItem($submitRow);
    }

    public function afterAdd(): void
    {
    }
}
