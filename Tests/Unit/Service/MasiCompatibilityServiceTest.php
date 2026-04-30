<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Wazum\Sluggi\Service\MasiCompatibilityService;

final class MasiCompatibilityServiceTest extends UnitTestCase
{
    private MasiCompatibilityService $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new MasiCompatibilityService();
    }

    #[Test]
    public function isExclusionFieldSubmittedReturnsTrueWhenPresent(): void
    {
        self::assertTrue($this->subject->isExclusionFieldSubmitted(['exclude_slug_for_subpages' => 1]));
    }

    #[Test]
    public function isExclusionFieldSubmittedReturnsTrueForZeroValue(): void
    {
        self::assertTrue($this->subject->isExclusionFieldSubmitted(['exclude_slug_for_subpages' => 0]));
    }

    #[Test]
    public function isExclusionFieldSubmittedReturnsFalseWhenAbsent(): void
    {
        self::assertFalse($this->subject->isExclusionFieldSubmitted(['other_field' => 1]));
    }

    #[Test]
    public function getSubmittedExclusionValueReturnsTrueForOne(): void
    {
        self::assertTrue($this->subject->getSubmittedExclusionValue(['exclude_slug_for_subpages' => 1]));
    }

    #[Test]
    public function getSubmittedExclusionValueReturnsFalseForZero(): void
    {
        self::assertFalse($this->subject->getSubmittedExclusionValue(['exclude_slug_for_subpages' => 0]));
    }

    #[Test]
    public function getSubmittedExclusionValueReturnsFalseWhenAbsent(): void
    {
        self::assertFalse($this->subject->getSubmittedExclusionValue([]));
    }
}
