<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Service;

use Psr\EventDispatcher\EventDispatcherInterface;
use Wazum\Sluggi\Event\ModifySlugGenerationSourceFieldsEvent;

/**
 * @internal
 */
final readonly class SlugGenerationRecordResolver
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private SlugConfigurationService $slugConfigurationService,
    ) {
    }

    /**
     * @param array<string, mixed> $record
     * @param array<string, mixed> $configuration
     *
     * @return array<string, mixed>
     */
    public function resolve(
        array $record,
        string $tableName,
        string $fieldName,
        int $pid,
        int $workspaceId,
        array $configuration,
    ): array {
        $sourceFields = $this->slugConfigurationService->getSourceFieldsFromFieldsConfig(
            $configuration['generatorOptions']['fields'] ?? []
        );

        $event = new ModifySlugGenerationSourceFieldsEvent(
            $record,
            $tableName,
            $fieldName,
            $pid,
            $workspaceId,
            $configuration,
            array_intersect_key($record, array_flip($sourceFields)),
        );
        $this->eventDispatcher->dispatch($event);

        return [...$record, ...$event->getSourceFieldValues()];
    }
}
