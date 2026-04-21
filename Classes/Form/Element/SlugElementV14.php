<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Form\Element;

use TYPO3\CMS\Backend\Form\Element\AbstractFormElement;
use TYPO3\CMS\Core\Information\Typo3Version;
use Wazum\Sluggi\Compatibility\Typo3Compatibility;
use Wazum\Sluggi\Configuration\ExtensionConfiguration;
use Wazum\Sluggi\Service\FullPathEditingService;
use Wazum\Sluggi\Service\HierarchyPermissionService;
use Wazum\Sluggi\Service\LastSegmentValidationService;
use Wazum\Sluggi\Service\RedirectInfoService;
use Wazum\Sluggi\Service\ReservedPathService;
use Wazum\Sluggi\Service\SlugConfigurationService;
use Wazum\Sluggi\Service\SlugElementRenderer;
use Wazum\Sluggi\Service\SlugGeneratorService;
use Wazum\Sluggi\Service\SlugLockService;
use Wazum\Sluggi\Service\SlugSyncService;
use Wazum\Sluggi\Service\UserSettingsService;

if ((new Typo3Version())->getMajorVersion() < 13) {
    return;
}

/**
 * SlugElement for TYPO3 13+ (uses DI injection).
 */
final class SlugElementV14 extends AbstractFormElement
{
    use SlugElementTrait;

    /**
     * @var array<string, array<string, mixed>>
     */
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
        SlugElementRenderer $slugElementRenderer,
        SlugSyncService $slugSyncService,
        SlugLockService $slugLockService,
        SlugConfigurationService $slugConfigurationService,
        LastSegmentValidationService $lastSegmentValidationService,
        HierarchyPermissionService $hierarchyPermissionService,
        SlugGeneratorService $slugGeneratorService,
        FullPathEditingService $fullPathEditingService,
        ExtensionConfiguration $extensionConfiguration,
        UserSettingsService $userSettingsService,
        RedirectInfoService $redirectInfoService,
        ReservedPathService $reservedPathService,
    ) {
        $this->slugElementRenderer = $slugElementRenderer;
        $this->slugSyncService = $slugSyncService;
        $this->slugLockService = $slugLockService;
        $this->slugConfigurationService = $slugConfigurationService;
        $this->lastSegmentValidationService = $lastSegmentValidationService;
        $this->hierarchyPermissionService = $hierarchyPermissionService;
        $this->slugGeneratorService = $slugGeneratorService;
        $this->fullPathEditingService = $fullPathEditingService;
        $this->extensionConfiguration = $extensionConfiguration;
        $this->userSettingsService = $userSettingsService;
        $this->redirectInfoService = $redirectInfoService;
        $this->reservedPathService = $reservedPathService;

        $this->defaultFieldInformation = Typo3Compatibility::getFormElementFieldInformation();
    }
}
