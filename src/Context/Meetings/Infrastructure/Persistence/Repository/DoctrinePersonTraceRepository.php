<?php

declare(strict_types=1);

namespace App\Context\Meetings\Infrastructure\Persistence\Repository;

use App\Context\Meetings\Domain\Repository\PersonTraceRepositoryInterface;
use App\Library\Types\Model\Type;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrinePersonTraceRepository implements PersonTraceRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<Uuid>
     */
    #[Override]
    public function presentAt(Uuid $personId): array
    {
        $ids = $this->entityManager
            ->getConnection()
            ->createQueryBuilder()
            ->select('session_id')
            ->from('meetings.attendance')
            ->where('person_id = :person')
            ->andWhere('removed_at IS NULL')
            ->setParameter('person', $personId->toRfc4122())
            ->fetchFirstColumn();

        return new ArrayCollection($ids)->map(static fn (mixed $id): Uuid => Uuid::fromString(
            Type::of($id)->toString(),
        ))->getValues();
    }

    #[Override]
    public function forget(Uuid $personId): int
    {
        $connection = $this->entityManager->getConnection();

        return (int) $connection->delete('meetings.attendance', ['person_id' => $personId->toRfc4122()]);
    }
}
