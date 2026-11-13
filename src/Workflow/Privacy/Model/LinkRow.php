<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Identity\Domain\ValueObject\NamedAccounts;
use App\Context\People\Domain\Entity\GuardianRelationship;
use App\Context\People\Domain\Enum\GuardianStatus;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class LinkRow
{
    public function __construct(
        public ?string $name,
        public GuardianStatus $linkStatus,
    ) {
    }

    /**
     * @param ReadableCollection<int, GuardianRelationship> $links
     *
     * @return list<self>
     */
    public static function parentsOf(ReadableCollection $links, NamedAccounts $accounts): array
    {
        $nameOf = static function (GuardianRelationship $link) use ($accounts): ?string {
            $account = $accounts->find($link->accountId());

            return $account === null ? null : $accounts->nameOf($account);
        };

        return $links->map(
            static fn (GuardianRelationship $link): self => new self($nameOf($link), self::statusOf($link)),
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

    private static function statusOf(GuardianRelationship $link): GuardianStatus
    {
        return $link->isApproved() ? GuardianStatus::Approved : GuardianStatus::Revoked;
    }
}
