<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Unit\DataHandler;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\DataHandling\Model\CorrelationId;
use TYPO3\CMS\Redirects\Service\SlugService;
use Wazum\Sluggi\DataHandler\BypassAccessCheckForRedirectCreation;

final class BypassAccessCheckForRedirectCreationTest extends TestCase
{
    #[Test]
    public function setsBypassForRedirectOnlyDatamap(): void
    {
        $subject = new BypassAccessCheckForRedirectCreation();

        $dataHandler = $this->createMock(DataHandler::class);
        $dataHandler->datamap = ['sys_redirect' => ['NEW123' => ['source_path' => '/old']]];
        $correlationId = CorrelationId::forScope('core/cli')
            ->withAspects(SlugService::CORRELATION_ID_IDENTIFIER, 'redirect');
        $dataHandler->method('getCorrelationId')->willReturn($correlationId);

        $subject->processDatamap_beforeStart($dataHandler);

        self::assertTrue($dataHandler->bypassAccessCheckForRecords);
    }

    #[Test]
    public function doesNotSetBypassWhenDatamapContainsOtherTables(): void
    {
        $subject = new BypassAccessCheckForRedirectCreation();

        $dataHandler = $this->createMock(DataHandler::class);
        $dataHandler->datamap = [
            'sys_redirect' => ['NEW123' => ['source_path' => '/old']],
            'pages' => [1 => ['title' => 'Injected']],
        ];
        $correlationId = CorrelationId::forScope('core/cli')
            ->withAspects(SlugService::CORRELATION_ID_IDENTIFIER, 'redirect');
        $dataHandler->method('getCorrelationId')->willReturn($correlationId);

        $subject->processDatamap_beforeStart($dataHandler);

        self::assertFalse($dataHandler->bypassAccessCheckForRecords);
    }

    #[Test]
    public function doesNotSetBypassWithoutCorrelation(): void
    {
        $subject = new BypassAccessCheckForRedirectCreation();

        $dataHandler = $this->createMock(DataHandler::class);
        $dataHandler->datamap = ['pages' => [1 => ['slug' => '/new']]];
        $dataHandler->method('getCorrelationId')->willReturn(null);

        $subject->processDatamap_beforeStart($dataHandler);

        self::assertFalse($dataHandler->bypassAccessCheckForRecords);
    }

    #[Test]
    public function doesNotSetBypassForSlugCorrelationWithoutRedirectAspect(): void
    {
        $subject = new BypassAccessCheckForRedirectCreation();

        $dataHandler = $this->createMock(DataHandler::class);
        $dataHandler->datamap = ['sys_redirect' => ['NEW123' => ['source_path' => '/old']]];
        $correlationId = CorrelationId::forScope('core/cli')
            ->withAspects(SlugService::CORRELATION_ID_IDENTIFIER, 'slug');
        $dataHandler->method('getCorrelationId')->willReturn($correlationId);

        $subject->processDatamap_beforeStart($dataHandler);

        self::assertFalse($dataHandler->bypassAccessCheckForRecords);
    }
}
