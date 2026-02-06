<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Form\Element;

use Wazum\Sluggi\Service\SlugConfigurationService;
use Wazum\Sluggi\Service\SlugSourceBadgeRenderer;
use Wazum\Sluggi\Service\SlugSyncService;

trait SlugSourceElementTrait
{
    protected SlugSourceBadgeRenderer $badgeRenderer;
    protected SlugConfigurationService $slugConfigurationService;
    protected SlugSyncService $slugSyncService;

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
            totalFields: count($fieldMetadata)
        );
        $confirmButton = $this->badgeRenderer->renderConfirmButton();
        $result['html'] = $this->badgeRenderer->insertBadgeIntoHtml($result['html'], $badge, $confirmButton, hidden: $hidden);

        return $result;
    }
}
