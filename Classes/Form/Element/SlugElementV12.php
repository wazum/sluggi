<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Form\Element;

use TYPO3\CMS\Backend\Form\Element\AbstractFormElement;
use TYPO3\CMS\Backend\Form\NodeFactory;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration as CoreExtensionConfiguration;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Wazum\Sluggi\Configuration\ExtensionConfiguration;
use Wazum\Sluggi\Service\FullPathEditingService;
use Wazum\Sluggi\Service\HierarchyPermissionService;
use Wazum\Sluggi\Service\LastSegmentValidationService;
use Wazum\Sluggi\Service\SlugConfigurationService;
use Wazum\Sluggi\Service\SlugElementRenderer;
use Wazum\Sluggi\Service\SlugGeneratorService;
use Wazum\Sluggi\Service\SlugLockService;
use Wazum\Sluggi\Service\SlugSyncService;

if ((new Typo3Version())->getMajorVersion() >= 13) {
    return;
}

/**
 * SlugElement for TYPO3 12 (constructor receives NodeFactory).
 *
 * @deprecated Remove this class when dropping TYPO3 12 support
 */
final class SlugElementV12 extends AbstractFormElement
{
    use SlugElementTrait;

    /**
     * @var array<string, array<string, string>>
     */
    protected $defaultFieldInformation = [
        'tcaDescription' => [
            'renderType' => 'tcaDescription',
        ],
    ];

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

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(?NodeFactory $nodeFactory = null, array $data = [])
    {
        // @phpstan-ignore staticMethod.notFound (TYPO3 12 constructor signature)
        parent::__construct($nodeFactory, $data);

        $this->slugElementRenderer = GeneralUtility::makeInstance(SlugElementRenderer::class);
        $this->slugSyncService = GeneralUtility::makeInstance(SlugSyncService::class);
        $this->slugLockService = GeneralUtility::makeInstance(SlugLockService::class);
        $this->slugConfigurationService = GeneralUtility::makeInstance(SlugConfigurationService::class);
        $this->lastSegmentValidationService = GeneralUtility::makeInstance(LastSegmentValidationService::class);
        $this->hierarchyPermissionService = GeneralUtility::makeInstance(HierarchyPermissionService::class);
        $this->slugGeneratorService = GeneralUtility::makeInstance(SlugGeneratorService::class);
        $this->fullPathEditingService = GeneralUtility::makeInstance(FullPathEditingService::class);
        $this->extensionConfiguration = GeneralUtility::makeInstance(
            ExtensionConfiguration::class,
            GeneralUtility::makeInstance(CoreExtensionConfiguration::class)
        );
    }
}
