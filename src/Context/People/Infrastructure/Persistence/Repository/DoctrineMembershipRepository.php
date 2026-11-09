<?php

declare(strict_types=1);

namespace App\Context\People\Infrastructure\Persistence\Repository;

use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Repository\MembershipRepositoryInterface;
use App\Context\People\Domain\ValueObject\Placement;
use App\Library\Problem\Exception\NotFound;
use App\Library\Time\Model\LocalDate;
use App\Library\Types\Model\Integers;
use App\Library\Types\Model\Type;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Order;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineMembershipRepository implements MembershipRepositoryInterface
{
    private const string PERSON_OF = 'SELECT person_id::text FROM people.membership WHERE id = ?';

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
    public function countInService(Uuid $serviceId): int
    {
        return $this->countWhere('service_id', $serviceId);
    }

    #[Override]
    public function countInStage(Uuid $stageId): int
    {
        return $this->countWhere('stage_id', $stageId);
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
    public function findAtOtherLevel(Person $person, Placement $placement, LocalDate $from): ?Membership
    {
        $level = $placement->stageId === null
            ? Criteria::expr()->neq('stageId', null)
            : Criteria::expr()->isNull('stageId');

        $first = $this->entityManager
            ->getRepository(Membership::class)
            ->matching(
                Criteria::create()
                    ->where(Criteria::expr()->eq('person', $person->id()))
                    ->andWhere(Criteria::expr()->eq('serviceId', $placement->serviceId))
                    ->andWhere($level)
                    ->andWhere(Criteria::expr()->orX(
                        Criteria::expr()->isNull('tenure.endsOn'),
                        Criteria::expr()->gte('tenure.endsOn', $from),
                    ))
                    ->orderBy(['tenure.startsOn' => Order::Ascending, 'id' => Order::Ascending])
                    ->setMaxResults(1),
            )
            ->first();

        return $first === false ? null : $first;
    }

    #[Override]
    public function personOf(Uuid $membershipId): ?Uuid
    {
        $connection = $this->entityManager->getConnection();
        $personId = Type::of($connection->fetchOne(self::PERSON_OF, [$membershipId->toRfc4122()]))->toStringOrNull();

        return $personId === null ? null : Uuid::fromString($personId);
    }

    #[Override]
    public function withWriteLock(Uuid $id, callable $work): mixed
    {
        return $this->entityManager->wrapInTransaction(fn (): mixed => $work(
            $this->entityManager->find(Membership::class, $id, LockMode::PESSIMISTIC_WRITE) ?? throw NotFound::of(
                'membership',
            ),
        ));
    }

    private function countWhere(string $column, Uuid $id): int
    {
        return Integers::of(
            $this->entityManager
                ->getConnection()
                ->createQueryBuilder()
                ->select('count(*)')
                ->from('people.membership')
                ->where($column . ' = :id')
                ->setParameter('id', $id->toRfc4122())
                ->fetchOne(),
        );
    }
}
