<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Repository\RoleRepositoryInterface;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Exception\RuleViolation;

final readonly class ParentAccounts
{
    public const string PARENT_ROLE = 'Parent';

    public function __construct(
        private UserRepositoryInterface $users,
        private RoleRepositoryInterface $roles,
    ) {
    }

    public function byEmail(string $email): User
    {
        return $this->users->findByEmail($email) ?? throw NotFound::of('account');
    }

    public function activeByEmail(string $email): User
    {
        $account = $this->byEmail($email);

        return $account->status()->isActive() ? $account : throw new RuleViolation('guardians.accountInactive');
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
