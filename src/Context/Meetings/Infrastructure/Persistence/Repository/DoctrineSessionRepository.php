<?php

declare(strict_types=1);

namespace App\Context\Meetings\Infrastructure\Persistence\Repository;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\Entity\SessionEvent;
use App\Context\Meetings\Domain\Enum\SessionEventType;
use App\Context\Meetings\Domain\Repository\SessionRepositoryInterface;
use App\Context\Meetings\Domain\ValueObject\Actor;
use App\Library\Time\Model\LocalDate;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Override;
use Throwable;

final readonly class DoctrineSessionRepository implements SessionRepositoryInterface
{
    private const string LOCK_SCHEDULE = 'SELECT id FROM meetings.meeting_schedule WHERE id = ? FOR UPDATE';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ManagerRegistry $doctrine,
    ) {
    }

    /**
     * @param list<LocalDate> $dates
     */
    #[Override]
    public function createMissing(Schedule $schedule, array $dates, Actor $actor, DateTimeImmutable $at): int
    {
        try {
            return $this->entityManager->wrapInTransaction(
                fn (): int => $this->createUnderLock($schedule, $dates, $actor, $at),
            );
        } catch (Throwable $failure) {
            $this->doctrine->resetManager();

            throw $failure;
        }
    }

    /**
     * @param list<LocalDate> $dates
     */
    private function createUnderLock(Schedule $schedule, array $dates, Actor $actor, DateTimeImmutable $at): int
    {
        $this->entityManager->getConnection()->executeQuery(self::LOCK_SCHEDULE, [$schedule->id()->toRfc4122()]);
        $locked = $this->entityManager->find(Schedule::class, $schedule->id());

        if ($locked === null) {
            return 0;
        }

        $missing = new ArrayCollection($dates)->filter(fn (LocalDate $date): bool => !$this->exists($locked, $date));

        foreach ($missing as $date) {
            $session = new Session($locked, $date);
            $this->entityManager->persist($session);

            $this->entityManager->persist(
                new SessionEvent($session, SessionEventType::Created, $at, $actor, note: null),
            );
        }

        return $missing->count();
    }

    private function exists(Schedule $schedule, LocalDate $date): bool
    {
        $found = $this->entityManager
            ->getRepository(Session::class)
            ->findOneBy(['schedule' => $schedule, 'occurrenceDate' => $date]);

        return $found !== null;
    }
}
