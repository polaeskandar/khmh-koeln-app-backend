<?php

declare(strict_types=1);

namespace App\Context\Meetings\Infrastructure\Persistence\Repository;

use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\Enum\SessionStatus;
use App\Context\Meetings\Domain\Repository\SessionListRepositoryInterface;
use App\Library\Time\Model\LocalDate;
use App\Library\Types\Model\ListOf;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineSessionListRepository implements SessionListRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function on(LocalDate $date): ReadableCollection
    {
        return $this->sessions(
            $this->withSchedules()->where('s.occurrenceDate = :date')->setParameter('date', $date, 'local_date'),
        );
    }

    #[Override]
    public function openBetween(LocalDate $from, LocalDate $to): ReadableCollection
    {
        return $this->sessions(
            $this
                ->withSchedules()
                ->where('s.occurrenceDate BETWEEN :from AND :to')
                ->andWhere('s.progress.status IN (:open)')
                ->setParameter('from', $from, 'local_date')
                ->setParameter('to', $to, 'local_date')
                ->setParameter('open', [SessionStatus::Scheduled, SessionStatus::Active]),
        );
    }

    /**
     * @param list<Uuid> $excludedServiceIds
     */
    #[Override]
    public function latestClosed(int $limit, array $excludedServiceIds): ReadableCollection
    {
        $query = $this
            ->withSchedules()
            ->where('s.progress.status = :completed')
            ->setParameter('completed', SessionStatus::Completed)
            ->orderBy('s.progress.closed.at', 'DESC')
            ->setMaxResults($limit);

        if ($excludedServiceIds !== []) {
            $query->andWhere('sc.roster.serviceId NOT IN (:excluded)')->setParameter('excluded', $excludedServiceIds);
        }

        return $this->sessions($query);
    }

    /**
     * @param list<Uuid> $ids
     */
    #[Override]
    public function byIds(array $ids): ReadableCollection
    {
        if ($ids === []) {
            return new ArrayCollection();
        }

        return $this->sessions($this->withSchedules()->where('s.id IN (:ids)')->setParameter('ids', $ids));
    }

    private function withSchedules(): QueryBuilder
    {
        return $this->entityManager
            ->createQueryBuilder()
            ->select('s', 'sc')
            ->from(Session::class, 's')
            ->join('s.schedule', 'sc');
    }

    /**
     * @return ReadableCollection<int, Session>
     */
    private function sessions(QueryBuilder $query): ReadableCollection
    {
        return new ArrayCollection(ListOf::instances($query->getQuery()->getResult(), Session::class));
    }
}
