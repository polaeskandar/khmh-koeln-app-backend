<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Repository\AccountRepositoryInterface;
use App\Context\Identity\Domain\Repository\PersonTraceRepositoryInterface;
use App\Context\Identity\Domain\ValueObject\AccountErased;
use App\Library\Problem\Exception\NotFound;
use Symfony\Component\Uid\Uuid;

final readonly class AccountErasures
{
    public function __construct(
        private AccountRepositoryInterface $accounts,
        private PersonTraceRepositoryInterface $people,
        private AccountDeletions $deletions,
        private AccountSweeps $sweeps,
    ) {
    }

    public function accountOfRecord(?Uuid $personId): ?Uuid
    {
        return $personId === null ? null : $this->people->accountOf($personId);
    }

    public function recordOfAccount(?Uuid $accountId): ?Uuid
    {
        return $accountId === null ? null : $this->accounts->find($accountId)?->member()->personId;
    }

    public function erase(Uuid $accountId): AccountErased
    {
        if ($this->accounts->find($accountId) === null) {
            $this->sweeps->sweep($accountId);

            throw NotFound::of('account');
        }

        $deleted = $this->deletions->delete($accountId);

        return new AccountErased(
            $deleted->roles,
            $deleted->passwordLinks,
            $deleted->rowsKept + $this->sweeps->sweep($accountId),
        );
    }

    public function sweep(Uuid $accountId): int
    {
        return $this->sweeps->sweep($accountId);
    }
}
