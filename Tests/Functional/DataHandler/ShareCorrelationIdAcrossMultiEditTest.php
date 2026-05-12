<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\DataHandler;

use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\DataHandling\Model\CorrelationId;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\Sluggi\DataHandler\ShareCorrelationIdAcrossMultiEdit;

final class ShareCorrelationIdAcrossMultiEditTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'wazum/sluggi',
    ];

    protected array $coreExtensionsToLoad = [
        'redirects',
    ];

    #[Test]
    public function slugTouchingDataHandlersShareOneCorrelation(): void
    {
        $this->setUpRequestWithPagesBody([
            11 => ['slug' => '/x'],
            12 => ['slug' => '/y'],
        ]);
        $hook = new ShareCorrelationIdAcrossMultiEdit();

        $first = $this->createDataHandlerWithDatamap([11 => ['slug' => '/x']]);
        $second = $this->createDataHandlerWithDatamap([12 => ['slug' => '/y']]);
        $hook->processDatamap_beforeStart($first);
        $hook->processDatamap_beforeStart($second);

        $firstId = (string)$first->getCorrelationId();
        self::assertSame($firstId, (string)$second->getCorrelationId());
        self::assertSame('multiedit', $first->getCorrelationId()?->getSubject());
    }

    #[Test]
    public function nonSlugDataHandlerInTheSameRequestKeepsItsOwnCorrelation(): void
    {
        $this->setUpRequestWithPagesBody([
            11 => ['slug' => '/x'],
            12 => ['slug' => '/y'],
            13 => ['nav_title' => 'Foo'],
        ]);
        $hook = new ShareCorrelationIdAcrossMultiEdit();

        $slugHandler = $this->createDataHandlerWithDatamap([11 => ['slug' => '/x']]);
        $navTitleHandler = $this->createDataHandlerWithDatamap([13 => ['nav_title' => 'Foo']]);
        $navTitleScopeBefore = $navTitleHandler->getCorrelationId()?->getScope();
        $hook->processDatamap_beforeStart($slugHandler);
        $hook->processDatamap_beforeStart($navTitleHandler);

        self::assertSame('multiedit', $slugHandler->getCorrelationId()?->getSubject());
        self::assertNull($navTitleHandler->getCorrelationId()?->getSubject());
        self::assertSame($navTitleScopeBefore, $navTitleHandler->getCorrelationId()?->getScope());
    }

    #[Test]
    public function singleSlugRequestIsLeftUntouched(): void
    {
        $this->setUpRequestWithPagesBody([
            11 => ['slug' => '/x'],
        ]);
        $hook = new ShareCorrelationIdAcrossMultiEdit();

        $handler = $this->createDataHandlerWithDatamap([11 => ['slug' => '/x']]);
        $scopeBefore = $handler->getCorrelationId()?->getScope();
        $hook->processDatamap_beforeStart($handler);

        self::assertNull($handler->getCorrelationId()?->getSubject());
        self::assertSame($scopeBefore, $handler->getCorrelationId()?->getScope());
    }

    #[Test]
    public function callerWithExplicitCorrelationSubjectIsLeftUntouched(): void
    {
        $this->setUpRequestWithPagesBody([
            11 => ['slug' => '/x'],
            12 => ['slug' => '/y'],
        ]);
        $hook = new ShareCorrelationIdAcrossMultiEdit();

        $handler = $this->createDataHandlerWithDatamap([11 => ['slug' => '/x']]);
        $explicit = CorrelationId::forScope('cascade')->withSubject('123');
        $handler->setCorrelationId($explicit);
        $hook->processDatamap_beforeStart($handler);

        self::assertSame((string)$explicit, (string)$handler->getCorrelationId());
    }

    #[Test]
    public function nestedDataHandlersAreLeftUntouched(): void
    {
        $this->setUpRequestWithPagesBody([
            11 => ['slug' => '/x'],
            12 => ['slug' => '/y'],
        ]);
        $hook = new ShareCorrelationIdAcrossMultiEdit();

        $parent = $this->createDataHandlerWithDatamap([10 => ['slug' => '/parent']]);
        $nested = $this->createDataHandlerWithDatamap([11 => ['slug' => '/x']]);
        // Mark the nested DH as having a different outermost parent.
        (new ReflectionProperty($nested, 'outerMostInstance'))->setValue($nested, $parent);
        $scopeBefore = $nested->getCorrelationId()?->getScope();
        $hook->processDatamap_beforeStart($nested);

        self::assertNull($nested->getCorrelationId()?->getSubject());
        self::assertSame($scopeBefore, $nested->getCorrelationId()?->getScope());
    }

    #[Test]
    public function separateRequestsGetTheirOwnSharedCorrelation(): void
    {
        $hook = new ShareCorrelationIdAcrossMultiEdit();

        $this->setUpRequestWithPagesBody([11 => ['slug' => '/x'], 12 => ['slug' => '/y']]);
        $firstRequestHandler = $this->createDataHandlerWithDatamap([11 => ['slug' => '/x']]);
        $hook->processDatamap_beforeStart($firstRequestHandler);

        $this->setUpRequestWithPagesBody([21 => ['slug' => '/a'], 22 => ['slug' => '/b']]);
        $secondRequestHandler = $this->createDataHandlerWithDatamap([21 => ['slug' => '/a']]);
        $hook->processDatamap_beforeStart($secondRequestHandler);

        self::assertNotSame(
            (string)$firstRequestHandler->getCorrelationId(),
            (string)$secondRequestHandler->getCorrelationId(),
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/share_correlation_admin.csv');
        $this->setUpBackendUser(1);
        $reflection = new ReflectionProperty(ShareCorrelationIdAcrossMultiEdit::class, 'sharedCorrelationIds');
        $reflection->setValue(null, []);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_REQUEST']);
        parent::tearDown();
    }

    /**
     * @param array<int|string, array<string, mixed>> $pagesBody
     */
    private function setUpRequestWithPagesBody(array $pagesBody): void
    {
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest())->withParsedBody(['data' => ['pages' => $pagesBody]]);
    }

    /**
     * @param array<int|string, array<string, mixed>> $pagesDatamap
     */
    private function createDataHandlerWithDatamap(array $pagesDatamap): DataHandler
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(['pages' => $pagesDatamap], []);
        $outermost = new ReflectionProperty($dataHandler, 'outerMostInstance');
        $outermost->setValue($dataHandler, $dataHandler);

        return $dataHandler;
    }
}
