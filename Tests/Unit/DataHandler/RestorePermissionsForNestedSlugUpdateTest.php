<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Unit\DataHandler;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\DataHandling\Model\CorrelationId;
use TYPO3\CMS\Redirects\Service\SlugService;
use Wazum\Sluggi\DataHandler\RestorePermissionsForNestedSlugUpdate;

final class RestorePermissionsForNestedSlugUpdateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['BE_USER'] = new stdClass();
        $GLOBALS['BE_USER']->groupData = ['tables_modify' => 'pages,tt_content,sys_redirect'];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['BE_USER']);
        parent::tearDown();
    }

    #[Test]
    public function restoresCorruptedPermissionsForNestedSlugUpdate(): void
    {
        $subject = new RestorePermissionsForNestedSlugUpdate();

        $topLevel = $this->createMock(DataHandler::class);
        $topLevel->method('getCorrelationId')->willReturn(null);
        $subject->processDatamap_beforeStart($topLevel);

        $GLOBALS['BE_USER']->groupData['tables_modify'] = 'tables_modify';

        $nested = $this->createMock(DataHandler::class);
        $correlationId = CorrelationId::forScope('core/cli')
            ->withAspects(SlugService::CORRELATION_ID_IDENTIFIER, 'slug');
        $nested->method('getCorrelationId')->willReturn($correlationId);
        $subject->processDatamap_beforeStart($nested);

        self::assertSame(
            'pages,tt_content,sys_redirect',
            $GLOBALS['BE_USER']->groupData['tables_modify']
        );
    }

    #[Test]
    public function doesNotRestoreForNonNestedDataHandler(): void
    {
        $subject = new RestorePermissionsForNestedSlugUpdate();

        $topLevel = $this->createMock(DataHandler::class);
        $topLevel->method('getCorrelationId')->willReturn(null);
        $subject->processDatamap_beforeStart($topLevel);

        $GLOBALS['BE_USER']->groupData['tables_modify'] = 'corrupted_value';

        $anotherTopLevel = $this->createMock(DataHandler::class);
        $anotherTopLevel->method('getCorrelationId')->willReturn(null);
        $subject->processDatamap_beforeStart($anotherTopLevel);

        self::assertSame(
            'corrupted_value',
            $GLOBALS['BE_USER']->groupData['tables_modify']
        );
    }

    #[Test]
    public function handlesEmptyPermissionsGracefully(): void
    {
        $GLOBALS['BE_USER']->groupData = [];

        $subject = new RestorePermissionsForNestedSlugUpdate();

        $topLevel = $this->createMock(DataHandler::class);
        $topLevel->method('getCorrelationId')->willReturn(null);
        $subject->processDatamap_beforeStart($topLevel);

        $nested = $this->createMock(DataHandler::class);
        $correlationId = CorrelationId::forScope('core/cli')
            ->withAspects(SlugService::CORRELATION_ID_IDENTIFIER, 'slug');
        $nested->method('getCorrelationId')->willReturn($correlationId);
        $subject->processDatamap_beforeStart($nested);

        self::assertNull($GLOBALS['BE_USER']->groupData['tables_modify'] ?? null);
    }
}
