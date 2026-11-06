<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Organization\Domain\Entity;

use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use PHPUnit\Framework\TestCase;

final class StageTest extends TestCase
{
    public function testANewStageBelongsToItsServiceAtItsPosition(): void
    {
        $service = Structure::service();
        $stage = Structure::stage($service, '3rd Class', position: 2);

        self::assertSame($service, $stage->service());
        self::assertSame('3rd Class', $stage->name()->value);
        self::assertNull($stage->description()->value);
        self::assertSame(2, $stage->position());
        self::assertFalse($stage->isArchived());
    }

    public function testArchivingMarksTheStage(): void
    {
        $stage = Structure::stage(Structure::service());

        $stage->archive(Structure::now());

        self::assertTrue($stage->isArchived());
    }
}
