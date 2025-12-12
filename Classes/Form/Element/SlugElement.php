<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Form\Element;

use TYPO3\CMS\Backend\Controller\FormSlugAjaxController;
use TYPO3\CMS\Backend\Form\Element\AbstractFormElement;
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Utility\MathUtility;
use Wazum\Sluggi\Service\LastSegmentValidationService;
use Wazum\Sluggi\Service\SlugConfigurationService;
use Wazum\Sluggi\Service\SlugElementRenderer;
use Wazum\Sluggi\Service\SlugSyncService;

final class SlugElement extends AbstractFormElement
{
    /** @var array<string, array<string, string>> */
    protected $defaultFieldInformation = [
        'tcaDescription' => [
            'renderType' => 'tcaDescription',
        ],
    ];

    /** @var array<string, array<string, mixed>> */
    protected $defaultFieldWizard = [
        'localizationStateSelector' => [
            'renderType' => 'localizationStateSelector',
        ],
        'otherLanguageContent' => [
            'renderType' => 'otherLanguageContent',
            'after' => ['localizationStateSelector'],
        ],
        'defaultLanguageDifferences' => [
            'renderType' => 'defaultLanguageDifferences',
            'after' => ['otherLanguageContent'],
        ],
    ];

    public function __construct(
        private readonly HashService $hashService,
        private readonly SlugElementRenderer $slugElementRenderer,
        private readonly SlugSyncService $slugSyncService,
        private readonly SlugConfigurationService $slugConfigurationService,
        private readonly LastSegmentValidationService $lastSegmentValidationService,
    ) {
    }

    /** @return array<string, mixed> */
    public function render(): array
    {
        $parameterArray = $this->data['parameterArray'];
        $context = $this->buildRenderContext($parameterArray);

        $resultArray = $this->initializeResultArray();

        $fieldInformationResult = $this->renderFieldInformation();
        $resultArray = $this->mergeChildReturnIntoExistingResult($resultArray, $fieldInformationResult, false);

        $fieldWizardResult = $this->renderFieldWizard();
        $resultArray = $this->mergeChildReturnIntoExistingResult($resultArray, $fieldWizardResult, false);

        $html = $this->buildSlugElementHtml(
            (string)($fieldInformationResult['html'] ?? ''),
            (string)($fieldWizardResult['html'] ?? ''),
            $this->buildSlugAttributeString($context),
            $context
        );

        $resultArray['html'] = $this->wrapWithFieldsetAndLegend($html, $context['slugPrefix']);
        $resultArray['javaScriptModules'][] = JavaScriptModuleInstruction::create('@wazum/sluggi/sluggi-element.js');

        return $resultArray;
    }

    /**
     * @param array<string, mixed> $parameterArray
     *
     * @return array<string, mixed>
     */
    private function buildRenderContext(array $parameterArray): array
    {
        $table = (string)$this->data['tableName'];
        $row = $this->data['databaseRow'];
        $fieldName = (string)$this->data['fieldName'];
        $config = $parameterArray['fieldConf']['config'];

        $languageId = $this->getLanguageId($table, $row);
        $recordId = $row['uid'] ?? 0;
        $parentPageId = (int)($this->data['parentPageRow']['uid'] ?? 0);
        $command = (string)$this->data['command'];
        $effectivePid = (int)$this->data['effectivePid'];

        $size = MathUtility::forceIntegerInRange(
            $config['size'] ?? $this->defaultInputWidth,
            $this->minimumInputWidth,
            $this->maxInputWidth
        );

        $itemValue = (string)$parameterArray['itemFormElValue'];
        $generatorFields = $config['generatorOptions']['fields'] ?? [];
        $includeUid = $this->hasUidInGeneratorFields($generatorFields);
        $hasPostModifiers = !empty($config['generatorOptions']['postModifiers']);

        $syncFeatureEnabled = $this->slugSyncService->isSyncFeatureEnabled();
        $requiredSourceFields = $this->slugConfigurationService->getRequiredSourceFields($table);
        $lastSegmentOnly = $this->lastSegmentValidationService->shouldRestrictUser(
            $this->getBackendUser()->isAdmin()
        );

        return [
            'table' => $table,
            'fieldName' => $fieldName,
            'languageId' => $languageId,
            'recordId' => $recordId,
            'parentPageId' => $parentPageId,
            'command' => $command,
            'effectivePid' => $effectivePid,
            'size' => $size,
            'isLocked' => (bool)($config['readOnly'] ?? false),
            'signature' => $this->generateSignature($table, $effectivePid, $recordId, $languageId, $fieldName, $command, $parentPageId),
            'slugPrefix' => $this->data['customData'][$fieldName]['slugPrefix'] ?? '',
            'itemName' => (string)$parameterArray['itemFormElName'],
            'itemValue' => $itemValue,
            'decodedValue' => rawurldecode($itemValue),
            'fallbackCharacter' => (string)($config['fallbackCharacter'] ?? '-'),
            'prependSlash' => (bool)($config['prependSlash'] ?? true),
            'includeUid' => $includeUid,
            'hasPostModifiers' => $hasPostModifiers,
            'syncFeatureEnabled' => $syncFeatureEnabled,
            'isSynced' => $syncFeatureEnabled && $this->slugSyncService->shouldSync($row),
            'syncFieldName' => $this->slugElementRenderer->buildSyncFieldName($table, $recordId),
            'requiredSourceFields' => $requiredSourceFields,
            'lastSegmentOnly' => $lastSegmentOnly,
        ];
    }

