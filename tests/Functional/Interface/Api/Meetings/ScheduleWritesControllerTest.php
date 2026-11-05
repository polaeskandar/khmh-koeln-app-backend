<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Meetings;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

final class ScheduleWritesControllerTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testAScheduleFromTodayGetsTodaysSessionAndOneFromNextWeekNone(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        [$service] = $this->structure();
        $this->signInWithRoles('leader@example.org', 'service_leader');

        $this->jsonRequest('POST', '/api/schedules', $this->body($service, null, '2026-11-08'));
        self::assertResponseStatusCodeSame(201);
        $this->assertJsonPath('createdSessions', 1);
        $id = $this->writtenId();

        self::assertStringEndsWith(
            '/api/schedules/' . $id,
            (string) $this->browser()->getResponse()->headers->get('Location'),
        );

        self::assertSame('Sunday class', $this->stored($id, 'name'));

        $this->jsonRequest('POST', '/api/schedules', $this->body($service, null, '2026-11-15'));
        $this->assertJsonPath('createdSessions', 0);
    }

    /**
     * @param array<string, array<string, mixed>> $changes
     * @param array<string, string|int> $params
     */
    #[DataProvider('invalidSchedules')]
    public function testEachInvalidFieldHasItsCode(
        array $changes,
        string $field,
        string $code,
        array $params = [],
    ): void {
        [$service, $stage] = $this->structure();
        $this->signInWithRoles('leader@example.org', 'service_leader');

        $this->jsonRequest(
            'POST',
            '/api/schedules',
            array_replace_recursive($this->body($service, $stage, '2026-11-08'), $changes),
        );

        $this->assertFieldError($field, $code, $params);
    }

    /**
     * @return iterable<string, array{0: array<string, array<string, mixed>>, 1: string, 2: string, 3?: array<string, string|int>}>
     */
    public static function invalidSchedules(): iterable
    {
        yield 'unknown service' => [
            ['roster' => ['serviceId' => '019a0000-0000-7000-8000-000000000000', 'stageId' => null]],
            'roster.serviceId',
            'meetings.chooseService',
        ];

        yield 'unknown stage' => [
            ['roster' => ['stageId' => '019a0000-0000-7000-8000-000000000000']],
            'roster.stageId',
            'meetings.chooseStage',
        ];

        yield 'no mode' => [
            ['roster' => ['mode' => 'everyone']],
            'roster.mode',
            'meetings.chooseRosterMode',
            ['choices' => '"expected", "open"'],
        ];

        yield 'blank name' => [['details' => ['name' => '   ']], 'details.name', 'meetings.scheduleNameRequired'];
        yield 'long name' => [
            ['details' => ['name' => str_repeat('a', times: 121)]],
            'details.name',
            'validation.tooLong',
            ['max' => 120, 'limit' => 120],
        ];

        yield 'bad time' => [['times' => ['startTime' => '25:00']], 'times.startTime', 'validation.invalidTime'];
        yield 'empty start time' => [['times' => ['startTime' => '']], 'times.startTime', 'validation.chooseTime'];
        yield 'end before start' => [['times' => ['endTime' => '11:00']], 'times.endTime', 'meetings.endAfterStart'];
        yield 'no interval' => [['recurrence' => ['interval' => 0]], 'recurrence.interval', 'recurrence.interval'];
    }

    public function testEditsKeepExistingSessionsAndAddTomorrowsWhenItBecomesAnOccurrence(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        [$service] = $this->structure();
        $this->signInWithRoles('leader@example.org', 'service_leader');
        $this->jsonRequest('POST', '/api/schedules', $this->body($service, null, '2026-11-08'));
        $id = $this->writtenId();

        $this->jsonRequest('PATCH', '/api/schedules/' . $id, [
            'details' => ['location' => 'Church'],
            'times' => ['startTime' => '18:00', 'endTime' => '19:00'],
        ]);

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('createdSessions', 0);
        self::assertSame('Sunday class', $this->stored($id, 'name'));
        self::assertSame('Church', $this->stored($id, 'location'));

        $this->jsonRequest('PATCH', '/api/schedules/' . $id, [
            'recurrence' => [
                'frequency' => 'weekly',
                'interval' => 1,
                'weekdays' => [1, 7],
                'span' => ['startsOn' => '2026-11-08'],
            ],
        ]);

        $this->assertJsonPath('createdSessions', 1);

        $this->jsonRequest('PATCH', '/api/schedules/' . $id, ['times' => ['startTime' => '20:00']]);
        $this->assertFieldError('times.endTime', 'meetings.endAfterStart');

        $this->jsonRequest('PATCH', '/api/schedules/' . $id, ['roster' => ['mode' => 'open']]);
        $this->assertFieldError('roster', 'validation.unexpectedField');
    }

    public function testAnEndTimeIsKeptWhenLeftOutAndClearedWhenSentEmpty(): void
    {
        [$service] = $this->structure();
        $this->signInWithRoles('leader@example.org', 'service_leader');
        $this->jsonRequest('POST', '/api/schedules', $this->body($service, null, '2026-11-08'));
        $id = $this->writtenId();

        $this->jsonRequest('PATCH', '/api/schedules/' . $id, ['times' => ['startTime' => '12:30']]);
        self::assertSame('13:30:00', $this->stored($id, 'end_time'));

        $this->jsonRequest('PATCH', '/api/schedules/' . $id, ['times' => ['endTime' => null]]);
        self::assertResponseIsSuccessful();
        self::assertNull($this->stored($id, 'end_time'));
    }

    public function testUnknownSchedulesArchivedServicesAndAccess(): void
    {
        [$service] = $this->structure();
        $this->signInWithRoles('leader@example.org', 'service_leader');
        $this->jsonRequest('POST', '/api/schedules', $this->body($service, null, '2026-11-15'));
        $id = $this->writtenId();

        $this->jsonRequest('PATCH', '/api/schedules/' . Uuid::v7()->toRfc4122(), ['details' => ['location' => 'Hall']]);
        $this->assertProblem(404, 'notFound.schedule');

        new Database(self::getContainer())
            ->connection()
            ->update(
                'organization.service',
                ['archived_at' => '2026-11-01 12:00:00+01'],
                ['id' => $service->id()->toRfc4122()],
            );

        $this->jsonRequest('PATCH', '/api/schedules/' . $id, ['details' => ['location' => 'Hall']]);
        $this->assertProblem(409, 'meetings.serviceArchivedRestoreFirst', ['name' => 'Sunday School']);

        $this->signInWithRoles('servant@example.org', 'servant');
        $this->jsonRequest('POST', '/api/schedules', $this->body($service, null, '2026-11-15'));
        $this->assertProblem(403, 'forbidden');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function body(Service $service, ?Stage $stage, string $startsOn): array
    {
        return [
            'roster' => [
                'serviceId' => $service->id()->toRfc4122(),
                'stageId' => $stage?->id()->toRfc4122(),
                'mode' => 'expected',
            ],
            'details' => ['name' => '  Sunday   class ', 'description' => '', 'location' => 'Hall'],
            'recurrence' => [
                'frequency' => 'weekly',
                'interval' => 1,
                'weekdays' => [7],
                'span' => ['startsOn' => $startsOn],
            ],
            'times' => ['startTime' => '12:00', 'endTime' => '13:30'],
        ];
    }

    private function writtenId(): string
    {
        return Type::of($this->responseJson()['id'] ?? null)->toString();
    }

    private function stored(string $id, string $column): mixed
    {
        return new Database(self::getContainer())
            ->connection()
            ->fetchOne('SELECT ' . $column . ' FROM meetings.meeting_schedule WHERE id = ?', [$id]);
    }

    /**
     * @return array{Service, Stage}
     */
    private function structure(): array
    {
        $service = Structure::service();
        $stage = Structure::stage($service);
        new Database(self::getContainer())->persisted($service, $stage);

        return [$service, $stage];
    }
}
