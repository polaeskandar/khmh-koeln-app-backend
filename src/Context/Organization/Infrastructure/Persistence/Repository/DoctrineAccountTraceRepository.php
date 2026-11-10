<?php

declare(strict_types=1);

namespace App\Context\Organization\Infrastructure\Persistence\Repository;

use App\Context\Organization\Domain\Repository\AccountTraceRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineAccountTraceRepository implements AccountTraceRepositoryInterface
{
    public const array ACTOR_COLUMNS = [];

    private const array OWNED_ROWS = [
        ['organization.registration_service', 'account_id'],
        ['organization.staff_assignment',     'account_id'],
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

        return 0;
    }
}
