<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Organization;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Library\Types\Model\Integers;
use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Meetings\Domain\Entity\Schedules;
use App\Tests\Support\Context\Organization\Domain\Entity\ServedGroups;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Database;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

final class StructureLifecycleControllerTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    private const string ACTIVE = "status = 'active', opened_at = created_at";

    public function testArchivingAServicePausesItsSchedulesKeepsItsSessionsAndUnknownIdsAreNotFound(): void
    {
        self::mockTime('2026-11-08T12:00:00+01:00');
        $youth = Structure::service('Youth Meeting');
        new Database(self::getContainer())->persisted($youth);
        $schedules = new Schedules(self::getContainer());
        $evening = $schedules->weekly($youth, 'Youth evening', 1);
        $tomorrow = $schedules->session($evening, '2026-11-09');
        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->post('services', $youth->id()->toRfc4122(), 'archive');

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('archived', true);
        self::assertSame([false, 'scheduled'], $this->stateOf($evening, $tomorrow));
        $this->jsonRequest('GET', '/api/services?status=archived');
        $this->assertJsonPath('items.0.name', 'Youth Meeting');
        $this->jsonRequest('POST', '/api/sessions/' . $tomorrow->id()->toRfc4122() . '/open');
        $this->assertProblem(409, 'meetings.serviceArchivedRestoreFirst', ['name' => 'Youth Meeting']);

        $this->post('services', $youth->id()->toRfc4122(), 'archive');
        $this->assertProblem(409, 'services.alreadyArchived', ['name' => 'Youth Meeting']);
        $this->post('services', $youth->id()->toRfc4122(), 'restore');
        self::assertResponseIsSuccessful();
        $this->assertJsonPath('archived', false);
        self::assertSame([false, 'scheduled'], $this->stateOf($evening, $tomorrow));
        $this->post('services', $youth->id()->toRfc4122(), 'restore');
        $this->assertProblem(409, 'services.notArchived', ['name' => 'Youth Meeting']);

        foreach (['archive', 'restore'] as $action) {
            foreach (['not-a-service', Uuid::v7()->toRfc4122()] as $id) {
                $this->post('services', $id, $action);
                $this->assertProblem(404, 'notFound.service');
                $this->post('stages', $id, $action);
                $this->assertProblem(404, 'notFound.stage');
            }
        }
    }

    public function testAServiceOrStageTakingAttendanceStaysAsItIs(): void
    {
        self::mockTime('2026-11-08T12:00:00+01:00');
        [$school, $third] = $this->school();
        $schedules = new Schedules(self::getContainer());
        $class = $schedules->weekly($school, 'Sunday class', 7, stage: $third);
        $schedules->session($class, '2026-11-08', self::ACTIVE);
        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->post('services', $school->id()->toRfc4122(), 'archive');
        $this->assertProblem(409, 'services.takingAttendance', ['name' => 'Sunday School']);
        $this->post('stages', $third->id()->toRfc4122(), 'archive');
        $this->assertProblem(409, 'stages.takingAttendance', ['name' => '3rd Class']);

        self::assertSame(0, $this->numberOf('SELECT count(*) FROM organization.service WHERE archived_at IS NOT NULL'));
        self::assertSame(0, $this->numberOf('SELECT count(*) FROM organization.stage WHERE archived_at IS NOT NULL'));
        self::assertSame(1, $this->numberOf('SELECT count(*) FROM meetings.meeting_schedule WHERE active'));
    }

    public function testALeaderArchivesAndRestoresAStage(): void
    {
        [$school, , $first] = $this->school();
        $mina = new Rosters(self::getContainer())->person('Mina');
        new Rosters(self::getContainer())->place($mina, $school, $first, '2026-09-01');

        new ServedGroups(self::getContainer())->everyService($this->signInWithRoles(
            'leader@example.org',
            'service_leader',
        ));

        $this->post('stages', $first->id()->toRfc4122(), 'archive');

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('archived', true);
        self::assertSame(['2nd Class 0', '3rd Class 1', '1st Class 2'], $this->positions());
        self::assertSame(1, $this->numberOf('SELECT count(*) FROM people.membership WHERE ends_on IS NULL'));

        $this->post('stages', $first->id()->toRfc4122(), 'restore');
        self::assertResponseIsSuccessful();
        self::assertSame(['2nd Class 0', '3rd Class 1', '1st Class 2'], $this->positions());
        $this->assertJsonPath('archived', false);

        $this->post('services', $school->id()->toRfc4122(), 'archive');
        $this->assertProblem(403, 'services.adminOnlyArchive');
        $this->post('services', $school->id()->toRfc4122(), 'restore');
        $this->assertProblem(403, 'services.adminOnlyRestore');
    }

    public function testStagesOfAnArchivedServiceAndServantsAreRefused(): void
    {
        [$school, $third] = $this->school();
        $school->archive(Structure::now());
        new Database(self::getContainer())->persisted($school);

        new ServedGroups(self::getContainer())->everyService($this->signInWithRoles(
            'leader@example.org',
            'service_leader',
        ));

        $this->post('stages', $third->id()->toRfc4122(), 'archive');
        $this->assertProblem(409, 'services.archivedNoStageChanges', ['name' => 'Sunday School']);

        $this->browser()->getCookieJar()->clear();
        new ServedGroups(self::getContainer())->everyService($this->signInWithRoles('servant@example.org', 'servant'));
        $this->post('stages', $third->id()->toRfc4122(), 'archive');
        $this->assertProblem(403, 'forbidden');
    }

    public function testImpactCountsActiveSchedulesAndUpcomingScheduledSessions(): void
    {
        self::mockTime('2026-11-08T12:00:00+01:00');
        [$school, $third] = $this->school();
        $schedules = new Schedules(self::getContainer());
        $class = $schedules->weekly($school, 'Sunday class', 7, stage: $third);
        $schedules->session($class, '2026-11-08');
        $schedules->session($class, '2026-11-15');
        $schedules->session($class, '2026-11-01');
        $schedules->session($class, '2026-11-22', "status = 'canceled'");
        $paused = $schedules->weekly($school, 'Paused meeting', 3);
        $schedules->session($paused, '2026-11-11');

        new Database(self::getContainer())
            ->connection()
            ->update('meetings.meeting_schedule', ['active' => 'false'], ['id' => $paused->id()->toRfc4122()]);

        new ServedGroups(self::getContainer())->everyService($this->signInWithRoles('servant@example.org', 'servant'));

        $this->jsonRequest('GET', '/api/services/insights');
        $this->assertJsonPath('items.0.serviceId', $school->id()->toRfc4122());
        $this->assertJsonPath('items.0.impact', ['activeSchedules' => 1, 'upcomingSessions' => 3]);
        $this->jsonRequest('GET', '/api/services/' . $school->id()->toRfc4122() . '/stages');
        $this->assertJsonPath('stages.2.impact', ['activeSchedules' => 1, 'upcomingSessions' => 2]);
        $this->assertJsonPath('stages.0.impact', ['activeSchedules' => 0, 'upcomingSessions' => 0]);
    }

    private function post(string $kind, string $id, string $action): void
    {
        $this->jsonRequest('POST', '/api/' . $kind . '/' . $id . '/' . $action);
    }

    /**
     * @return array{bool, string}
     */
    private function stateOf(Schedule $schedule, Session $session): array
    {
        $connection = new Database(self::getContainer())->connection();

        return [
            $connection->fetchOne('SELECT active FROM meetings.meeting_schedule WHERE id = ?', [$schedule->id()->toRfc4122()])
                === true,
            Type::of($connection->fetchOne('SELECT status FROM meetings.meeting_session WHERE id = ?', [$session->id()->toRfc4122()]))->toString(),
        ];
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

    private function numberOf(string $sql): int
    {
        return Integers::of(
            new Database(self::getContainer())
                ->connection()
                ->fetchOne($sql),
        );
    }

    /**
     * @return array{Service, Stage, Stage, Stage}
     */
    private function school(): array
    {
        $school = Structure::service();
        $first = Structure::stage($school, '1st Class', 0);
        $second = Structure::stage($school, '2nd Class', 1);
        $third = Structure::stage($school, '3rd Class', 2);
        new Database(self::getContainer())->persisted($school, $first, $second, $third);

        return [$school, $third, $first, $second];
    }
}
