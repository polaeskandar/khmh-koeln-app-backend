<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Interface\AdministrationLockInterface;
use App\Context\Identity\Domain\Repository\PasswordTokenRepositoryInterface;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Context\Identity\Domain\ValueObject\AccountErased;
use App\Library\Problem\Exception\RuleViolation;
use LogicException;
use Symfony\Component\Uid\Uuid;

final readonly class AccountRemovals
{
    public function __construct(
        private AdministrationLockInterface $administration,
        private UserRepositoryInterface $users,
        private LastAdministrator $lastAdministrator,
        private PasswordTokenRepositoryInterface $tokens,
        private AccountSweeps $sweeps,
    ) {
    }

    public function remove(Uuid $accountId): AccountErased
    {
        $erased = null;

        $this->administration->whileLocked(function () use ($accountId, &$erased): void {
            $this->users->withWriteLock($accountId, function (User $user) use (&$erased): void {
                $erased = $this->removed($user);
            });
        });

        return $erased ?? throw new LogicException('The account was not erased.');
    }

    private function removed(User $user): AccountErased
    {
        if ($this->lastAdministrator->isKeptOnlyByAccount($user)) {
            throw new RuleViolation('roles.lastAdministrator');
        }

        $erased = new AccountErased(
            $user->roles()->count(),
            $this->tokens->countFor($user->id()),
            $this->sweeps->sweep($user->id()),
        );

        $this->users->remove($user);

        return $erased;
    }
}
