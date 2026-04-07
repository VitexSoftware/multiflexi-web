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
use Ease\Html\H2Tag;
use Ease\Html\InputHiddenTag;
use Ease\Html\LabelTag;
use Ease\Html\PTag;
use Ease\Html\SmallTag;
use Ease\TWB4\Card;
use Ease\TWB4\Row;
use Ease\TWB4\SubmitButton;

require_once './init.php';

WebPage::singleton()->onlyForLogged();

$companer = new \MultiFlexi\Company(\Ease\WebPage::getRequestValue('company_id', 'int'));

if (null === $companer->getMyKey()) {
    WebPage::singleton()->redirect('companies.php');
}

$companyApp = new \MultiFlexi\CompanyApp($companer);

// Handle form submission
if (\Ease\WebPage::isPosted()) {
    $selectedApps = \Ease\WebPage::getRequestValue('apps', 'array');
    $appIds = $selectedApps ? array_map('intval', $selectedApps) : [];
    $companyApp->assignApps($appIds);
    WebPage::singleton()->addStatusMessage(_('Applications updated successfully'), 'success');
}

WebPage::singleton()->addItem(new PageTop(_('Applications used by Company')));

// Get all applications and currently assigned ones with localized names and descriptions
$apper = new \MultiFlexi\Application();
$currentLang = substr(\Ease\Locale::$localeUsed ?? 'en_US', 0, 2);

$allApps = $apper->getFluentPDO()
    ->from('apps')
    ->select('apps.id, apps.name, apps.description, apps.uuid, apps.image, apps.tags')
    ->select('COALESCE(app_translations.name, apps.name) AS localized_name')
    ->select('COALESCE(app_translations.description, apps.description) AS localized_description')
    ->leftJoin('app_translations ON app_translations.app_id = apps.id AND app_translations.lang = ?', $currentLang)
    ->orderBy('COALESCE(app_translations.name, apps.name)')
    ->fetchAll();

$assignedRaw = $companyApp->getAssigned()->fetchAll('app_id');
$assigned = empty($assignedRaw) ? [] : array_keys($assignedRaw);

// Collect all unique tags from applications
$allTags = [];

foreach ($allApps as $app) {
    if (!empty($app['tags'])) {
        $tags = explode(',', $app['tags']);

        foreach ($tags as $tag) {
            $tag = trim($tag);

            if (!empty($tag) && !isset($allTags[$tag])) {
                $allTags[$tag] = ['id' => $tag, 'name' => $tag];
            }
        }
    }
}

// Sort tags alphabetically
ksort($allTags);
$allTags = array_values($allTags);

// Create container for filter controls (outside form to avoid CSRF issues)
$filterContainer = new DivTag();
$filterContainer->addItem(new H2Tag(sprintf(_('Choose Applications for %s'), $companer->getRecordName())));

// Include Selectize assets for tag filtering
if (!empty($allTags)) {
    WebPage::singleton()->includeJavaScript('js/selectize.min.js');
    WebPage::singleton()->includeCss('css/selectize.bootstrap4.css');
}

// Add tag filter using PillBox if tags are available
if (!empty($allTags)) {
    $filterContainer->addItem(new \Ease\Html\H4Tag(_('Filter by Tags')));
    $filterContainer->addItem(new PTag(_('Select tags to filter applications. All tags are selected by default to show all applications.')));

    $filterRow = new Row();

    // Pre-select all tags by default
    $allTagIds = array_column($allTags, 'id');
    $tagFilter = new PillBox('tag_filter', $allTags, $allTagIds, ['class' => 'form-control mb-3', 'placeholder' => _('Select tags to filter applications...')]);
    $filterRow->addColumn(10, $tagFilter);

    // Add reset filter button
    $resetButton = new \Ease\Html\ButtonTag(_('Reset Filter'), [
        'class' => 'btn btn-outline-secondary mb-3',
        'type' => 'button',
        'id' => 'reset-tag-filter',
        'title' => _('Select all tags to show all applications'),
    ]);
    $filterRow->addColumn(2, $resetButton);

    $filterContainer->addItem($filterRow);
}

