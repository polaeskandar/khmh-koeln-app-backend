<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Meetings;

use App\Context\Organization\Domain\Entity\Service;
use App\Library\Types\Model\Integers;
use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Meetings\Domain\Entity\Schedules;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Tester\ApplicationTester;

final class SessionWritesControllerTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testAOneOffIsScheduledOnItsDateAndListedInTheAgenda(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $service = $this->service();
        $leader = $this->signInWithRoles('leader@example.org', 'service_leader');

        $first = $this->created($this->body($service));
        $second = $this->created($this->body($service, ['title' => 'Choir practice']));
        self::assertNotSame($first, $second);

        $this->jsonRequest('GET', '/api/agenda?from=2026-11-08&to=2026-11-08');
        $this->assertJsonPath('items.0.title', 'Choir practice');
        $this->assertJsonPath('items.1.oneOff', true);
        $this->assertJsonPath('items.1.scheduleId', null);
        $this->assertJsonPath('items.1.title', 'Christmas rehearsal');
        $this->assertJsonPath('items.1.location', 'Hall');
        $this->assertJsonPath('items.1.startsAt', '2026-11-08T17:00:00+01:00');
        $this->assertJsonPath('items.1.endsAt', '2026-11-08T19:00:00+01:00');
        $this->assertJsonPath('items.1.status', 'scheduled');

        $this->jsonRequest('GET', '/api/sessions/' . $first);
        $this->assertJsonPath('schedule', null);
        $this->assertJsonPath('history.0.type', 'created');
        $this->assertJsonPath('history.0.actor.id', $leader);

        $this->jsonRequest('GET', '/api/schedules');
        $this->assertJsonPath('items', []);

        $application = new Application($this->browser()->getKernel());
        $application->setAutoExit(false);
        self::mockTime('2026-11-15T10:00:00+01:00');
        new ApplicationTester($application)->run(['command' => 'app:sessions:generate']);
        self::assertSame(2, $this->numberOf('SELECT count(*) FROM meetings.meeting_session'));
    }

    /**
     * @param array<string, string|array<string, string|null>|null> $changes
     */
    #[DataProvider('invalidOneOffs')]
    public function testEachInvalidFieldHasItsCode(array $changes, string $field, string $code): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $service = $this->service();
        $this->signInWithRoles('leader@example.org', 'service_leader');

        $this->jsonRequest('POST', '/api/sessions', $this->body($service, $changes));

        $this->assertFieldError($field, $code, $code === 'validation.tooLong' ? ['max' => 120, 'limit' => 120] : []);
    }

    /**
     * @return iterable<string, array{array<string, string|array<string, string|null>|null>, string, string}>
     */
    public static function invalidOneOffs(): iterable
    {
        yield 'unknown service' => [
            ['roster' => ['serviceId' => '019a0000-0000-7000-8000-000000000000']],
            'roster.serviceId',
            'meetings.chooseService',
        ];

        yield 'unknown stage' => [
            ['roster' => ['stageId' => '019a0000-0000-7000-8000-000000000000']],
            'roster.stageId',
            'meetings.chooseStage',
        ];

        yield 'blank title' => [['title' => ' '], 'title', 'meetings.titleRequired'];
        yield 'long title' => [['title' => str_repeat('a', times: 121)], 'title', 'validation.tooLong'];
        yield 'long place' => [['location' => str_repeat('a', times: 121)], 'location', 'validation.tooLong'];
        yield 'no roster mode' => [['roster' => ['mode' => null]], 'roster.mode', 'meetings.chooseRosterMode'];
        yield 'end before start' => [['endsAt' => '2026-11-08T16:00:00+01:00'], 'endsAt', 'meetings.endAfterStart'];
        yield 'end tomorrow' => [['endsAt' => '2026-11-09T01:00:00+01:00'], 'endsAt', 'meetings.endAfterStart'];
        yield 'yesterday' => [
            ['startsAt' => '2026-11-07T17:00:00+01:00', 'endsAt' => null],
            'startsAt',
            'meetings.dateInPast',
        ];

        yield 'not an instant' => [['startsAt' => 'tomorrow'], 'startsAt', 'validation.invalidDate'];

        yield 'the same minute' => [
            ['startsAt' => '2026-11-08T17:00:10+01:00', 'endsAt' => '2026-11-08T17:00:50+01:00'],
            'endsAt',
            'meetings.endAfterStart',
        ];

        yield 'earlier on the clock after the autumn change' => [
            ['startsAt' => '2027-10-31T02:30:00+02:00', 'endsAt' => '2027-10-31T02:10:00+01:00'],
            'endsAt',
            'meetings.endAfterStart',
        ];
    }

    public function testArchivedPlacesAreRefusedAndLaterTodayIsAccepted(): void
    {
        self::mockTime('2026-11-08T20:00:00+01:00');
        $service = $this->service();
        $archived = Structure::service('Youth Meeting');
        $archived->archive(Structure::now());
        new Database(self::getContainer())->persisted($archived);
        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->jsonRequest('POST', '/api/sessions', $this->body($service, ['roster' => [
            'serviceId' => $archived->id()->toRfc4122(),
        ]]));

        $this->assertFieldError('roster.serviceId', 'meetings.serviceArchived', ['name' => 'Youth Meeting']);

        $this->created($this->body($service, ['startsAt' => '2026-11-08T20:30:00+01:00', 'endsAt' => null]));
    }

    public function testEditingChangesOnlyTheSessionAndRecordsIt(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $service = $this->service();
        $schedules = new Schedules(self::getContainer());
        $class = $schedules->weekly($service, 'Sunday class', 7);

        $sessions = [
            'scheduled' => $schedules->session($class, '2026-11-15'),
            'active' => $schedules->session($class, '2026-11-08', "status = 'active', opened_at = created_at"),
            'completed' => $schedules->session(
                $class,
                '2026-11-01',
                "status = 'completed', opened_at = created_at, closed_at = created_at",
            ),
        ];

        $canceled = $schedules->session($class, '2026-11-22', "status = 'canceled'");
        $this->signInWithRoles('leader@example.org', 'service_leader');

        foreach ($sessions as $status => $session) {
            $this->jsonRequest('PATCH', '/api/sessions/' . $session->id()->toRfc4122(), [
                'title' => 'Harvest feast',
                'location' => ' ',
            ]);

            self::assertResponseIsSuccessful($status);
            $this->jsonRequest('GET', '/api/sessions/' . $session->id()->toRfc4122());
            $this->assertJsonPath('session.title', 'Harvest feast');
            $this->assertJsonPath('session.location', null);
        }

        self::assertSame(3, $this->numberOf("SELECT count(*) FROM meetings.session_event WHERE type = 'edited'"));

        self::assertSame(
            1,
            $this->numberOf(
                "SELECT count(*) FROM meetings.meeting_schedule WHERE name = 'Sunday class' AND location = 'Hall'",
            ),
        );

        $this->jsonRequest('PATCH', '/api/sessions/' . $canceled->id()->toRfc4122(), ['title' => 'Harvest feast']);
        $this->assertProblem(409, 'meetings.sessionCanceled');
        $this->jsonRequest('PATCH', '/api/sessions/' . $sessions['scheduled']->id()->toRfc4122(), ['title' => '']);
        $this->assertFieldError('title', 'meetings.titleRequired');
    }

    public function testServantsAndParentsMayNotCreateOneOffs(): void
    {
        $service = $this->service();

        foreach (['servant', 'parent'] as $role) {
            $this->signInWithRoles($role . '@example.org', $role);
            $this->jsonRequest('POST', '/api/sessions', $this->body($service));
            $this->assertProblem(403, 'forbidden');
            $this->browser()->getCookieJar()->clear();
        }
    }

    private function service(): Service
    {
        $service = Structure::service();
        new Database(self::getContainer())->persisted($service);

        return $service;
    }

    /**
     * @param array<string, string|array<string, string|null>|null> $changes
     *
     * @return array<array-key, mixed>
     */
    private function body(Service $service, array $changes = []): array
    {
        return array_replace_recursive([
            'roster' => ['serviceId' => $service->id()->toRfc4122(), 'stageId' => null, 'mode' => 'open'],
            'title' => 'Christmas rehearsal',
            'location' => 'Hall',
            'startsAt' => '2026-11-08T17:00:00+01:00',
            'endsAt' => '2026-11-08T19:00:00+01:00',
        ], $changes);
    }

    /**
     * @param array<array-key, mixed> $body
     */
    private function created(array $body): string
    {
        $this->jsonRequest('POST', '/api/sessions', $body);
        self::assertResponseStatusCodeSame(201);

        return Type::of($this->responseJson()['id'] ?? null)->toString();
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
