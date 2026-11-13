<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Repository\ActiveAccountRepositoryInterface;
use App\Context\Identity\Domain\Repository\RoleRepositoryInterface;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Context\Identity\Domain\ValueObject\NamedAccounts;
use App\Library\Collections\Model\Collections;
use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Exception\RuleViolation;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Uid\Uuid;

final readonly class ParentAccounts
{
    public const string PARENT_ROLE = 'Parent';

    public function __construct(
        private UserRepositoryInterface $users,
        private RoleRepositoryInterface $roles,
        private ActiveAccountRepositoryInterface $activeAccounts,
        private AccountNames $names,
        private AccountLookup $accounts,
    ) {
    }

    public function getByUsername(string $username): User
    {
        return $this->users->findByUsername($username) ?? throw NotFound::of('account');
    }

    public function getActiveByUsername(string $username): User
    {
        $account = $this->getByUsername($username);

        return $account->status()->isActive() ? $account : throw new RuleViolation('guardians.accountInactive');
    }

    public function getActive(Uuid $id): User
    {
        $account = $this->users->find($id) ?? throw NotFound::of('user');

        return $account->status()->isActive() ? $account : throw new RuleViolation('guardians.accountInactive');
    }

    /**
     * @param list<Uuid> $ids
     */
    public function namedByIds(array $ids): NamedAccounts
    {
        return $this->accounts->namedByIds($ids);
    }

    public function nameOf(User $account): string
    {
        return $this->accounts->nameOf($account);
    }

    /**
     * @param positive-int $limit
     *
     * @return list<array{id: string, name: string, username: string}> by name
     */
    public function listActive(int $limit): array
    {
        $rows = $this->activeAccounts->listed($limit);
        $names = $this->names->of($rows);
        $sortKeyOf = AccountNames::sortKeys();

        return Collections::sortedBy(
            new ArrayCollection($rows)->map(static fn (array $row): array => [
                'id' => $row['id'],
                'name' => $names[$row['personId']] ?? '',
                'username' => $row['username'],
            ]),
            keyOf: static fn (array $row): string => $sortKeyOf($row['name']),
        );
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
