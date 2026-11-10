<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Organization;

use App\Context\Organization\Domain\Entity\Service;
use App\Library\Types\Model\Integers;
use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Learning\Domain\Entity\Lessons;
use App\Tests\Support\Context\Meetings\Domain\Entity\Schedules;
use App\Tests\Support\Context\Organization\Domain\Entity\ServedGroups;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Database;
use Symfony\Component\Uid\Uuid;

final class StructureLifecycleControllerDeleteTest extends ApiTestCase
{
    public function testAnUnusedServiceGoesWithItsStagesAndSchedules(): void
    {
        $service = Structure::service('Mistake');

        new Database(self::getContainer())->persisted(
            $service,
            Structure::stage($service, 'A', 0),
            Structure::stage($service, 'B', 1),
        );

        new Schedules(self::getContainer())->weekly($service, 'Mistaken meeting', 3);
        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->delete('services', $service->id()->toRfc4122());

        self::assertResponseStatusCodeSame(204);
        self::assertSame([0, 0, 0], $this->counts());
    }

    public function testHistoryLessonsAndMembersKeepAService(): void
    {
        $this->signInWithRoles('admin@example.org', 'administrator');
        $canceled = $this->service('With a canceled session');
        $schedules = new Schedules(self::getContainer());
        $schedules->session($schedules->weekly($canceled, 'Evening', 3), '2026-12-02', "status = 'canceled'");
        $planned = $this->service('With a lesson');
        $stage = Structure::stage($planned, '3rd Class');
        new Database(self::getContainer())->persisted($stage);
        new Lessons(self::getContainer())->stored($stage, null, '2026-12-06');
        $left = $this->service('With an ended membership');

        new Rosters(self::getContainer())->place(
            new Rosters(self::getContainer())->person('Mina'),
            $left,
            null,
            '2026-09-01',
            '2026-10-01',
        );

        $before = $this->counts();

        $this->delete('services', $canceled->id()->toRfc4122());
        $this->assertProblem(409, 'services.hasHistory', ['name' => 'With a canceled session', 'count' => 1]);
        $this->delete('services', $planned->id()->toRfc4122());
        $this->assertProblem(409, 'services.hasLessons', ['name' => 'With a lesson', 'count' => 1]);
        $this->delete('services', $left->id()->toRfc4122());
        $this->assertProblem(409, 'services.hasMembers', ['name' => 'With an ended membership', 'count' => 1]);

        self::assertSame($before, $this->counts());
    }

    public function testLeadersMayDeleteStagesButNotServices(): void
    {
        $service = $this->service('Sunday School');
        $first = Structure::stage($service, '1st Class', 0);
        $unused = Structure::stage($service, 'Unused', 1);
        $third = Structure::stage($service, '3rd Class', 2);
        $used = Structure::stage($service, 'Used', 3);
        new Database(self::getContainer())->persisted($first, $unused, $third, $used);

        new Rosters(self::getContainer())->place(
            new Rosters(self::getContainer())->person('Mina'),
            $service,
            $used,
            '2026-09-01',
        );

        new ServedGroups(self::getContainer())->everyService($this->signInWithRoles(
            'leader@example.org',
            'service_leader',
        ));

        $this->delete('services', $service->id()->toRfc4122());
        $this->assertProblem(403, 'services.adminOnlyDelete');
        $this->delete('stages', $unused->id()->toRfc4122());
        self::assertResponseStatusCodeSame(204);
        self::assertSame(['1st Class 0', '3rd Class 1', 'Used 2'], $this->positions());
        $this->delete('stages', $used->id()->toRfc4122());
        $this->assertProblem(409, 'stages.hasMembers', ['count' => 1, 'name' => 'Used']);
        $this->delete('services', Uuid::v7()->toRfc4122());
        $this->assertProblem(403, 'services.adminOnlyDelete');

        $this->browser()->getCookieJar()->clear();
        $this->signInWithRoles('admin@example.org', 'administrator');
        $this->delete('services', Uuid::v7()->toRfc4122());
        $this->assertProblem(404, 'notFound.service');
        $this->delete('services', 'not-a-service');
        $this->assertProblem(404, 'notFound.service');
        $this->delete('stages', 'not-a-stage');
        $this->assertProblem(404, 'notFound.stage');
    }

    private function service(string $name): Service
    {
        $service = Structure::service($name);
        new Database(self::getContainer())->persisted($service);

        return $service;
    }

    private function delete(string $kind, string $id): void
    {
        $this->jsonRequest('DELETE', '/api/' . $kind . '/' . $id);
    }

    /**
     * @return list<int>
     */
    private function counts(): array
    {
        $connection = new Database(self::getContainer())->connection();

        return array_map(static fn (string $table): int => Integers::of($connection->fetchOne('SELECT count(*) FROM '
        . $table)), ['organization.service', 'organization.stage', 'meetings.meeting_schedule']);
    }

    /**
     * @return list<string>
     */
    private function positions(): array
    {
        return array_map(
            static fn (mixed $row): string => Type::of($row)->toString(),
            new Database(self::getContainer())
                ->connection()
                ->fetchFirstColumn("SELECT name || ' ' || position FROM organization.stage ORDER BY position"),
        );
    }
}
