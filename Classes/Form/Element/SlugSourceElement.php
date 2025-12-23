<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Form\Element;

use TYPO3\CMS\Backend\Form\Element\InputTextElement;
use Wazum\Sluggi\Service\SlugConfigurationService;
use Wazum\Sluggi\Service\SlugSourceBadgeRenderer;
use Wazum\Sluggi\Service\SlugSyncService;

final class SlugSourceElement extends InputTextElement
{
    public function __construct(
        private readonly SlugSourceBadgeRenderer $badgeRenderer,
        private readonly SlugConfigurationService $slugConfigurationService,
        private readonly SlugSyncService $slugSyncService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function render(): array
    {
        $result = parent::render();

        $fieldName = $this->data['fieldName'];
        $tableName = $this->data['tableName'];
        $fieldMetadata = $this->slugConfigurationService->getFieldMetadata($tableName);

        if (!isset($fieldMetadata[$fieldName])) {
            return $result;
        }

        $result['html'] = $this->badgeRenderer->markAsSourceField($result['html']);

        $command = (string)($this->data['command'] ?? 'edit');
        $record = $this->data['databaseRow'] ?? [];
        $shouldShow = $this->slugSyncService->shouldShowSourceBadge($command, $record);

        $hidden = !$shouldShow;
        $badge = $this->badgeRenderer->renderBadgeWithMetadata(
            $fieldMetadata[$fieldName],
            totalFields: count($fieldMetadata),
            hidden: $hidden
        );
        $result['html'] = $this->badgeRenderer->insertBadgeIntoHtml($result['html'], $badge, hidden: $hidden);

        return $result;
    }
}
