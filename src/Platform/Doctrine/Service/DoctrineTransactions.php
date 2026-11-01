<?php

declare(strict_types=1);

namespace App\Platform\Doctrine\Service;

use App\Platform\Doctrine\Contract\Transactions;
use Doctrine\ORM\EntityManagerInterface;
use Override;

final readonly class DoctrineTransactions implements Transactions
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function run(callable $work): void
    {
        $this->entityManager->wrapInTransaction(static function () use ($work): void {
            $work();
        });
    }
}
