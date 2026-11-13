<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Context\Identity\Domain\ValueObject\AccountErased;
use App\Library\Problem\Exception\RuleViolation;
use App\Platform\Access\Contract\Administration;
use LogicException;
use Symfony\Component\Uid\Uuid;

final readonly class AccountRemovals
{
    public function __construct(
        private Administration $administration,
        private UserRepositoryInterface $users,
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
        if ($this->administration->isLastAdministrator($user->id())) {
            throw new RuleViolation('roles.lastAdministrator');
        }

        $erased = new AccountErased(
            $this->administration->countRolesOf($user->id()),
            $this->sweeps->sweep($user->id()),
        );

        $this->users->remove($user);

        return $erased;
    }
}
