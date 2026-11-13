<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Persistence\Repository;

use App\Context\Scheduling\Domain\Entity\Session;
use App\Context\Scheduling\Domain\Enum\SessionStatus;
use App\Context\Scheduling\Domain\Repository\SessionListRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\CoveredRosters;
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
    public function listOn(LocalDate $date): ReadableCollection
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
     * @param list<Uuid> $archivedIds
     */
    #[Override]
    public function latestClosed(int $limit, array $archivedIds, CoveredRosters $covered): ReadableCollection
    {
        $query = $this
            ->withSchedules()
            ->where('s.progress.status = :completed')
            ->setParameter('completed', SessionStatus::Completed)
            ->orderBy('s.progress.closed.at', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->setMaxResults($limit);

        if ($archivedIds !== []) {
            $query
                ->andWhere('sc.roster.serviceId NOT IN (:excluded)')
                ->andWhere('sc.roster.stageId IS NULL OR sc.roster.stageId NOT IN (:excluded)')
                ->setParameter('excluded', $archivedIds);
        }

        if ($covered->serviceIds !== null) {
            $query
                ->andWhere('sc.roster.serviceId IN (:coveredServices) OR sc.roster.stageId IN (:coveredStages)')
                ->setParameter('coveredServices', $covered->serviceIds)
                ->setParameter('coveredStages', $covered->stageIds ?? []);
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
