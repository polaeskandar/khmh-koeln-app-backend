<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Meetings\Application\Service;

use App\Context\Meetings\Application\Service\PlannedDates;
use App\Context\Meetings\Domain\ValueObject\ArchivedStructure;
use App\Context\Meetings\Domain\ValueObject\PlannedDate;
use App\Library\Time\Model\LocalDate;
use App\Tests\Support\Context\Meetings\Domain\Entity\Schedules;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PlannedDatesTest extends KernelTestCase
{
    public function testSessionsKeepTheirStatusAndOnlyMissingOccurrencesArePlanned(): void
    {
        $service = Structure::service('Youth');
        new Database(self::getContainer())->persisted($service);
        $schedules = new Schedules(self::getContainer());
        $friday = $schedules->weekly($service, 'Youth Meeting', 5);
        $canceled = $schedules->session($friday, '2026-11-13', "status = 'canceled', cancel_reason = 'Holiday'");
        $plannedDates = self::getContainer()->get(PlannedDates::class);
        self::assertInstanceOf(PlannedDates::class, $plannedDates);

        $dates = $plannedDates->between(
            new ArrayCollection([$friday]),
            ArchivedStructure::of([]),
            LocalDate::of('2026-11-06'),
            LocalDate::of('2026-11-20'),
        );

        self::assertSame(
            [
                ['2026-11-06', 'planned', null],
                ['2026-11-13', 'canceled', $canceled->id()->toRfc4122()],
                ['2026-11-20', 'planned', null],
            ],
            array_map(static fn (PlannedDate $date): array => [
                $date->date->toString(),
                $date->status,
                $date->sessionId,
            ], $dates),
        );
    }
}
