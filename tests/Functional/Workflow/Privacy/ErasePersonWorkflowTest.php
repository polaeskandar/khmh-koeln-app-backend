<?php

declare(strict_types=1);

namespace App\Tests\Functional\Workflow\Privacy;

use App\Context\Learning\Application\Service\HomeworkCompletions;
use App\Context\Meetings\Application\Service\AttendanceMarks;
use App\Context\Meetings\Application\Service\ErasedTotals;
use App\Context\Meetings\Domain\Entity\Attendance;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\People\Application\Service\PersonErasures;
use App\Context\People\Domain\Repository\PersonTraceRepositoryInterface;
use App\Context\People\Domain\Repository\RosterRepositoryInterface;
use App\Context\People\Domain\ValueObject\ForgottenPerson;
use App\Context\People\Infrastructure\Persistence\Repository\DoctrinePersonRepository;
use App\Platform\Doctrine\Contract\Transactions;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Meetings\Domain\Entity\Schedules;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Database;
use App\Workflow\Privacy\ErasePersonWorkflow;
use DateTimeImmutable;
use Override;
use RuntimeException;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

final class ErasePersonWorkflowTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    private const string DONE = "status = 'completed', opened_at = created_at, closed_at = created_at";

    public function testAnErasedPersonCannotBeMarkedAnymore(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        [$mina, $session] = $this->sessionWith(['Mina'], "status = 'active', opened_at = created_at");
        self::service(ErasePersonWorkflow::class)($mina);

        $this->signInWithRoles('servant@example.org', 'servant');
        $this->jsonRequest('PUT', '/api/sessions/' . $session->id()->toRfc4122() . '/attendance/' . $mina);
        $this->assertProblem(404, 'notFound.person');
    }

    public function testErasuresAddToTheCountersInsteadOfOverwritingThem(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        [$mina, $session, $marina] = $this->sessionWith(['Mina', 'Marina'], self::DONE);
        self::service(ErasePersonWorkflow::class)($mina);

        $stale = new Database(self::getContainer())
            ->entityManager()
            ->find(Session::class, $session->id());

        self::assertInstanceOf(Session::class, $stale);
        self::service(ErasePersonWorkflow::class)($marina);

        self::assertSame(
            ['erased_roster' => 2, 'erased_present' => 2, 'erased_roster_present' => 2],
            new Database(self::getContainer())
                ->connection()
                ->fetchAssociative('SELECT erased_roster, erased_present, erased_roster_present FROM meetings.meeting_session WHERE id = ?', [$session->id()->toRfc4122()]),
        );
    }

    public function testAFailureMidwayRollsEverythingBack(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        [$mina] = $this->sessionWith(['Mina'], self::DONE);
        $entityManager = new Database(self::getContainer())->entityManager();

        $failing = new class implements PersonTraceRepositoryInterface {
            #[Override]
            public function forget(Uuid $personId): ForgottenPerson
            {
                throw new RuntimeException('Midway.');
            }
        };

        $erasure = new ErasePersonWorkflow(
            self::service(Transactions::class),
            new PersonErasures(
                new DoctrinePersonRepository($entityManager),
                self::service(RosterRepositoryInterface::class),
                $failing,
            ),
            self::service(ErasedTotals::class),
            self::service(AttendanceMarks::class),
            self::service(HomeworkCompletions::class),
        );

        try {
            $erasure($mina);
            self::fail('The erasure did not fail.');
        } catch (RuntimeException $failure) {
            self::assertSame('Midway.', $failure->getMessage());
        }

        $connection = new Database(self::getContainer())->connection();
        self::assertSame(1, $connection->fetchOne('SELECT count(*) FROM meetings.attendance'));
        self::assertSame(0, $connection->fetchOne('SELECT sum(erased_present) FROM meetings.meeting_session'));
    }

    /**
     * @param non-empty-list<string> $names
     *
     * @return array{string, Session, string}
     */
    private function sessionWith(array $names, string $set): array
    {
        $service = Structure::service();
        new Database(self::getContainer())->persisted($service);
        $rosters = new Rosters(self::getContainer());
        $schedules = new Schedules(self::getContainer());
        $session = $schedules->session($schedules->weekly($service, 'Service', 7), '2026-11-01', $set);
        $ids = [];

        foreach ($names as $name) {
            $person = $rosters->person($name, 'Hanna');
            $rosters->place($person, $service, null, '2026-09-01');
            $at = new DateTimeImmutable('2026-11-01T12:00:00+01:00');

            new Database(self::getContainer())->persisted(
                new Attendance($schedules->managed($session), $person->id(), $at, recordedBy: null),
            );

            $ids[] = $person->id()->toRfc4122();
        }

        return [$ids[0], $session, $ids[1] ?? ''];
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private static function service(string $class): object
    {
        $service = self::getContainer()->get($class);
        self::assertInstanceOf($class, $service);

        return $service;
    }
}
