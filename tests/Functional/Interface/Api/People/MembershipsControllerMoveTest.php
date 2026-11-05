<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\People;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\People\Domain\Repository\RosterRepositoryInterface;
use App\Context\People\Domain\ValueObject\RosterKey;
use App\Library\Time\Model\LocalDate;
use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Database;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

final class MembershipsControllerMoveTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    private const string ROWS = <<<'SQL'
        SELECT coalesce(stage_id::text, '-') || ' ' || starts_on || ' ' || coalesce(ends_on::text, '-')
        FROM people.membership ORDER BY starts_on, stage_id
        SQL;

    public function testAMoveEndsTheOldMembershipYesterdayAndStartsANewOneToday(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        [$school, $second, $third] = $this->structure();
        $mina = new Rosters(self::getContainer())->person('Mina');
        new Rosters(self::getContainer())->place($mina, $school, $second, '2026-09-01');
        $this->signInWithRoles('leader@example.org', 'service_leader');

        $this->move($this->membershipOf($mina->id()->toRfc4122()), $third->id()->toRfc4122());

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('stage.name', '3rd Class');
        $this->assertJsonPath('startsOn', '2026-11-08');
        $this->assertJsonPath('endsOn', null);

        self::assertSame(
            [
                $second->id()->toRfc4122() . ' 2026-09-01 2026-11-07',
                $third->id()->toRfc4122() . ' 2026-11-08 -',
            ],
            $this->rows(),
        );

        self::assertSame([$mina->id()->toRfc4122()], $this->rosterOn($school, $second, '2026-11-07'));
        self::assertSame([], $this->rosterOn($school, $second, '2026-11-08'));
        self::assertSame([$mina->id()->toRfc4122()], $this->rosterOn($school, $third, '2026-11-08'));
    }

    public function testASameDayMembershipChangesInPlaceAndTheSameStageChangesNothing(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        [$school, $second, $third] = $this->structure();
        $mina = new Rosters(self::getContainer())->person('Mina');
        new Rosters(self::getContainer())->place($mina, $school, $second, '2026-11-08');
        $id = $this->membershipOf($mina->id()->toRfc4122());
        $this->signInWithRoles('leader@example.org', 'service_leader');

        $this->move($id, $third->id()->toRfc4122());
        $this->assertJsonPath('id', $id);
        $this->move($id, $third->id()->toRfc4122());
        self::assertResponseIsSuccessful();
        $this->assertJsonPath('id', $id);
        $this->move($id, null);
        $this->assertJsonPath('stage', null);

        self::assertSame(['- 2026-11-08 -'], $this->rows());
    }

    public function testRefusalsChangeNoRows(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        [$school, $second, $third] = $this->structure();
        $other = Structure::service('Youth');
        $youth = Structure::stage($other, 'Youth 1');
        $archived = Structure::stage($school, '5th Class', position: 3);
        $archived->archive(Structure::now());
        new Database(self::getContainer())->persisted($other, $youth, $archived);
        $rosters = new Rosters(self::getContainer());
        $mina = $rosters->person('Mina');
        $rosters->place($mina, $school, $second, '2026-09-01');
        $rosters->place($mina, $school, $third, '2026-09-01');
        $kiro = $rosters->person('Kiro');
        $rosters->place($kiro, $school, $second, '2026-09-01', '2026-10-01');
        $this->signInWithRoles('leader@example.org', 'service_leader');
        $before = $this->rows();
        $minaInSecond = $this->membershipIn($mina->id()->toRfc4122(), $second);

        $this->move($minaInSecond, $third->id()->toRfc4122());
        $this->assertProblem(409, 'people.alreadyIn', ['name' => 'Mina Iskander', 'where' => '3rd Class']);
        $this->move($this->membershipOf($kiro->id()->toRfc4122()), $third->id()->toRfc4122());
        $this->assertProblem(409, 'people.membershipEnded');
        $this->move($minaInSecond, $youth->id()->toRfc4122());
        $this->assertFieldError('stageId', 'meetings.chooseStage');
        $this->move($minaInSecond, $archived->id()->toRfc4122());
        $this->assertFieldError('stageId', 'meetings.stageArchived', ['name' => '5th Class']);
        $this->move(Uuid::v7()->toRfc4122(), $third->id()->toRfc4122());
        $this->assertProblem(404, 'notFound.membership');

        self::assertSame($before, $this->rows());
    }

    public function testMovingNeedsTheRightToChangeMembers(): void
    {
        [$school, $second, $third] = $this->structure();
        $mina = new Rosters(self::getContainer())->person('Mina');
        new Rosters(self::getContainer())->place($mina, $school, $second, '2026-09-01');
        $this->signInWithRoles('parent@example.org', 'parent');

        $this->move($this->membershipOf($mina->id()->toRfc4122()), $third->id()->toRfc4122());

        $this->assertProblem(403, 'people.changeNotAllowed');
    }

    private function move(string $membershipId, ?string $stageId): void
    {
        $this->jsonRequest('POST', '/api/memberships/' . $membershipId . '/move', ['stageId' => $stageId]);
    }

    private function membershipOf(string $personId): string
    {
        $id = new Database(self::getContainer())
            ->connection()
            ->fetchOne('SELECT id FROM people.membership WHERE person_id = ?', [$personId]);

        self::assertIsString($id);

        return $id;
    }

    private function membershipIn(string $personId, Stage $stage): string
    {
        $id = new Database(self::getContainer())
            ->connection()
            ->fetchOne('SELECT id FROM people.membership WHERE person_id = ? AND stage_id = ?', [
                $personId,
                $stage->id()->toRfc4122(),
            ]);

        self::assertIsString($id);

        return $id;
    }

    /**
     * @return list<string>
     */
    private function rows(): array
    {
        return array_map(
            static fn (mixed $row): string => Type::of($row)->toString(),
            new Database(self::getContainer())
                ->connection()
                ->fetchFirstColumn(self::ROWS),
        );
    }

    /**
     * @return list<string>
     */
    private function rosterOn(Service $service, Stage $stage, string $day): array
    {
        $rosters = self::getContainer()->get(RosterRepositoryInterface::class);
        self::assertInstanceOf(RosterRepositoryInterface::class, $rosters);

        return array_map(
            static fn (Uuid $id): string => $id->toRfc4122(),
            $rosters->rosterOn(new RosterKey($service->id(), $stage->id(), LocalDate::of($day))),
        );
    }

    /**
     * @return array{Service, Stage, Stage}
     */
    private function structure(): array
    {
        $service = Structure::service();
        $second = Structure::stage($service, '2nd Class', position: 1);
        $third = Structure::stage($service);
        new Database(self::getContainer())->persisted($service, $second, $third);

        return [$service, $second, $third];
    }
}
