<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Meetings;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Organization\Domain\Entity\Service;
use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Meetings\Domain\Entity\Schedules;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use App\Tests\Support\JsonBody;
use App\Tests\Support\Logs;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class DashboardControllerTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testTodaysCardsEqualTheOverviewAndTheWeekFollows(): void
    {
        self::mockTime('2026-11-08T12:08:00+01:00');
        $this->fixture();
        $this->signInWithRoles('leader@example.org', 'service_leader');

        $this->jsonRequest('GET', '/api/attendance/overview');
        $overview = JsonBody::asArray($this->responseJson()['sessions'] ?? null, 'sessions');
        $this->jsonRequest('GET', '/api/dashboard');

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('today', '2026-11-08');
        self::assertSame($overview, JsonBody::asArray($this->responseJson()['sessions'] ?? null, 'sessions'));
        self::assertSame(['Kindergarten', '3rd Class', '4th Class', 'Youth evening'], $this->titles('sessions'));

        self::assertSame(
            [
                'planned 2026-11-14 Youth evening',
                'planned 2026-11-15 3rd Class',
                'session 2026-11-15 4th Class',
                'planned 2026-11-15 Kindergarten',
                'planned 2026-11-15 Youth evening',
            ],
            $this->upcoming(),
        );
    }

    public function testASessionAfterMidnightIsUpcoming(): void
    {
        self::mockTime('2026-11-08T23:50:00+01:00');
        $service = Structure::service();
        new Database(self::getContainer())->persisted($service);
        $schedules = new Schedules(self::getContainer());
        $late = $schedules->weekly($service, 'Night vigil', 1);

        new Database(self::getContainer())
            ->connection()
            ->update(
                'meetings.meeting_schedule',
                ['start_time' => '00:30', 'end_time' => null],
                ['id' => $late->id()->toRfc4122()],
            );

        $schedules->session($late, '2026-11-09');
        $this->signInWithRoles('leader@example.org', 'service_leader');

        $this->jsonRequest('GET', '/api/dashboard');

        $this->assertJsonPath('sessions', []);
        $this->assertJsonPath('upcoming.0.startsAt', '2026-11-09T00:30:00+01:00');
    }

    public function testMoreSessionsCostNoMoreStatementsAndParentsAreRefused(): void
    {
        self::mockTime('2026-11-08T12:08:00+01:00');
        [$school] = $this->fixture();
        $this->signInWithRoles('leader@example.org', 'service_leader');
        $this->jsonRequest('GET', '/api/dashboard');
        $few = \count(new Logs(self::getContainer())->containing('SELECT'));
        $schedules = new Schedules(self::getContainer());

        foreach (range(start: 1, end: 6) as $n) {
            $schedule = $schedules->weekly($school, 'Extra ' . $n, 7);
            $schedules->session($schedule, '2026-11-08');
            $schedules->session($schedule, '2026-11-15');
        }

        $this->jsonRequest('GET', '/api/dashboard');
        self::assertCount($few, new Logs(self::getContainer())->containing('SELECT'));

        $this->browser()->getCookieJar()->clear();
        $this->jsonRequest('GET', '/api/dashboard');
        $this->assertProblem(401, 'auth.unauthenticated');
        $this->signInWithRoles('parent@example.org', 'parent');
        $this->jsonRequest('GET', '/api/dashboard');
        $this->assertProblem(403, 'forbidden');
    }

    /**
     * @return array{Service}
     */
    private function fixture(): array
    {
        $school = Structure::service();
        $kindergarten = Structure::stage($school, 'Kindergarten', position: 0);
        $third = Structure::stage($school, '3rd Class', position: 3);
        $fourth = Structure::stage($school, '4th Class', position: 4);
        $youth = Structure::service('Youth Meeting');
        $archived = Structure::service('Old choir');
        $archived->archive(Structure::now());
        new Database(self::getContainer())->persisted($school, $kindergarten, $third, $fourth, $youth, $archived);
        $schedules = new Schedules(self::getContainer());

        $schedules->session(
            $schedules->weekly($school, 'Kindergarten', 7, stage: $kindergarten),
            '2026-11-08',
            "status = 'active', opened_at = created_at",
        );

        $schedules->session($schedules->weekly($school, '3rd Class', 7, stage: $third), '2026-11-08');
        $fourthClass = $schedules->weekly($school, '4th Class', 7, stage: $fourth);
        $schedules->session($fourthClass, '2026-11-08', "status = 'canceled'");
        $this->canceledAhead($fourthClass);
        $evening = $schedules->weekly($youth, 'Youth evening', 7);

        new Database(self::getContainer())
            ->connection()
            ->update('meetings.meeting_schedule', ['start_time' => '19:00'], ['id' => $evening->id()->toRfc4122()]);

        $schedules->session($evening, '2026-11-08');
        $this->onSaturdays($evening);
        $schedules->session($schedules->weekly($archived, 'Old choir', 7), '2026-11-08');

        return [$school];
    }

    private function canceledAhead(Schedule $schedule): void
    {
        new Schedules(self::getContainer())->session(
            $schedule,
            '2026-11-15',
            "status = 'canceled', cancel_reason = 'Trip'",
        );
    }

    private function onSaturdays(Schedule $evening): void
    {
        new Database(self::getContainer())
            ->connection()
            ->executeStatement('UPDATE meetings.meeting_schedule SET weekdays = weekdays | 32 WHERE id = ?', [$evening->id()->toRfc4122()]);
    }

    /**
     * @return list<string>
     */
    private function titles(string $path): array
    {
        return array_values(array_map(
            static fn (mixed $item): string => Type::of(JsonBody::asArray($item, 'item')['title'] ?? null)->toString(),
            JsonBody::asArray($this->responseJson()[$path] ?? null, $path),
        ));
    }

    /**
     * @return list<string>
     */
    private function upcoming(): array
    {
        return array_values(array_map(
            static fn (mixed $item): string => self::describe(JsonBody::asArray($item, 'item')),
            JsonBody::asArray($this->responseJson()['upcoming'] ?? null, 'upcoming'),
        ));
    }

    /**
     * @param array<array-key, mixed> $item
     */
    private static function describe(array $item): string
    {
        $kind = Type::of($item['kind'] ?? null)->toString();
        $date = Type::of($item['date'] ?? null)->toString();

        return $kind . ' ' . $date . ' ' . Type::of($item['title'] ?? null)->toString();
    }
}