    /** @param array<int, string|array<int, string>> $fields */
    private function hasUidInGeneratorFields(array $fields): bool
    {
        foreach ($fields as $field) {
            if (is_array($field) && in_array('uid', $field, true)) {
                return true;
            }
            if ($field === 'uid') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function buildSlugElementHtml(string $fieldInformationHtml, string $fieldWizardHtml, string $attributeString, array $context): string
    {
        return sprintf(
            '<div class="formengine-field-item t3js-formengine-field-item">
                %s
                <div class="form-control-wrap" style="max-width: %dpx">
                    <div class="form-wizards-wrap">
                        <div class="form-wizards-item-element">
                            <sluggi-element%s></sluggi-element>
                            <input type="hidden"
                                class="sluggi-hidden-field"
                                name="%s"
                                value="%s"
                            />
                            <input type="hidden"
                                class="sluggi-sync-field"
                                name="%s"
                                value="%s"
                            />
                        </div>
                        <div class="form-wizards-item-bottom">%s</div>
                    </div>
                </div>
            </div>',
            $fieldInformationHtml,
            $this->formMaxWidth($context['size']),
            $attributeString,
            htmlspecialchars($context['itemName']),
            htmlspecialchars($context['itemValue']),
            htmlspecialchars($context['syncFieldName']),
            $context['isSynced'] ? '1' : '0',
            $fieldWizardHtml
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    private function buildSlugAttributeString(array $context): string
    {
        $attributes = $this->slugElementRenderer->buildAttributes($context, $this->getLabels());
        $attributeString = $this->slugElementRenderer->buildAttributeString($attributes);

        return $context['isLocked'] ? $attributeString . ' is-locked' : $attributeString;
    }

    /** @return array<string, string> */
    private function getLabels(): array
    {
        $languageService = $this->getLanguageService();

        return [
            'conflict.title' => $languageService->sL('LLL:EXT:sluggi/Resources/Private/Language/locallang.xlf:conflict.title'),
            'conflict.message' => $languageService->sL('LLL:EXT:sluggi/Resources/Private/Language/locallang.xlf:conflict.message'),
            'conflict.suggestion' => $languageService->sL('LLL:EXT:sluggi/Resources/Private/Language/locallang.xlf:conflict.suggestion'),
            'conflict.button.cancel' => $languageService->sL('LLL:EXT:sluggi/Resources/Private/Language/locallang.xlf:conflict.button.cancel'),
            'conflict.button.useSuggestion' => $languageService->sL('LLL:EXT:sluggi/Resources/Private/Language/locallang.xlf:conflict.button.useSuggestion'),
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function getLanguageId(string $table, array $row): int
    {
        $languageField = $GLOBALS['TCA'][$table]['ctrl']['languageField'] ?? '';
        if ($languageField === '') {
            return 0;
        }

        $value = $row[$languageField] ?? 0;

        return (int)(is_array($value) ? ($value[0] ?? 0) : $value);
    }

    private function generateSignature(
        string $table,
        int $effectivePid,
        int|string $recordId,
        int $languageId,
        string $fieldName,
        string $command,
        int $parentPageId,
    ): string {
        return $this->hashService->hmac(
            $table . $effectivePid . $recordId . $languageId . $fieldName . $command . $parentPageId,
            FormSlugAjaxController::class
        );
    }

    protected function wrapWithFieldsetAndLegend(string $innerHTML, string $baseUrl = ''): string
    {
        $chainIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px; margin-right: 4px; color: #737373;"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>';

        $legend = htmlspecialchars($this->data['parameterArray']['fieldConf']['label'] ?? '');
        if ($this->getBackendUser()->shallDisplayDebugInformation()) {
            $fieldName = $this->data['flexFormContainerFieldName'] ?? $this->data['flexFormFieldName'] ?? $this->data['fieldName'];
            $legend .= ' <code>[' . htmlspecialchars($fieldName) . ']</code>';
        }
        if ($baseUrl !== '') {
            $legend .= ' <span style="font-weight: normal;">(' . htmlspecialchars($baseUrl) . ')</span>';
        }

        return '<fieldset>'
            . '<legend class="form-label t3js-formengine-label">' . $chainIcon . $legend . '</legend>'
            . $innerHTML
            . '</fieldset>';
    }
}
