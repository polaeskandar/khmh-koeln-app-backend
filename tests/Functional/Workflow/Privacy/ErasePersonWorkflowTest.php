<?php

declare(strict_types=1);

namespace App\Tests\Functional\Workflow\Privacy;

use App\Context\Identity\Application\Service\PersonTraces as IdentityTraces;
use App\Context\Learning\Application\Service\PersonTraces as LearningTraces;
use App\Context\Meetings\Application\Service\PersonTraces as MeetingTraces;
use App\Context\Meetings\Domain\Entity\AttendanceMark;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\PersonErasures;
use App\Context\People\Domain\Repository\PersonTraceRepositoryInterface;
use App\Context\People\Domain\Repository\RosterRepositoryInterface;
use App\Context\People\Domain\ValueObject\ForgottenPerson;
use App\Context\People\Infrastructure\Persistence\Repository\DoctrinePersonRepository;
use App\Context\Privacy\Application\Service\PersonTraces as PrivacyTraces;
use App\Platform\Doctrine\Contract\TransactionsInterface;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Domain\Entity\Accounts;
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

    public function testErasingAPersonRemovesTheLinkOfTheirOwnAccount(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        [$mina] = $this->sessionWith(['Mina'], self::DONE);

        $this->linkAccountTo($mina);
        self::service(ErasePersonWorkflow::class)($mina);

        self::assertSame(
            ['person_id' => null, 'age_check' => null, 'guardian_consent_note' => null],
            new Database(self::getContainer())
                ->connection()
                ->fetchAssociative('SELECT person_id, age_check, guardian_consent_note FROM identity.app_user'),
        );
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
        $this->linkAccountTo($mina);
        $entityManager = new Database(self::getContainer())->entityManager();

        $failing = new class implements PersonTraceRepositoryInterface {
            #[Override]
            public function forget(Uuid $personId): ForgottenPerson
            {
                throw new RuntimeException('Midway.');
            }

            #[Override]
            public function countGuardianLinksOf(Uuid $accountId): int
            {
                return 0;
            }
        };

        $erasure = new ErasePersonWorkflow(
            new PersonErasures(
                new DoctrinePersonRepository($entityManager),
                self::service(RosterRepositoryInterface::class),
                $failing,
                [
                    self::service(MeetingTraces::class),
                    self::service(LearningTraces::class),
                    self::service(PrivacyTraces::class),
                    self::service(IdentityTraces::class),
                ],
                [],
            ),
            self::service(StructureLookup::class),
            self::service(TransactionsInterface::class),
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
        self::assertSame($mina, $connection->fetchOne('SELECT person_id::text FROM identity.app_user'));
    }

    private function linkAccountTo(string $personId): void
    {
        new Database(self::getContainer())
            ->connection()
            ->insert('identity.app_user', Accounts::row([
                'email' => 'mina@example.org',
                'person_id' => $personId,
                'age_check' => 'guardianConsent',
                'guardian_consent_note' => 'Father agreed.',
            ]));
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
                new AttendanceMark($schedules->managed($session), $person->id(), $at, recordedBy: null),
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
