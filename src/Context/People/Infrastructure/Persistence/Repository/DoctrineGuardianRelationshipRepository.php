<?php

declare(strict_types=1);

namespace App\Context\People\Infrastructure\Persistence\Repository;

use App\Context\People\Domain\Entity\GuardianRelationship;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Enum\GuardianStatus;
use App\Context\People\Domain\Repository\GuardianRelationshipRepositoryInterface;
use App\Library\Types\Model\ListOf;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineGuardianRelationshipRepository implements GuardianRelationshipRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function save(GuardianRelationship $relationship): void
    {
        $this->entityManager->persist($relationship);
        $this->entityManager->flush();
    }

    #[Override]
    public function approvedFor(Uuid $accountId, Person $child): ?GuardianRelationship
    {
        return $this->entityManager
            ->getRepository(GuardianRelationship::class)
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
            ->from(GuardianRelationship::class, 'g')
            ->join('g.child', 'p')
            ->where('g.accountId = :account')
            ->andWhere('g.status = :approved')
            ->setParameter('account', $accountId, 'uuid')
            ->setParameter('approved', GuardianStatus::Approved)
            ->orderBy('p.name.firstName')
            ->getQuery();

        return new ArrayCollection(ListOf::instances($query->getResult(), GuardianRelationship::class));
    }
}
