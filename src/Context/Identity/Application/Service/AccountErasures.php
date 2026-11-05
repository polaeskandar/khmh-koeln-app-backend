<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Repository\PasswordTokenRepositoryInterface;
use App\Context\Identity\Domain\Repository\PersonTraceRepositoryInterface;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Context\Identity\Domain\ValueObject\AccountErased;
use App\Library\Problem\Exception\RuleViolation;
use App\Platform\Doctrine\Contract\Transactions;
use LogicException;
use Symfony\Component\Uid\Uuid;

final readonly class AccountErasures
{
    public function __construct(
        private UserRepositoryInterface $users,
        private LastAdministrator $lastAdministrator,
        private PasswordTokenRepositoryInterface $tokens,
        private Transactions $transactions,
        private PersonTraceRepositoryInterface $people,
    ) {
    }

    public function existing(?Uuid $accountId): ?Uuid
    {
        return $accountId === null ? null : $this->users->find($accountId)?->id();
    }

    public function accountOfRecord(?Uuid $personId): ?Uuid
    {
        return $personId === null ? null : $this->people->accountOf($personId);
    }

    public function recordOfAccount(?Uuid $accountId): ?Uuid
    {
        return $accountId === null ? null : $this->users->find($accountId)?->member()->personId;
    }

    public function forgetPerson(Uuid $personId): int
    {
        return $this->people->forget($personId);
    }

    /**
     * @param callable(): int $sweep
     */
    public function erase(Uuid $accountId, callable $sweep): AccountErased
    {
        $erased = null;

        $this->lastAdministrator->whileLocked(function () use ($accountId, $sweep, &$erased): void {
            $this->users->withWriteLock($accountId, function (User $user) use ($sweep, &$erased): void {
                $erased = $this->removed($user, $sweep);
            });
        });

        return $erased ?? throw new LogicException('The account was not erased.');
    }

    /**
     * @param callable(): int $sweep
     */
    public function rehearse(Uuid $accountId, callable $sweep): AccountErased
    {
        return $this->transactions->rehearse(fn (): AccountErased => $this->erase($accountId, $sweep));
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
