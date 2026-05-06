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
        $sourceMetadata = $this->data['parameterArray']['fieldConf']['config']['sluggiSourceMetadata'] ?? $fieldMetadata[$fieldName] ?? null;
        $totalFields = (int)($this->data['parameterArray']['fieldConf']['config']['sluggiSourceTotalFields'] ?? count($fieldMetadata));

        if (!is_array($sourceMetadata)
            || !isset($sourceMetadata['slot'], $sourceMetadata['role'], $sourceMetadata['chainSize'])
            || !is_int($sourceMetadata['slot'])
            || !is_string($sourceMetadata['role'])
            || !is_int($sourceMetadata['chainSize'])
        ) {
            return $result;
        }

        $result['html'] = $this->badgeRenderer->markAsSourceField($result['html']);

        $command = (string)($this->data['command'] ?? 'edit');
        $record = $this->data['databaseRow'] ?? [];
        $shouldShow = $this->slugSyncService->shouldShowSourceBadge($tableName, $command, $record);

        $hidden = !$shouldShow;
        $badge = $this->badgeRenderer->renderBadgeWithMetadata(
            $sourceMetadata,
            totalFields: $totalFields
        );
        $confirmButton = $this->badgeRenderer->renderConfirmButton();
        $result['html'] = $this->badgeRenderer->insertBadgeIntoHtml($result['html'], $badge, $confirmButton, hidden: $hidden);

        return $result;
    }
}
