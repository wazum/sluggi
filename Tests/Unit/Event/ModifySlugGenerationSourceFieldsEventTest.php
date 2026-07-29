<?php

declare(strict_types=1);

namespace Wazum\Sluggi\Tests\Unit\Event;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Wazum\Sluggi\Event\ModifySlugGenerationSourceFieldsEvent;

final class ModifySlugGenerationSourceFieldsEventTest extends TestCase
{
    #[Test]
    public function changingAnIdentityFieldIsRejected(): void
    {
        $subject = new ModifySlugGenerationSourceFieldsEvent(
            ['uid' => 3, 'title' => 'Original'],
            'pages',
            'slug',
            1,
            0,
            [],
            ['title' => 'Original'],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/sys_language_uid/');

        $subject->setSourceFieldValues(['title' => 'Fine', 'sys_language_uid' => 2]);
    }

    #[Test]
    public function valuesFromSeveralListenersAreMerged(): void
    {
        $subject = new ModifySlugGenerationSourceFieldsEvent(
            ['uid' => 3, 'title' => 'Original', 'nav_title' => 'Navigation'],
            'pages',
            'slug',
            1,
            0,
            [],
            ['title' => 'Original', 'nav_title' => 'Navigation'],
        );

        $subject->setSourceFieldValues(['title' => 'From the first listener']);
        $subject->setSourceFieldValues(['nav_title' => 'From the second listener']);

        self::assertSame(
            ['title' => 'From the first listener', 'nav_title' => 'From the second listener'],
            $subject->getSourceFieldValues(),
        );
    }
}
