<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Identity\Domain\Entity\User;
use App\Context\People\Domain\Entity\GuardianRelationship;
use App\Context\People\Domain\Enum\GuardianStatus;
use App\Library\Collections\Model\Collections;
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
     * @param ReadableCollection<int, User> $accounts
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

    private static function statusOf(GuardianRelationship $link): GuardianStatus
    {
        return $link->isApproved() ? GuardianStatus::Approved : GuardianStatus::Revoked;
    }

    private static function accountKey(User $user): string
    {
        return $user->id()->toRfc4122();
    }
}
