<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration as CoreExtensionConfiguration;
use Wazum\Sluggi\Configuration\ExtensionConfiguration;
use Wazum\Sluggi\Service\LastSegmentValidationService;

final class LastSegmentValidationServiceTest extends TestCase
{
    private function createConfigWithLastSegmentOnly(bool $enabled): ExtensionConfiguration
    {
        $coreConfig = $this->createMock(CoreExtensionConfiguration::class);
        $coreConfig->method('get')
            ->with('sluggi', 'last_segment_only')
            ->willReturn($enabled ? '1' : '0');

        return new ExtensionConfiguration($coreConfig);
    }

    #[Test]
    public function shouldRestrictUserReturnsTrueForNonAdminWhenEnabled(): void
    {
        $config = $this->createConfigWithLastSegmentOnly(true);
        $subject = new LastSegmentValidationService($config);

        self::assertTrue($subject->shouldRestrictUser(isAdmin: false));
    }

    #[Test]
    public function shouldRestrictUserReturnsFalseForAdminWhenEnabled(): void
    {
        $config = $this->createConfigWithLastSegmentOnly(true);
        $subject = new LastSegmentValidationService($config);

        self::assertFalse($subject->shouldRestrictUser(isAdmin: true));
    }

    #[Test]
    public function shouldRestrictUserReturnsFalseWhenDisabled(): void
    {
        $config = $this->createConfigWithLastSegmentOnly(false);
        $subject = new LastSegmentValidationService($config);

        self::assertFalse($subject->shouldRestrictUser(isAdmin: false));
    }

    /** @return array<string, array{oldSlug: string, newSlug: string, expected: bool}> */
    public static function slugValidationDataProvider(): array
    {
        return [
            'valid: last segment changed' => [
                'oldSlug' => '/parent/child',
                'newSlug' => '/parent/new-child',
                'expected' => true,
            ],
            'valid: root level segment changed' => [
                'oldSlug' => '/page',
                'newSlug' => '/new-page',
                'expected' => true,
            ],
            'invalid: parent segment modified' => [
                'oldSlug' => '/parent/child',
                'newSlug' => '/new-parent/child',
                'expected' => false,
            ],
            'invalid: extra segment added' => [
                'oldSlug' => '/parent/child',
                'newSlug' => '/parent/extra/child',
                'expected' => false,
            ],
            'invalid: segment removed' => [
                'oldSlug' => '/parent/child',
                'newSlug' => '/child',
                'expected' => false,
            ],
        ];
    }

    #[Test]
    #[DataProvider('slugValidationDataProvider')]
    public function validateSlugChangeReturnsExpectedResult(
        string $oldSlug,
        string $newSlug,
        bool $expected,
    ): void {
        $config = $this->createConfigWithLastSegmentOnly(false);
        $subject = new LastSegmentValidationService($config);

        self::assertSame($expected, $subject->validateSlugChange($oldSlug, $newSlug));
    }
}
