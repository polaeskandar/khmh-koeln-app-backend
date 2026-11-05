<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Attendance;

use App\Context\Attendance\Domain\Entity\Attendance;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\People\Domain\Entity\Person;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Meetings\Domain\Entity\Schedules;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Database;
use App\Tests\Support\JsonBody;
use App\Tests\Support\Logs;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

final class AttendanceBoardControllerTest extends ApiTestCase
{
    private const string ACTIVE = "status = 'active', opened_at = created_at";

    private const array LAST_NAMES = [
        'Rizk',
        'Özdemir',
        'Nashed',
        'Abadir',
        'Gerges',
        'Hanna',
        'Iskander',
        'Bishara',
        'Daoud',
        'Ebeid',
        'Fahmy',
        'Labib',
        'Makram',
        'Tawfik',
    ];

    public function testTheBoardListsTheRosterVisitorsCorrectionsAndCounts(): void
    {
        [$service, $third] = $this->structure();
        $session = $this->session($service, $third, self::ACTIVE);
        $rosters = new Rosters(self::getContainer());
        $children = array_map(static fn (string $last): Person => $rosters->person('Child', $last), self::LAST_NAMES);

        foreach ($children as $child) {
            $rosters->place($child, $service, $third, '2026-09-01');
        }

        $visitor = $rosters->person('Visiting', 'Youth');
        $youth = Structure::service('Youth Meeting');
        new Database(self::getContainer())->persisted($youth);
        $rosters->place($visitor, $youth, null, '2026-09-01');
        $servant = $this->signInWithRoles('servant@example.org', 'servant');
        $marks = [...array_slice($children, offset: 0, length: 7), $visitor];
        $this->mark($session, $servant, ...$marks);
        $this->correct($session, $servant, $children[7]);

        $this->jsonRequest('GET', $this->board($session));

        self::assertResponseIsSuccessful();

        $this->assertJsonPath('counts', [
            'present' => 8,
            'rosterSize' => 14,
            'rosterPresent' => 7,
            'notMarked' => 7,
            'notOnRoster' => 1,
        ]);

        $roster = JsonBody::asArray($this->responseJson()['roster'] ?? null, 'roster');
        self::assertCount(14, $roster);
        $this->assertJsonPath('roster.0.person.lastName', 'Abadir');
        $this->assertJsonPath('roster.10.person.lastName', 'Nashed');
        $this->assertJsonPath('roster.11.person.lastName', 'Özdemir');
        $this->assertJsonPath('roster.12.person.lastName', 'Rizk');
        $this->assertJsonPath('notOnRoster.0.person.lastName', 'Youth');
        $this->assertJsonPath('notOnRoster.0.placements.0.service.name', 'Youth Meeting');
        $this->assertJsonPath('corrections.0.reason', 'wrong child');
        $this->assertJsonPath('corrections.0.removedBy.id', $servant);
        self::assertCount(6, JsonBody::asArray($this->responseJson()['recentlyMarked'] ?? null, 'recentlyMarked'));
        $this->assertJsonPath('recentlyMarked.0.person.lastName', 'Youth');
    }

    public function testTheSessionAndHistoryMatchTheSessionEndpoint(): void
    {
        [$service, $third] = $this->structure();
        $session = $this->session($service, $third, self::ACTIVE);
        $this->signInWithRoles('leader@example.org', 'service_leader');

        $this->jsonRequest('GET', '/api/sessions/' . $session->id()->toRfc4122());
        $detail = $this->responseJson();
        $this->jsonRequest('GET', $this->board($session));
        $board = $this->responseJson();

        $boardSession = JsonBody::asArray($board['session'] ?? null, 'session');

        self::assertSame($detail['session'] ?? null, array_diff_key($boardSession, [
            'openedAt' => 0,
            'closedAt' => 0,
            'closedBy' => 0,
        ]));

        self::assertSame($detail['history'] ?? null, $board['history'] ?? null);
        self::assertNotNull($boardSession['openedAt'] ?? null);
        $this->assertJsonPath('session.can', ['manage' => true, 'record' => true, 'reopen' => true]);
    }

