<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Persistence\Repository;

use App\Context\Scheduling\Domain\Entity\Attendance;
use App\Context\Scheduling\Domain\Repository\AttendanceRepositoryInterface;
use App\Library\Collections\Model\Groups;
use App\Library\Types\Model\Type;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Order;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineAttendanceRepository implements AttendanceRepositoryInterface
{
    private const string PRESENT = <<<'SQL'
        SELECT session_id, person_id FROM scheduling.attendance
        WHERE removed_at IS NULL AND session_id IN (?)
        ORDER BY recorded_at, id
        SQL;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function save(Attendance $attendance): void
    {
        $this->entityManager->persist($attendance);
        $this->entityManager->flush();
    }

    #[Override]
    public function currentMark(Uuid $sessionId, Uuid $personId): ?Attendance
    {
        return $this->entityManager
            ->getRepository(Attendance::class)
            ->findOneBy([
                'session' => $sessionId,
                'personId' => $personId,
                'removal.at' => null,
            ]);
    }

    #[Override]
    public function listRemovedMarksOf(Uuid $sessionId): ReadableCollection
    {
        return $this->entityManager->getRepository(Attendance::class)->matching(Criteria::create()
            ->where(Criteria::expr()->eq('session', $sessionId))
            ->andWhere(Criteria::expr()->neq('removal.at', null))
            ->orderBy(['removal.at' => Order::Ascending, 'id' => Order::Ascending]));
    }

    #[Override]
    public function listMarksOfPerson(Uuid $personId): ReadableCollection
    {
        return new ArrayCollection($this->entityManager->getRepository(Attendance::class)->findBy([
            'personId' => $personId,
        ], ['recordedAt' => 'ASC', 'id' => 'ASC']));
    }

    #[Override]
    public function listCurrentMarksOf(Uuid $sessionId): ReadableCollection
    {
        return $this->entityManager->getRepository(Attendance::class)->matching(Criteria::create()
            ->where(Criteria::expr()->eq('session', $sessionId))
            ->andWhere(Criteria::expr()->isNull('removal.at'))
            ->orderBy(['recordedAt' => Order::Descending, 'id' => Order::Descending]));
    }

    /**
     * @param list<Uuid> $sessionIds
     */
    #[Override]
    public function listPresentBySession(array $sessionIds): ReadableCollection
    {
        if ($sessionIds === []) {
            return new ArrayCollection();
        }

        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            self::PRESENT,
            [new ArrayCollection($sessionIds)->map(static fn (Uuid $id): string => $id->toRfc4122())->getValues()],
            [ArrayParameterType::STRING],
        );

        return Groups::of(
            new ArrayCollection($rows),
            keyOf: static fn (array $row): string => Type::of($row['session_id'] ?? null)->toString(),
            valueOf: static fn (array $row): string => Type::of($row['person_id'] ?? null)->toString(),
        );
    }
}
