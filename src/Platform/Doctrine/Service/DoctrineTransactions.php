<?php

declare(strict_types=1);

namespace App\Platform\Doctrine\Service;

use App\Platform\Doctrine\Contract\Transactions;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Override;

final readonly class DoctrineTransactions implements Transactions
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ManagerRegistry $doctrine,
    ) {
    }

    #[Override]
    public function run(callable $work): void
    {
        $this->entityManager->wrapInTransaction(static function () use ($work): void {
            $work();
        });
    }

    #[Override]
    public function recover(): void
    {
        $this->doctrine->resetManager();
    }
}
