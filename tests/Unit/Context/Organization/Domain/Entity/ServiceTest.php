<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Organization\Domain\Entity;

use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use LogicException;
use PHPUnit\Framework\TestCase;

final class ServiceTest extends TestCase
{
    public function testANewServiceKeepsWhatItIsGiven(): void
    {
        $service = Structure::service('Youth Meeting', description: 'Fridays');

        self::assertSame('Youth Meeting', $service->name()->value);
        self::assertSame('Fridays', $service->description()->value);
        self::assertSame(1, $service->tone()->value);
        self::assertSame(7, $service->id()->toRfc4122() |> self::uuidVersion(...));
        self::assertFalse($service->isArchived());
    }

    public function testArchivingMarksTheService(): void
    {
        $service = Structure::service();

        $service->archive(Structure::now());

        self::assertTrue($service->isArchived());
    }

    public function testAServiceHasNoCreationTimeBeforeItIsStored(): void
    {
        $this->expectException(LogicException::class);

        Structure::service()->createdAt();
    }

    private static function uuidVersion(string $uuid): int
    {
        return (int) $uuid[14];
    }
}
