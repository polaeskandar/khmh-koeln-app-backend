<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Organization\Domain\Entity;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\ValueObject\Tone;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use Doctrine\Common\Collections\ArrayCollection;
use LogicException;
use PHPUnit\Framework\TestCase;

final class ServiceTest extends TestCase
{
    public function testANewServiceKeepsItsCleanedNameAndDescription(): void
    {
        $service = new Service('  Youth   Meeting ', description: '  Fridays ');

        self::assertSame('Youth Meeting', $service->name()->value);
        self::assertSame('youth_meeting', $service->name()->key);
        self::assertSame('Fridays', $service->description()->value);
        self::assertSame(7, $service->id()->toRfc4122() |> self::uuidVersion(...));
        self::assertFalse($service->isArchived());
    }

    public function testABlankDescriptionIsNoDescription(): void
    {
        self::assertNull(new Service('Choir', description: '   ')->description()->value);
    }

    public function testAServiceHasNoToneBeforeItIsSaved(): void
    {
        $this->expectException(LogicException::class);

        Structure::service()->tone();
    }

    public function testAServiceKeepsTheToneItWasFirstGiven(): void
    {
        $service = Structure::service();
        $service->takeTone(Tone::leastUsed(new ArrayCollection([1])));

        self::assertSame(2, $service->tone()->value);
        $this->expectException(LogicException::class);
        $service->takeTone(Tone::leastUsed(new ArrayCollection()));
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
