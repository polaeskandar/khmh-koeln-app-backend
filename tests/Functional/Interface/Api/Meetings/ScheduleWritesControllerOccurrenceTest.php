<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Meetings;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Meetings\Domain\Entity\Schedules;
use App\Tests\Support\Context\Organization\Domain\Entity\ServedGroups;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use App\Workflow\Meetings\GenerateSessionsWorkflow;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

final class ScheduleWritesControllerOccurrenceTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testADateTwoWeeksAheadIsCreatedCanceledAndLeftAloneByTheGenerator(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $schedule = $this->schedule();
        $admin = $this->signInWithRoles('admin@example.org', 'administrator');

        $this->cancel($schedule, '2026-11-22', ['reason' => ' Holiday ']);

        self::assertResponseIsSuccessful();
        $this->assertSessions([['2026-11-22', 'canceled', 'Holiday']]);

        $this->assertRows(
            [
                ['created',  $admin, null],
                ['canceled', $admin, 'Holiday'],
            ],
            'SELECT type, actor_id::text, note FROM meetings.session_event WHERE session_id = ? '
            . 'ORDER BY occurred_at, type DESC',
            [Type::of($this->responseJson()['sessionId'] ?? null)->toString()],
        );

        self::mockTime('2026-11-21T10:00:00+01:00');
        $this->generate();
        $this->assertSessions([['2026-11-22', 'canceled', 'Holiday']]);
    }

    public function testAGeneratedSessionIsCanceledInPlaceAndOnlyOnce(): void
    {
        self::mockTime('2026-11-07T10:00:00+01:00');
        $schedule = $this->schedule();
        $this->generate();

        new ServedGroups(self::getContainer())->everyService($this->signInWithRoles(
            'leader@example.org',
            'service_leader',
        ));

        $this->cancel($schedule, '2026-11-08', []);
        self::assertResponseIsSuccessful();
        $this->assertSessions([['2026-11-08', 'canceled', null]]);

        $this->cancel($schedule, '2026-11-08', []);
        $this->assertProblem(409, 'meetings.onlyScheduledCanCancel');
        $this->assertRows([['canceled']], 'SELECT type FROM meetings.session_event WHERE type = ?', ['canceled']);
    }

    /**
     * @param array<string, string> $params
     */
    #[DataProvider('refusals')]
    public function testEachRuleHasItsCodeAndWritesNothing(
        string $date,
        string $set,
        string $code,
        array $params = [],
    ): void {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $schedule = $this->schedule();

        if ($set !== '') {
            new Database(self::getContainer())
                ->connection()
                ->executeStatement('UPDATE ' . $set);
        }

        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->cancel($schedule, $date, []);

        $this->assertProblem(409, $code, $params);
        $this->assertSessions([]);
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: string, 3?: array<string, string>}>
     */
    public static function refusals(): iterable
    {
        yield 'yesterday' => ['2026-11-07', '', 'meetings.dateInPast'];
        yield 'not a Sunday' => ['2026-11-10', '', 'meetings.noMeetingOnDate'];
        yield 'inactive' => ['2026-11-15', 'meetings.meeting_schedule SET active = false', 'meetings.scheduleInactive'];
        yield 'archived service' => [
            '2026-11-15',
            "organization.service SET archived_at = '2026-11-01 12:00:00+01'",
            'meetings.serviceArchivedRestoreFirst',
            ['name' => 'Sunday School'],
        ];
    }

    public function testImpossibleDatesUnknownSchedulesAndAccess(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $schedule = $this->schedule();
        $this->signInWithRoles('admin@example.org', 'administrator');

        foreach (['2026-02-30', 'tomorrow'] as $date) {
            $this->cancel($schedule, $date, []);
            $this->assertFieldError('date', 'validation.invalidDate');
        }

        foreach ([Uuid::v7()->toRfc4122(), 'not-a-uuid'] as $id) {
            $this->jsonRequest('POST', '/api/schedules/' . $id . '/occurrences/2026-11-15/cancel', []);
            $this->assertProblem(404, 'notFound.schedule');
        }

        $this->cancel($schedule, '2026-11-15', ['reason' => str_repeat('a', times: 201)]);
        $this->assertFieldError('reason', 'validation.tooLong', ['max' => 200]);

        foreach (['servant', 'parent'] as $role) {
            $this->signInWithRoles($role . '@example.org', $role);
            $this->cancel($schedule, '2026-11-15', []);
            $this->assertProblem(403, 'forbidden');
        }

        $this->assertSessions([]);
    }

    /**
     * @param array<string, string> $body
     */
    private function cancel(Schedule $schedule, string $date, array $body): void
    {
        $this->jsonRequest(
            'POST',
            '/api/schedules/' . $schedule->id()->toRfc4122() . '/occurrences/' . $date . '/cancel',
            $body,
        );
    }

    private function schedule(): Schedule
    {
        $service = Structure::service();
        new Database(self::getContainer())->persisted($service);

        return new Schedules(self::getContainer())->weekly($service, 'Sunday class', 7);
    }

    private function generate(): void
    {
        $generate = self::getContainer()->get(GenerateSessionsWorkflow::class);
        self::assertInstanceOf(GenerateSessionsWorkflow::class, $generate);
        $generate();
    }

    /**
     * @param list<list<string|null>> $expected
     */
    private function assertSessions(array $expected): void
    {
        $this->assertRows(
            $expected,
            'SELECT occurrence_date::text, status, cancel_reason FROM meetings.meeting_session ORDER BY 1',
            [],
        );
    }

    /**
     * @param list<list<string|null>> $expected
     * @param list<string> $params
     */
    private function assertRows(array $expected, string $sql, array $params): void
    {
        self::assertSame($expected, new Database(self::getContainer())
            ->connection()
            ->fetchAllNumeric($sql, $params));
    }
}
