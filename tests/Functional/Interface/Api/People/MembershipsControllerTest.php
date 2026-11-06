<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\People;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\People\Domain\Repository\RosterRepositoryInterface;
use App\Context\People\Domain\ValueObject\RosterKey;
use App\Library\Time\Model\LocalDate;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Database;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

final class MembershipsControllerTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testAddingStartsTodayAndASecondOpenPlacementIsRefused(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        [$school, $third, $fourth] = $this->structure();
        $mina = new Rosters(self::getContainer())->person('Mina', 'Hanna');
        $this->signInWithRoles('servant@example.org', 'servant');

        $this->add($mina->id()->toRfc4122(), $school, $fourth);
        self::assertResponseStatusCodeSame(201);
        $this->assertJsonPath('startsOn', '2026-11-08');
        $this->assertJsonPath('stage.name', '4th Class');
        $this->assertJsonPath('personId', $mina->id()->toRfc4122());

        $this->add($mina->id()->toRfc4122(), $school, $fourth);
        $this->assertProblem(409, 'people.alreadyIn', ['name' => 'Mina Hanna', 'where' => '4th Class']);

        $this->add($mina->id()->toRfc4122(), $school, $third);
        self::assertResponseStatusCodeSame(201);

        $this->add($mina->id()->toRfc4122(), $school, null);
        self::assertResponseStatusCodeSame(201);
        $this->add($mina->id()->toRfc4122(), $school, null);
        $this->assertProblem(409, 'people.alreadyIn', ['name' => 'Mina Hanna', 'where' => 'Sunday School']);
    }

    public function testAnOpenMembershipThatStartsLaterIsAConflictNotAnError(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        [$school, $third] = $this->structure();
        $rosters = new Rosters(self::getContainer());
        $mina = $rosters->person('Mina', 'Hanna');
        $rosters->place($mina, $school, $third, '2026-11-09');
        $this->signInWithRoles('servant@example.org', 'servant');

        $this->add($mina->id()->toRfc4122(), $school, $third);

        $this->assertProblem(409, 'people.alreadyIn', ['name' => 'Mina Hanna', 'where' => '3rd Class']);
    }

    public function testUnknownPeopleAndInvalidPlacementsAreRefused(): void
    {
        [$school] = $this->structure();
        $old = Structure::stage($school, 'Old Class', position: 2);
        $old->archive(Structure::now());
        new Database(self::getContainer())->persisted($old);
        $mina = new Rosters(self::getContainer())->person('Mina');
        $this->signInWithRoles('servant@example.org', 'servant');

        $this->add(Uuid::v7()->toRfc4122(), $school, null);
        $this->assertProblem(404, 'notFound.person');

        $this->add('not-a-uuid', $school, null);
        $this->assertProblem(404, 'notFound.person');

        $this->add($mina->id()->toRfc4122(), $school, $old);
        $this->assertFieldError('stageId', 'meetings.stageArchived', ['name' => 'Old Class']);

        $this->jsonRequest('POST', '/api/people/' . $mina->id()->toRfc4122() . '/memberships', [
            'serviceId' => Uuid::v7()->toRfc4122(),
        ]);

        $this->assertFieldError('serviceId', 'meetings.chooseService');
    }

    public function testEndingKeepsThePastRosterAndASameDayMembershipIsDeleted(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        [$school, $third] = $this->structure();
        $rosters = new Rosters(self::getContainer());
        $mina = $rosters->person('Mina');
        $marina = $rosters->person('Marina');
        $rosters->place($mina, $school, $third, '2026-09-01');
        $rosters->place($marina, $school, $third, '2026-11-08');
        $this->signInWithRoles('leader@example.org', 'service_leader');

        $this->end($this->membershipOf($mina->id()->toRfc4122()));
        self::assertResponseStatusCodeSame(204);
        $this->end($this->membershipOf($marina->id()->toRfc4122()));
        self::assertResponseStatusCodeSame(204);

        self::assertSame([$mina->id()->toRfc4122()], $this->rosterOn($school, $third, '2026-11-07'));
        self::assertSame([], $this->rosterOn($school, $third, '2026-11-08'));

        self::assertSame(
            1,
            new Database(self::getContainer())
                ->connection()
                ->fetchOne('SELECT count(*) FROM people.membership'),
        );

        $this->end($this->membershipOf($mina->id()->toRfc4122()));
        $this->assertProblem(409, 'people.membershipEnded');

        $this->end(Uuid::v7()->toRfc4122());
        $this->assertProblem(404, 'notFound.membership');

        $this->end('not-a-uuid');
        $this->assertProblem(404, 'notFound.membership');

        $this->jsonRequest('DELETE', '/api/memberships/' . $this->membershipOf($mina->id()->toRfc4122()));
        $this->assertProblem(404, 'notFound.route');
    }

    public function testParentsMayNeitherAddNorEndMemberships(): void
    {
        [$school, $third] = $this->structure();
        $rosters = new Rosters(self::getContainer());
        $mina = $rosters->person('Mina');
        $rosters->place($mina, $school, $third, '2026-09-01');
        $this->signInWithRoles('parent@example.org', 'parent');

        $this->add($mina->id()->toRfc4122(), $school, null);
        $this->assertProblem(403, 'people.changeNotAllowed');

        $this->end($this->membershipOf($mina->id()->toRfc4122()));
        $this->assertProblem(403, 'people.removeNotAllowed');
    }

    private function add(string $personId, Service $service, ?Stage $stage): void
    {
        $this->jsonRequest('POST', '/api/people/' . $personId . '/memberships', [
            'serviceId' => $service->id()->toRfc4122(),
            'stageId' => $stage?->id()->toRfc4122(),
        ]);
    }

    private function end(string $membershipId): void
    {
        $this->jsonRequest('POST', '/api/memberships/' . $membershipId . '/end');
    }

    private function membershipOf(string $personId): string
    {
        $id = new Database(self::getContainer())
            ->connection()
            ->fetchOne('SELECT id FROM people.membership WHERE person_id = ?', [$personId]);

        self::assertIsString($id);

        return $id;
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
        $third = Structure::stage($service);
        $fourth = Structure::stage($service, '4th Class', position: 1);
        new Database(self::getContainer())->persisted($service, $third, $fourth);

        return [$service, $third, $fourth];
    }
}
