<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\ValueObject\NamedAccounts;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\ValueObject\RosterBook;
use App\Library\Collections\Model\Collections;
use App\Workflow\Identity\Model\AccountReference;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class AudienceAccounts
{
    /**
     * @param NamedAccounts $users the parents' and the reporters' accounts
     * @param ReadableCollection<string, string> $own the active account linked to each person's own record
     */
    private function __construct(
        private RosterBook $rosters,
        public NamedAccounts $users,
        private ReadableCollection $own,
    ) {
    }

    /**
     * @param NamedAccounts $users the parents' and the reporters' accounts
     * @param ReadableCollection<string, string> $own the active account linked to each person's own record
     */
    public static function of(RosterBook $rosters, NamedAccounts $users, ReadableCollection $own): self
    {
        return new self($rosters, $users, $own);
    }

    /**
     * @return list<AccountReference>
     */
    public function parentsOf(Person $child): array
    {
        $accountOf = $this->users->find(...);

        return Collections::mapPresent(new ArrayCollection($this->rosters->guardianAccountIdsOf($child)), $accountOf)
            ->filter(static fn (User $user): bool => $user->status()->isActive())
            ->map(fn (User $user): AccountReference => AccountReference::of($user, $this->users))
            ->getValues();
    }

    public function hasOwnAccount(Person $person): bool
    {
        return $this->own->containsKey($person->id()->toRfc4122());
    }

    public function isOwnAccount(Person $person, ?Uuid $accountId): bool
    {
        return $accountId !== null && $this->own->get($person->id()->toRfc4122()) === $accountId->toRfc4122();
    }

    public function canBeReached(Person $person): bool
    {
        return $this->hasOwnAccount($person) || $this->parentsOf($person) !== [];
    }
}
