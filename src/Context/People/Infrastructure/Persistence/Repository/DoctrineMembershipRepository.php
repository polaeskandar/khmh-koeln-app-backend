<?php

declare(strict_types=1);

namespace App\Context\People\Infrastructure\Persistence\Repository;

use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Repository\MembershipRepositoryInterface;
use App\Library\Problem\Exception\NotFound;
use App\Library\Time\Model\LocalDate;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineMembershipRepository implements MembershipRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function save(Membership $membership): void
    {
        $this->entityManager->persist($membership);
        $this->entityManager->flush();
    }

    #[Override]
    public function remove(Membership $membership): void
    {
        $this->entityManager->remove($membership);
        $this->entityManager->flush();
    }

    #[Override]
    public function hasCurrent(Person $person, Uuid $serviceId, ?Uuid $stageId, LocalDate $on): bool
    {
        return $this->entityManager
            ->getConnection()
            ->createQueryBuilder()
            ->select('1')
            ->from('people.membership', 'm')
            ->where('m.person_id = :person')
            ->andWhere('m.service_id = :service')
            ->andWhere('m.stage_id IS NOT DISTINCT FROM CAST(:stage AS uuid)')
            ->andWhere('m.starts_on <= :on AND (m.ends_on IS NULL OR m.ends_on >= :on)')
            ->setParameter('person', $person->id()->toRfc4122())
            ->setParameter('service', $serviceId->toRfc4122())
            ->setParameter('stage', $stageId?->toRfc4122())
            ->setParameter('on', $on->toString())
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne() !== false;
    }

    #[Override]
    public function withWriteLock(Uuid $id, callable $work): void
    {
        $this->entityManager->wrapInTransaction(function () use ($id, $work): void {
            $work(
                $this->entityManager->find(Membership::class, $id, LockMode::PESSIMISTIC_WRITE) ?? throw NotFound::of(
                    'membership',
                ),
            );
        });
    }
}
