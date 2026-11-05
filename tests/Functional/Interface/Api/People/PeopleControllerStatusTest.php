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
use App\Tests\Support\Logs;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

final class PeopleControllerStatusTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    private const string ROWS = <<<'SQL'
        SELECT coalesce(stage_id::text, service_id::text) || ' ' || starts_on || ' ' || coalesce(ends_on::text, '-')
        FROM people.membership ORDER BY starts_on, 1
        SQL;

    public function testDeactivatingEndsTheOpenMembershipsAndKeepsThePast(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        [$school, $fourth, $youth] = $this->structure();
        $rosters = new Rosters(self::getContainer());
        $lydia = $rosters->person('Lydia', 'Gerges');
        $rosters->place($lydia, $school, $fourth, '2026-09-01');
        $rosters->place($lydia, $youth, null, '2026-10-01');
        $rosters->place($lydia, $school, null, '2026-11-08');
        $this->signInWithRoles('leader@example.org', 'service_leader');

        $this->deactivate($lydia->id()->toRfc4122());

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('status', 'inactive');
        $this->assertJsonPath('firstName', 'Lydia');

        self::assertSame(
            [
                $fourth->id()->toRfc4122() . ' 2026-09-01 2026-11-07',
                $youth->id()->toRfc4122() . ' 2026-10-01 2026-11-07',
            ],
            $this->rows(),
        );

        self::assertSame([$lydia->id()->toRfc4122()], $this->rosterOn($school, $fourth, '2026-10-04'));
        self::assertSame([], $this->rosterOn($school, $fourth, '2026-11-08'));

        $this->jsonRequest('GET', '/api/people/directory?q=Lydia');
        $this->assertJsonPath('items', []);
    }

    public function testBothActionsAreIdempotentAndReactivatingPlacesNobody(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        [$school, $fourth] = $this->structure();
        $rosters = new Rosters(self::getContainer());
        $lydia = $rosters->person('Lydia', 'Gerges');
        $rosters->place($lydia, $school, $fourth, '2026-09-01');
        $this->signInWithRoles('servant@example.org', 'servant');
        $id = $lydia->id()->toRfc4122();

        $this->deactivate($id);
        $after = $this->rows();
        $this->deactivate($id);
        self::assertResponseIsSuccessful();
        $this->assertJsonPath('status', 'inactive');
        self::assertSame($after, $this->rows());

        $this->jsonRequest('POST', '/api/people/' . $id . '/reactivate');
        $this->assertJsonPath('status', 'active');
        $this->jsonRequest('POST', '/api/people/' . $id . '/reactivate');
        self::assertResponseIsSuccessful();
        $this->assertJsonPath('status', 'active');
        self::assertSame($after, $this->rows());
    }

    public function testAnInactivePersonCannotBeAddedToARoster(): void
    {
        [$school, $fourth] = $this->structure();
        $lydia = new Rosters(self::getContainer())->person('Lydia', 'Gerges');
        $this->signInWithRoles('leader@example.org', 'service_leader');
        $this->deactivate($lydia->id()->toRfc4122());

        $this->jsonRequest('POST', '/api/people/' . $lydia->id()->toRfc4122() . '/memberships', [
            'serviceId' => $school->id()->toRfc4122(),
            'stageId' => $fourth->id()->toRfc4122(),
        ]);

        $this->assertProblem(409, 'people.personInactive', ['name' => 'Lydia Gerges']);
        self::assertSame([], $this->rows());
    }

    public function testAMoveLocksThePersonBeforeTheMembershipLikeADeactivation(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        [$school, $fourth] = $this->structure();
        $rosters = new Rosters(self::getContainer());
        $lydia = $rosters->person('Lydia', 'Gerges');
        $rosters->place($lydia, $school, $fourth, '2026-09-01');

        $membership = Type::of(
            new Database(self::getContainer())
                ->connection()
                ->fetchOne('SELECT id FROM people.membership WHERE person_id = ?', [$lydia->id()->toRfc4122()]),
        )->toString();

        $this->signInWithRoles('leader@example.org', 'service_leader');

        $this->jsonRequest('POST', '/api/memberships/' . $membership . '/move', ['stageId' => null]);

        self::assertResponseIsSuccessful();
        $locks = new Logs(self::getContainer())->containing('FOR UPDATE');

        $lastOf = static fn (string $table): int => max([
            -1,
            ...array_keys(array_filter($locks, static fn (string $lock): bool => str_contains($lock, $table))),
        ]);

        self::assertGreaterThan(-1, $lastOf('people.person'));
        self::assertGreaterThan($lastOf('people.person'), $lastOf('people.membership'));
    }

    public function testUnknownPeopleAndParentsAreRefused(): void
    {
        $lydia = new Rosters(self::getContainer())->person('Lydia', 'Gerges');
        $this->signInWithRoles('leader@example.org', 'service_leader');
        $this->deactivate(Uuid::v7()->toRfc4122());
        $this->assertProblem(404, 'notFound.person');
        $this->deactivate('not-a-person');
        $this->assertProblem(404, 'notFound.person');

        $this->browser()->getCookieJar()->clear();
        $this->signInWithRoles('parent@example.org', 'parent');
        $this->deactivate($lydia->id()->toRfc4122());
        $this->assertProblem(403, 'people.changeNotAllowed');
        $this->jsonRequest('POST', '/api/people/' . $lydia->id()->toRfc4122() . '/reactivate');
        $this->assertProblem(403, 'people.changeNotAllowed');
    }

    private function deactivate(string $personId): void
    {
        $this->jsonRequest('POST', '/api/people/' . $personId . '/deactivate');
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
     * @return array{Service, Stage, Service}
     */
    private function structure(): array
    {
        $school = Structure::service();
        $fourth = Structure::stage($school, '4th Class');
        $youth = Structure::service('Youth Meeting');
        new Database(self::getContainer())->persisted($school, $fourth, $youth);

        return [$school, $fourth, $youth];
    }
}
