<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Unit\Compatibility;

use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Information\Typo3Version;
use Wazum\Sluggi\Compatibility\Typo3Compatibility;

final class Typo3CompatibilityTest extends TestCase
{
    public function testFormElementFieldInformationMatchesCurrentMajorVersion(): void
    {
        $major = (new Typo3Version())->getMajorVersion();

        $result = Typo3Compatibility::getFormElementFieldInformation();

        if ($major < 14) {
            self::assertSame(
                ['tcaDescription' => ['renderType' => 'tcaDescription']],
                $result,
            );
        } else {
            self::assertSame([], $result);
        }
    }
}
