<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Persistence\Repository;

use App\Context\Scheduling\Domain\Entity\Session;
use App\Context\Scheduling\Domain\Enum\SessionStatus;
use App\Context\Scheduling\Domain\Repository\RecentSessionRepositoryInterface;
use App\Library\Collections\Model\Collections;
use App\Library\Types\Model\ListOf;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\ORM\EntityManagerInterface;
use Override;

final readonly class DoctrineRecentSessionRepository implements RecentSessionRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function latestCompleted(ReadableCollection $schedules, int $limit): ReadableCollection
    {
        if ($schedules->isEmpty()) {
            return new ArrayCollection();
        }

        $query = $this->entityManager
            ->createQueryBuilder()
            ->select('s')
            ->from(Session::class, 's')
            ->join('s.schedule', 'schedule')
            ->where('s.schedule IN (:schedules)')
            ->andWhere('s.progress.status = :completed')
            ->setParameter('schedules', $schedules->toArray())
            ->setParameter('completed', SessionStatus::Completed)
            ->orderBy('s.occurrenceDate', 'DESC')
            ->addOrderBy('schedule.timeSlot.startTime', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery();

        $latest = new ArrayCollection(ListOf::instances($query->getResult(), Session::class));

        return new ArrayCollection(Collections::sortedBy($latest, keyOf: self::orderKey(...)));
    }

    private static function orderKey(Session $session): string
    {
        $startTime = $session->schedule()->timeSlot()->startTime->toString();

        return $session->occurrenceDate()->toString() . ' ' . $startTime . ' ' . $session->id()->toRfc4122();
    }
}
