<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Meetings\Application\Service;

use App\Context\Meetings\Application\Service\SessionGenerator;
use App\Context\Meetings\Domain\ValueObject\ArchivedStructure;
use App\Context\Meetings\Domain\ValueObject\GenerationResult;
use App\Library\Time\Model\LocalDate;
use App\Tests\Support\Context\Meetings\Application\Service\SessionGeneration;
use App\Tests\Support\Database;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SessionGeneratorTest extends KernelTestCase
{
    public function testGeneratingForOneScheduleLeavesTheOthersAlone(): void
    {
        $first = SessionGeneration::sundays();
        new Database(self::getContainer())->persisted($first, SessionGeneration::sundays());

        self::assertEquals(
            new GenerationResult(created: 1),
            $this->generator()->generateFor($first, LocalDate::of('2026-11-07'), ArchivedStructure::of([])),
        );

        self::assertSame(['2026-11-08'], new SessionGeneration(self::getContainer())->sessionDates());
    }

    public function testGeneratingForOneScheduleOfAnArchivedServiceCreatesNothing(): void
    {
        $schedule = SessionGeneration::sundays();
        new Database(self::getContainer())->persisted($schedule);
        $archived = ArchivedStructure::of([$schedule->roster()->serviceId]);

        self::assertEquals(
            new GenerationResult(),
            $this->generator()->generateFor($schedule, LocalDate::of('2026-11-07'), $archived),
        );
    }

    private function generator(): SessionGenerator
    {
        $generator = self::getContainer()->get(SessionGenerator::class);
        self::assertInstanceOf(SessionGenerator::class, $generator);

        return $generator;
    }
}
