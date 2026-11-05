<?php

declare(strict_types=1);

namespace App\Context\People\Infrastructure\Persistence\Repository;

use App\Context\People\Domain\Repository\AccountTraceRepositoryInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineAccountTraceRepository implements AccountTraceRepositoryInterface
{
    public const array ACTOR_COLUMNS = [
        ['people.guardian_relationship', 'approved_by'],
        ['people.guardian_relationship', 'revoked_by'],
    ];

    private const array OWNED_ROWS = [
        ['people.guardian_relationship', 'user_id'],
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function forget(Uuid $accountId): int
    {
        $connection = $this->entityManager->getConnection();
        $id = $accountId->toRfc4122();

        foreach (self::OWNED_ROWS as [$table, $column]) {
            $connection->delete($table, [$column => $id]);
        }

        return new ArrayCollection(self::ACTOR_COLUMNS)->reduce(
            /**
             * @param array{string, string} $actor
             */
            static fn (int $kept, array $actor): int => $kept
            + (int) $connection->update($actor[0], [$actor[1] => null], [$actor[1] => $id]),
            initial: 0,
        );
    }
}
