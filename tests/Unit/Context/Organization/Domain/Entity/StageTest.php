<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Organization\Domain\Entity;

use App\Context\Organization\Domain\Entity\Stage;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Library\Problem\Exception\ThrownFieldErrors;
use PHPUnit\Framework\TestCase;

final class StageTest extends TestCase
{
    public function testANewStageBelongsToItsServiceWithACleanedName(): void
    {
        $service = Structure::service();
        $stage = new Stage($service, ' 3rd  Class', description: ' Ages 8 to 9 ', position: 2);

        self::assertSame($service, $stage->service());
        self::assertSame('3rd Class', $stage->name()->value);
        self::assertSame('3rd_class', $stage->name()->key);
        self::assertSame('Ages 8 to 9', $stage->description()->value);
        self::assertSame(2, $stage->position());
        self::assertFalse($stage->isArchived());
    }

    public function testABlankNameIsRejected(): void
    {
        self::assertSame(
            ['name' => ['code' => 'stages.nameRequired']],
            ThrownFieldErrors::of(static fn (): Stage => Structure::stage(Structure::service(), name: '   ')),
        );
    }

    public function testANameLongerThan80CharactersIsRejected(): void
    {
        Structure::stage(Structure::service(), name: str_repeat('a', times: 80));

        self::assertSame(
            ['name' => ['code' => 'validation.tooLong', 'params' => ['max' => 80]]],
            ThrownFieldErrors::of(static fn (): Stage => Structure::stage(Structure::service(), name: str_repeat(
                'a',
                times: 81,
            ))),
        );
    }

    public function testADescriptionLongerThan500CharactersIsRejected(): void
    {
        self::assertSame(
            ['description' => ['code' => 'validation.tooLong', 'params' => ['max' => 500]]],
            ThrownFieldErrors::of(
                static fn (): Stage => new Stage(
                    Structure::service(),
                    'Choir',
                    description: str_repeat('a', times: 501),
                    position: 0,
                ),
            ),
        );
    }

    public function testArchivingMarksTheStage(): void
    {
        $stage = Structure::stage(Structure::service());

        $stage->archive(Structure::now());

        self::assertTrue($stage->isArchived());
    }
}
