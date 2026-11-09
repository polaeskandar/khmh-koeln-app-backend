<?php

declare(strict_types=1);

namespace App\Platform\Doctrine\Service;

use App\Platform\Doctrine\Contract\TransactionsInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Override;

final readonly class DoctrineTransactions implements TransactionsInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ManagerRegistry $doctrine,
    ) {
    }

    #[Override]
    public function run(callable $work): mixed
    {
        return $this->entityManager->wrapInTransaction(static fn (): mixed => $work());
    }

    #[Override]
    public function recover(): void
    {
        $this->doctrine->resetManager();
    }

    #[Override]
    public function rehearse(callable $work): mixed
    {
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            return $work();
        } finally {
            $connection->rollBack();
            $this->entityManager->clear();
        }
    }
}
