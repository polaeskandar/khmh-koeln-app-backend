<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\People;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Organization\Domain\Entity\ServedGroups;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Database;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class MembershipsControllerServiceOrStagesTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testAPersonIsEitherOnTheServiceItselfOrOnItsStages(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        [$school, $third, $fourth] = $this->structure();
        $youth = Structure::service('Youth');
        $teens = Structure::stage($youth, 'Teens');
        new Database(self::getContainer())->persisted($youth, $teens);
        $rosters = new Rosters(self::getContainer());
        $mina = $rosters->person('Mina', 'Hanna');
        $marina = $rosters->person('Marina', 'Hanna');
        $this->signInWithRoles('office@example.org', 'administrator');

        $this->add($mina->id()->toRfc4122(), $school, null);
        self::assertResponseStatusCodeSame(201);
        $this->add($mina->id()->toRfc4122(), $school, $third);
        $this->assertProblem(409, 'people.alreadyInService', ['name' => 'Mina Hanna', 'where' => 'Sunday School']);
        $this->add($mina->id()->toRfc4122(), $youth, $teens);
        self::assertResponseStatusCodeSame(201);

        $this->add($marina->id()->toRfc4122(), $school, $fourth);
        self::assertResponseStatusCodeSame(201);
        $this->add($marina->id()->toRfc4122(), $school, $third);
        self::assertResponseStatusCodeSame(201);
        $this->add($marina->id()->toRfc4122(), $school, null);
        $this->assertProblem(409, 'people.alreadyInService', ['name' => 'Marina Hanna', 'where' => '4th Class']);
        $this->add($marina->id()->toRfc4122(), $youth, null);
        self::assertResponseStatusCodeSame(201);

        self::assertSame(
            5,
            new Database(self::getContainer())
                ->connection()
                ->fetchOne('SELECT count(*) FROM people.membership'),
        );
    }

    public function testOnlyMembershipsThatHaveNotEndedCount(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        [$school, $third] = $this->structure();
        $rosters = new Rosters(self::getContainer());
        $mina = $rosters->person('Mina', 'Hanna');
        $rosters->place($mina, $school, $third, '2026-09-01', '2026-11-07');
        $marina = $rosters->person('Marina', 'Hanna');
        $rosters->place($marina, $school, null, '2026-11-09');
        $this->signInWithRoles('office@example.org', 'administrator');

        $this->add($mina->id()->toRfc4122(), $school, null);
        self::assertResponseStatusCodeSame(201);

        $this->add($marina->id()->toRfc4122(), $school, $third);
        $this->assertProblem(409, 'people.alreadyInService', ['name' => 'Marina Hanna', 'where' => 'Sunday School']);
    }

    public function testAMoveToTheServiceItselfStaysAllowedNextToAnotherStage(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        [$school, $third, $fourth] = $this->structure();
        $rosters = new Rosters(self::getContainer());
        $mina = $rosters->person('Mina');
        $rosters->place($mina, $school, $third, '2026-09-01');
        $rosters->place($mina, $school, $fourth, '2026-09-01');

        new ServedGroups(self::getContainer())->everyService($this->signInWithRoles(
            'leader@example.org',
            'service_leader',
        ));

        $this->jsonRequest(
            'POST',
            '/api/memberships/' . $this->membershipIn($mina->id()->toRfc4122(), $third) . '/move',
            [
                'stageId' => null,
            ],
        );

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('stage', null);
        $this->assertJsonPath('startsOn', '2026-11-08');
    }

    private function add(string $personId, Service $service, ?Stage $stage): void
    {
        $this->jsonRequest('POST', '/api/people/' . $personId . '/memberships', [
            'serviceId' => $service->id()->toRfc4122(),
            'stageId' => $stage?->id()->toRfc4122(),
        ]);
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
