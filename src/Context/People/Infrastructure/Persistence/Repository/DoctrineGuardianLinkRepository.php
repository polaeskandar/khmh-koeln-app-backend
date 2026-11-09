<?php

declare(strict_types=1);

namespace App\Context\People\Infrastructure\Persistence\Repository;

use App\Context\People\Domain\Entity\GuardianLink;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Enum\GuardianStatus;
use App\Context\People\Domain\Repository\GuardianLinkRepositoryInterface;
use App\Library\Types\Model\ListOf;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineGuardianLinkRepository implements GuardianLinkRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function save(GuardianLink $link): void
    {
        $this->entityManager->persist($link);
        $this->entityManager->flush();
    }

    #[Override]
    public function find(Uuid $id): ?GuardianLink
    {
        return $this->entityManager->find(GuardianLink::class, $id);
    }

    /**
     * @param positive-int $limit
     */
    #[Override]
    public function approved(int $limit): ReadableCollection
    {
        $query = $this->entityManager
            ->createQueryBuilder()
            ->select('g', 'p')
            ->from(GuardianLink::class, 'g')
            ->join('g.child', 'p')
            ->where('g.status = :approved')
            ->setParameter('approved', GuardianStatus::Approved)
            ->orderBy('g.approvedAt', 'DESC')
            ->addOrderBy('g.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery();

        return new ArrayCollection(ListOf::instances($query->getResult(), GuardianLink::class));
    }

    #[Override]
    public function approvedFor(Uuid $accountId, Person $child): ?GuardianLink
    {
        return $this->entityManager
            ->getRepository(GuardianLink::class)
            ->findOneBy([
                'accountId' => $accountId,
                'child' => $child,
                'status' => GuardianStatus::Approved,
            ]);
    }

    #[Override]
    public function approvedOf(Uuid $accountId): ReadableCollection
    {
        $query = $this->entityManager
            ->createQueryBuilder()
            ->select('g', 'p')
            ->from(GuardianLink::class, 'g')
            ->join('g.child', 'p')
            ->where('g.accountId = :account')
            ->andWhere('g.status = :approved')
            ->setParameter('account', $accountId, 'uuid')
            ->setParameter('approved', GuardianStatus::Approved)
            ->orderBy('p.name.firstName')
            ->getQuery();

        return new ArrayCollection(ListOf::instances($query->getResult(), GuardianLink::class));
    }

    /**
     * @param list<Uuid> $childIds
     */
    #[Override]
    public function approvedForChildren(array $childIds): ReadableCollection
    {
        if ($childIds === []) {
            return new ArrayCollection();
        }

        $query = $this->entityManager
            ->createQueryBuilder()
            ->select('g')
            ->from(GuardianLink::class, 'g')
            ->where('IDENTITY(g.child) IN (:children)')
            ->andWhere('g.status = :approved')
            ->setParameter(
                'children',
                new ArrayCollection($childIds)->map(static fn (Uuid $id): string => $id->toRfc4122())->getValues(),
                ArrayParameterType::STRING,
            )
            ->setParameter('approved', GuardianStatus::Approved)
            ->getQuery();

        return new ArrayCollection(ListOf::instances($query->getResult(), GuardianLink::class));
    }

    #[Override]
    public function allForChild(Person $child): ReadableCollection
    {
        return new ArrayCollection($this->entityManager->getRepository(GuardianLink::class)->findBy([
            'child' => $child,
        ], ['approvedAt' => 'ASC', 'id' => 'ASC']));
    }

    #[Override]
    public function allOfAccount(Uuid $accountId): ReadableCollection
    {
        return new ArrayCollection($this->entityManager->getRepository(GuardianLink::class)->findBy([
            'accountId' => $accountId,
        ], ['approvedAt' => 'ASC', 'id' => 'ASC']));
    }
}