// Add search box
$searchBox = new \Ease\Html\InputSearchTag('app_search', '', ['placeholder' => _('Search applications...'), 'class' => 'form-control form-control-lg mb-3']);
$filterContainer->addItem($searchBox);

// Show count of selected apps
$countDiv = new DivTag(
    new SmallTag(['<strong id="selected-count">'.\count($assigned).'</strong> ', _('applications selected')], ['class' => 'text-muted']),
    ['class' => 'mb-3'],
);
$filterContainer->addItem($countDiv);

// Create form with card grid
$addAppForm = new SecureForm();
$addAppForm->addItem(new InputHiddenTag('company_id', $companer->getMyKey()));

// Create cards grid
$cardsRow = new Row();

foreach ($allApps as $app) {
    $isAssigned = \in_array($app['id'], $assigned, true);

    // Add data-tags attribute for JavaScript filtering
    $tagsList = !empty($app['tags']) ? explode(',', $app['tags']) : [];
    $tagsDataAttr = implode(',', array_map('trim', $tagsList));

    $cardDiv = new DivTag(null, ['class' => 'col-md-4 col-lg-3 mb-3 app-card-wrapper', 'data-app-name' => strtolower($app['name']), 'data-app-desc' => strtolower($app['description'] ?? ''), 'data-tags' => $tagsDataAttr]);

    $card = new Card(
        null,
        ['class' => 'h-100 app-card '.($isAssigned ? 'border-primary' : ''), 'style' => $isAssigned ? 'background-color: #e7f3ff;' : ''],
    );

    $cardBody = new DivTag(null, ['class' => 'card-body']);

    // Checkbox at top
    $checkboxDiv = new DivTag(null, ['class' => 'form-check']);
    $checkbox = new CheckboxTag('apps[]', $isAssigned, (string) $app['id'], ['class' => 'form-check-input app-checkbox', 'id' => 'app_'.$app['id']]);
    $checkboxLabel = new LabelTag('app_'.$app['id'], '', ['class' => 'form-check-label']);
    $checkboxDiv->addItem($checkbox);
    $checkboxDiv->addItem($checkboxLabel);
    $cardBody->addItem($checkboxDiv);

    // App logo centered
    $logoDiv = new DivTag(null, ['class' => 'text-center my-3']);
    $appImage = empty($app['image']) ? 'appimage.php?uuid='.$app['uuid'] : $app['image'];
    $displayName = $app['localized_name'] ?? $app['name'];
    $logoDiv->addItem(new \Ease\Html\ImgTag($appImage, $displayName, ['style' => 'max-width: 80px; max-height: 80px;']));
    $cardBody->addItem($logoDiv);

    // App name (localized) with link to detail
    $nameWithLink = new \Ease\Html\H5Tag(null, ['class' => 'card-title text-center']);
    $nameWithLink->addItem(new \Ease\Html\ATag('app.php?id='.$app['id'], $displayName, ['class' => 'text-decoration-none app-detail-link', 'title' => _('View application details'), 'onclick' => 'event.stopPropagation();']));
    $cardBody->addItem($nameWithLink);

    // App description (localized)
    $displayDescription = $app['localized_description'] ?? $app['description'] ?? '';

    if (!empty($displayDescription)) {
        $desc = mb_strlen($displayDescription) > 100 ? mb_substr($displayDescription, 0, 97).'...' : $displayDescription;
        $cardBody->addItem(new PTag(new SmallTag($desc, ['class' => 'text-muted']), ['class' => 'card-text text-center']));
    }

    // Show tags as badges
    if (!empty($app['tags'])) {
        $tagBadges = new DivTag(null, ['class' => 'mb-2 tag-badges text-center']);

        foreach ($tagsList as $tag) {
            $tag = trim($tag);

            if (!empty($tag)) {
                $badge = new \Ease\TWB4\Badge('secondary', $tag, ['class' => 'mr-1 mb-1 tag-badge']);
                $tagBadges->addItem($badge);
            }
        }

        $cardBody->addItem($tagBadges);
    }

    $card->addItem($cardBody);
    $cardDiv->addItem($card);
    $cardsRow->addItem($cardDiv);
}

