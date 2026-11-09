<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Identity\Domain\Entity\Account;
use App\Context\People\Domain\Entity\GuardianRelationship;
use App\Library\Collections\Model\Collections;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class LinkRow
{
    private const string APPROVED = 'approved';

    private const string REVOKED = 'revoked';

    public function __construct(
        public ?string $name,
        public string $linkStatus,
    ) {
    }

    /**
     * @param ReadableCollection<int, GuardianRelationship> $links
     * @param ReadableCollection<int, Account> $accounts
     *
     * @return list<self>
     */
    public static function parentsOf(ReadableCollection $links, ReadableCollection $accounts): array
    {
        $names = Collections::firstPerKey($accounts, keyOf: self::accountKey(...));

        return $links->map(
            static fn (GuardianRelationship $link): self => new self(
                $names->get($link->accountId()->toRfc4122())?->profile()->displayName->value,
                self::statusOf($link),
            ),
        )->getValues();
    }

    /**
     * @param ReadableCollection<int, GuardianRelationship> $links
     *
     * @return list<Uuid>
     */
    public static function accountIdsOf(ReadableCollection $links): array
    {
        return $links->map(static fn (GuardianRelationship $link): Uuid => $link->accountId())->getValues();
    }

    /**
     * @param ReadableCollection<int, GuardianRelationship> $links
     *
     * @return list<self>
     */
    public static function childrenOf(ReadableCollection $links): array
    {
        return $links->map(static function (GuardianRelationship $link): self {
            $name = $link->child()->name();

            return new self($name->firstName . ' ' . $name->lastName, self::statusOf($link));
        })->getValues();
    }

    private static function statusOf(GuardianRelationship $link): string
    {
        return $link->isApproved() ? self::APPROVED : self::REVOKED;
    }

    private static function accountKey(Account $account): string
    {
        return $account->id()->toRfc4122();
    }
}
