<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Repository\PersonTraceRepositoryInterface;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Context\Identity\Domain\ValueObject\AccountErased;
use App\Library\Problem\Exception\NotFound;
use Symfony\Component\Uid\Uuid;

final readonly class AccountErasures
{
    public function __construct(
        private UserRepositoryInterface $users,
        private PersonTraceRepositoryInterface $people,
        private AccountRemovals $removals,
        private AccountSweeps $sweeps,
    ) {
    }

    public function accountOfRecord(?Uuid $personId): ?Uuid
    {
        return $personId === null ? null : $this->people->accountOf($personId);
    }

    public function recordOfAccount(?Uuid $accountId): ?Uuid
    {
        return $accountId === null ? null : $this->users->find($accountId)?->member()->personId;
    }

    public function erase(Uuid $accountId): AccountErased
    {
        if ($this->users->find($accountId) === null) {
            $this->sweeps->sweep($accountId);

            throw NotFound::of('account');
        }

        $removed = $this->removals->remove($accountId);

        return new AccountErased(
            $removed->roles,
            $removed->passwordLinks,
            $removed->rowsKept + $this->sweeps->sweep($accountId),
        );
    }

    public function sweep(Uuid $accountId): int
    {
        return $this->sweeps->sweep($accountId);
    }
}
