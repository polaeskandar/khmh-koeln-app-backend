<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Repository\MemberAccountRepositoryInterface;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Context\Identity\Domain\ValueObject\NamedAccounts;
use App\Library\Collections\Model\Collections;
use App\Library\Problem\Exception\NotFound;
use App\Platform\Directory\Contract\PersonNames;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class AccountLookup
{
    public function __construct(
        private UserRepositoryInterface $users,
        private MemberAccountRepositoryInterface $members,
        private PersonNames $people,
    ) {
    }

    /**
     * @param ReadableCollection<int, User> $accounts
     */
    public function named(ReadableCollection $accounts): NamedAccounts
    {
        return NamedAccounts::of(
            $accounts,
            $this->people->namesOf(
                $accounts->map(static fn (User $account): Uuid => $account->personId())->getValues(),
            ),
        );
    }

    /**
     * @param list<?Uuid> $ids
     */
    public function namedByIds(array $ids): NamedAccounts
    {
        $present = Collections::mapPresent(new ArrayCollection($ids), static fn (?Uuid $id): ?Uuid => $id);

        return $this->named($this->users->findByIds($present->getValues()));
    }

    /**
     * @param list<Uuid> $ids
     */
    public function activeNamedByIds(array $ids): NamedAccounts
    {
        return $this->named($this->users
            ->findByIds($ids)
            ->filter(static fn (User $account): bool => $account->status()->isActive()));
    }

    /**
     * @param list<Uuid> $ids
     *
     * @return list<User> the enabled accounts among them
     */
    public function enabledAmong(array $ids): array
    {
        return $this->users
            ->findByIds($ids)
            ->filter(static fn (User $account): bool => $account->status()->isActive())
            ->getValues();
    }

    public function nameOf(User $account): string
    {
        return $this->named(new ArrayCollection([$account]))->nameOf($account);
    }

    /**
     * @param list<Uuid> $personIds
     *
     * @return ReadableCollection<string, string> the approved, enabled account linked to each person's own record
     */
    public function listOwnAccountsOf(array $personIds): ReadableCollection
    {
        return new ArrayCollection($this->members->activeAccountsOf($personIds));
    }

    public function get(Uuid $id): User
    {
        return $this->users->find($id) ?? throw NotFound::of('user');
    }

    public function getByUsername(string $username): User
    {
        return $this->users->findByUsername($username) ?? throw NotFound::of('account');
    }

    public function findAccount(?Uuid $id): ?User
    {
        return $id === null ? null : $this->users->find($id);
    }
}
