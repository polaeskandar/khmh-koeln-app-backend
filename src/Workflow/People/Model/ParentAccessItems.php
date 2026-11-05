<?php

declare(strict_types=1);

namespace App\Workflow\People\Model;

use App\Context\Identity\Application\Service\ParentAccounts;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\ParentAccess;
use App\Context\People\Domain\Entity\GuardianRelationship;
use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use App\Library\Collections\Model\Collections;
use App\Library\Strings\Model\Text;
use App\Library\Time\Client\Calendar;
use Collator;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class ParentAccessItems
{
    private const string COLLATION = 'de';

    /**
     * @param ReadableCollection<non-empty-string, User> $accounts
     * @param ReadableCollection<string, DirectoryEntry> $children
     */
    private function __construct(
        private ReadableCollection $accounts,
        private ReadableCollection $children,
        private Calendar $calendar,
    ) {
    }

    /**
     * @param ReadableCollection<int, GuardianRelationship> $links
     *
     * @return list<ParentAccessItem>
     */
    public static function lookedUpIn(
        ParentAccounts $accounts,
        ParentAccess $access,
        StructureLookup $structure,
        Calendar $calendar,
        ReadableCollection $links,
    ): array {
        $items = new self(
            self::accountsOf($accounts, $links),
            self::childrenOf($access, $structure, $links),
            $calendar,
        );

        $collator = new Collator(self::COLLATION);

        return Collections::sortedBy(
            Collections::mapPresent($links, $items->itemOf(...)),
            keyOf: static fn (ParentAccessItem $item): string => (string) $collator->getSortKey(self::sortName($item)),
        );
    }

    private function itemOf(GuardianRelationship $link): ?ParentAccessItem
    {
        $parent = $this->accounts->get($link->accountId()->toRfc4122());
        $child = $this->children->get($link->child()->id()->toRfc4122());

        if ($parent === null || $child === null) {
            return null;
        }

        return new ParentAccessItem(
            $link->id()->toRfc4122(),
            $link->relationship()->value,
            ParentAccountView::of($parent),
            $child,
            AccessApproval::of($link, $this->accounts, $this->calendar),
        );
    }

    /**
     * @param ReadableCollection<int, GuardianRelationship> $links
     *
     * @return ReadableCollection<non-empty-string, User>
     */
    private static function accountsOf(ParentAccounts $accounts, ReadableCollection $links): ReadableCollection
    {
        $ids = $links->map(static fn (GuardianRelationship $link): Uuid => $link->accountId())->getValues();
        $approverOf = static fn (GuardianRelationship $link): ?Uuid => $link->approvedBy();
        $approvers = Collections::mapPresent($links, $approverOf);

        return Collections::firstPerKey(
            $accounts->accounts([...$ids, ...$approvers->getValues()]),
            keyOf: static fn (User $user): string => $user->id()->toRfc4122(),
        );
    }

    /**
     * @param ReadableCollection<int, GuardianRelationship> $links
     *
     * @return ReadableCollection<string, DirectoryEntry>
     */
    private static function childrenOf(
        ParentAccess $access,
        StructureLookup $structure,
        ReadableCollection $links,
    ): ReadableCollection {
        $children = new ArrayCollection(
            Collections::firstPerKey(
                $links->map(static fn (GuardianRelationship $link): Person => $link->child()),
                keyOf: static fn (Person $child): string => $child->id()->toRfc4122(),
            )->getValues(),
        );

        $memberships = $access->currentMembershipsOf($children);
        $placements = Placements::lookedUpIn($structure, $memberships);

        $entries = $children->map(static fn (Person $child): DirectoryEntry => DirectoryEntry::of(
            $child,
            $placements->groupsOf($memberships->filter(
                static fn (Membership $membership): bool => $membership->person() === $child,
            )),
        ));

        return Collections::firstPerKey($entries, keyOf: static fn (DirectoryEntry $entry): string => $entry->id);
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
}