    public function testOpenRostersListEveryMarkAsNotOnTheRoster(): void
    {
        [$service, $third] = $this->structure();
        $session = $this->session($service, $third, self::ACTIVE);

        new Database(self::getContainer())
            ->connection()
            ->executeStatement("UPDATE meetings.meeting_schedule SET roster_mode = 'open'");

        $rosters = new Rosters(self::getContainer());
        $mina = $rosters->person('Mina');
        $rosters->place($mina, $service, $third, '2026-09-01');
        $this->mark($session, null, $mina);
        $this->signInWithRoles('servant@example.org', 'servant');

        $this->jsonRequest('GET', $this->board($session));

        $this->assertJsonPath('roster', []);
        $this->assertJsonPath('counts.rosterSize', null);
        $this->assertJsonPath('notOnRoster.0.person.firstName', 'Mina');
    }

    public function testScheduledAndCanceledSessionsListNobody(): void
    {
        [$service, $third] = $this->structure();
        $mina = new Rosters(self::getContainer())->person('Mina');
        new Rosters(self::getContainer())->place($mina, $service, $third, '2026-09-01');
        $this->signInWithRoles('servant@example.org', 'servant');

        foreach (['', "status = 'canceled', cancel_reason = 'Holiday'"] as $set) {
            $session = $this->session($service, $third, $set);
            $this->jsonRequest('GET', $this->board($session));
            $this->assertJsonPath('roster', []);
            $this->assertJsonPath('notOnRoster', []);
            $this->assertJsonPath('recentlyMarked', []);
        }
    }

    public function testMoreMarksCostNoMoreStatementsAndParentsAreRefused(): void
    {
        [$service, $third] = $this->structure();
        $session = $this->session($service, $third, self::ACTIVE);
        $rosters = new Rosters(self::getContainer());
        $this->mark($session, null, $rosters->person('First'));
        $this->signInWithRoles('servant@example.org', 'servant');

        $this->jsonRequest('GET', $this->board($session));
        $few = count(new Logs(self::getContainer())->containing('SELECT'));

        $this->mark(
            $session,
            null,
            ...array_map(
                static fn (int $n): Person => $rosters->person('Person', 'Number ' . $n),
                range(start: 1, end: 8),
            ),
        );

        $this->jsonRequest('GET', $this->board($session));

        self::assertCount($few, new Logs(self::getContainer())->containing('SELECT'));

        $this->signInWithRoles('parent@example.org', 'parent');
        $this->jsonRequest('GET', $this->board($session));
        $this->assertProblem(403, 'forbidden');

        $this->jsonRequest('GET', '/api/sessions/' . Uuid::v7()->toRfc4122() . '/attendance');
        $this->assertProblem(403, 'forbidden');
    }

    private function board(Session $session): string
    {
        return '/api/sessions/' . $session->id()->toRfc4122() . '/attendance';
    }

    private function mark(Session $session, ?string $by, Person ...$people): void
    {
        $minute = 0;

        foreach ($people as $person) {
            $at = new DateTimeImmutable('2026-11-08T12:00:00+01:00')->modify('+' . $minute++ . ' minutes');

            new Database(self::getContainer())->persisted(Attendance::record(
                $session->id(),
                $person->id(),
                $at,
                $by === null ? null : Uuid::fromString($by),
            ));
        }
    }

    private function correct(Session $session, string $by, Person $person): void
    {
        $mark = Attendance::record(
            $session->id(),
            $person->id(),
            new DateTimeImmutable('2026-11-08T11:00:00+01:00'),
            Uuid::fromString($by),
        );

        $mark->remove(Uuid::fromString($by), new DateTimeImmutable('2026-11-08T11:05:00+01:00'), 'wrong child');
        new Database(self::getContainer())->persisted($mark);
    }

    /**
     * @return array{Service, Stage}
     */
    private function structure(): array
    {
        $service = Structure::service();
        $third = Structure::stage($service);
        new Database(self::getContainer())->persisted($service, $third);

        return [$service, $third];
    }

    private function session(Service $service, Stage $stage, string $set): Session
    {
        $schedules = new Schedules(self::getContainer());

        return $schedules->session(
            $schedules->weekly($service, 'Sunday class ' . Uuid::v7()->toRfc4122(), 7, stage: $stage),
            '2026-11-08',
            $set,
        );
    }
}
