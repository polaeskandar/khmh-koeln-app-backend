<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Identity\Domain\Entity\Account;
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
     * @param ReadableCollection<string, Account> $accounts the parents' and the reporters' accounts, by id
     * @param ReadableCollection<string, string> $own the active account linked to each person's own record
     */
    private function __construct(
        private RosterBook $rosters,
        public ReadableCollection $accounts,
        private ReadableCollection $own,
    ) {
    }

    /**
     * @param ReadableCollection<int, Account> $accounts the parents' and the reporters' accounts
     * @param ReadableCollection<string, string> $own the active account linked to each person's own record
     */
    public static function of(RosterBook $rosters, ReadableCollection $accounts, ReadableCollection $own): self
    {
        return new self($rosters, Collections::firstPerKey($accounts, keyOf: self::accountKey(...)), $own);
    }

    /**
     * @return list<AccountReference>
     */
    public function parentsOf(Person $child): array
    {
        $accountOf = fn (Uuid $id): ?Account => $this->accounts->get($id->toRfc4122());

        return Collections::mapPresent(new ArrayCollection($this->rosters->guardianAccountIdsOf($child)), $accountOf)
            ->filter(static fn (Account $account): bool => $account->status()->isActive())
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

    private static function accountKey(Account $account): string
    {
        return $account->id()->toRfc4122();
    }
}
