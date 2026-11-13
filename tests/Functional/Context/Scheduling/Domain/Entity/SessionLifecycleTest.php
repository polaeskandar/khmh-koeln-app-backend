<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Scheduling\Domain\Entity;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Scheduling\Domain\Entity\Session;
use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Context\Scheduling\Domain\Entity\Schedules;
use App\Tests\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

final class SessionLifecycleTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    private const string TODAY = '2026-11-08';

    private const string NOW = '2026-11-08 10:00:00+01';

    private const string OPENED = '2026-11-08 09:00:00+01';

    private const string CLOSED = '2026-11-08 09:30:00+01';

    private const string REASON = 'Late list';

    private const string VIEWER = 'viewer';

    private const string SCHEDULED = '';

    private const string ACTIVE = "status = 'active', opened_at = '" . self::OPENED . "'";

    private const string COMPLETED =
        "status = 'completed', opened_at = '" . self::OPENED . "', closed_at = '" . self::CLOSED . "'";

    private const string CANCELED = "status = 'canceled', cancel_reason = 'Storm'";

    private const string ROW = <<<'SQL'
        SELECT status, cancel_reason, opened_at::text AS opened_at, opened_by::text AS opened_by,
               closed_at::text AS closed_at, closed_by::text AS closed_by
        FROM scheduling.session WHERE id = ?
        SQL;

    private const string EVENTS = <<<'SQL'
        SELECT type, note, actor_id::text AS actor FROM scheduling.session_event
        WHERE session_id = ? ORDER BY occurred_at, id
        SQL;

    /**
     * @param array<string, ?string> $row
     * @param list<list<?string>> $events
     */
    #[DataProvider('transitions')]
    public function testEveryStateMeetsEveryTransition(
        string $state,
        string $action,
        ?string $refusal,
        array $row,
        array $events,
    ): void {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $session = $this->session(self::TODAY, $state);
        $viewer = $this->signInWithRoles('admin@example.org', 'administrator');

        $this->act($session, $action);

        self::assertResponseStatusCodeSame($refusal === null ? 200 : 409);
        self::assertSame($refusal ?? $row['status'] ?? null, $this->answer());
        self::assertSame($row, $this->stored($session, $viewer));
        self::assertSame($events, $this->events($session, $viewer));
    }

    /**
     * @return iterable<string, array{string, string, ?string, array<string, ?string>, list<list<?string>>}>
     */
    public static function transitions(): iterable
    {
        $scheduled = self::row();
        $active = self::row(['status' => 'active', 'opened_at' => self::OPENED]);
        $completed = self::row(['status' => 'completed', 'opened_at' => self::OPENED, 'closed_at' => self::CLOSED]);
        $canceled = self::row(['status' => 'canceled', 'cancel_reason' => 'Storm']);
        $opened = self::row(['status' => 'active', 'opened_at' => self::NOW, 'opened_by' => self::VIEWER]);
        $closed = [...$completed, 'closed_at' => self::NOW, 'closed_by' => self::VIEWER];

        yield 'scheduled, open' => [self::SCHEDULED, 'open', null, $opened, [['opened', null, self::VIEWER]]];
        yield 'scheduled, close' => [self::SCHEDULED, 'close', 'meetings.onlyActiveCanClose', $scheduled, []];
        yield 'scheduled, reopen' => [
            self::SCHEDULED,
            'reopen',
            'meetings.onlyCompletedCanReopen',
            $scheduled,
            [],
        ];

        yield 'scheduled, cancel' => [
            self::SCHEDULED,
            'cancel',
            null,
            self::row(['status' => 'canceled', 'cancel_reason' => self::REASON]),
            [['canceled', self::REASON, self::VIEWER]],
        ];

        yield 'active, open again' => [self::ACTIVE, 'open', null, $active, []];
        yield 'active, close' => [self::ACTIVE, 'close', null, $closed, [['closed', null, self::VIEWER]]];
        yield 'active, reopen again' => [self::ACTIVE, 'reopen', null, $active, []];
        yield 'active, cancel' => [self::ACTIVE, 'cancel', 'meetings.onlyScheduledCanCancel', $active, []];
        yield 'completed, open' => [self::COMPLETED, 'open', 'meetings.attendanceClosed', $completed, []];
        yield 'completed, close again' => [self::COMPLETED, 'close', null, $completed, []];

        yield 'completed, reopen' => [
            self::COMPLETED,
            'reopen',
            null,
            $active,
            [['reopened', self::REASON, self::VIEWER]],
        ];

        yield 'completed, cancel' => [
            self::COMPLETED,
            'cancel',
            'meetings.onlyScheduledCanCancel',
            $completed,
            [],
        ];

        yield 'canceled, open' => [self::CANCELED, 'open', 'meetings.sessionCanceled', $canceled, []];
        yield 'canceled, close' => [self::CANCELED, 'close', 'meetings.onlyActiveCanClose', $canceled, []];
        yield 'canceled, reopen' => [
            self::CANCELED,
            'reopen',
            'meetings.onlyCompletedCanReopen',
            $canceled,
            [],
        ];

        yield 'canceled, cancel again' => [
            self::CANCELED,
            'cancel',
            'meetings.onlyScheduledCanCancel',
            $canceled,
            [],
        ];
    }

    public function testASessionOpensFromItsDayOnButItsStatusIsCheckedFirst(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $tomorrow = $this->session('2026-11-09', self::SCHEDULED);
        $canceledTomorrow = $this->session('2026-11-09', self::CANCELED);
        $lastWeek = $this->session('2026-11-01', self::SCHEDULED);
        $viewer = $this->signInWithRoles('admin@example.org', 'administrator');

        $this->act($tomorrow, 'open');
        $this->assertProblem(409, 'meetings.opensOnTheDay');
        self::assertSame(self::row(), $this->stored($tomorrow, $viewer));

        $this->act($canceledTomorrow, 'open');
        $this->assertProblem(409, 'meetings.sessionCanceled');

        $this->act($lastWeek, 'open');
        self::assertSame('active', $this->answer());
        self::assertSame([['opened', null, self::VIEWER]], $this->events($lastWeek, $viewer));
    }

    public function testAnArchivedServiceRefusesOnlyOpeningAndBeforeTheStatus(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $service = Structure::service('Archived School');
        $service->archive(Structure::now());
        $scheduled = $this->sessionOf($service, self::TODAY, self::SCHEDULED);
        $active = $this->sessionOf($service, self::TODAY, self::ACTIVE);
        $canceled = $this->sessionOf($service, self::TODAY, self::CANCELED);
        $viewer = $this->signInWithRoles('admin@example.org', 'administrator');

        foreach ([$scheduled, $active, $canceled] as $session) {
            $this->act($session, 'open');
            $this->assertProblem(409, 'meetings.serviceArchivedRestoreFirst', ['name' => 'Archived School']);
            self::assertSame([], $this->events($session, $viewer));
        }

        $this->act($active, 'close');
        self::assertSame('completed', $this->answer());
        $this->act($active, 'reopen');
        self::assertSame('active', $this->answer());
        $this->act($scheduled, 'cancel');
        self::assertSame('canceled', $this->answer());
    }

    public function testAReopeningWithoutAReasonAndACancelWithoutOneStoreNoNote(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $completed = $this->session(self::TODAY, self::COMPLETED);
        $scheduled = $this->session(self::TODAY, self::SCHEDULED);
        $viewer = $this->signInWithRoles('admin@example.org', 'administrator');

        $this->jsonRequest('POST', $this->path($completed, 'reopen'), ['reason' => '   ']);
        $this->jsonRequest('POST', $this->path($scheduled, 'cancel'));

        self::assertSame([['reopened', null, self::VIEWER]], $this->events($completed, $viewer));
        self::assertSame(self::row(['status' => 'canceled']), $this->stored($scheduled, $viewer));
        self::assertSame([['canceled', null, self::VIEWER]], $this->events($scheduled, $viewer));
    }

    /**
     * @param array<string, ?string> $changes
     *
     * @return array<string, ?string>
     */
    private static function row(array $changes = []): array
    {
        return [
            'status' => 'scheduled',
            'cancel_reason' => null,
            'opened_at' => null,
            'opened_by' => null,
            'closed_at' => null,
            'closed_by' => null,
            ...$changes,
        ];
    }

    private function session(string $date, string $state): Session
    {
        return $this->sessionOf(Structure::service('School ' . Uuid::v7()->toBase58()), $date, $state);
    }

    private function sessionOf(Service $service, string $date, string $state): Session
    {
        new Database(self::getContainer())->persisted($service);
        $schedules = new Schedules(self::getContainer());
        $schedule = $schedules->weekly($service, 'Class ' . Uuid::v7()->toBase58(), 7);

        return $schedules->session($schedule, $date, $state);
    }

    private function act(Session $session, string $action): void
    {
        $this->jsonRequest('POST', $this->path($session, $action), ['reason' => self::REASON]);
    }

    private function path(Session $session, string $action): string
    {
        return '/api/sessions/' . $session->id()->toRfc4122() . '/' . $action;
    }

    private function answer(): ?string
    {
        $body = $this->responseJson();

        return Type::of($body['code'] ?? $body['status'] ?? null)->toStringOrNull();
    }

    /**
     * @return array<string, ?string>
     */
    private function stored(Session $session, string $viewer): array
    {
        $row = new Database(self::getContainer())
            ->connection()
            ->fetchAssociative(self::ROW, [$session->id()->toRfc4122()]);

        $values = array_map(
            static fn (mixed $value): ?string => Type::of($value)->toStringOrNull(),
            $row === false ? [] : $row,
        );

        return [
            ...$values,
            'opened_by' => self::who($values['opened_by'] ?? null, $viewer),
            'closed_by' => self::who($values['closed_by'] ?? null, $viewer),
        ];
    }

    /**
     * @return list<list<?string>>
     */
    private function events(Session $session, string $viewer): array
    {
        $events = new Database(self::getContainer())
            ->connection()
            ->fetchAllAssociative(self::EVENTS, [$session->id()->toRfc4122()]);

        return array_map(static fn (array $event): array => [
            Type::of($event['type'] ?? null)->toStringOrNull(),
            Type::of($event['note'] ?? null)->toStringOrNull(),
            self::who(Type::of($event['actor'] ?? null)->toStringOrNull(), $viewer),
        ], $events);
    }

    private static function who(?string $accountId, string $viewer): ?string
    {
        return match ($accountId) {
            null => null,
            $viewer => self::VIEWER,
            default => 'another account',
        };
    }
}
