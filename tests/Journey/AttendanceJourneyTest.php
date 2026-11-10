<?php

declare(strict_types=1);

namespace App\Tests\Journey;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\People\Domain\Entity\Person;
use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Context\Organization\Domain\Entity\ServedGroups;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Database;
use App\Tests\Support\JsonBody;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Tester\CommandTester;

final class AttendanceJourneyTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    private const string CHRISTINE = 'christine@example.org';

    private const string DANIEL = 'daniel@example.org';

    public function testTheFirstSundayFromScheduleToCorrectedAttendance(): void
    {
        [$school, $third, $first] = JourneyStructure::sundaySchool(self::getContainer());
        $rosters = new Rosters(self::getContainer());
        $mina = $rosters->person('Mina', 'Hanna');
        $kiro = $rosters->person('Kiro', 'Nashed');
        $rosters->place($rosters->person('Mimi', 'Gerges'), $school, $first, '2026-09-01');
        new ServedGroups(self::getContainer())->everyService($this->signInWithRoles(self::DANIEL, 'servant'));
        $this->signInWithRoles('hoda@example.org', 'parent');

        $this->planOnFriday($school, $third, [$mina, $kiro, $rosters->person('Lydia', 'Rizk')]);
        $this->generateOnSaturday();
        $session = $this->markOnSunday($mina, $kiro);
        $this->correctOnSunday($session, $kiro);
        $this->assertHistory($session);

        $this->signInAs('hoda@example.org', SignInAccounts::TYPED);
        $this->jsonRequest('GET', '/api/sessions/' . $session . '/attendance');
        $this->assertProblem(403, 'forbidden');
    }

    /**
     * @param list<Person> $children
     */
    private function planOnFriday(Service $school, Stage $third, array $children): void
    {
        self::mockTime('2026-11-13T10:00:00+01:00');
        new ServedGroups(self::getContainer())->everyService($this->signInWithRoles(self::CHRISTINE, 'service_leader'));
        $this->jsonRequest('POST', '/api/schedules', JourneyStructure::schedule($school, $third));
        self::assertResponseStatusCodeSame(201);
        $this->assertJsonPath('createdSessions', 0);

        foreach ($children as $child) {
            $this->jsonRequest('POST', '/api/people/' . $child->id()->toRfc4122() . '/memberships', [
                'serviceId' => $school->id()->toRfc4122(),
                'stageId' => $third->id()->toRfc4122(),
            ]);

            self::assertResponseStatusCodeSame(201);
        }
    }

    private function generateOnSaturday(): void
    {
        self::mockTime('2026-11-14T23:05:00+01:00');
        self::assertSame(1, $this->generate());
        self::assertSame(1, $this->generate());
    }

    private function markOnSunday(Person $mina, Person $kiro): string
    {
        self::mockTime('2026-11-15T12:05:00+01:00');
        $this->signInAs(self::DANIEL, SignInAccounts::TYPED);
        $this->jsonRequest('GET', '/api/attendance/overview');
        $this->assertJsonPath('sessions.0.session.startsAt', '2026-11-15T12:00:00+01:00');
        $this->assertJsonPath('sessions.0.state.status', 'scheduled');

        $session = Type::of(JsonBody::valueAt(
            $this->responseJson(),
            ['sessions', '0', 'session', 'id'],
            'sessions.0.session.id',
        ))->toString();

        $this->jsonRequest('POST', '/api/sessions/' . $session . '/open', []);
        self::assertResponseIsSuccessful();
        $mark = $this->mark($session, $mina->id()->toRfc4122(), 201);
        $this->mark($session, $kiro->id()->toRfc4122(), 201);
        $this->jsonRequest('GET', '/api/people/directory?q=mimi');
        $this->mark($session, $this->firstId('items'), 201);
        self::assertSame($mark, $this->mark($session, $mina->id()->toRfc4122(), 200));

        $this->assertCounts($session, [3, 3, 2, 1, 1], openCount: 1);
        $this->assertJsonPath('notOnRoster.0.person.firstName', 'Mimi');

        return $session;
    }

    private function correctOnSunday(string $session, Person $kiro): void
    {
        $this->jsonRequest('POST', '/api/sessions/' . $session . '/close', []);
        $this->signInAs(self::CHRISTINE, SignInAccounts::TYPED);
        $this->jsonRequest('POST', '/api/sessions/' . $session . '/reopen', ['reason' => 'Kiro left early']);
        self::assertResponseIsSuccessful();
        $this->signInAs(self::DANIEL, SignInAccounts::TYPED);
        $path = '/api/sessions/' . $session . '/attendance/' . $kiro->id()->toRfc4122();
        $this->jsonRequest('DELETE', $path, ['reason' => 'Left early']);
        self::assertResponseStatusCodeSame(204);
        $this->jsonRequest('POST', '/api/sessions/' . $session . '/close', []);

        $this->assertCounts($session, [2, 3, 1, 2, 1], openCount: 0);
        $this->jsonRequest('GET', '/api/sessions/' . $session . '/attendance/corrections');
        $this->assertJsonPath('items.0.reason', 'Left early');
        $this->assertJsonPath('items.0.person.firstName', 'Kiro');
    }

    private function assertHistory(string $session): void
    {
        $this->jsonRequest('GET', '/api/sessions/' . $session);
        $history = JsonBody::asArray($this->responseJson()['history'] ?? null, 'history');

        self::assertSame(
            ['created', 'opened', 'closed', 'reopened', 'closed'],
            array_map(static fn (mixed $event): mixed => JsonBody::asArray($event, 'event')['type'] ?? null, $history),
        );

        $this->assertJsonPath('history.0.byGenerator', true);
        $this->assertJsonPath('history.3.note', 'Kiro left early');
    }

    private function firstId(string $list): string
    {
        $items = JsonBody::asArray($this->responseJson()[$list] ?? null, $list);

        return Type::of(JsonBody::asArray($items[0] ?? null, 'item')['id'] ?? null)->toString();
    }

    /**
     * @param array{int, int, int, int, int} $expected
     */
    private function assertCounts(string $session, array $expected, int $openCount): void
    {
        $counts = array_combine(['present', 'rosterSize', 'rosterPresent', 'notMarked', 'notOnRoster'], $expected);

        $this->jsonRequest('GET', '/api/attendance/overview');
        $this->assertJsonPath('openCount', $openCount);

        $this->jsonRequest('GET', '/api/attendance/counts?sessionIds[]=' . $session);
        $this->assertJsonPath('items.0.counts', $counts);

        $this->jsonRequest('GET', '/api/sessions/' . $session . '/attendance');
        $this->assertJsonPath('counts', $counts);
    }

    private function mark(string $session, string $personId, int $status): string
    {
        $this->jsonRequest('PUT', '/api/sessions/' . $session . '/attendance/' . $personId);
        self::assertResponseStatusCodeSame($status);

        return Type::of($this->responseJson()['id'] ?? null)->toString();
    }

    private function generate(): int
    {
        $command = new Application($this->browser()->getKernel())->find('app:sessions:generate');
        self::assertSame(0, new CommandTester($command)->execute([]));

        $sessions = new Database(self::getContainer())
            ->connection()
            ->fetchOne("SELECT count(*) FROM meetings.meeting_session WHERE occurrence_date = '2026-11-15'");

        self::assertIsInt($sessions);

        return $sessions;
    }
}
