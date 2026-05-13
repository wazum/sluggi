<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Functional\Hooks;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\Sluggi\Hooks\DispatchSlugChangeReport;

final class DispatchSlugChangeReportTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'wazum/sluggi',
    ];

    protected array $coreExtensionsToLoad = [
        'redirects',
    ];

    #[Test]
    public function populatesHtmlWithImmediateActionDispatchingTheSluggiReportEvent(): void
    {
        $params = [
            'set' => 'sluggi:slugChangeReport',
            'parameter' => [
                'entries' => [['pageId' => 42, 'correlations' => ['correlationIdSlugUpdate' => 'a/x']]],
                'pagesUpdated' => 3,
                'redirectsCreated' => 2,
            ],
            'html' => '',
        ];
        $hook = GeneralUtility::makeInstance(DispatchSlugChangeReport::class);

        $hook->dispatch($params);

        self::assertIsString($params['html']);
        self::assertStringContainsString('<typo3-immediate-action', $params['html']);
        self::assertStringContainsString('TYPO3.Backend.Event.EventDispatcher.dispatchCustomEvent', $params['html']);
        self::assertStringContainsString('typo3:sluggi:slugChangeReport', $params['html']);
        // Payload is JSON-encoded into the args attribute; ImmediateActionElement
        // double-encodes (jsonEncodeForHtmlAttribute + implodeAttributes), so quotes
        // are rendered as &amp;quot; in the final markup.
        self::assertStringContainsString('&amp;quot;pagesUpdated&amp;quot;:3', $params['html']);
        self::assertStringContainsString('&amp;quot;redirectsCreated&amp;quot;:2', $params['html']);
    }
}
