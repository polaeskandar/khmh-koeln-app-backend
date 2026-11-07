<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Meetings;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Interface\Api\Meetings\ScheduleBodies;
use App\Tests\Support\Interface\Api\Meetings\ScheduleRows;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

final class ScheduleWritesControllerEditTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    private const string TODAYS_PLAN = 'Sunday class|Hall|12:00:00|13:30:00|expected';

    public function testAnEditReplansTheSessionsThatHaveNotStartedAndKeepsTheOthers(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $rows = new ScheduleRows(self::getContainer());
        [$service] = $rows->structure();
        $id = $this->schedule($service, null, '2026-11-08');
        $rows->openSessionsOf($id);

        $this->jsonRequest('PUT', '/api/schedules/' . $id, ScheduleBodies::replaced([
            'details' => ['location' => 'Church'],
            'recurrence' => ['weekdays' => [1, 7]],
            'times' => ['startTime' => '18:00', 'endTime' => '19:00'],
        ]));

        self::assertResponseStatusCodeSame(200);
        $this->assertJsonPath('createdSessions', 1);
        self::assertSame('Church', $rows->column($id, 'location'));
        self::assertSame(self::TODAYS_PLAN, $rows->sessionOn($id, '2026-11-08'));
        self::assertSame('Sunday class|Church|18:00:00|19:00:00|expected', $rows->sessionOn($id, '2026-11-09'));

        $this->jsonRequest('PUT', '/api/schedules/' . $id, ScheduleBodies::replaced([
            'details' => ['name' => 'Evening   class', 'location' => ''],
            'recurrence' => ['weekdays' => [1, 7]],
            'times' => ['startTime' => '17:00', 'endTime' => null],
            'rosterMode' => 'open',
        ]));

        $this->assertJsonPath('createdSessions', 0);
        self::assertSame(self::TODAYS_PLAN, $rows->sessionOn($id, '2026-11-08'));
        self::assertSame('Evening class|17:00:00|open', $rows->sessionOn($id, '2026-11-09'));
    }

    public function testAnInactiveScheduleGetsNoNewSessions(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $rows = new ScheduleRows(self::getContainer());
        [$service] = $rows->structure();
        $id = $this->schedule($service, null, '2026-11-15');
        $rows->deactivate($id);

        $this->jsonRequest('PUT', '/api/schedules/' . $id, ScheduleBodies::replaced([
            'recurrence' => ['weekdays' => [1, 7], 'span' => ['startsOn' => '2026-11-08']],
        ]));

        self::assertResponseStatusCodeSame(200);
        $this->assertJsonPath('createdSessions', 0);
    }

    public function testAnEditReplacesTheWholeScheduleAndTheRosterCannotMove(): void
    {
        $rows = new ScheduleRows(self::getContainer());
        [$service] = $rows->structure();
        $id = $this->schedule($service, null, '2026-11-15');

        $this->jsonRequest('PUT', '/api/schedules/' . $id, [
            'details' => ['name' => 'Sunday class'],
            'recurrence' => [
                'frequency' => 'weekly',
                'interval' => 2,
                'weekdays' => [7],
                'span' => [
                    'startsOn' => '2026-11-15',
                ],
            ],
            'times' => ['startTime' => '12:00'],
            'rosterMode' => 'open',
        ]);

        self::assertResponseStatusCodeSame(200);
        self::assertNull($rows->column($id, 'location'));
        self::assertNull($rows->column($id, 'end_time'));
        self::assertSame(2, $rows->column($id, 'interval'));
        self::assertSame('open', $rows->column($id, 'roster_mode'));

        $this->jsonRequest('PUT', '/api/schedules/' . $id, [
            ...ScheduleBodies::replaced(),
            'roster' => ['mode' => 'open'],
        ]);

        $this->assertFieldError('roster', 'validation.unexpectedField');

        $this->jsonRequest('PUT', '/api/schedules/' . $id, ['details' => ['name' => 'Sunday class']]);
        $this->assertFieldError('rosterMode', 'meetings.chooseRosterMode');
        $this->assertFieldError('recurrence.frequency', 'validation.invalidChoice');
        $this->assertFieldError('recurrence.span.startsOn', 'validation.chooseDate');
    }

    public function testUnknownSchedulesArchivedPlacesAndAccess(): void
    {
        $rows = new ScheduleRows(self::getContainer());
        [$service, $stage] = $rows->structure();
        $id = $this->schedule($service, $stage, '2026-11-15');

        $this->jsonRequest('PUT', '/api/schedules/' . Uuid::v7()->toRfc4122(), ScheduleBodies::replaced());
        $this->assertProblem(404, 'notFound.schedule');
        $this->jsonRequest('PUT', '/api/schedules/not-a-uuid', ScheduleBodies::replaced());
        $this->assertProblem(404, 'notFound.schedule');

        $rows->archive('organization.stage', $stage->id());
        $this->jsonRequest('PUT', '/api/schedules/' . $id, ScheduleBodies::replaced());
        $this->assertProblem(409, 'meetings.stageArchivedRestoreFirst', ['name' => '3rd Class']);

        $rows->archive('organization.service', $service->id());
        $this->jsonRequest('PUT', '/api/schedules/' . $id, ScheduleBodies::replaced());
        $this->assertProblem(409, 'meetings.serviceArchivedRestoreFirst', ['name' => 'Sunday School']);

        foreach (['servant', 'parent'] as $role) {
            $this->signInWithRoles($role . '@example.org', $role);
            $this->jsonRequest('PUT', '/api/schedules/' . $id, ScheduleBodies::replaced());
            $this->assertProblem(403, 'forbidden');
        }
    }

    private function schedule(Service $service, ?Stage $stage, string $startsOn): string
    {
        $this->signInWithRoles('leader@example.org', 'service_leader');
        $this->jsonRequest('POST', '/api/schedules', ScheduleBodies::created($service, $stage, $startsOn));

        return Type::of($this->responseJson()['id'] ?? null)->toString();
    }
}
