<?php

declare(strict_types=1);

namespace App\Workflow\People\Model;

use App\Context\Identity\Domain\Entity\Account;
use App\Context\People\Domain\Entity\GuardianLink;
use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use App\Library\Collections\Model\Collections;
use App\Library\Strings\Model\Text;
use Collator;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class ParentAccessItems
{
    private const string COLLATION = 'de';

    /**
     * @param ReadableCollection<non-empty-string, Account> $accounts
     * @param ReadableCollection<int, Membership> $memberships
     */
    private function __construct(
        private ReadableCollection $accounts,
        private ReadableCollection $memberships,
        private Placements $placements,
    ) {
    }

    /**
     * @param ReadableCollection<int, GuardianLink> $links
     * @param ReadableCollection<int, Account> $accounts the parents and approvers
     * @param ReadableCollection<int, Membership> $memberships the children's current memberships
     *
     * @return list<ParentAccessItem>
     */
    public static function of(
        ReadableCollection $links,
        ReadableCollection $accounts,
        ReadableCollection $memberships,
        Placements $placements,
    ): array {
        $keyed = Collections::firstPerKey($accounts, keyOf: self::accountKey(...));
        $items = new self($keyed, $memberships, $placements);
        $collator = new Collator(self::COLLATION);

        return Collections::sortedBy(
            Collections::mapPresent($links, $items->itemOf(...)),
            keyOf: static fn (ParentAccessItem $item): string => (string) $collator->getSortKey(self::sortName($item)),
        );
    }

    /**
     * @param ReadableCollection<int, GuardianLink> $links
     *
     * @return list<Uuid>
     */
    public static function accountIdsOf(ReadableCollection $links): array
    {
        $ids = $links->map(static fn (GuardianLink $link): Uuid => $link->accountId())->getValues();
        $approverOf = static fn (GuardianLink $link): ?Uuid => $link->approvedBy();
        $approvers = Collections::mapPresent($links, $approverOf);

        return [...$ids, ...$approvers->getValues()];
    }

    /**
     * @param ReadableCollection<int, GuardianLink> $links
     *
     * @return ReadableCollection<int, Person>
     */
    public static function childrenOf(ReadableCollection $links): ReadableCollection
    {
        return new ArrayCollection(
            Collections::firstPerKey(
                $links->map(static fn (GuardianLink $link): Person => $link->child()),
                keyOf: static fn (Person $child): string => $child->id()->toRfc4122(),
            )->getValues(),
        );
    }

    private function itemOf(GuardianLink $link): ?ParentAccessItem
    {
        $parent = $this->accounts->get($link->accountId()->toRfc4122());

        if ($parent === null) {
            return null;
        }

        $child = $link->child();

        return new ParentAccessItem(
            $link->id()->toRfc4122(),
            $link->relationship()->value,
            ParentAccountView::of($parent),
            DirectoryEntry::of(
                $child,
                $this->placements->groupsOf($this->memberships->filter(
                    static fn (Membership $membership): bool => $membership->person() === $child,
                )),
            ),
            AccessApproval::of($link, $this->accounts),
        );
    }

    private static function sortName(ParentAccessItem $item): string
    {
        return Text::format(
            "%s\u{1}%s\u{1}%s\u{1}%s",
            $item->parent->name,
            $item->child->lastName,
            $item->child->firstName,
            $item->id,
        )->toString();
    }

    /**
     * @return non-empty-string
     */
    private static function accountKey(Account $account): string
    {
        return $account->id()->toRfc4122();
    }
}
