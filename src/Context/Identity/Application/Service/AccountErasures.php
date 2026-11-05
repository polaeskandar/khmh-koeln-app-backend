<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Interface\AdministrationLockInterface;
use App\Context\Identity\Domain\Repository\PasswordTokenRepositoryInterface;
use App\Context\Identity\Domain\Repository\PersonTraceRepositoryInterface;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Context\Identity\Domain\ValueObject\AccountErased;
use App\Library\Problem\Exception\RuleViolation;
use LogicException;
use Symfony\Component\Uid\Uuid;

final readonly class AccountErasures
{
    public function __construct(
        private AdministrationLockInterface $administration,
        private UserRepositoryInterface $users,
        private LastAdministrator $lastAdministrator,
        private PasswordTokenRepositoryInterface $tokens,
        private PersonTraceRepositoryInterface $people,
    ) {
    }

    public function exists(Uuid $accountId): bool
    {
        return $this->users->find($accountId) !== null;
    }

    public function accountOfRecord(?Uuid $personId): ?Uuid
    {
        return $personId === null ? null : $this->people->accountOf($personId);
    }

    public function recordOfAccount(?Uuid $accountId): ?Uuid
    {
        return $accountId === null ? null : $this->users->find($accountId)?->member()->personId;
    }

    /**
     * @param callable(): int $sweep
     */
    public function erase(Uuid $accountId, callable $sweep): AccountErased
    {
        $erased = null;

        $this->administration->whileLocked(function () use ($accountId, $sweep, &$erased): void {
            $this->users->withWriteLock($accountId, function (User $user) use ($sweep, &$erased): void {
                $erased = $this->removed($user, $sweep);
            });
        });

        return $erased ?? throw new LogicException('The account was not erased.');
    }

    /**
     * @param callable(): int $sweep
     */
    private function removed(User $user, callable $sweep): AccountErased
    {
        if ($this->lastAdministrator->is($user)) {
            throw new RuleViolation('roles.lastAdministrator');
        }

        $erased = new AccountErased($user->roles()->count(), $this->tokens->countFor($user->id()), $sweep());
        $this->users->remove($user);

        return $erased;
    }
}
