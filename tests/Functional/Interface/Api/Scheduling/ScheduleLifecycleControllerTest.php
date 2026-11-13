<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Scheduling;

use App\Context\Scheduling\Domain\Entity\Schedule;
use App\Library\Types\Model\Integers;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Learning\Domain\Entity\Lessons;
use App\Tests\Support\Context\Organization\Domain\Entity\ServedGroups;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Context\Scheduling\Domain\Entity\Schedules;
use App\Tests\Support\Database;
use App\Tests\Support\Logs;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Uid\Uuid;

final class ScheduleLifecycleControllerTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testAnArchivedServiceOrStageMustBeRestoredFirst(): void
    {
        $service = Structure::service();
        $stage = Structure::stage($service);
        $other = Structure::service('Youth Meeting');
        $other->archive(Structure::now());
        $stage->archive(Structure::now());
        new Database(self::getContainer())->persisted($service, $stage, $other);
        $schedules = new Schedules(self::getContainer());
        $inArchivedService = $schedules->weekly($other, 'Youth evening', 5);
        $inArchivedStage = $schedules->weekly($service, 'Sunday class', 7, stage: $stage);
        $active = $schedules->weekly($service, 'Service meeting', 3);

        new ServedGroups(self::getContainer())->everyService($this->signInWithRoles(
            'leader@example.org',
            'service_leader',
        ));

        $this->post($inArchivedService, 'activate');
        $this->assertProblem(409, 'meetings.serviceArchivedRestoreFirst', ['name' => 'Youth Meeting']);
        $this->post($inArchivedStage, 'activate');
        $this->assertProblem(409, 'meetings.stageArchivedRestoreFirst', ['name' => '3rd Class']);
        $this->post($active, 'activate');
        self::assertResponseIsSuccessful();
        $this->assertJsonPath('createdSessions', 0);
    }

    public function testActivatingOnSaturdayCreatesSundaysSession(): void
    {
        self::mockTime('2026-11-07T10:00:00+01:00');
        $sunday = $this->inactiveSunday();

        new ServedGroups(self::getContainer())->everyService($this->signInWithRoles(
            'leader@example.org',
            'service_leader',
        ));

        $this->post($sunday, 'activate');

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('id', $sunday->id()->toRfc4122());
        $this->assertJsonPath('createdSessions', 1);

        self::assertSame(
            1,
            $this->numberOf("SELECT count(*) FROM scheduling.session WHERE occurrence_date = '2026-11-08'"),
        );
    }

    public function testDeactivatingKeepsTheSessionsButTheGeneratorAddsNone(): void
    {
        self::mockTime('2026-11-07T10:00:00+01:00');
        $service = Structure::service();
        new Database(self::getContainer())->persisted($service);
        $sunday = new Schedules(self::getContainer())->weekly($service, 'Sunday class', 7);
        new Schedules(self::getContainer())->session($sunday, '2026-11-08');

        new ServedGroups(self::getContainer())->everyService($this->signInWithRoles(
            'leader@example.org',
            'service_leader',
        ));

        $this->post($sunday, 'deactivate');
        self::assertResponseIsSuccessful();
        $this->assertJsonPath('id', $sunday->id()->toRfc4122());
        $this->post($sunday, 'deactivate');
        self::assertResponseIsSuccessful();

        self::mockTime('2026-11-14T10:00:00+01:00');
        $application = new Application($this->browser()->getKernel());
        $application->setAutoExit(false);
        new ApplicationTester($application)->run(['command' => 'app:sessions:generate']);

        self::assertSame(1, $this->numberOf('SELECT count(*) FROM scheduling.session'));
    }

    public function testOnlyAScheduleWithoutSessionsOrLessonsIsDeleted(): void
    {
        $service = Structure::service();
        $stage = Structure::stage($service);
        new Database(self::getContainer())->persisted($service, $stage);
        $schedules = new Schedules(self::getContainer());
        $unused = $schedules->weekly($service, 'Mistake', 2);
        $canceled = $schedules->weekly($service, 'Canceled ahead', 3);
        $schedules->session($canceled, '2026-12-02', "status = 'canceled'");
        $planned = $schedules->weekly($service, 'Planned', 7, stage: $stage);
        new Lessons(self::getContainer())->stored($stage, $planned, '2026-12-06');

        new ServedGroups(self::getContainer())->everyService($this->signInWithRoles(
            'leader@example.org',
            'service_leader',
        ));

        $this->jsonRequest('DELETE', '/api/schedules/' . $canceled->id()->toRfc4122());
        $this->assertProblem(409, 'meetings.scheduleHasSessions', ['count' => 1]);
        $this->jsonRequest('DELETE', '/api/schedules/' . $planned->id()->toRfc4122());
        $this->assertProblem(409, 'meetings.scheduleHasLessons', ['count' => 1]);
        $this->jsonRequest('DELETE', '/api/schedules/' . $unused->id()->toRfc4122());
        self::assertResponseStatusCodeSame(204);

        self::assertSame(2, $this->numberOf('SELECT count(*) FROM scheduling.schedule'));
    }

    public function testALessonOnTheScheduleAndADeletionBothLockTheScheduleFirst(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $service = Structure::service();
        $stage = Structure::stage($service);
        new Database(self::getContainer())->persisted($service, $stage);
        $class = new Schedules(self::getContainer())->weekly($service, 'Sunday class', 7, stage: $stage);

        $leader = new ServedGroups(self::getContainer())->everyService($this->signInWithRoles(
            'leader@example.org',
            'service_leader',
        ));

        $this->jsonRequest('POST', '/api/stages/' . $stage->id()->toRfc4122() . '/lessons', [
            'details' => ['title' => 'The Good Samaritan'],
            'meeting' => ['scheduleId' => $class->id()->toRfc4122(), 'date' => '2026-11-15'],
            'responsibleId' => $leader,
        ]);

        self::assertResponseIsSuccessful();
        $this->assertScheduleLockedBefore('FOR SHARE', 'INSERT INTO learning.lesson');

        $this->jsonRequest('DELETE', '/api/schedules/' . $class->id()->toRfc4122());

        $this->assertProblem(409, 'meetings.scheduleHasLessons', ['count' => 1]);
        $this->assertScheduleLockedBefore('FOR UPDATE', 'FROM learning.lesson WHERE schedule_id');
    }

    public function testServantsAndUnknownSchedulesAreRefused(): void
    {
        $sunday = $this->inactiveSunday();
        new ServedGroups(self::getContainer())->everyService($this->signInWithRoles('servant@example.org', 'servant'));

        foreach (['activate', 'deactivate'] as $action) {
            $this->post($sunday, $action);
            $this->assertProblem(403, 'forbidden');
        }

        $this->jsonRequest('DELETE', '/api/schedules/' . $sunday->id()->toRfc4122());
        $this->assertProblem(403, 'forbidden');

        $this->browser()->getCookieJar()->clear();

        new ServedGroups(self::getContainer())->everyService($this->signInWithRoles(
            'leader@example.org',
            'service_leader',
        ));

        $this->jsonRequest('POST', '/api/schedules/' . Uuid::v7()->toRfc4122() . '/activate');
        $this->assertProblem(404, 'notFound.schedule');

        foreach (['/activate', '/deactivate'] as $action) {
            $this->jsonRequest('POST', '/api/schedules/not-a-schedule' . $action);
            $this->assertProblem(404, 'notFound.schedule');
        }
    }

    private function inactiveSunday(): Schedule
    {
        $service = Structure::service();
        new Database(self::getContainer())->persisted($service);
        $sunday = new Schedules(self::getContainer())->weekly($service, 'Sunday class', 7);

        new Database(self::getContainer())
            ->connection()
            ->update('scheduling.schedule', ['active' => 'false'], ['id' => $sunday->id()->toRfc4122()]);

        return $sunday;
    }

    private function assertScheduleLockedBefore(string $lock, string $statement): void
    {
        $lines = new Logs(self::getContainer())->containing('');

        $lastOf = static fn (string $needle): int => max([
            -1,
            ...array_keys(array_filter($lines, static fn (string $line): bool => str_contains($line, $needle))),
        ]);

        $locked = $lastOf('FROM scheduling.schedule WHERE id = ? ' . $lock);

        self::assertGreaterThan(-1, $locked);
        self::assertGreaterThan($locked, $lastOf($statement));
    }

    private function post(Schedule $schedule, string $action): void
    {
        $this->jsonRequest('POST', '/api/schedules/' . $schedule->id()->toRfc4122() . '/' . $action);
    }

    private function numberOf(string $sql): int
    {
        return Integers::of(
            new Database(self::getContainer())
                ->connection()
                ->fetchOne($sql),
        );
    }
}
