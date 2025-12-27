<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Form\Element;

use TYPO3\CMS\Backend\Form\Element\InputTextElement;
use TYPO3\CMS\Backend\Form\NodeFactory;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Wazum\Sluggi\Service\SlugConfigurationService;
use Wazum\Sluggi\Service\SlugSourceBadgeRenderer;
use Wazum\Sluggi\Service\SlugSyncService;

if ((new Typo3Version())->getMajorVersion() >= 13) {
    return;
}

/**
 * SlugSourceElement for TYPO3 12 (constructor receives NodeFactory).
 *
 * @deprecated Remove this class when dropping TYPO3 12 support
 */
final class SlugSourceElementV12 extends InputTextElement
{
    use SlugSourceElementTrait;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(?NodeFactory $nodeFactory = null, array $data = [])
    {
        // @phpstan-ignore staticMethod.notFound (TYPO3 12 constructor signature)
        parent::__construct($nodeFactory, $data);

        $this->badgeRenderer = GeneralUtility::makeInstance(SlugSourceBadgeRenderer::class);
        $this->slugConfigurationService = GeneralUtility::makeInstance(SlugConfigurationService::class);
        $this->slugSyncService = GeneralUtility::makeInstance(SlugSyncService::class);
    }
}