$addAppForm->addItem($cardsRow);

// Fixed submit button
$addAppForm->addItem(new \Ease\Html\HrTag());
$addAppForm->addItem(new SubmitButton('🍏 '._('Apply Changes'), 'success btn-lg btn-block', ['style' => 'position: sticky; bottom: 10px; z-index: 100;']));

// Create a container with filters and form
$contentContainer = new DivTag();
$contentContainer->addItem($filterContainer);
$contentContainer->addItem($addAppForm);

WebPage::singleton()->container->addItem(new CompanyPanel($companer, $contentContainer));

// Add CSS
WebPage::singleton()->addCSS(<<<'CSS'
.app-card {
    cursor: pointer;
    transition: all 0.2s;
}
.app-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}
.app-card-wrapper[data-hidden="true"] {
    display: none;
}
.app-checkbox {
    width: 20px;
    height: 20px;
    cursor: pointer;
}
.tag-badge {
    transition: all 0.3s ease-in-out;
    font-size: 0.75rem;
}
.app-detail-link {
    color: inherit;
}
.app-detail-link:hover {
    color: #007bff;
    text-decoration: underline !important;
}
CSS);

// Add JavaScript for interactivity
WebPage::singleton()->addJavaScript(<<<'JS'
$(document).ready(function() {
    // Click on card to toggle checkbox
    $('.app-card').click(function(e) {
        if (!$(e.target).is('input[type="checkbox"]')) {
            var checkbox = $(this).find('.app-checkbox');
            checkbox.prop('checked', !checkbox.prop('checked')).trigger('change');
        }
    });

    // Update card styling when checkbox changes
    $('.app-checkbox').change(function() {
        var card = $(this).closest('.app-card');
        if ($(this).is(':checked')) {
            card.addClass('border-primary').css('background-color', '#e7f3ff');
        } else {
            card.removeClass('border-primary').css('background-color', '');
        }
        updateCount();
    });

    // Search functionality
    $('#app_search').on('keyup', function() {
        var searchText = $(this).val().toLowerCase();
        $('.app-card-wrapper').each(function() {
            var appName = $(this).data('app-name');
            var appDesc = $(this).data('app-desc') || '';
            var isVisible = $(this).css('display') !== 'none';
            if (appName.includes(searchText) || appDesc.includes(searchText)) {
                $(this).attr('data-hidden', 'false').show();
            } else {
                $(this).attr('data-hidden', 'true').hide();
            }
        });
    });

    // Tag filtering functionality with localStorage support
    const STORAGE_KEY = 'multiflexi_companyapps_tag_filter';
    const DEFAULT_ALL_SELECTED = 'all_tags_selected';

    var tagFilterSelectize = null;
    var allAvailableTags = [];

    // Function to save tag selection to localStorage
    function saveTagSelection(selectedTags) {
        try {
            if (selectedTags.length === allAvailableTags.length) {
                localStorage.setItem(STORAGE_KEY, DEFAULT_ALL_SELECTED);
            } else if (selectedTags.length === 0) {
                localStorage.setItem(STORAGE_KEY, JSON.stringify([]));
            } else {
                localStorage.setItem(STORAGE_KEY, JSON.stringify(selectedTags));
            }
        } catch (e) {
            console.warn('Failed to save tag selection to localStorage:', e);
        }
    }

    // Function to load tag selection from localStorage
    function loadTagSelection() {
        try {
            const saved = localStorage.getItem(STORAGE_KEY);
            if (!saved || saved === DEFAULT_ALL_SELECTED) {
                return allAvailableTags.slice();
            }
            const parsed = JSON.parse(saved);
            if (Array.isArray(parsed)) {
                return parsed.filter(tag => allAvailableTags.includes(tag));
            }
            return allAvailableTags.slice();
        } catch (e) {
            console.warn('Failed to load tag selection to localStorage:', e);
            return allAvailableTags.slice();
        }
    }

    // Function to filter applications based on selected tags
    function filterApplicationsByTags(selectedTags) {
        var tagsArray = [];
        if (typeof selectedTags === 'string' && selectedTags.length > 0) {
            tagsArray = selectedTags.split(',');
        } else if (Array.isArray(selectedTags)) {
            tagsArray = selectedTags;
        }

        var visibleCount = 0;
        $('.app-card-wrapper').each(function() {
            var cardTags = $(this).attr('data-tags') || '';
            var cardTagsArray = cardTags.split(',').map(function(tag) {
                return tag.trim();
            }).filter(function(tag) {
                return tag.length > 0;
            });

            var shouldShow = true;

            // If no tags are selected, show ALL applications
            if (tagsArray.length === 0) {
                shouldShow = true;
            } else {
                // Show apps that have no tags or have at least one matching tag
                if (cardTagsArray.length === 0) {
                    shouldShow = true;
                } else {
                    shouldShow = false;
                    for (var i = 0; i < tagsArray.length; i++) {
                        if (cardTagsArray.indexOf(tagsArray[i]) !== -1) {
                            shouldShow = true;
                            break;
                        }
                    }
                }
            }

            if (shouldShow) {
                $(this).show();
                visibleCount++;
            } else {
                $(this).hide();
                var checkbox = $(this).find('.app-checkbox');
                if (checkbox.is(':checked')) {
                    checkbox.prop('checked', false).trigger('change');
                }
            }
        });

        highlightSelectedTags(tagsArray);
        updateCount();
    }

    // Function to highlight selected tags on application cards
    function highlightSelectedTags(selectedTags) {
        $('.tag-badge').each(function() {
            var tagText = $(this).text().trim();

            $(this).removeClass('badge-primary badge-warning badge-success badge-info').addClass('badge-secondary');
            $(this).css({
                'font-weight': 'normal',
                'box-shadow': 'none',
                'transform': 'scale(1)',
                'border': 'none'
            });

            if (selectedTags.includes(tagText)) {
                $(this).removeClass('badge-secondary').addClass('badge-primary');
                $(this).css({
                    'font-weight': 'bold',
                    'box-shadow': '0 2px 6px rgba(0,123,255,0.4)',
                    'transform': 'scale(1.1)',
                    'border': '2px solid #0056b3',
                    'background-color': '#007bff',
                    'color': '#ffffff'
                });
            }
        });
    }

    // Initialize tag filter selectize
    setTimeout(function() {
        var element = $('#tag_filterpillBox');
        if (element.length > 0 && element[0].selectize) {
            tagFilterSelectize = element[0].selectize;
            var options = tagFilterSelectize.options;
            allAvailableTags = Object.keys(options);

            var savedSelection = loadTagSelection();
            tagFilterSelectize.setValue(savedSelection, true);
            filterApplicationsByTags(savedSelection);

            tagFilterSelectize.on('change', function(value) {
                var selectedTags = Array.isArray(value) ? value : (value ? value.split(',') : []);
                saveTagSelection(selectedTags);
                filterApplicationsByTags(selectedTags);
            });

            $('#reset-tag-filter').on('click', function() {
                tagFilterSelectize.setValue(allAvailableTags, true);
                saveTagSelection(allAvailableTags);
                filterApplicationsByTags(allAvailableTags);
            });
        } else {
            setTimeout(arguments.callee, 500);
        }
    }, 1000);

    // Update selected count
    function updateCount() {
        var count = $('.app-checkbox:checked').length;
        $('#selected-count').text(count);
    }
});
JS);

WebPage::singleton()->addItem(new PageBottom());
WebPage::singleton()->draw();
