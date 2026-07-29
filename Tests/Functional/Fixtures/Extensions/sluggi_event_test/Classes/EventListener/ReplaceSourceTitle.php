<?php

declare(strict_types=1);

namespace Wazum\SluggiEventTest\EventListener;

use Wazum\Sluggi\Event\ModifySlugGenerationSourceFieldsEvent;

final readonly class ReplaceSourceTitle
{
    public function __invoke(ModifySlugGenerationSourceFieldsEvent $event): void
    {
        if ($event->getTableName() !== 'pages') {
            return;
        }

        $event->setSourceFieldValues(['title' => 'Replaced by the listener']);
    }
}
