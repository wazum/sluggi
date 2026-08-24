<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Unit\DataHandler;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use Wazum\Sluggi\DataHandler\TrackPendingSlug;

final class TrackPendingSlugTest extends TestCase
{
    #[Test]
    public function findsTheTranslationPointerInTheDatamapWhenTheFieldArrayLacksIt(): void
    {
        $dataHandler = $this->createMock(DataHandler::class);
        $dataHandler->datamap = ['pages' => ['NEW123' => ['l10n_parent' => 5]]];
        $subject = new TrackPendingSlug();

        $fieldArray = ['title' => '[Translate to German:] Locked Page'];
        $subject->processDatamap_postProcessFieldArray('new', 'pages', 'NEW123', $fieldArray, $dataHandler);

        self::assertSame(1, $fieldArray['tx_sluggi_slug_pending'] ?? null);
    }
}
