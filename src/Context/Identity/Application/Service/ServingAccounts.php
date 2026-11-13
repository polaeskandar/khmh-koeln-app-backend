<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Library\Problem\Exception\NotFound;
use Symfony\Component\Uid\Uuid;

final readonly class ServingAccounts
{
    public function __construct(
        private UserRepositoryInterface $users,
        private AccountLookup $accounts,
    ) {
    }

    public function get(Uuid $id): User
    {
        return $this->users->find($id) ?? throw NotFound::of('user');
    }

    /**
     * @param list<Uuid> $ids the accounts with a role that holds in assigned groups
     *
     * @return list<User> the enabled ones, by name
     */
    public function listActiveAmong(array $ids): array
    {
        return $this->accounts->named($this->users
            ->findByIds($ids)
            ->filter(static fn (User $user): bool => $user->status()->isActive()))->byName();
    }
}
