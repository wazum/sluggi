<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration as CoreExtensionConfiguration;
use Wazum\Sluggi\Configuration\ExtensionConfiguration;
use Wazum\Sluggi\Service\SlugGeneratorService;

final class SlugGeneratorServiceTest extends TestCase
{
    private SlugGeneratorService $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $coreExtensionConfiguration = $this->createMock(CoreExtensionConfiguration::class);
        $extensionConfiguration = new ExtensionConfiguration($coreExtensionConfiguration);
        $this->subject = new SlugGeneratorService($extensionConfiguration);
    }

    /**
     * @return array<string, array{parentSlug: string, childSlug: string, expected: string}>
     */
    public static function slugCombinationDataProvider(): array
    {
        return [
            'simple case' => [
                'parentSlug' => '/parent-page',
                'childSlug' => '/child-page',
                'expected' => '/parent-page/child-page',
            ],
            'child already has parent prefix' => [
                'parentSlug' => '/parent-page',
                'childSlug' => '/old-parent/child-page',
                'expected' => '/parent-page/child-page',
            ],
            'root parent empty string' => [
                'parentSlug' => '',
                'childSlug' => '/child',
                'expected' => '/child',
            ],
            'trailing slash in parent' => [
                'parentSlug' => '/parent/',
                'childSlug' => '/child',
                'expected' => '/parent/child',
            ],
        ];
    }

    #[Test]
    #[DataProvider('slugCombinationDataProvider')]
    public function combineWithParentProducesExpectedResults(
        string $parentSlug,
        string $childSlug,
        string $expected,
    ): void {
        self::assertSame($expected, $this->subject->combineWithParent($parentSlug, $childSlug));
    }
}
