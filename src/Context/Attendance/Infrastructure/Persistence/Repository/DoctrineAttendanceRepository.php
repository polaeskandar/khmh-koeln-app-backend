<?php

declare(strict_types=1);

namespace App\Context\Attendance\Infrastructure\Persistence\Repository;

use App\Context\Attendance\Domain\Entity\Attendance;
use App\Context\Attendance\Domain\Repository\AttendanceRepositoryInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Order;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineAttendanceRepository implements AttendanceRepositoryInterface
{
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
                'sessionId' => $sessionId,
                'personId' => $personId,
                'removal.at' => null,
            ]);
    }

    #[Override]
    public function marksOf(Uuid $sessionId): ReadableCollection
    {
        return new ArrayCollection($this->entityManager->getRepository(Attendance::class)->findBy([
            'sessionId' => $sessionId,
        ], ['recordedAt' => 'ASC']));
    }

    #[Override]
    public function removedMarksOf(Uuid $sessionId): ReadableCollection
    {
        return $this->entityManager->getRepository(Attendance::class)->matching(Criteria::create()
            ->where(Criteria::expr()->eq('sessionId', $sessionId))
            ->andWhere(Criteria::expr()->neq('removal.at', null))
            ->orderBy(['removal.at' => Order::Ascending]));
    }

    /**
     * @param list<Uuid> $sessionIds
     */
    #[Override]
    public function currentMarksOf(array $sessionIds): ReadableCollection
    {
        if ($sessionIds === []) {
            return new ArrayCollection();
        }

        return new ArrayCollection($this->entityManager
            ->getRepository(Attendance::class)
            ->findBy(['sessionId' => $sessionIds, 'removal.at' => null]));
    }
}
