<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\People\Infrastructure\Persistence\Repository;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\People\Domain\Repository\RosterRepositoryInterface;
use App\Context\People\Domain\ValueObject\RosterKey;
use App\Library\Time\Model\LocalDate;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Database;
use App\Tests\Support\Logs;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class DoctrineRosterRepositoryTest extends KernelTestCase
{
    public function testAMembershipCountsFromItsFirstDayThroughItsLastDay(): void
    {
        [$service, $third] = $this->structure();
        $ended = $this->rosters()->person('Mina');
        $starting = $this->rosters()->person('Marina');
        $this->rosters()->place($ended, $service, $third, '2026-09-01', '2026-11-07');
        $this->rosters()->place($starting, $service, $third, '2026-11-08');
        $this->rosters()->place($this->rosters()->person('Kirollos'), $service, $third, '2026-11-09');

        self::assertSame([$ended->id()->toRfc4122()], $this->rosterOf($service, $third, '2026-11-07'));
        self::assertSame([$starting->id()->toRfc4122()], $this->rosterOf($service, $third, '2026-11-08'));

        foreach (['2026-11-07', '2026-11-08'] as $day) {
            $on = LocalDate::of($day);
            $stageCounts = $this->repository()->countStageMembers([$third->id()], $on);
            $serviceCounts = $this->repository()->countServiceMembers([$service->id()], $on);

            self::assertSame(1, $stageCounts->get($third->id()->toRfc4122()), $day);

            self::assertSame(
                ['members' => 1, 'serviceLevel' => 0],
                $serviceCounts->get($service->id()->toRfc4122()),
                $day,
            );
        }
    }

    public function testOverlappingMembershipsOfOnePersonPutThemOnTheRosterOnce(): void
    {
        [$service, $third] = $this->structure();
        $mina = $this->rosters()->person('Mina');
        $this->rosters()->place($mina, $service, $third, '2026-09-01', '2026-11-30');
        $this->rosters()->place($mina, $service, $third, '2026-09-01');

        self::assertSame([$mina->id()->toRfc4122()], $this->rosterOf($service, $third, '2026-09-15'));
    }

    public function testTheServiceLevelRosterHoldsOnlyMembershipsWithoutAStageInGermanNameOrder(): void
    {
        [$service, $third] = $this->structure();
        $rizk = $this->rosters()->person('Bola', 'Rizk');
        $ozdemir = $this->rosters()->person('Ayla', 'Özdemir');
        $nashed = $this->rosters()->person('Kirollos', 'Nashed');
        $child = $this->rosters()->person('Mina');

        foreach ([$rizk, $ozdemir, $nashed] as $adult) {
            $this->rosters()->place($adult, $service, null, '2026-09-01');
        }

        $this->rosters()->place($child, $service, $third, '2026-09-01');

        new Database(self::getContainer())
            ->connection()
            ->update(
                'people.person',
                ['status' => 'inactive'],
                [
                    'id' => $nashed->id()->toRfc4122(),
                ],
            );

        self::assertSame(
            [$nashed->id()->toRfc4122(), $ozdemir->id()->toRfc4122(), $rizk->id()->toRfc4122()],
            $this->rosterOf($service, null, '2026-11-08'),
        );
    }

    public function testManyRostersAreReadInOneStatement(): void
    {
        [$service, $third] = $this->structure();
        $mina = $this->rosters()->person('Mina');
        $this->rosters()->place($mina, $service, $third, '2026-09-01');

        $keys = array_map(
            static fn (int $day): RosterKey => new RosterKey(
                $service->id(),
                $third->id(),
                LocalDate::of(sprintf('2026-11-%02d', $day)),
            ),
            range(start: 1, end: 20),
        );

        $rosters = $this->repository()->rostersOn($keys);

        self::assertCount(20, $rosters);

        self::assertSame(
            [$mina->id()->toRfc4122()],
            array_map(
                static fn (Uuid $id): string => $id->toRfc4122(),
                $rosters->get(new RosterKey($service->id(), $third->id(), LocalDate::of('2026-11-20'))->id()) ?? [],
            ),
        );

        self::assertCount(1, new Logs(self::getContainer())->containing('jsonb_to_recordset'));
    }

    public function testAPersonInTwoStagesCountsOnceForTheService(): void
    {
        [$service, $third] = $this->structure();
        $fourth = Structure::stage($service, '4th Class', position: 1);
        new Database(self::getContainer())->persisted($fourth);
        $mina = $this->rosters()->person('Mina');
        $this->rosters()->place($mina, $service, $third, '2026-09-01');
        $this->rosters()->place($mina, $service, $fourth, '2026-09-01');
        $this->rosters()->place($mina, $service, null, '2026-09-01');
        $day = LocalDate::of('2026-11-08');

        self::assertSame(
            ['members' => 1, 'serviceLevel' => 1],
            $this->repository()->countServiceMembers([$service->id()], $day)->get($service->id()->toRfc4122()),
        );

        self::assertSame(
            1,
            $this
                ->repository()
                ->countStageMembers([$third->id(), $fourth->id()], $day)
                ->get($fourth->id()->toRfc4122()),
        );
    }

    /**
     * @return list<string>
     */
    private function rosterOf(Service $service, ?Stage $stage, string $day): array
    {
        return array_map(
            static fn (Uuid $id): string => $id->toRfc4122(),
            $this->repository()->rosterOn(new RosterKey($service->id(), $stage?->id(), LocalDate::of($day))),
        );
    }

    /**
     * @return array{Service, Stage}
     */
    private function structure(): array
    {
        $service = Structure::service();
        $third = Structure::stage($service);
        new Database(self::getContainer())->persisted($service, $third);

        return [$service, $third];
    }

    private function rosters(): Rosters
    {
        return new Rosters(self::getContainer());
    }

    private function repository(): RosterRepositoryInterface
    {
        $repository = self::getContainer()->get(RosterRepositoryInterface::class);
        self::assertInstanceOf(RosterRepositoryInterface::class, $repository);

        return $repository;
    }
}
