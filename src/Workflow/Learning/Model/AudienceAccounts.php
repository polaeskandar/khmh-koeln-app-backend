<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Learning\Domain\ValueObject\TaskReports;
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
     * @param ReadableCollection<string, User> $users the parents' and the reporters' accounts, by id
     * @param ReadableCollection<string, string> $own the active account linked to each person's own record
     */
    private function __construct(
        private RosterBook $rosters,
        public ReadableCollection $users,
        private ReadableCollection $own,
    ) {
    }

    public static function lookedUpIn(AccountLookup $accounts, RosterBook $rosters, TaskReports $reports): self
    {
        $accountIds = [...$rosters->guardianAccountIds(), ...$reports->completerIds()];
        $users = Collections::firstPerKey($accounts->accounts($accountIds), keyOf: self::userKey(...));

        return new self($rosters, $users, $accounts->ownAccountsOf($rosters->personIds()));
    }

    /**
     * @return list<AccountReference>
     */
    public function parentsOf(Person $child): array
    {
        $accountOf = fn (Uuid $id): ?User => $this->users->get($id->toRfc4122());

        return Collections::mapPresent(new ArrayCollection($this->rosters->guardianAccountIdsOf($child)), $accountOf)
            ->filter(static fn (User $user): bool => $user->status()->isActive())
            ->map(AccountReference::of(...))
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

    private static function userKey(User $user): string
    {
        return $user->id()->toRfc4122();
    }
}
