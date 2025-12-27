<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Form\Element;

use TYPO3\CMS\Backend\Form\Element\InputTextElement;
use TYPO3\CMS\Core\Information\Typo3Version;
use Wazum\Sluggi\Service\SlugConfigurationService;
use Wazum\Sluggi\Service\SlugSourceBadgeRenderer;
use Wazum\Sluggi\Service\SlugSyncService;

if ((new Typo3Version())->getMajorVersion() < 13) {
    return;
}

/**
 * SlugSourceElement for TYPO3 13+ (uses DI injection).
 */
final class SlugSourceElementV14 extends InputTextElement
{
    use SlugSourceElementTrait;

    public function __construct(
        SlugSourceBadgeRenderer $badgeRenderer,
        SlugConfigurationService $slugConfigurationService,
        SlugSyncService $slugSyncService,
    ) {
        $this->badgeRenderer = $badgeRenderer;
        $this->slugConfigurationService = $slugConfigurationService;
        $this->slugSyncService = $slugSyncService;
    }
}
