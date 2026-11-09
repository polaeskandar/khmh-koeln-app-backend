<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\Account;
use App\Context\Identity\Domain\Interface\AdministrationLockInterface;
use App\Context\Identity\Domain\Repository\AccountRepositoryInterface;
use App\Context\Identity\Domain\Repository\PasswordTokenRepositoryInterface;
use App\Context\Identity\Domain\ValueObject\AccountErased;
use App\Library\Problem\Exception\RuleViolation;
use LogicException;
use Symfony\Component\Uid\Uuid;

final readonly class AccountRemovals
{
    public function __construct(
        private AdministrationLockInterface $administration,
        private AccountRepositoryInterface $accounts,
        private LastAdministrator $lastAdministrator,
        private PasswordTokenRepositoryInterface $tokens,
        private AccountSweeps $sweeps,
    ) {
    }

    public function remove(Uuid $accountId): AccountErased
    {
        $erased = null;

        $this->administration->whileLocked(function () use ($accountId, &$erased): void {
            $this->accounts->withWriteLock($accountId, function (Account $account) use (&$erased): void {
                $erased = $this->removed($account);
            });
        });

        return $erased ?? throw new LogicException('The account was not erased.');
    }

    private function removed(Account $account): AccountErased
    {
        if ($this->lastAdministrator->is($account)) {
            throw new RuleViolation('roles.lastAdministrator');
        }

        $erased = new AccountErased(
            $account->roles()->count(),
            $this->tokens->countFor($account->id()),
            $this->sweeps->sweep($account->id()),
        );

        $this->accounts->remove($account);

        return $erased;
    }
}
