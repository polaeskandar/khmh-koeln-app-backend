<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\People;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\People\Domain\Entity\Person;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Database;
use App\Tests\Support\JsonBody;
use App\Tests\Support\Logs;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

final class PeopleControllerListTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testCurrentAndEndedMembershipsAreSeparated(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        [$school, $third] = $this->structure('Sunday School');
        $youth = Structure::service('Youth Meeting');
        new Database(self::getContainer())->persisted($youth);
        $rosters = new Rosters(self::getContainer());
        $mina = $rosters->person('Mina', 'Hanna');
        $rosters->place($mina, $school, $third, '2026-09-01');
        $rosters->place($mina, $youth, null, '2026-09-01');
        $bishoy = $rosters->person('Bishoy', 'Gerges');
        $rosters->place($bishoy, $school, $third, '2026-09-01', '2026-11-07');
        $this->signInWithRoles('leader@example.org', 'service_leader');

        $this->jsonRequest('GET', '/api/people');

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('today', '2026-11-08');
        $this->assertJsonPath('items.0.person.lastName', 'Gerges');
        $this->assertJsonPath('items.0.placements', []);
        $this->assertJsonPath('items.0.pastPlacements.0.stage.name', '3rd Class');
        $this->assertJsonPath('items.0.pastPlacements.0.endsOn', '2026-11-07');
        $this->assertJsonPath('items.1.person.firstName', 'Mina');
        self::assertCount(2, JsonBody::asArray($this->responseJson()['items'] ?? null, 'items'));
        $this->assertJsonPath('items.1.can.edit', true);
        $this->assertJsonPath('can.add', true);
    }

    public function testAStageOrServiceNarrowsTheListToItsRosterToday(): void
    {
        [$school, $third] = $this->structure('Sunday School');
        $fourth = Structure::stage($school, '4th Class', position: 1);
        new Database(self::getContainer())->persisted($fourth);
        $rosters = new Rosters(self::getContainer());
        $rosters->place($rosters->person('Mina'), $school, $third, '2026-09-01');
        $rosters->place($rosters->person('Marina'), $school, $fourth, '2026-09-01');
        $rosters->person('Kirollos');
        $this->signInWithRoles('servant@example.org', 'servant');

        $this->jsonRequest('GET', '/api/people?stageId=' . $third->id()->toRfc4122());
        $this->assertJsonPath('total', 1);
        $this->assertJsonPath('items.0.person.firstName', 'Mina');

        $this->jsonRequest('GET', '/api/people?serviceId=' . $school->id()->toRfc4122());
        $this->assertJsonPath('total', 2);

        $this->jsonRequest('GET', '/api/people?stageId=' . Uuid::v7()->toRfc4122());
        $this->assertProblem(404, 'notFound.stage');

        $this->jsonRequest('GET', '/api/people?serviceId=' . Uuid::v7()->toRfc4122());
        $this->assertProblem(404, 'notFound.service');

        $this->jsonRequest('GET', '/api/people?serviceId=not-an-id');
        $this->assertFieldError('serviceId', 'validation.invalidType');

        $this->jsonRequest('GET', '/api/people?stageId=');
        $this->assertFieldError('stageId', 'validation.invalidType');
    }

    public function testWordsAndStatusFilterThePeople(): void
    {
        $rosters = new Rosters(self::getContainer());
        $rosters->person('Mina', 'Hanna');
        $rosters->person('Mina', 'Iskander');
        $marina = $rosters->person('Marina', 'Hanna');

        new Database(self::getContainer())
            ->connection()
            ->update('people.person', ['status' => 'inactive'], ['id' => $marina->id()->toRfc4122()]);

        $this->signInWithRoles('servant@example.org', 'servant');

        $this->jsonRequest('GET', '/api/people?q=mina%20han');
        $this->assertJsonPath('total', 1);
        $this->assertJsonPath('items.0.person.lastName', 'Hanna');

        $this->jsonRequest('GET', '/api/people?status=inactive');
        $this->assertJsonPath('items.0.person.firstName', 'Marina');

        $this->jsonRequest('GET', '/api/people?status=all');
        $this->assertJsonPath('total', 3);

        $this->jsonRequest('GET', '/api/people?status=deleted');
        $this->assertFieldError('status', 'validation.invalidChoice');
    }

    public function testPagesHoldTwentyFiveRowsAndTheTotal(): void
    {
        $this->people(30);
        $this->signInWithRoles('servant@example.org', 'servant');

        $this->jsonRequest('GET', '/api/people?page=2');

        $this->assertJsonPath('page', 2);
        $this->assertJsonPath('pageSize', 25);
        $this->assertJsonPath('total', 30);
        self::assertCount(5, JsonBody::asArray($this->responseJson()['items'] ?? null, 'items'));

        $this->jsonRequest('GET', '/api/people?page=0');
        $this->assertFieldError('page', 'validation.tooSmall', ['min' => 1]);
    }

    public function testAFullPageCostsAsManyStatementsAsAnAlmostEmptyOne(): void
    {
        [$school, $third] = $this->structure('Sunday School');
        $rosters = new Rosters(self::getContainer());
        $rosters->place($rosters->person('Mina'), $school, $third, '2026-09-01');
        $this->signInWithRoles('servant@example.org', 'servant');

        $this->jsonRequest('GET', '/api/people');
        $few = count(new Logs(self::getContainer())->containing('SELECT'));

        foreach ($this->people(24) as $person) {
            $rosters->place($person, $school, $third, '2026-09-01');
        }

        $this->jsonRequest('GET', '/api/people');

        self::assertGreaterThan(0, $few);
        self::assertCount($few, new Logs(self::getContainer())->containing('SELECT'));
    }

    public function testParentsCannotListPeople(): void
    {
        $this->signInWithRoles('parent@example.org', 'parent');

        $this->jsonRequest('GET', '/api/people');

        $this->assertProblem(403, 'forbidden');
    }

    /**
     * @return list<Person>
     */
    private function people(int $count): array
    {
        $rosters = new Rosters(self::getContainer());

        return array_map(
            static fn (int $number): Person => $rosters->person('Person', sprintf('Number %02d', $number)),
            range(start: 1, end: $count),
        );
    }

    /**
     * @return array{Service, Stage}
     */
    private function structure(string $name): array
    {
        $service = Structure::service($name);
        $third = Structure::stage($service);
        new Database(self::getContainer())->persisted($service, $third);

        return [$service, $third];
    }
}
