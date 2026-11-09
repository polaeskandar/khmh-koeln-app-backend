<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\Account;
use App\Context\Identity\Domain\Repository\AccountRepositoryInterface;
use App\Context\Identity\Domain\Repository\ActiveAccountRepositoryInterface;
use App\Context\Identity\Domain\Repository\RoleRepositoryInterface;
use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Exception\RuleViolation;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class ParentAccounts
{
    public const string PARENT_ROLE = 'Parent';

    public function __construct(
        private AccountRepositoryInterface $accounts,
        private RoleRepositoryInterface $roles,
        private ActiveAccountRepositoryInterface $activeAccounts,
    ) {
    }

    public function byEmail(string $email): Account
    {
        return $this->accounts->findByEmail($email) ?? throw NotFound::of('account');
    }

    public function activeByEmail(string $email): Account
    {
        $account = $this->byEmail($email);

        return $account->status()->isActive() ? $account : throw new RuleViolation('guardians.accountInactive');
    }

    public function active(string $id): Account
    {
        $account = (Uuid::isValid($id) ? $this->accounts->find(Uuid::fromString($id)) : null) ?? throw NotFound::of(
            'user',
        );

        return $account->status()->isActive() ? $account : throw new RuleViolation('guardians.accountInactive');
    }

    /**
     * @param list<Uuid> $ids
     *
     * @return ReadableCollection<int, Account>
     */
    public function accounts(array $ids): ReadableCollection
    {
        return $this->accounts->findByIds($ids);
    }

    /**
     * @param positive-int $limit
     *
     * @return list<array{id: string, name: string, email: string}>
     */
    public function activeListed(int $limit): array
    {
        return $this->activeAccounts->listed($limit);
    }

    public function giveParentRole(Account $account): void
    {
        $parent = $this->roles->findByName(self::PARENT_ROLE) ?? throw NotFound::of('role');

        if ($account->roles()->contains($parent)) {
            return;
        }

        $account->assign($parent);
        $this->accounts->save($account);
    }
}
