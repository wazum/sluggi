<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\ContextMenu;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\Sluggi\ContextMenu\SlugUpdateItemProvider;

final class SlugUpdateItemProviderTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'wazum/sluggi',
    ];

    protected array $coreExtensionsToLoad = [
        'redirects',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/be_users.csv');
        $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
    }

    #[Test]
    public function canHandleReturnsTrueForPagesTable(): void
    {
        $provider = $this->get(SlugUpdateItemProvider::class);
        $provider->setContext('pages', '2', 'tree');

        self::assertTrue($provider->canHandle());
    }

    #[Test]
    public function canHandleReturnsFalseForOtherTables(): void
    {
        $provider = $this->get(SlugUpdateItemProvider::class);
        $provider->setContext('tt_content', '1', 'tree');

        self::assertFalse($provider->canHandle());
    }

    #[Test]
    public function addItemsInsertsItemIntoMoreSubmenu(): void
    {
        $provider = $this->get(SlugUpdateItemProvider::class);
        $provider->setContext('pages', '2', 'tree');

        $existingItems = [
            'more' => [
                'type' => 'submenu',
                'label' => 'More',
                'childItems' => [
                    'exportT3d' => ['type' => 'item', 'label' => 'Export'],
                ],
            ],
        ];

        $result = $provider->addItems($existingItems);

        self::assertArrayHasKey('recursiveSlugUpdate', $result['more']['childItems']);
        $item = $result['more']['childItems']['recursiveSlugUpdate'];
        self::assertSame('item', $item['type']);
        self::assertNotEmpty($item['label']);
        self::assertSame('recursiveSlugUpdate', $item['callbackAction']);
        self::assertArrayHasKey('data-callback-module', $item['additionalAttributes']);
        self::assertSame('@wazum/sluggi/context-menu-actions', $item['additionalAttributes']['data-callback-module']);
    }

    #[Test]
    public function addItemsFallsBackToTopLevelWhenNoMoreSubmenu(): void
    {
        $provider = $this->get(SlugUpdateItemProvider::class);
        $provider->setContext('pages', '2', 'tree');

        $existingItems = [
            'edit' => ['type' => 'item', 'label' => 'Edit'],
        ];

        $result = $provider->addItems($existingItems);

        self::assertArrayHasKey('recursiveSlugUpdate', $result);
    }

    #[Test]
    public function canHandleReturnsFalseForRootPage(): void
    {
        $provider = $this->get(SlugUpdateItemProvider::class);
        $provider->setContext('pages', '0', 'tree');

        self::assertFalse($provider->canHandle());
    }

    #[Test]
    public function canHandleReturnsFalseForNonAdminUser(): void
    {
        $this->setUpBackendUser(2);
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');

        $provider = GeneralUtility::makeInstance(SlugUpdateItemProvider::class);
        $provider->setContext('pages', '2', 'tree');

        self::assertFalse($provider->canHandle());
    }

    #[Test]
    public function priorityIsLowerThanPageProvider(): void
    {
        $provider = $this->get(SlugUpdateItemProvider::class);

        self::assertLessThan(100, $provider->getPriority());
    }
}
