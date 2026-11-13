<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Organization;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\People\Domain\Entity\Person;
use App\Context\Scheduling\Domain\Entity\Attendance;
use App\Context\Scheduling\Domain\Entity\Session;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Organization\Domain\Entity\ServedGroups;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Context\Scheduling\Domain\Entity\Schedules;
use App\Tests\Support\Database;
use App\Tests\Support\JsonBody;
use DateTimeImmutable;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class ServicesControllerTrendTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    private const string DONE = "status = 'completed', opened_at = created_at, closed_at = created_at";

    public function testTheTrendCountsTheFourWeeksBeforeToday(): void
    {
        self::mockTime('2026-11-08T12:00:00+01:00');
        $service = Structure::service();
        $third = Structure::stage($service);
        new Database(self::getContainer())->persisted($service, $third);
        $rosters = new Rosters(self::getContainer());

        $members = array_map(
            static fn (int $n): Person => $rosters->person('Member', 'Number ' . $n),
            range(start: 1, end: 4),
        );

        foreach ($members as $member) {
            $rosters->place($member, $service, $third, '2026-09-01');
        }

        $schedules = new Schedules(self::getContainer());
        $class = $schedules->weekly($service, 'Sunday class', 7, stage: $third);

        $this->marked(
            $schedules->session($class, '2026-10-11', self::DONE),
            ...\array_slice($members, offset: 0, length: 2),
        );

        $this->marked(
            $schedules->session($class, '2026-11-01', self::DONE),
            ...\array_slice($members, offset: 0, length: 3),
        );

        $this->marked($schedules->session($class, '2026-10-10', self::DONE), ...$members);
        $this->marked($schedules->session($class, '2026-11-08', self::DONE), ...$members);
        $open = $schedules->weekly($service, 'Open evening', 6);

        new Database(self::getContainer())
            ->connection()
            ->update('scheduling.schedule', ['roster_mode' => 'open'], ['id' => $open->id()->toRfc4122()]);

        $this->marked(
            $schedules->session($open, '2026-11-07', self::DONE . ", roster_mode = 'open'"),
            ...$this->visitors(5),
        );

        $this->marked(
            $schedules->session($open, '2026-10-24', self::DONE . ", roster_mode = 'open'"),
            ...$this->visitors(2),
        );

        $schedules->session($open, '2026-10-31', "status = 'canceled'");
        $choir = $this->service('Choir');
        new ServedGroups(self::getContainer())->everyService($this->signInWithRoles('servant@example.org', 'servant'));

        self::assertSame(
            ['sessions' => 4, 'rate' => 0.63, 'averagePresent' => 4],
            $this->insightOf($service)['trend'] ?? null,
        );

        self::assertSame(
            ['sessions' => 0, 'rate' => null, 'averagePresent' => null],
            $this->insightOf($choir)['trend'] ?? null,
        );
    }

    public function testTheNextMeetingSkipsCanceledDatesAndArchivedServices(): void
    {
        self::mockTime('2026-11-08T12:00:00+01:00');
        $service = $this->service('Sunday School');
        $schedules = new Schedules(self::getContainer());
        $schedules->session($schedules->weekly($service, 'Sunday class', 7), '2026-11-08', "status = 'canceled'");
        $youth = $this->service('Youth Meeting');

        $schedules->session(
            $schedules->weekly($youth, 'Youth evening', 7),
            '2026-11-08',
            "status = 'active', opened_at = created_at",
        );

        $later = $this->service('Choir');
        $schedules->weekly($later, 'Monthly choir', 1, endsOn: '2026-11-08');
        $archived = $this->service('Old club');
        $schedules->weekly($archived, 'Old club', 7);
        $archived->archive(Structure::now());
        new Database(self::getContainer())->persisted($archived);
        new ServedGroups(self::getContainer())->everyService($this->signInWithRoles('servant@example.org', 'servant'));

        self::assertSame(
            ['date' => '2026-11-15', 'startTime' => '18:30', 'stage' => null, 'status' => 'planned'],
            $this->insightOf($service)['next'] ?? null,
        );

        self::assertSame(
            'active',
            JsonBody::asArray($this->insightOf($youth)['next'] ?? null, 'next')['status'] ?? null,
        );

        self::assertNull($this->insightOf($later)['next'] ?? null);
        self::assertNull($this->insightOf($archived, status: 'archived')['next'] ?? null);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function insightOf(Service $service, string $status = 'active'): array
    {
        $this->jsonRequest('GET', '/api/services/insights?status=' . $status);
        self::assertResponseIsSuccessful();

        $items = array_map(
            static fn (mixed $item): array => JsonBody::asArray($item, 'item'),
            JsonBody::asArray($this->responseJson()['items'] ?? null, 'items'),
        );

        foreach ($items as $insight) {
            if (($insight['serviceId'] ?? null) === $service->id()->toRfc4122()) {
                return $insight;
            }
        }

        self::fail('No insight for ' . $service->name()->value);
    }

    private function service(string $name): Service
    {
        $service = Structure::service($name);
        new Database(self::getContainer())->persisted($service);

        return $service;
    }

    /**
     * @return list<Person>
     */
    private function visitors(int $count): array
    {
        $rosters = new Rosters(self::getContainer());

        return array_map(
            static fn (int $n): Person => $rosters->person('Visitor', 'Number ' . $n),
            range(start: 1, end: $count),
        );
    }

    private function marked(Session $session, Person ...$people): void
    {
        $at = new DateTimeImmutable($session->occurrenceDate()->toString() . 'T18:45:00+01:00');

        foreach ($people as $person) {
            new Database(self::getContainer())->persisted(
                new Attendance(
                    new Schedules(self::getContainer())->managed($session),
                    $person->id(),
                    $at,
                    recordedBy: null,
                ),
            );
        }
    }
}
