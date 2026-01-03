<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Unit\EventListener;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Redirects\Event\SlugRedirectChangeItemCreatedEvent;
use TYPO3\CMS\Redirects\RedirectUpdate\PlainSlugReplacementRedirectSource;
use TYPO3\CMS\Redirects\RedirectUpdate\RedirectSourceCollection;
use TYPO3\CMS\Redirects\RedirectUpdate\SlugRedirectChangeItem;
use Wazum\Sluggi\EventListener\SkipRedirectOnRequest;

final class SkipRedirectOnRequestTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_REQUEST']);
        parent::tearDown();
    }

    #[Test]
    public function redirectIsCreatedWhenToggleIsOn(): void
    {
        $this->setRedirectToggleInRequest(2, true);

        $changeItem = $this->createChangeItemWithSources(2);
        $event = new SlugRedirectChangeItemCreatedEvent($changeItem);

        $subject = new SkipRedirectOnRequest();
        $subject($event);

        $resultItem = $event->getSlugRedirectChangeItem();
        self::assertGreaterThan(0, count($resultItem->getSourcesCollection()->all()));
    }

    #[Test]
    public function redirectIsCreatedWhenToggleNotSet(): void
    {
        $changeItem = $this->createChangeItemWithSources(2);
        $event = new SlugRedirectChangeItemCreatedEvent($changeItem);

        $subject = new SkipRedirectOnRequest();
        $subject($event);

        $resultItem = $event->getSlugRedirectChangeItem();
        self::assertGreaterThan(0, count($resultItem->getSourcesCollection()->all()));
    }

    #[Test]
    public function noRedirectCreatedWhenToggleIsOff(): void
    {
        $this->setRedirectToggleInRequest(2, false);

        $changeItem = $this->createChangeItemWithSources(2);
        $event = new SlugRedirectChangeItemCreatedEvent($changeItem);

        $subject = new SkipRedirectOnRequest();
        $subject($event);

        $resultItem = $event->getSlugRedirectChangeItem();
        self::assertCount(0, $resultItem->getSourcesCollection()->all());
    }

    #[Test]
    public function toggleIsReadFromCorrectPageInRequest(): void
    {
        $body = [
            'data' => [
                'pages' => [
                    5 => ['tx_sluggi_redirect' => '0'],
                    2 => ['tx_sluggi_redirect' => '1'],
                ],
            ],
        ];
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest())->withParsedBody($body);

        $changeItem = $this->createChangeItemWithSources(2);
        $event = new SlugRedirectChangeItemCreatedEvent($changeItem);

        $subject = new SkipRedirectOnRequest();
        $subject($event);

        $resultItem = $event->getSlugRedirectChangeItem();
        self::assertGreaterThan(0, count($resultItem->getSourcesCollection()->all()));
    }

    #[Test]
    public function toggleIsOffForDifferentPageInRequest(): void
    {
        $body = [
            'data' => [
                'pages' => [
                    5 => ['tx_sluggi_redirect' => '0'],
                    2 => ['tx_sluggi_redirect' => '1'],
                ],
            ],
        ];
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest())->withParsedBody($body);

        $changeItem = $this->createChangeItemWithSources(5);
        $event = new SlugRedirectChangeItemCreatedEvent($changeItem);

        $subject = new SkipRedirectOnRequest();
        $subject($event);

        $resultItem = $event->getSlugRedirectChangeItem();
        self::assertCount(0, $resultItem->getSourcesCollection()->all());
    }

    #[Test]
    public function childPageInheritsParentRedirectToggleOff(): void
    {
        $body = [
            'data' => [
                'pages' => [
                    2 => ['tx_sluggi_redirect' => '0'],
                ],
            ],
        ];
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest())->withParsedBody($body);

        $changeItem = $this->createChangeItemWithSources(3);
        $event = new SlugRedirectChangeItemCreatedEvent($changeItem);

        $subject = new SkipRedirectOnRequest();
        $subject($event);

        $resultItem = $event->getSlugRedirectChangeItem();
        self::assertCount(0, $resultItem->getSourcesCollection()->all(), 'Child page should inherit redirect=off from parent in request');
    }

    private function setRedirectToggleInRequest(int $pageUid, bool $createRedirect): void
    {
        $body = [
            'data' => [
                'pages' => [
                    $pageUid => [
                        'tx_sluggi_redirect' => $createRedirect ? '1' : '0',
                    ],
                ],
            ],
        ];

        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest())->withParsedBody($body);
    }

    private function createChangeItemWithSources(int $pageId): SlugRedirectChangeItem
    {
        $source = new PlainSlugReplacementRedirectSource(
            '*',
            '/old-slug',
            []
        );
        $sources = new RedirectSourceCollection($source);

        return new SlugRedirectChangeItem(
            defaultLanguagePageId: $pageId,
            pageId: $pageId,
            site: $this->createMock(Site::class),
            siteLanguage: $this->createMock(SiteLanguage::class),
            original: ['uid' => $pageId, 'slug' => '/old-slug'],
            sourcesCollection: $sources,
            changed: null,
        );
    }
}
