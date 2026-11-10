<?php

declare(strict_types=1);

namespace App\Context\Organization\Infrastructure\Persistence\Repository;

use App\Context\Organization\Domain\Entity\StaffAssignment;
use App\Context\Organization\Domain\Repository\StaffAssignmentRepositoryInterface;
use App\Library\Types\Model\ListOf;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineStaffAssignmentRepository implements StaffAssignmentRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function add(StaffAssignment $assignment): void
    {
        $this->entityManager->persist($assignment);
        $this->entityManager->flush();
    }

    #[Override]
    public function remove(StaffAssignment $assignment): void
    {
        $this->entityManager->remove($assignment);
        $this->entityManager->flush();
    }

    #[Override]
    public function find(Uuid $id): ?StaffAssignment
    {
        return $this->entityManager->find(StaffAssignment::class, $id);
    }

    #[Override]
    public function findHeld(Uuid $accountId, Uuid $serviceId, ?Uuid $stageId): ?StaffAssignment
    {
        return $this->entityManager
            ->getRepository(StaffAssignment::class)
            ->findOneBy([
                'accountId' => $accountId,
                'service' => $serviceId,
                'stage' => $stageId,
            ]);
    }

    /**
     * @param list<Uuid> $accountIds
     */
    #[Override]
    public function ofAccounts(array $accountIds): ReadableCollection
    {
        if ($accountIds === []) {
            return new ArrayCollection();
        }

        return $this->listed(
            $this->ordered()->where('a.accountId IN (:accounts)')->setParameter('accounts', $accountIds),
        );
    }

    #[Override]
    public function ofService(Uuid $serviceId): ReadableCollection
    {
        return $this->listed($this->ordered()->where('s.id = :service')->setParameter('service', $serviceId));
    }

    /**
     * @param list<Uuid> $serviceIds
     * @param list<Uuid> $stageIds
     */
    #[Override]
    public function covering(array $serviceIds, array $stageIds): ReadableCollection
    {
        if ($serviceIds === [] && $stageIds === []) {
            return new ArrayCollection();
        }

        return $this->listed(
            $this
                ->ordered()
                ->where('(t.id IS NULL AND s.id IN (:services)) OR t.id IN (:stages)')
                ->setParameter('services', $serviceIds)
                ->setParameter('stages', $stageIds),
        );
    }

    private function ordered(): QueryBuilder
    {
        return $this->entityManager
            ->createQueryBuilder()
            ->select('a', 's', 't')
            ->addSelect('CASE WHEN t.id IS NULL THEN 0 ELSE 1 END AS HIDDEN level')
            ->from(StaffAssignment::class, 'a')
            ->join('a.service', 's')
            ->leftJoin('a.stage', 't')
            ->orderBy('s.name.key', 'ASC')
            ->addOrderBy('level', 'ASC')
            ->addOrderBy('t.position', 'ASC')
            ->addOrderBy('a.id', 'ASC');
    }

    /**
     * @return ReadableCollection<int, StaffAssignment>
     */
    private function listed(QueryBuilder $query): ReadableCollection
    {
        return new ArrayCollection(ListOf::instances($query->getQuery()->getResult(), StaffAssignment::class));
    }
}
