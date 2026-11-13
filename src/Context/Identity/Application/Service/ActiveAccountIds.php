<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Platform\Access\Contract\ActiveAccounts;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class ActiveAccountIds implements ActiveAccounts
{
    public function __construct(
        private UserRepositoryInterface $users,
    ) {
    }

    #[Override]
    public function isEnabled(Uuid $accountId): bool
    {
        return $this->users->find($accountId)?->status()->isActive() === true;
    }

    /**
     * @param list<Uuid> $accountIds
     *
     * @return list<Uuid> the enabled accounts among them
     */
    #[Override]
    public function enabledAmong(array $accountIds): array
    {
        return $this->users
            ->findByIds($accountIds)
            ->filter(static fn (User $user): bool => $user->status()->isActive())
            ->map(static fn (User $user): Uuid => $user->id())
            ->getValues();
    }

    /**
     * @param list<Uuid> $accountIds
     *
     * @return list<Uuid> the enabled accounts among them that no requested erasure waits for
     */
    #[Override]
    public function remainingAmong(array $accountIds): array
    {
        return $this->users
            ->findByIds($accountIds)
            ->filter(static fn (User $user): bool => $user->status()->isActive() && $user->erasesOn() === null)
            ->map(static fn (User $user): Uuid => $user->id())
            ->getValues();
    }
}
