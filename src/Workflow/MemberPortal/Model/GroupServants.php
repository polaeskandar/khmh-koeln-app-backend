<?php

declare(strict_types=1);

namespace App\Workflow\MemberPortal\Model;

use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class GroupServants
{
    private const string LEADER = 'service_leader';

    private const string SERVANT = 'servant';

    /**
     * @return list<GroupServant> service leaders first, then servants, each by name
     */
    public static function lookedUpIn(AccountLookup $accounts): array
    {
        $leaders = new ArrayCollection($accounts->activeHoldersOf(self::LEADER));
        $leaderIds = $leaders->map(static fn (User $leader): string => $leader->id()->toRfc4122());

        $servants = new ArrayCollection($accounts->activeHoldersOf(self::SERVANT))->filter(
            static fn (User $servant): bool => !$leaderIds->contains($servant->id()->toRfc4122()),
        );

        return [
            ...$leaders->map(
                static fn (User $leader): GroupServant => GroupServant::of($leader, self::LEADER),
            )->getValues(),
            ...$servants->map(
                static fn (User $servant): GroupServant => GroupServant::of($servant, self::SERVANT),
            )->getValues(),
        ];
    }
}
