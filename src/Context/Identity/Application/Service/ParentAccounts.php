<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Repository\ActiveAccountRepositoryInterface;
use App\Context\Identity\Domain\Repository\RoleRepositoryInterface;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Exception\RuleViolation;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class ParentAccounts
{
    public const string PARENT_ROLE = 'Parent';

    public function __construct(
        private UserRepositoryInterface $users,
        private RoleRepositoryInterface $roles,
        private ActiveAccountRepositoryInterface $activeAccounts,
    ) {
    }

    public function getByEmail(string $email): User
    {
        return $this->users->findByEmail($email) ?? throw NotFound::of('account');
    }

    public function getActiveByEmail(string $email): User
    {
        $account = $this->getByEmail($email);

        return $account->status()->isActive() ? $account : throw new RuleViolation('guardians.accountInactive');
    }

    public function getActive(Uuid $id): User
    {
        $account = $this->users->find($id) ?? throw NotFound::of('user');

        return $account->status()->isActive() ? $account : throw new RuleViolation('guardians.accountInactive');
    }

    /**
     * @param list<Uuid> $ids
     *
     * @return ReadableCollection<int, User>
     */
    public function listByIds(array $ids): ReadableCollection
    {
        return $this->users->findByIds($ids);
    }

    /**
     * @param positive-int $limit
     *
     * @return list<array{id: string, name: string, email: string}>
     */
    public function listActive(int $limit): array
    {
        return $this->activeAccounts->listed($limit);
    }

    public function giveParentRole(User $account): void
    {
        $parent = $this->roles->findByName(self::PARENT_ROLE) ?? throw NotFound::of('role');

        if ($account->roles()->contains($parent)) {
            return;
        }

        $account->assign($parent);
        $this->users->save($account);
    }
}
